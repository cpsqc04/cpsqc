<?php

/**
 * Partner lifecycle API for Campaign / Disaster Preparedness simulations.
 *
 * POST JSON:
 * {
 *   "action": "start_simulation" | "complete_simulation",
 *   "source_reference_id": "simulation-event:4",   // preferred
 *   "request_id": "PT-REQ-2026-009",               // optional alternative
 *   "source_group": "disaster-preparedness"        // optional filter
 * }
 *
 * start_simulation  → assigned personnel status = On Patrol
 * complete_simulation → assigned personnel status = Available, request status = Completed,
 *                       and linked patrol_schedules for the request are Completed
 *                       (so Patrol List does not snap status back to Assigned)
 *
 * Auth: PATROL_REQUEST_API_KEY via X-API-Key or Bearer token.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/patrol_requests_schema.php';
require_once __DIR__ . '/notifications_schema.php';
require_once __DIR__ . '/../includes/api_key_auth.php';
require_once __DIR__ . '/../includes/patrol_availability.php';

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit;
}

requirePartnerApiKey('PATROL_REQUEST_API_KEY', 'Patrol request lifecycle');

try {
    ensurePatrolRequestsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare patrol requests table: ' . $e->getMessage()]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim((string) ($input['action'] ?? ''));
$allowedActions = ['start_simulation', 'complete_simulation'];

if (!in_array($action, $allowedActions, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'action must be start_simulation or complete_simulation.',
    ]);
    exit;
}

$requestId = trim((string) ($input['request_id'] ?? ''));
$sourceReferenceId = trim((string) ($input['source_reference_id'] ?? ''));
$sourceGroup = normalizePatrolSourceGroup((string) ($input['source_group'] ?? ''));

if ($requestId === '' && $sourceReferenceId === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Provide request_id and/or source_reference_id.',
    ]);
    exit;
}

try {
    $sql = 'SELECT id, request_id, source_group, source_reference_id, status, assigned_patrol_ids, event_name
            FROM patrol_requests
            WHERE 1=1';
    $params = [];

    if ($requestId !== '') {
        $sql .= ' AND request_id = :request_id';
        $params[':request_id'] = $requestId;
    }
    if ($sourceReferenceId !== '') {
        $sql .= ' AND source_reference_id = :source_reference_id';
        $params[':source_reference_id'] = $sourceReferenceId;
    }
    if ($sourceGroup !== '') {
        if ($sourceGroup === 'campaign') {
            $sql .= " AND source_group IN ('campaign', 'group_6', 'group 6')";
        } elseif ($sourceGroup === 'disaster-preparedness') {
            $sql .= " AND source_group IN ('disaster-preparedness', 'group_8', 'group 8')";
        } else {
            $sql .= ' AND source_group = :source_group';
            $params[':source_group'] = $sourceGroup;
        }
    }

    $sql .= ' ORDER BY id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows === []) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No matching patrol request found.']);
        exit;
    }

    $personnelStatus = $action === 'start_simulation' ? 'On Patrol' : 'Available';
    $updatedRequests = [];
    $updatedPersonnel = [];

    foreach ($rows as $row) {
        $assignedIds = parsePatrolRequestAssignedIds($row['assigned_patrol_ids'] ?? null);
        $requestCode = (string) ($row['request_id'] ?? '');

        foreach ($assignedIds as $patrolId) {
            setPatrolAvailabilityStatus($pdo, (int) $patrolId, $personnelStatus);
            $updatedPersonnel[] = [
                'id' => (int) $patrolId,
                'status' => $personnelStatus,
                'request_id' => $requestCode,
            ];

            if ($action === 'start_simulation') {
                createPatrolNotification(
                    $pdo,
                    (int) $patrolId,
                    'patrol_request_simulation_start',
                    'Simulation in progress',
                    'You are On Patrol for request ' . $requestCode . '. Status set while the disaster simulation is ongoing.',
                    'tab:schedule'
                );
            } elseif ($action === 'complete_simulation') {
                createPatrolNotification(
                    $pdo,
                    (int) $patrolId,
                    'patrol_request_simulation_complete',
                    'Simulation completed',
                    'Request ' . $requestCode . ' is complete. You are Available again.',
                    'tab:schedule'
                );
            }
        }

        if ($action === 'complete_simulation') {
            $update = $pdo->prepare(
                "UPDATE patrol_requests
                 SET status = 'Completed',
                     reviewed_by = COALESCE(reviewed_by, 'partner_api'),
                     reviewed_at = NOW(),
                     updated_at = NOW()
                 WHERE id = :id"
            );
            $update->execute([':id' => (int) $row['id']]);

            // Assigning a request creates a patrol_schedules row (notes: "Patrol request: PT-REQ-...").
            // Complete those too, or Patrol List resolve() keeps forcing status back to Assigned.
            if ($requestCode !== '') {
                try {
                    $completeSchedules = $pdo->prepare(
                        "UPDATE patrol_schedules
                         SET status = 'Completed'
                         WHERE status IN ('Scheduled', 'In Progress')
                           AND (
                             notes LIKE :notes_exact
                             OR notes LIKE :notes_prefix
                           )"
                    );
                    $completeSchedules->execute([
                        ':notes_exact' => 'Patrol request: ' . $requestCode . '%',
                        ':notes_prefix' => '%Patrol request: ' . $requestCode . '%',
                    ]);
                } catch (PDOException $e) {
                    // Table may differ; personnel Available is still set above.
                }
            }

            // Re-resolve after schedule cleanup so leftover real assignments stay Assigned.
            foreach ($assignedIds as $patrolId) {
                $fresh = refreshPatrolAvailabilityStatus($pdo, (int) $patrolId);
                foreach ($updatedPersonnel as $idx => $person) {
                    if ((int) ($person['id'] ?? 0) === (int) $patrolId
                        && ($person['request_id'] ?? '') === $requestCode) {
                        $updatedPersonnel[$idx]['status'] = $fresh;
                    }
                }
            }
        }

        $updatedRequests[] = [
            'request_id' => $requestCode,
            'previous_status' => $row['status'] ?? null,
            'status' => $action === 'complete_simulation' ? 'Completed' : ($row['status'] ?? null),
            'personnel_updated' => count($assignedIds),
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => $action === 'start_simulation'
            ? 'Assigned personnel marked On Patrol for simulation.'
            : 'Simulation completed. Assigned personnel marked Available.',
        'action' => $action,
        'data' => [
            'requests' => $updatedRequests,
            'personnel' => $updatedPersonnel,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lifecycle update failed: ' . $e->getMessage(),
    ]);
}

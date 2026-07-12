<?php
session_start();

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/patrol_requests_schema.php';
require_once __DIR__ . '/notifications_schema.php';
require_once __DIR__ . '/../includes/json_response.php';
require_once __DIR__ . '/../includes/api_key_auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!$pdo instanceof PDO) {
    jsonResponse(['success' => false, 'message' => 'Database connection unavailable.'], 500, false);
}

try {
    ensurePatrolRequestsTable($pdo);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Failed to prepare patrol requests table: ' . $e->getMessage()], 500, false);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? ($_GET['action'] ?? '');

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if ($method === 'GET') {
    if (!canAccessPartnerList($isAdmin, 'PATROL_REQUEST_API_KEY')) {
        denyPartnerListAccess($isAdmin, 'PATROL_REQUEST_API_KEY', 'Patrol request');
    }

    try {
        $cols = patrolRequestsSelectColumns();
        $sql = "SELECT {$cols} FROM patrol_requests WHERE 1=1";
        $params = [];

        $requestId = trim($_GET['request_id'] ?? '');
        if ($requestId !== '') {
            $sql .= ' AND request_id = :request_id';
            $params[':request_id'] = $requestId;
        }

        $status = trim($_GET['status'] ?? '');
        if ($status !== '') {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }

        $sourceGroup = strtolower(trim($_GET['source_group'] ?? ''));
        if ($sourceGroup === 'group 6') {
            $sourceGroup = 'group_6';
        }
        if ($sourceGroup === 'group 8') {
            $sourceGroup = 'group_8';
        }
        if ($sourceGroup !== '') {
            $sql .= ' AND source_group = :source_group';
            $params[':source_group'] = $sourceGroup;
        }

        $sourceReferenceId = trim($_GET['source_reference_id'] ?? '');
        if ($sourceReferenceId !== '') {
            $sql .= ' AND source_reference_id = :source_reference_id';
            $params[':source_reference_id'] = $sourceReferenceId;
        }

        $sql .= ' ORDER BY submitted_at DESC, id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $index => $row) {
            $rows[$index]['source_group_label'] = patrolRequestSourceGroupLabel((string) $row['source_group']);
            $rows[$index] = enrichPatrolRequestAssignments($pdo, $rows[$index]);
        }

        jsonResponse([
            'success' => true,
            'count' => count($rows),
            'data' => $rows,
        ]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to load patrol requests: ' . $e->getMessage()], 500);
    }
}

if ($method === 'POST' && $action === 'create') {
    if (!$isAdmin && !validatePatrolRequestApiKey()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing API key.']);
        exit;
    }

    $data = normalizePatrolRequestInput($input);
    $error = validatePatrolRequestRequiredFields($data);
    if ($error !== null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    }

    try {
        $requestId = generatePatrolRequestId($pdo);
        $stmt = $pdo->prepare('INSERT INTO patrol_requests (
            request_id, source, source_group, source_reference_id, requesting_unit,
            contact_person, contact_position, contact_number, contact_email,
            event_name, event_date, event_start_time, event_end_time, event_location,
            patrols_needed, event_description, special_instructions, status, submitted_at
        ) VALUES (
            :request_id, :source, :source_group, :source_reference_id, :requesting_unit,
            :contact_person, :contact_position, :contact_number, :contact_email,
            :event_name, :event_date, :event_start_time, :event_end_time, :event_location,
            :patrols_needed, :event_description, :special_instructions, :status, NOW()
        )');

        $stmt->execute([
            ':request_id' => $requestId,
            ':source' => $data['source'] !== '' ? $data['source'] : ($isAdmin ? 'admin_form' : 'partner_api'),
            ':source_group' => $data['source_group'],
            ':source_reference_id' => $data['source_reference_id'] !== '' ? $data['source_reference_id'] : null,
            ':requesting_unit' => $data['requesting_unit'],
            ':contact_person' => $data['contact_person'],
            ':contact_position' => $data['contact_position'] !== '' ? $data['contact_position'] : null,
            ':contact_number' => $data['contact_number'],
            ':contact_email' => $data['contact_email'] !== '' ? $data['contact_email'] : null,
            ':event_name' => $data['event_name'],
            ':event_date' => $data['event_date'],
            ':event_start_time' => $data['event_start_time'],
            ':event_end_time' => $data['event_end_time'] !== '' ? $data['event_end_time'] : null,
            ':event_location' => $data['event_location'],
            ':patrols_needed' => $data['patrols_needed'],
            ':event_description' => $data['event_description'] !== '' ? $data['event_description'] : null,
            ':special_instructions' => $data['special_instructions'] !== '' ? $data['special_instructions'] : null,
            ':status' => 'Pending',
        ]);

        $groupLabel = patrolRequestSourceGroupLabel($data['source_group']);
        createAdminNotification(
            $pdo,
            'patrol_request',
            'New Patrol Request',
            'Request #' . $requestId . ' from ' . $groupLabel . ' — ' . $data['event_name'],
            'patrol-request.php?id=' . urlencode($requestId)
        );

        echo json_encode([
            'success' => true,
            'message' => 'Patrol request submitted successfully.',
            'data' => [
                'id' => (int) $pdo->lastInsertId(),
                'request_id' => $requestId,
            ],
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save patrol request: ' . $e->getMessage()]);
    }
    exit;
}

if (!$isAdmin) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($method === 'POST' && $action === 'manage') {
    $id = (int) ($input['id'] ?? 0);
    $status = trim($input['status'] ?? '');
    $allowedStatuses = ['Pending', 'Under Review', 'Approved', 'Scheduled', 'Rejected', 'Cancelled'];

    if ($id <= 0 || $status === '' || !in_array($status, $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid request ID and status are required.']);
        exit;
    }

    $assignedPatrolIds = [];
    if (array_key_exists('assigned_patrol_ids', $input)) {
        if (!is_array($input['assigned_patrol_ids'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Assigned personnel must be an array.']);
            exit;
        }
        $assignedPatrolIds = parsePatrolRequestAssignedIds(json_encode($input['assigned_patrol_ids']));
    }

    $patrolsAssigned = count($assignedPatrolIds);
    if ($patrolsAssigned < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Patrols assigned cannot be negative.']);
        exit;
    }

    try {
        $currentStmt = $pdo->prepare('SELECT request_id, status, patrols_needed FROM patrol_requests WHERE id = :id');
        $currentStmt->execute([':id' => $id]);
        $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$current) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Patrol request not found.']);
            exit;
        }

        if ($assignedPatrolIds !== []) {
            $placeholders = implode(',', array_fill(0, count($assignedPatrolIds), '?'));
            $personnelStmt = $pdo->prepare("SELECT id FROM patrols WHERE id IN ({$placeholders})");
            $personnelStmt->execute($assignedPatrolIds);
            $foundIds = array_map('intval', $personnelStmt->fetchAll(PDO::FETCH_COLUMN));
            if (count($foundIds) !== count($assignedPatrolIds)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'One or more selected BPSO personnel were not found.']);
                exit;
            }

            $patrolsNeeded = (int) ($current['patrols_needed'] ?? 0);
            if ($patrolsNeeded > 0 && $patrolsAssigned > $patrolsNeeded) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Cannot assign more personnel than patrols needed (' . $patrolsNeeded . ').']);
                exit;
            }
        }

        $stmt = $pdo->prepare('UPDATE patrol_requests SET
            status = :status,
            review_notes = :review_notes,
            rejection_reason = :rejection_reason,
            patrols_assigned = :patrols_assigned,
            assigned_patrol_ids = :assigned_patrol_ids,
            scheduling_notes = :scheduling_notes,
            reviewed_by = :reviewed_by,
            reviewed_at = NOW()
            WHERE id = :id');

        $stmt->execute([
            ':status' => $status,
            ':review_notes' => trim($input['review_notes'] ?? '') !== '' ? trim($input['review_notes']) : null,
            ':rejection_reason' => trim($input['rejection_reason'] ?? '') !== '' ? trim($input['rejection_reason']) : null,
            ':patrols_assigned' => $patrolsAssigned > 0 ? $patrolsAssigned : null,
            ':assigned_patrol_ids' => encodePatrolRequestAssignedIds($assignedPatrolIds),
            ':scheduling_notes' => trim($input['scheduling_notes'] ?? '') !== '' ? trim($input['scheduling_notes']) : null,
            ':reviewed_by' => $_SESSION['username'] ?? 'Admin',
            ':id' => $id,
        ]);

        echo json_encode(['success' => true, 'message' => 'Patrol request updated successfully.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update patrol request: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

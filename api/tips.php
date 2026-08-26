<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/tips_schema.php';
require_once __DIR__ . '/../includes/public_id.php';
require_once __DIR__ . '/bpso_attendance_schema.php';
require_once __DIR__ . '/notifications_schema.php';
require_once __DIR__ . '/../includes/patrol_availability.php';

try {
    ensureTipsTable($pdo);
    ensureBpsoAttendanceTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare tips table: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

if ($action !== 'create' && (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($method === 'GET') {
    try {
        $id = (int) ($_GET['id'] ?? 0);
        $idsParam = trim((string) ($_GET['ids'] ?? ''));
        $includePhoto = isset($_GET['include_photo'])
            && in_array(strtolower((string) $_GET['include_photo']), ['1', 'true', 'yes'], true);

        // Single tip (includes photo) — used for View / Manage / lazy thumbnail.
        if ($id > 0) {
            $tip = fetchTipById($pdo, $id, true);
            if (!$tip) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Tip not found.']);
                exit;
            }
            echo json_encode([
                'success' => true,
                'data' => normalizeTipListRow($tip),
            ]);
            exit;
        }

        // Explicit multi-id fetch with photos (export).
        if ($idsParam !== '') {
            $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $idsParam)))));
            if (!$ids) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $orderField = implode(',', $ids); // ints only
            $cols = tipsSelectColumns('', true);
            $stmt = $pdo->prepare("SELECT {$cols} FROM tips WHERE id IN ({$placeholders}) ORDER BY FIELD(id, {$orderField})");
            $stmt->execute($ids);
            $tips = array_map('normalizeTipListRow', $stmt->fetchAll(PDO::FETCH_ASSOC));
            echo json_encode(['success' => true, 'data' => $tips]);
            exit;
        }

        // Default list: no photo payloads (has_photo flag only) for fast page load.
        $cols = tipsSelectColumns('', $includePhoto);
        $stmt = $pdo->query("SELECT {$cols} FROM tips ORDER BY id DESC");
        $tips = array_map('normalizeTipListRow', $stmt->fetchAll(PDO::FETCH_ASSOC));

        echo json_encode([
            'success' => true,
            'data' => $tips,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load tips: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    if ($action === 'create') {
        $location = trim($input['location'] ?? '');
        $description = trim($input['description'] ?? '');
        $photoData = $input['photo'] ?? null;
        $incidentRaw = $input['incident_at']
            ?? $input['incident_datetime']
            ?? $input['date_time']
            ?? $input['incident_date']
            ?? null;
        // Mobile apps may send separate date + time.
        if (($incidentRaw === null || trim((string) $incidentRaw) === '')
            && !empty($input['date'])
            && !empty($input['time'])) {
            $incidentRaw = trim((string) $input['date']) . ' ' . trim((string) $input['time']);
        }

        if ($location === '' || $description === '' || empty($photoData)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Location, description, and photo are required.']);
            exit;
        }

        $incidentAtValue = null;
        $incidentRawStr = is_string($incidentRaw) || is_numeric($incidentRaw) ? trim((string) $incidentRaw) : '';
        if ($incidentRawStr === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Date and time of the incident are required.']);
            exit;
        }
        $incident = normalizeTipIncidentAt($incidentRawStr);
        if (!$incident['ok']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $incident['message'] ?? 'Invalid incident date/time.']);
            exit;
        }
        $incidentAtValue = $incident['value'];

        $tipId = generateYearlySequentialId($pdo, 'tips', 'tip_id', 'TIP-');

        try {
            $stmt = $pdo->prepare('INSERT INTO tips (tip_id, location, description, photo_data, incident_at, status, outcome) VALUES (:tip_id, :location, :description, :photo_data, :incident_at, :status, :outcome)');
            $stmt->execute([
                ':tip_id' => $tipId,
                ':location' => $location,
                ':description' => $description,
                ':photo_data' => $photoData,
                ':incident_at' => $incidentAtValue,
                ':status' => 'New',
                ':outcome' => 'No Outcome Yet',
            ]);

            $id = (int) $pdo->lastInsertId();

            createAdminNotification(
                $pdo,
                'tip',
                'New Tip Received',
                'Tip #' . $tipId . ' - ' . $location,
                'review-tip.php?id=' . $tipId
            );

            echo json_encode([
                'success' => true,
                'message' => 'Tip submitted successfully!',
                'data' => [
                    'id' => $id,
                    'tip_id' => $tipId,
                    'location' => $location,
                    'description' => $description,
                    'incident_at' => $incidentAtValue,
                    'status' => 'New',
                    'outcome' => 'No Outcome Yet',
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to submit tip: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'assign') {
        $id = (int) ($input['id'] ?? 0);
        $patrolId = (int) ($input['assigned_patrol_id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid tip ID.']);
            exit;
        }

        try {
            $tip = fetchTipById($pdo, $id);
            if (!$tip) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Tip not found.']);
                exit;
            }

            if ($patrolId <= 0) {
                $previousPatrolId = (int) ($tip['assigned_patrol_id'] ?? 0);
                $stmt = $pdo->prepare('UPDATE tips SET assigned_patrol_id = NULL, assigned_to = NULL, assigned_at = NULL, resolution_report = NULL, resolved_at = NULL, status = :status, outcome = :outcome WHERE id = :id');
                $stmt->execute([
                    ':status' => 'New',
                    ':outcome' => 'No Outcome Yet',
                    ':id' => $id,
                ]);
                if ($previousPatrolId > 0) {
                    refreshPatrolAvailabilityStatus($pdo, $previousPatrolId);
                }
                echo json_encode(['success' => true, 'message' => 'Assignment cleared.', 'data' => ['status' => 'New', 'assigned_to' => null]]);
                exit;
            }

            $personnelStmt = $pdo->prepare('SELECT id, personnel_name, bpso_personnel_id, status FROM patrols WHERE id = :id');
            $personnelStmt->execute([':id' => $patrolId]);
            $personnel = $personnelStmt->fetch(PDO::FETCH_ASSOC);

            if (!$personnel) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'BPSO personnel not found.']);
                exit;
            }

            $availability = resolvePatrolAvailabilityStatus(
                $pdo,
                $patrolId,
                isset($personnel['status']) ? (string) $personnel['status'] : null
            );
            if ($availability !== 'Available') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Selected personnel is currently "' . $availability . '". Only Available personnel can be assigned.']);
                exit;
            }

            if (!isPatrolAtHall($pdo, $patrolId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Selected personnel is not at the barangay hall. Only personnel who have timed in today can be assigned.']);
                exit;
            }

            $previousPatrolId = (int) ($tip['assigned_patrol_id'] ?? 0);
            $assignedLabel = $personnel['personnel_name'] . ' (' . $personnel['bpso_personnel_id'] . ')';
            $timestamp = date('Y-m-d H:i:s');

            // Outcome stays "No Outcome Yet" until the assigned patrol submits their report.
            $stmt = $pdo->prepare('UPDATE tips SET assigned_patrol_id = :assigned_patrol_id, assigned_to = :assigned_to, assigned_at = :assigned_at, status = :status, outcome = :outcome, resolution_report = NULL, resolved_at = NULL WHERE id = :id');
            $stmt->execute([
                ':assigned_patrol_id' => $patrolId,
                ':assigned_to' => $assignedLabel,
                ':assigned_at' => $timestamp,
                ':status' => 'Assigned',
                ':outcome' => 'No Outcome Yet',
                ':id' => $id,
            ]);

            $pdo->prepare('UPDATE patrols SET status = :status WHERE id = :id')->execute([
                ':status' => 'Assigned',
                ':id' => $patrolId,
            ]);
            refreshPatrolAvailabilityStatus($pdo, $patrolId);
            if ($previousPatrolId > 0 && $previousPatrolId !== $patrolId) {
                refreshPatrolAvailabilityStatus($pdo, $previousPatrolId);
            }

            $tipLabel = (string) ($tip['tip_id'] ?? ('#' . $id));
            createPatrolNotification(
                $pdo,
                $patrolId,
                'tip_assignment',
                'Tip Assigned',
                'Tip #' . $tipLabel . ' has been assigned to you for response.',
                'tab:tips:' . $id,
                $timestamp
            );

            echo json_encode([
                'success' => true,
                'message' => 'Tip assigned successfully.',
                'data' => [
                    'assigned_to' => $assignedLabel,
                    'assigned_patrol_id' => $patrolId,
                    'status' => 'Assigned',
                    'outcome' => 'No Outcome Yet',
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to assign tip: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update_backup_status') {
        $id = (int) ($input['id'] ?? 0);
        $status = trim((string) ($input['backup_status'] ?? $input['status'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? $input['backup_status_notes'] ?? ''));

        if ($id <= 0 || $status === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tip id and backup_status are required.']);
            exit;
        }

        try {
            $result = updateTipBackupStatus(
                $pdo,
                $id,
                $status,
                $notes !== '' ? $notes : null
            );
            if (!$result['success']) {
                http_response_code(400);
                echo json_encode($result);
                exit;
            }

            $tip = $result['data']['tip'] ?? [];
            if (($result['data']['previous_status'] ?? '') !== ($result['data']['backup_status'] ?? '')) {
                notifyTipBackupStatusChange($pdo, $tip, (string) ($result['data']['backup_status'] ?? $status));
            }

            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'id' => $id,
                    'tip_id' => $result['data']['tip_id'] ?? null,
                    'backup_status' => $result['data']['backup_status'] ?? null,
                    'backup_status_updated_at' => $result['data']['backup_status_updated_at'] ?? null,
                    'backup_status_notes' => $result['data']['backup_status_notes'] ?? null,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update backup status: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid tip ID.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM tips WHERE id = :id');
            $stmt->execute([':id' => $id]);

            echo json_encode(['success' => true, 'message' => 'Tip deleted successfully!']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete tip: ' . $e->getMessage()]);
        }
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

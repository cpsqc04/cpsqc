<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/complaints_schema.php';
require_once __DIR__ . '/../includes/bpso_auth.php';

try {
    ensureComplaintsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare complaints table: ' . $e->getMessage()]);
    exit;
}

if (!isBpsoLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$patrolId = getBpsoPatrolId();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

if ($method === 'GET') {
    try {
        $cols = complaintsSelectColumns();
        $stmt = $pdo->prepare("SELECT {$cols} FROM complaints WHERE assigned_patrol_id = :patrol_id ORDER BY FIELD(status, 'Processing', 'Pending', 'Resolved', 'Rejected'), assigned_at DESC, id DESC");
        $stmt->execute([':patrol_id' => $patrolId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load assigned complaints: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST' && $action === 'submit_resolution') {
    $complaintDbId = (int) ($input['id'] ?? 0);
    $resolutionReport = trim($input['resolution_report'] ?? '');
    $status = trim($input['status'] ?? 'Resolved');

    if ($complaintDbId <= 0 || $resolutionReport === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Complaint and resolution report are required.']);
        exit;
    }

    if (!in_array($status, ['Processing', 'Resolved'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status.']);
        exit;
    }

    try {
        $check = $pdo->prepare('SELECT id, complaint_id, notes FROM complaints WHERE id = :id AND assigned_patrol_id = :patrol_id');
        $check->execute([':id' => $complaintDbId, ':patrol_id' => $patrolId]);
        $complaint = $check->fetch(PDO::FETCH_ASSOC);

        if (!$complaint) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Complaint not found or not assigned to you.']);
            exit;
        }

        $timestamp = date('Y-m-d H:i:s');
        $personnelName = getBpsoPersonnelName();
        $noteEntry = "[{$timestamp}] {$personnelName}: " . ($status === 'Resolved' ? 'Marked as resolved. ' : 'Updated progress. ') . $resolutionReport;
        $updatedNotes = trim(($complaint['notes'] ?? '') . "\n\n" . $noteEntry);

        if ($status === 'Resolved') {
            $stmt = $pdo->prepare('UPDATE complaints SET status = :status, resolution_report = :resolution_report, resolved_at = :resolved_at, notes = :notes WHERE id = :id AND assigned_patrol_id = :patrol_id');
            $stmt->execute([
                ':status' => 'Resolved',
                ':resolution_report' => $resolutionReport,
                ':resolved_at' => $timestamp,
                ':notes' => $updatedNotes,
                ':id' => $complaintDbId,
                ':patrol_id' => $patrolId,
            ]);

            $pdo->prepare('UPDATE patrols SET status = :status WHERE id = :id')->execute([
                ':status' => 'Available',
                ':id' => $patrolId,
            ]);
        } else {
            $stmt = $pdo->prepare('UPDATE complaints SET status = :status, resolution_report = :resolution_report, notes = :notes WHERE id = :id AND assigned_patrol_id = :patrol_id');
            $stmt->execute([
                ':status' => 'Processing',
                ':resolution_report' => $resolutionReport,
                ':notes' => $updatedNotes,
                ':id' => $complaintDbId,
                ':patrol_id' => $patrolId,
            ]);
        }

        echo json_encode(['success' => true, 'message' => $status === 'Resolved' ? 'Complaint marked as resolved.' : 'Progress report saved.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to submit resolution report: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

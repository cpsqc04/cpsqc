<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/nw_incidents_schema.php';
require_once __DIR__ . '/../includes/bpso_auth.php';

try {
    ensureNwIncidentReportsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare incident reports table: ' . $e->getMessage()]);
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
        $cols = nwIncidentSelectColumns();
        $stmt = $pdo->prepare("SELECT {$cols} FROM nw_incident_reports WHERE assigned_patrol_id = :patrol_id ORDER BY FIELD(status, 'In Progress', 'Under Review', 'Resolved', 'Closed'), assigned_at DESC, id DESC");
        $stmt->execute([':patrol_id' => $patrolId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load assigned neighborhood watch incidents: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST' && $action === 'submit_resolution') {
    $reportDbId = (int) ($input['id'] ?? 0);
    $resolutionReport = trim($input['resolution_report'] ?? '');
    $status = trim($input['status'] ?? 'Resolved');

    if ($reportDbId <= 0 || $resolutionReport === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Incident report and resolution report are required.']);
        exit;
    }

    if (!in_array($status, ['In Progress', 'Resolved'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status.']);
        exit;
    }

    try {
        $check = $pdo->prepare('SELECT id, report_id FROM nw_incident_reports WHERE id = :id AND assigned_patrol_id = :patrol_id');
        $check->execute([':id' => $reportDbId, ':patrol_id' => $patrolId]);
        $report = $check->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Incident report not found or not assigned to you.']);
            exit;
        }

        $timestamp = date('Y-m-d H:i:s');

        if ($status === 'Resolved') {
            $stmt = $pdo->prepare('UPDATE nw_incident_reports SET status = :status, resolution_report = :resolution_report, resolved_at = :resolved_at WHERE id = :id AND assigned_patrol_id = :patrol_id');
            $stmt->execute([
                ':status' => 'Resolved',
                ':resolution_report' => $resolutionReport,
                ':resolved_at' => $timestamp,
                ':id' => $reportDbId,
                ':patrol_id' => $patrolId,
            ]);

            $pdo->prepare('UPDATE patrols SET status = :status WHERE id = :id')->execute([
                ':status' => 'Available',
                ':id' => $patrolId,
            ]);
        } else {
            $stmt = $pdo->prepare('UPDATE nw_incident_reports SET status = :status, resolution_report = :resolution_report WHERE id = :id AND assigned_patrol_id = :patrol_id');
            $stmt->execute([
                ':status' => 'In Progress',
                ':resolution_report' => $resolutionReport,
                ':id' => $reportDbId,
                ':patrol_id' => $patrolId,
            ]);
        }

        echo json_encode(['success' => true, 'message' => $status === 'Resolved' ? 'Incident report marked as resolved.' : 'Progress report saved.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to submit resolution report: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

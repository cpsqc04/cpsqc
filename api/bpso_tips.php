<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/tips_schema.php';
require_once __DIR__ . '/../includes/bpso_auth.php';
require_once __DIR__ . '/../includes/patrol_availability.php';
require_once __DIR__ . '/notifications_schema.php';

try {
    ensureTipsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare tips table: ' . $e->getMessage()]);
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
        $id = (int) ($_GET['id'] ?? 0);

        // Single assigned tip with photo (modal / resolve).
        if ($id > 0) {
            $tip = fetchTipById($pdo, $id, true);
            if (!$tip || (int) ($tip['assigned_patrol_id'] ?? 0) !== (int) $patrolId) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Tip not found.']);
                exit;
            }
            $tip = normalizeTipListRow($tip);
            $tip['status'] = normalizeTipStatus($tip['status'] ?? 'Assigned');
            echo json_encode(['success' => true, 'data' => $tip]);
            exit;
        }

        // List without photo payloads for faster portal load.
        $cols = tipsSelectColumns('', false);
        $stmt = $pdo->prepare("SELECT {$cols} FROM tips WHERE assigned_patrol_id = :patrol_id ORDER BY FIELD(status, 'Assigned', 'New', 'Resolved'), assigned_at DESC, id DESC");
        $stmt->execute([':patrol_id' => $patrolId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row = normalizeTipListRow($row);
            $row['status'] = normalizeTipStatus($row['status'] ?? 'Assigned');
        }
        unset($row);
        echo json_encode(['success' => true, 'data' => $rows]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load assigned tips: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST' && $action === 'submit_resolution') {
    $tipDbId = (int) ($input['id'] ?? 0);
    $resolutionReport = trim($input['resolution_report'] ?? '');
    $outcome = trim($input['outcome'] ?? '');
    $status = trim($input['status'] ?? 'Resolved');

    if ($tipDbId <= 0 || $resolutionReport === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tip and resolution report are required.']);
        exit;
    }

    if (!in_array($status, ['Assigned', 'Resolved'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status.']);
        exit;
    }

    $allowedOutcomes = tipOutcomeOptions();
    if ($status === 'Resolved') {
        if (!in_array($outcome, $allowedOutcomes, true) || in_array($outcome, ['No Outcome Yet', 'Under Investigation'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select a final outcome (not Under Investigation) before resolving.']);
            exit;
        }
    } else {
        if ($outcome === '' || !in_array($outcome, $allowedOutcomes, true)) {
            $outcome = 'Under Investigation';
        }
    }

    try {
        $check = $pdo->prepare('SELECT id, tip_id FROM tips WHERE id = :id AND assigned_patrol_id = :patrol_id');
        $check->execute([':id' => $tipDbId, ':patrol_id' => $patrolId]);
        $tip = $check->fetch(PDO::FETCH_ASSOC);

        if (!$tip) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Tip not found or not assigned to you.']);
            exit;
        }

        $timestamp = date('Y-m-d H:i:s');
        $personnelName = getBpsoPersonnelName();

        if ($status === 'Resolved') {
            $stmt = $pdo->prepare('UPDATE tips SET status = :status, outcome = :outcome, resolution_report = :resolution_report, resolved_at = :resolved_at WHERE id = :id AND assigned_patrol_id = :patrol_id');
            $stmt->execute([
                ':status' => 'Resolved',
                ':outcome' => $outcome,
                ':resolution_report' => $resolutionReport,
                ':resolved_at' => $timestamp,
                ':id' => $tipDbId,
                ':patrol_id' => $patrolId,
            ]);
            refreshPatrolAvailabilityStatus($pdo, $patrolId);
        } else {
            $stmt = $pdo->prepare('UPDATE tips SET status = :status, outcome = :outcome, resolution_report = :resolution_report WHERE id = :id AND assigned_patrol_id = :patrol_id');
            $stmt->execute([
                ':status' => 'Assigned',
                ':outcome' => $outcome,
                ':resolution_report' => $resolutionReport,
                ':id' => $tipDbId,
                ':patrol_id' => $patrolId,
            ]);
        }

        $tipLabel = (string) ($tip['tip_id'] ?? ('#' . $tipDbId));
        notifyAdminActorActivity(
            $pdo,
            'patrol',
            $personnelName !== '' ? $personnelName : 'Patrol personnel',
            $status === 'Resolved'
                ? 'resolved anonymous tip ' . $tipLabel . ' (' . $outcome . ').'
                : 'updated progress on anonymous tip ' . $tipLabel . '.',
            'review-tip.php?id=' . rawurlencode($tipLabel)
        );

        echo json_encode([
            'success' => true,
            'message' => $status === 'Resolved' ? 'Tip marked as resolved.' : 'Progress report saved.',
            'data' => [
                'status' => $status,
                'outcome' => $outcome,
            ],
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to submit tip report: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

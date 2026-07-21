<?php

/**
 * Admin endpoint: send a tip to Group 3 Inter-agency Coordination Portal (police backup).
 *
 * POST JSON:
 *   { "id": 1, "police_backup_reason": "Youth riot reported; immediate backup needed." }
 *   or { "tip_id": "TIP-2026-002", "police_backup_reason": "..." }
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/tips_schema.php';
require_once __DIR__ . '/../includes/group3_forward.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int) ($input['id'] ?? 0);
$tipId = trim($input['tip_id'] ?? '');
$backupReason = trim($input['police_backup_reason'] ?? '');

try {
    ensureTipsTable($pdo);

    if ($id <= 0 && $tipId !== '') {
        $stmt = $pdo->prepare('SELECT id FROM tips WHERE tip_id = :tip_id LIMIT 1');
        $stmt->execute([':tip_id' => $tipId]);
        $id = (int) $stmt->fetchColumn();
    }

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid tip id is required.']);
        exit;
    }

    $tip = fetchTipById($pdo, $id);
    if (!$tip) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Tip not found.']);
        exit;
    }

    if (!empty($tip['backup_requested_at'])) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Police backup was already requested for this tip.',
            'data' => [
                'backup_requested_at' => $tip['backup_requested_at'],
                'group3_reference_id' => $tip['group3_reference_id'],
            ],
        ]);
        exit;
    }

    $result = forwardTipToGroup3($tip, $backupReason);
    if (!$result['success']) {
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to request police backup.',
        ]);
        exit;
    }

    $timestamp = date('Y-m-d H:i:s');
    $referenceId = trim($result['group3_reference_id'] ?? '');
    $reasonToStore = $backupReason !== '' ? $backupReason : trim($tip['police_backup_reason'] ?? '');
    if ($reasonToStore === '') {
        $reasonToStore = trim($tip['description'] ?? '');
    }

    $update = $pdo->prepare('UPDATE tips SET backup_requested_at = :backup_requested_at, group3_reference_id = :reference_id, police_backup_reason = :reason WHERE id = :id');
    $update->execute([
        ':backup_requested_at' => $timestamp,
        ':reference_id' => $referenceId !== '' ? $referenceId : null,
        ':reason' => $reasonToStore !== '' ? $reasonToStore : null,
        ':id' => $id,
    ]);

    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'data' => [
            'id' => $id,
            'tip_id' => $tip['tip_id'],
            'backup_requested_at' => $timestamp,
            'group3_reference_id' => $referenceId,
            'police_backup_reason' => $reasonToStore,
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

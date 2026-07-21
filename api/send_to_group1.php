<?php

/**
 * Admin endpoint: forward a tip to Group 1 Incident Logging and Classification.
 *
 * POST JSON:
 *   { "id": 1 }
 *   or { "tip_id": "TIP-2026-002" }
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
require_once __DIR__ . '/../includes/tip_forward.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int) ($input['id'] ?? 0);
$tipId = trim($input['tip_id'] ?? '');

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

    if (!empty($tip['forwarded_at'])) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'This tip was already sent to Incident Logging and Classification.',
            'data' => [
                'forwarded_at' => $tip['forwarded_at'],
                'blotter_reference_id' => $tip['blotter_reference_id'],
            ],
        ]);
        exit;
    }

    $result = forwardTipToGroup1($tip);
    if (!$result['success']) {
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to forward tip.',
        ]);
        exit;
    }

    $timestamp = date('Y-m-d H:i:s');
    $referenceId = trim($result['blotter_reference_id'] ?? '');

    $update = $pdo->prepare('UPDATE tips SET forwarded_at = :forwarded_at, blotter_reference_id = :reference_id WHERE id = :id');
    $update->execute([
        ':forwarded_at' => $timestamp,
        ':reference_id' => $referenceId !== '' ? $referenceId : null,
        ':id' => $id,
    ]);

    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'data' => [
            'id' => $id,
            'tip_id' => $tip['tip_id'],
            'forwarded_at' => $timestamp,
            'blotter_reference_id' => $referenceId,
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

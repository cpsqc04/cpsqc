<?php

/**
 * Inbound API: Emergency Response / Inter-Agency updates police backup status.
 *
 * POST JSON:
 *   {
 *     "source_tip_id": "TIP-2026-002",
 *     "tip_id": "TIP-2026-002",
 *     "backup_status": "Dispatched",
 *     "status": "Dispatched",
 *     "notes": "Unit 12 en route",
 *     "emergency_response_reference_id": "COORD-2026-A1B2C3"
 *   }
 *
 * Headers: X-API-Key or Authorization: Bearer {EMERGENCY_RESPONSE_API_KEY}
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/api_key_auth.php';
require_once __DIR__ . '/tips_schema.php';

requirePartnerApiKey(partnerEnvKeyCandidates('emergency-response'), 'Emergency Response');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

try {
    ensureTipsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare tips table.']);
    exit;
}

$sourceTipId = trim((string) ($input['source_tip_id'] ?? $input['tip_id'] ?? ''));
$statusRaw = trim((string) (
    $input['backup_status']
    ?? $input['status']
    ?? $input['backup']['status']
    ?? ''
));
$notes = trim((string) (
    $input['notes']
    ?? $input['backup_status_notes']
    ?? $input['backup']['notes']
    ?? ''
));
$referenceId = trim((string) (
    $input['emergency_response_reference_id']
    ?? $input['coordination_reference_id']
    ?? $input['reference_id']
    ?? ''
));

if ($sourceTipId === '' || $statusRaw === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'source_tip_id (or tip_id) and backup_status are required.',
    ]);
    exit;
}

$normalized = normalizeTipBackupStatus($statusRaw, true);
if (!in_array($normalized, ['Requested', 'Dispatched', 'Completed', 'Declined'], true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid backup_status. Use Requested, Dispatched, Completed, or Declined.',
        'accepted' => ['Requested', 'Dispatched', 'Completed', 'Declined'],
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id FROM tips WHERE tip_id = :tip_id LIMIT 1');
    $stmt->execute([':tip_id' => $sourceTipId]);
    $tipId = (int) $stmt->fetchColumn();

    if ($tipId <= 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Tip not found for source_tip_id: ' . $sourceTipId]);
        exit;
    }

    $result = updateTipBackupStatus(
        $pdo,
        $tipId,
        $normalized,
        $notes !== '' ? $notes : null,
        $referenceId !== '' ? $referenceId : null
    );

    if (!$result['success']) {
        http_response_code(400);
        echo json_encode($result);
        exit;
    }

    $tip = $result['data']['tip'] ?? [];
    if (($result['data']['previous_status'] ?? '') !== $normalized) {
        notifyTipBackupStatusChange($pdo, $tip, $normalized);
    }

    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'source_tip_id' => $sourceTipId,
        'backup_status' => $normalized,
        'backup_status_updated_at' => $result['data']['backup_status_updated_at'] ?? null,
        'emergency_response_reference_id' => $result['data']['emergency_response_reference_id'] ?? null,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error while updating backup status.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update backup status: ' . $e->getMessage()]);
}

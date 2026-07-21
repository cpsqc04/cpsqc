<?php

/**
 * Reference inbound API for Group 3 — Inter-agency Coordination Portal (police backup).
 * Group 3 hosts an equivalent endpoint; AlertaraQC forwards backup requests here when configured.
 *
 * POST JSON: see includes/group3_forward.php buildGroup3BackupPayload()
 * Headers: X-API-Key or Authorization: Bearer {GROUP3_API_KEY}
 *
 * Response:
 *   { "success": true, "coordination_reference_id": "COORD-2026-000001", "message": "Backup request received." }
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/api_key_auth.php';

requirePartnerApiKey('GROUP3_API_KEY', 'Inter-agency Coordination');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

$sourceTipId = trim($input['source_tip_id'] ?? '');
$location = trim($input['incident']['location'] ?? '');
$reason = trim($input['backup']['reason'] ?? '');

if ($sourceTipId === '' || $location === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing source_tip_id or incident.location.']);
    exit;
}

if ($reason === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing backup.reason.']);
    exit;
}

$referenceId = 'COORD-' . date('Y') . '-' . strtoupper(substr(md5($sourceTipId . microtime(true)), 0, 6));

echo json_encode([
    'success' => true,
    'coordination_reference_id' => $referenceId,
    'message' => 'Police backup request received by Inter-agency Coordination Portal.',
    'received' => [
        'source' => $input['source'] ?? 'alertaraqc',
        'request_type' => $input['request_type'] ?? 'police_backup',
        'source_tip_id' => $sourceTipId,
        'requesting_agency' => $input['requesting_agency'] ?? '',
        'priority' => $input['backup']['priority'] ?? 'high',
        'status' => trim($input['review']['status'] ?? ''),
    ],
]);

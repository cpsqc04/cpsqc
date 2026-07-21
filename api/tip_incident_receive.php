<?php

/**
 * Reference inbound API for Group 1 — Incident Logging and Classification (tips).
 * Group 1 hosts an equivalent endpoint; AlertaraQC forwards tips here when configured.
 *
 * POST JSON: see includes/tip_forward.php buildTipIncidentPayload()
 * Headers: X-API-Key or Authorization: Bearer {BLOTTER_API_KEY}
 *
 * Response:
 *   { "success": true, "blotter_reference_id": "INC-2026-000001", "message": "Tip incident logged." }
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/api_key_auth.php';

requirePartnerApiKey('BLOTTER_API_KEY', 'Incident Logging');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

$sourceTipId = trim($input['source_tip_id'] ?? '');
$location = trim($input['incident']['location'] ?? '');
$description = trim($input['incident']['description'] ?? '');

if ($sourceTipId === '' || $location === '' || $description === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing source_tip_id, incident.location, or incident.description.']);
    exit;
}

$referenceId = 'INC-' . date('Y') . '-' . strtoupper(substr(md5($sourceTipId . microtime(true)), 0, 6));

echo json_encode([
    'success' => true,
    'blotter_reference_id' => $referenceId,
    'incident_reference_id' => $referenceId,
    'message' => 'Tip received and logged in Incident Logging and Classification.',
    'received' => [
        'source' => $input['source'] ?? 'alertaraqc',
        'record_type' => $input['record_type'] ?? 'tip',
        'source_tip_id' => $sourceTipId,
        'location' => $location,
        'status' => trim($input['review']['status'] ?? ''),
        'outcome' => trim($input['review']['outcome'] ?? ''),
    ],
]);

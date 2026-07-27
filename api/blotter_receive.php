<?php

/**
 * Reference inbound API for Incident Reporting (Digital Blotter).
 * Incident Reporting hosts an equivalent endpoint; AlertaraQC forwards complaints here when configured.
 *
 * POST JSON body: see includes/blotter_forward.php buildBlotterForwardPayload()
 * Headers: X-API-Key or Authorization: Bearer {INCIDENT_REPORTING_API_KEY}
 *
 * Response:
 *   { "success": true, "blotter_reference_id": "DB-2026-000001", "message": "Complaint received." }
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/api_key_auth.php';

if (envFirst(...partnerEnvKeyCandidates('incident-reporting')) !== '') {
    requirePartnerApiKey(partnerEnvKeyCandidates('incident-reporting'), 'Incident Reporting');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

$sourceComplaintId = trim($input['source_complaint_id'] ?? '');
if ($sourceComplaintId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing source_complaint_id.']);
    exit;
}

$complainantName = trim($input['complainant']['name'] ?? '');
$incidentDescription = trim($input['incident']['description'] ?? '');
if ($complainantName === '' || $incidentDescription === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required complainant or incident fields.']);
    exit;
}

$referenceId = 'DB-' . date('Y') . '-' . strtoupper(substr(md5($sourceComplaintId . microtime(true)), 0, 6));

echo json_encode([
    'success' => true,
    'blotter_reference_id' => $referenceId,
    'message' => 'Complaint received by Digital Blotter System.',
    'received' => [
        'source' => $input['source'] ?? 'alertaraqc',
        'source_complaint_id' => $sourceComplaintId,
    ],
]);

<?php

/**
 * Reference inbound API for Group 1 Digital Blotter System.
 * Group 1 can host an equivalent endpoint; AlertaraQC forwards complaints here when configured.
 *
 * POST JSON body: see includes/blotter_forward.php buildBlotterForwardPayload()
 * Headers: X-API-Key or Authorization: Bearer {BLOTTER_API_KEY}
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

$expectedKey = trim($_ENV['BLOTTER_API_KEY'] ?? '');
if ($expectedKey !== '') {
    $providedKey = '';
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        $providedKey = $matches[1];
    }
    if ($providedKey === '') {
        $providedKey = trim($_SERVER['HTTP_X_API_KEY'] ?? '');
    }

    if ($providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing API key.']);
        exit;
    }
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

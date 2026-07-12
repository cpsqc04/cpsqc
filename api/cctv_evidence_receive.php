<?php

/**
 * Reference inbound API for Group 1 — CCTV evidence packages.
 * Group 1 hosts an equivalent endpoint; AlertaraQC forwards evidence here when configured.
 *
 * POST JSON: see includes/cctv_forward.php buildCctvEvidencePayload()
 * Headers: X-API-Key or Authorization: Bearer {BLOTTER_API_KEY}
 *
 * Response:
 *   { "success": true, "evidence_reference_id": "EVD-2026-000001", "message": "CCTV evidence received." }
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/api_key_auth.php';

requirePartnerApiKey('BLOTTER_API_KEY', 'CCTV Evidence');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

$sourceRequestId = trim($input['source_request_id'] ?? '');
$segmentCount = (int) ($input['footage']['segment_count'] ?? count($input['footage']['segments'] ?? []));

if ($sourceRequestId === '' || $segmentCount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing source_request_id or footage segments.']);
    exit;
}

$referenceId = 'EVD-' . date('Y') . '-' . strtoupper(substr(md5($sourceRequestId . microtime(true)), 0, 6));

echo json_encode([
    'success' => true,
    'evidence_reference_id' => $referenceId,
    'reference_id' => $referenceId,
    'message' => 'CCTV evidence received and logged for incident review.',
    'received' => [
        'source' => $input['source'] ?? 'alertaraqc',
        'record_type' => $input['record_type'] ?? 'cctv_evidence',
        'source_request_id' => $sourceRequestId,
        'segment_count' => $segmentCount,
        'requesting_agency' => $input['request']['requesting_agency'] ?? '',
        'case_reference' => $input['request']['case_reference'] ?? null,
    ],
]);

<?php

/**
 * Local / partner-facing stub: receive campaign recommendations from AlertaraQC.
 *
 * POST JSON with request_type = campaign_recommendation
 * Public — no API key required (partners may add auth on their side).
 *
 * Point CAMPAIGN_RECOMMENDATION_API_URL here for local testing:
 *   CAMPAIGN_RECOMMENDATION_API_URL=http://localhost/cpsqc-main/api/campaign_recommendation_receive.php
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

$requestType = strtolower(trim((string) ($input['request_type'] ?? '')));
if ($requestType !== '' && $requestType !== 'campaign_recommendation') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'request_type must be campaign_recommendation.']);
    exit;
}

$title = trim((string) ($input['title'] ?? ''));
$themes = $input['themes'] ?? [];
$reports = $input['patrol_reports'] ?? [];

if ($title === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'title is required.']);
    exit;
}

if (!is_array($reports) || count($reports) < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'At least one patrol_reports item is required.']);
    exit;
}

$referenceId = 'CAM-REC-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

echo json_encode([
    'success' => true,
    'message' => 'Campaign recommendation received.',
    'campaign_reference_id' => $referenceId,
    'data' => [
        'title' => $title,
        'themes' => is_array($themes) ? $themes : [],
        'patrol_report_count' => count($reports),
        'received_at' => date('c'),
    ],
]);

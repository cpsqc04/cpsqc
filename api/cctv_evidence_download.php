<?php

/**
 * Partner download endpoint for CCTV evidence files forwarded to Group 1.
 *
 * GET ?request_id=CCTV-REQ-2026-001&file=recording_YYYYMMDD_HHMMSS.mp4
 * Headers or query: X-API-Key / Authorization: Bearer / api_key={BLOTTER_API_KEY}
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/api_key_auth.php';
require_once __DIR__ . '/cctv_requests_schema.php';
require_once __DIR__ . '/recordings_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

requirePartnerApiKey('BLOTTER_API_KEY', 'CCTV Evidence Download', true);

$requestId = trim($_GET['request_id'] ?? '');
$filename = basename(trim($_GET['file'] ?? ''));

if ($requestId === '' || $filename === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'request_id and file are required.']);
    exit;
}

if (!isValidRecordingFilename($filename)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid recording file.']);
    exit;
}

try {
    ensureCctvRequestsTable($pdo);

    $stmt = $pdo->prepare('SELECT request_id, forwarded_to_group1_at, forwarded_recording_files FROM cctv_requests WHERE request_id = :request_id LIMIT 1');
    $stmt->execute([':request_id' => $requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'CCTV request not found.']);
        exit;
    }

    if (empty($request['forwarded_to_group1_at'])) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'CCTV evidence has not been forwarded for this request.']);
        exit;
    }

    $allowedFiles = json_decode((string) ($request['forwarded_recording_files'] ?? ''), true);
    if (!is_array($allowedFiles) || !in_array($filename, $allowedFiles, true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'This recording file is not linked to the forwarded evidence package.']);
        exit;
    }

    $filepath = recordingsDirectory() . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($filepath) || filesize($filepath) < RECORDING_MIN_BYTES) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Recording not found.']);
        exit;
    }

    $size = filesize($filepath);
    header('Content-Type: video/mp4');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=3600');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $size);
    readfile($filepath);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

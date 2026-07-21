<?php
/**
 * Serves the latest IP camera frame written by detect.py
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/live_frame_helpers.php';

$frameFile = newestLiveFramePath();

if ($frameFile === null) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Camera frame not available. Run start_detection.bat']);
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($frameFile));

if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}

readfile($frameFile);

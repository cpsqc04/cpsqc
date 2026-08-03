<?php
/**
 * Receive live JPEG frames from the on-site detection PC (Hostinger-safe, no Python).
 *
 * POST multipart/form-data:
 *   frame  — JPEG image (required)
 *   detections — optional JSON string for detections.json
 *
 * Auth: header X-CCTV-Upload-Key or Authorization: Bearer {key}
 * Must match CCTV_FRAME_UPLOAD_KEY in .env on the server.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../includes/detection_env.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$expectedKey = getCctvFrameUploadKey();
if ($expectedKey === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'CCTV frame upload is not configured on this server.']);
    exit;
}

$providedKey = trim((string) ($_SERVER['HTTP_X_CCTV_UPLOAD_KEY'] ?? ''));
if ($providedKey === '') {
    $auth = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (stripos($auth, 'Bearer ') === 0) {
        $providedKey = trim(substr($auth, 7));
    }
}

if ($providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid upload key']);
    exit;
}

// Simple rate limit: max ~3 uploads/sec per server process
$rateFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cctv_upload_rate.tmp';
$now = microtime(true);
if (is_file($rateFile)) {
    $last = (float) @file_get_contents($rateFile);
    if ($last > 0 && ($now - $last) < 0.25) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Rate limit exceeded']);
        exit;
    }
}
@file_put_contents($rateFile, (string) $now, LOCK_EX);

if (!isset($_FILES['frame']) || !is_uploaded_file($_FILES['frame']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing frame upload']);
    exit;
}

$upload = $_FILES['frame'];
if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Upload error']);
    exit;
}

$size = (int) ($upload['size'] ?? 0);
if ($size < 500 || $size > 8 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid frame size']);
    exit;
}

$mime = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = (string) finfo_file($finfo, $upload['tmp_name']);
        finfo_close($finfo);
    }
}
if ($mime === '') {
    $mime = (string) ($upload['type'] ?? '');
}
if ($mime !== '' && stripos($mime, 'image/') !== 0 && $mime !== 'application/octet-stream') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid content type']);
    exit;
}

$root = dirname(__DIR__);
$useAlt = ((int) (microtime(true) * 1000) % 2) === 0;
$target = $root . DIRECTORY_SEPARATOR . ($useAlt ? 'current_frame_alt.jpg' : 'current_frame.jpg');
$fallback = $root . DIRECTORY_SEPARATOR . ($useAlt ? 'current_frame.jpg' : 'current_frame_alt.jpg');
$temp = $root . DIRECTORY_SEPARATOR . 'current_frame_upload.tmp';

if (!@move_uploaded_file($upload['tmp_name'], $temp)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save frame']);
    exit;
}

$saved = @rename($temp, $target);
if (!$saved) {
    $saved = @rename($temp, $fallback);
}
if (!$saved && is_file($temp)) {
    @unlink($temp);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not write frame file']);
    exit;
}

$detectionsSaved = false;
$detectionsRaw = $_POST['detections'] ?? '';
if (is_string($detectionsRaw) && $detectionsRaw !== '') {
    $decoded = json_decode($detectionsRaw, true);
    if (is_array($decoded)) {
        $detPath = $root . DIRECTORY_SEPARATOR . 'detections.json';
        $detTemp = $root . DIRECTORY_SEPARATOR . 'detections_upload.tmp';
        if (@file_put_contents($detTemp, json_encode($decoded), LOCK_EX) !== false) {
            $detectionsSaved = @rename($detTemp, $detPath);
            if (!$detectionsSaved && is_file($detTemp)) {
                @unlink($detTemp);
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Frame received',
    'detections_saved' => $detectionsSaved,
    'updated_at' => date('c'),
]);

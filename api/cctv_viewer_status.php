<?php
/**
 * On-site detection agent polls this endpoint to learn when Open Surveillance is open.
 *
 * Auth: header X-CCTV-Upload-Key (same as frame upload) or ?api_key=
 * GET → { success, viewer_active, should_run, age_seconds, ... }
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../includes/detection_env.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$expectedKey = getCctvFrameUploadKey();
if ($expectedKey === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'CCTV upload key is not configured on this server.']);
    exit;
}

$providedKey = trim((string) ($_SERVER['HTTP_X_CCTV_UPLOAD_KEY'] ?? ''));
if ($providedKey === '') {
    $auth = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (stripos($auth, 'Bearer ') === 0) {
        $providedKey = trim(substr($auth, 7));
    }
}
if ($providedKey === '') {
    $providedKey = trim((string) ($_GET['api_key'] ?? $_POST['api_key'] ?? ''));
}

if ($providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid upload key']);
    exit;
}

$maxAge = (float) ($_ENV['CCTV_VIEWER_ACTIVE_SECONDS'] ?? getenv('CCTV_VIEWER_ACTIVE_SECONDS') ?: 45);
if ($maxAge < 20) {
    $maxAge = 20;
}

$status = getDetectionViewerStatus($maxAge);

echo json_encode([
    'success' => true,
    'viewer_active' => $status['active'],
    'should_run' => $status['active'],
    'age_seconds' => $status['age_seconds'],
    'source' => $status['source'],
    'updated_iso' => $status['updated_iso'],
    'feed_mode' => getCctvFeedMode(),
    'checked_at' => date('c'),
]);

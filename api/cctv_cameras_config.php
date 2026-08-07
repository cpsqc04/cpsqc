<?php
/**
 * On-site detection PC pulls camera config from Hostinger (same key as frame upload).
 *
 * GET with header X-CCTV-Upload-Key or Authorization: Bearer {key}
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../includes/detection_env.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$expectedKey = getCctvFrameUploadKey();
if ($expectedKey === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'CCTV config sync is not configured on this server.']);
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

$camerasFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cameras.json';
$cameras = [];
if (is_file($camerasFile)) {
    $decoded = json_decode((string) file_get_contents($camerasFile), true);
    if (is_array($decoded)) {
        $cameras = $decoded;
    }
}

echo json_encode([
    'success' => true,
    'cameras' => $cameras,
    'updated_at' => is_file($camerasFile) ? date('c', filemtime($camerasFile)) : null,
    'revision' => (static function () use ($camerasFile) {
        $path = dirname($camerasFile) . DIRECTORY_SEPARATOR . 'camera_config_revision.json';
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded) && isset($decoded['revision'])) {
                return (string) $decoded['revision'];
            }
        }
        return is_file($camerasFile) ? (string) filemtime($camerasFile) : '';
    })(),
]);

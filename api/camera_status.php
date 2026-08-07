<?php
/**
 * Quick health check for IP camera frame updates.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/live_frame_helpers.php';
require_once __DIR__ . '/../includes/detection_env.php';

$maxAgeSeconds = 4;
$frameFile = newestLiveFramePath();

if ($frameFile === null) {
    echo json_encode([
        'success' => false,
        'available' => false,
        'has_frame' => false,
        'age_seconds' => null,
        'message' => 'Frame file not found',
        'feed_mode' => getCctvFeedMode(),
        'local_detection_enabled' => isLocalDetectionEnabled(),
    ]);
    exit;
}

clearstatcache(true, $frameFile);
$mtime = filemtime($frameFile);
$age = max(0, time() - $mtime);
$available = $age <= $maxAgeSeconds;

echo json_encode([
    'success' => true,
    'available' => $available,
    'has_frame' => true,
    'age_seconds' => $age,
    'updated_at' => date('c', $mtime),
    'frame_file' => basename($frameFile),
    'feed_mode' => getCctvFeedMode(),
    'local_detection_enabled' => isLocalDetectionEnabled(),
]);

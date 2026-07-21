<?php
/**
 * Quick health check for IP camera frame updates.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/live_frame_helpers.php';

$maxAgeSeconds = 5;
$frameFile = newestLiveFramePath();

if ($frameFile === null) {
    echo json_encode([
        'success' => false,
        'available' => false,
        'age_seconds' => null,
        'message' => 'Frame file not found',
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
    'age_seconds' => $age,
    'updated_at' => date('c', $mtime),
    'frame_file' => basename($frameFile),
]);

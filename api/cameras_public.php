<?php
header('Content-Type: application/json');

$camerasFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cameras.json';
if (!file_exists($camerasFile)) {
    echo json_encode(['success' => true, 'cameras' => []]);
    exit;
}

$content = file_get_contents($camerasFile);
$cameras = json_decode($content, true) ?: [];

$publicCameras = array_map(static function ($camera) {
    return [
        'cameraId' => $camera['cameraId'] ?? '',
        'name' => $camera['name'] ?? '',
        'location' => $camera['location'] ?? '',
        'status' => $camera['status'] ?? 'Unknown',
    ];
}, $cameras);

echo json_encode(['success' => true, 'cameras' => $publicCameras]);

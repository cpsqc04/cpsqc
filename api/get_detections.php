<?php
/**
 * API endpoint to get current detections from YOLO detection script
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$detections_file = __DIR__ . '/../detections.json';

if (!file_exists($detections_file)) {
    echo json_encode([
        'success' => false,
        'timestamp' => date('c'),
        'detections' => [],
        'count' => 0,
        'message' => 'No detection data available yet'
    ]);
    exit;
}

$detection_data = json_decode(file_get_contents($detections_file), true);

if ($detection_data === null) {
    echo json_encode([
        'success' => false,
        'timestamp' => date('c'),
        'detections' => [],
        'count' => 0,
        'message' => 'Error parsing detection data'
    ]);
    exit;
}

$detection_data['success'] = true;
echo json_encode($detection_data);














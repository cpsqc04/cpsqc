<?php
/**
 * API endpoint to get current detections from YOLO detection script
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

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

// Normalize image URLs for the browser (and keep embedded thumbnails when present).
if (isset($detection_data['detections']) && is_array($detection_data['detections'])) {
    foreach ($detection_data['detections'] as &$det) {
        if (!is_array($det)) {
            continue;
        }
        if (!empty($det['image_data']) && is_string($det['image_data'])) {
            $det['image'] = $det['image_data'];
            continue;
        }
        $rel = trim((string) ($det['image'] ?? ''));
        if ($rel === '') {
            continue;
        }
        if (stripos($rel, 'data:image/') === 0 || preg_match('#^https?://#i', $rel)) {
            continue;
        }
        $file = basename($rel);
        $id = isset($det['id']) ? rawurlencode((string) $det['id']) : '';
        $query = 'f=' . rawurlencode($file);
        if ($id !== '') {
            $query .= '&id=' . $id;
        }
        $det['image'] = 'api/detection_image.php?' . $query;
    }
    unset($det);
}

$detection_data['success'] = true;
echo json_encode($detection_data);

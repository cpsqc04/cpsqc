<?php
// Camera Management API
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$camerasFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cameras.json';

// Initialize cameras file if it doesn't exist
if (!file_exists($camerasFile)) {
    // Default camera (current one)
    $defaultCameras = [
        [
            'id' => '1',
            'cameraId' => 'CAM-001',
            'name' => 'Main Entrance Camera',
            'location' => 'Susano Road, Barangay San Agustin, Quezon City',
            'ipAddress' => '10.219.58.187',
            'port' => '554',
            'username' => 'admin',
            'password' => 'admin123',
            'streamType' => 'sub', // main or sub
            'rtspUrl' => 'rtsp://admin:admin123@10.219.58.187:554/h264Preview_01_sub',
            'status' => 'Online',
            'description' => 'Main entrance surveillance camera',
            'createdAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s')
        ]
    ];
    file_put_contents($camerasFile, json_encode($defaultCameras, JSON_PRETTY_PRINT));
}

function loadCameras() {
    global $camerasFile;
    if (!file_exists($camerasFile)) {
        return [];
    }
    $content = file_get_contents($camerasFile);
    return json_decode($content, true) ?: [];
}

function saveCameras($cameras) {
    global $camerasFile;
    // Sort by camera ID for consistent ordering
    usort($cameras, function($a, $b) {
        return strcmp($a['cameraId'], $b['cameraId']);
    });
    file_put_contents($camerasFile, json_encode($cameras, JSON_PRETTY_PRINT));
    return true;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    // Get all cameras
    $cameras = loadCameras();
    echo json_encode(['success' => true, 'cameras' => $cameras]);
    
} elseif ($method === 'POST') {
    // Add new camera
    $data = json_decode(file_get_contents('php://input'), true);
    
    $cameras = loadCameras();
    
    // Generate new ID
    $newId = (string)(time());
    
    // Generate camera ID
    $existingIds = array_column($cameras, 'cameraId');
    $newCameraId = 'CAM-001';
    $maxNum = 0;
    foreach ($existingIds as $id) {
        if (preg_match('/CAM-(\d+)/', $id, $matches)) {
            $num = intval($matches[1]);
            if ($num > $maxNum) $maxNum = $num;
        }
    }
    $newCameraId = 'CAM-' . str_pad($maxNum + 1, 3, '0', STR_PAD_LEFT);
    
    // Build RTSP URL
    $ip = $data['ipAddress'] ?? '';
    $port = $data['port'] ?? '554';
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    $streamType = $data['streamType'] ?? 'sub';
    
    $streamPath = $streamType === 'main' ? 'h264Preview_01_main' : 'h264Preview_01_sub';
    $rtspUrl = "rtsp://{$username}:{$password}@{$ip}:{$port}/{$streamPath}";
    
    $newCamera = [
        'id' => $newId,
        'cameraId' => $newCameraId,
        'name' => $data['name'] ?? '',
        'location' => $data['location'] ?? '',
        'ipAddress' => $ip,
        'port' => $port,
        'username' => $username,
        'password' => $password,
        'streamType' => $streamType,
        'rtspUrl' => $rtspUrl,
        'status' => $data['status'] ?? 'Online',
        'description' => $data['description'] ?? '',
        'createdAt' => date('Y-m-d H:i:s'),
        'updatedAt' => date('Y-m-d H:i:s')
    ];
    
    $cameras[] = $newCamera;
    saveCameras($cameras);
    
    echo json_encode(['success' => true, 'camera' => $newCamera]);
    
} elseif ($method === 'PUT') {
    // Update camera
    $data = json_decode(file_get_contents('php://input'), true);
    $cameraId = $data['id'] ?? '';
    
    $cameras = loadCameras();
    $found = false;
    
    foreach ($cameras as &$camera) {
        if ($camera['id'] === $cameraId) {
            $camera['name'] = $data['name'] ?? $camera['name'];
            $camera['location'] = $data['location'] ?? $camera['location'];
            $camera['ipAddress'] = $data['ipAddress'] ?? $camera['ipAddress'];
            $camera['port'] = $data['port'] ?? $camera['port'];
            $camera['username'] = $data['username'] ?? $camera['username'];
            $camera['password'] = $data['password'] ?? $camera['password'];
            $camera['streamType'] = $data['streamType'] ?? $camera['streamType'];
            $camera['status'] = $data['status'] ?? $camera['status'];
            $camera['description'] = $data['description'] ?? $camera['description'];
            $camera['updatedAt'] = date('Y-m-d H:i:s');
            
            // Rebuild RTSP URL
            $ip = $camera['ipAddress'];
            $port = $camera['port'];
            $username = $camera['username'];
            $password = $camera['password'];
            $streamType = $camera['streamType'];
            $streamPath = $streamType === 'main' ? 'h264Preview_01_main' : 'h264Preview_01_sub';
            $camera['rtspUrl'] = "rtsp://{$username}:{$password}@{$ip}:{$port}/{$streamPath}";
            
            $found = true;
            break;
        }
    }
    
    if ($found) {
        saveCameras($cameras);
        echo json_encode(['success' => true, 'camera' => $camera]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Camera not found']);
    }
    
} elseif ($method === 'DELETE') {
    // Delete camera
    $cameraId = $_GET['id'] ?? '';
    
    $cameras = loadCameras();
    $cameras = array_filter($cameras, function($camera) use ($cameraId) {
        return $camera['id'] !== $cameraId;
    });
    
    saveCameras(array_values($cameras));
    echo json_encode(['success' => true]);
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>






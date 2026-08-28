<?php
/**
 * Camera Management API
 * Source of truth: cameras.json (edited only via this API / Camera Management UI).
 *
 * Hostinger-safe methods:
 *   GET
 *   POST { action: "create"|"update"|"delete", ... }
 * Legacy PUT / DELETE still accepted when the host allows them.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$camerasFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cameras.json';

function camerasEnsureFile(string $camerasFile): void
{
    if (is_file($camerasFile)) {
        return;
    }
    $defaultCameras = [
        [
            'id' => '1',
            'cameraId' => 'CAM-001',
            'name' => 'Main Entrance Camera',
            'location' => 'Susano Road, Barangay San Agustin, Quezon City',
            'ipAddress' => '192.168.1.100',
            'port' => '554',
            'username' => 'admin',
            'password' => 'admin123',
            'streamType' => 'main',
            'rtspUrl' => 'rtsp://admin:admin123@192.168.1.100:554/Preview_01_main',
            'status' => 'Online',
            'description' => 'Configure via Camera Management (do not edit cameras.json by hand).',
            'createdAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s'),
        ],
    ];
    camerasSave($camerasFile, $defaultCameras);
}

function camerasLoad(string $camerasFile): array
{
    camerasEnsureFile($camerasFile);
    $content = @file_get_contents($camerasFile);
    $decoded = is_string($content) ? json_decode($content, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function camerasSave(string $camerasFile, array $cameras): bool
{
    usort($cameras, static function ($a, $b) {
        return strcmp((string) ($a['cameraId'] ?? ''), (string) ($b['cameraId'] ?? ''));
    });
    $json = json_encode(array_values($cameras), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    $temp = $camerasFile . '.tmp';
    if (@file_put_contents($temp, $json . "\n", LOCK_EX) === false) {
        return false;
    }
    if (!@rename($temp, $camerasFile)) {
        $copied = @copy($temp, $camerasFile);
        @unlink($temp);
        if (!$copied) {
            return false;
        }
    }

    // Signal on-site detect.py / Open Surveillance to refresh immediately.
    $revision = [
        'revision' => (string) (int) round(microtime(true) * 1000),
        'updated_at' => date('c'),
        'count' => count($cameras),
    ];
    @file_put_contents(
        dirname($camerasFile) . DIRECTORY_SEPARATOR . 'camera_config_revision.json',
        json_encode($revision, JSON_PRETTY_PRINT),
        LOCK_EX
    );

    return true;
}

function camerasRevisionPayload(string $camerasFile): array
{
    $path = dirname($camerasFile) . DIRECTORY_SEPARATOR . 'camera_config_revision.json';
    if (is_file($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (is_array($decoded)) {
            return [
                'revision' => (string) ($decoded['revision'] ?? ''),
                'updated_at' => (string) ($decoded['updated_at'] ?? (is_file($camerasFile) ? date('c', filemtime($camerasFile)) : '')),
            ];
        }
    }
    return [
        'revision' => is_file($camerasFile) ? (string) filemtime($camerasFile) : '',
        'updated_at' => is_file($camerasFile) ? date('c', filemtime($camerasFile)) : null,
    ];
}

function normalizeStreamType($streamType): string
{
    $streamType = strtolower(trim((string) $streamType));
    if (in_array($streamType, ['high', 'main'], true)) {
        return 'high';
    }
    if (in_array($streamType, ['low', 'ext'], true)) {
        return 'low';
    }
    if (in_array($streamType, ['mid', 'sub'], true)) {
        return 'mid';
    }
    return 'mid';
}

function streamPathForType(string $streamType): string
{
    $streamType = normalizeStreamType($streamType);
    if ($streamType === 'high') {
        return 'h264Preview_01_main';
    }
    if ($streamType === 'low') {
        return 'h264Preview_01_ext';
    }
    return 'h264Preview_01_sub';
}

function streamQualityLabel(string $streamType): string
{
    $streamType = normalizeStreamType($streamType);
    return match ($streamType) {
        'high' => 'High',
        'low' => 'Low',
        default => 'Mid',
    };
}

function buildRtspUrl(string $ip, string $port, string $username, string $password, string $streamType): string
{
    $streamPath = streamPathForType($streamType);
    return 'rtsp://' . rawurlencode($username) . ':' . rawurlencode($password) . '@' . $ip . ':' . $port . '/' . $streamPath;
}

function updateCameraStreamQuality(string $camerasFile, array $data): array
{
    $id = trim((string) ($data['id'] ?? ''));
    if ($id === '') {
        return ['success' => false, 'error' => 'Camera id is required.', 'code' => 400];
    }

    $streamType = normalizeStreamType($data['streamType'] ?? 'mid');
    $cameras = camerasLoad($camerasFile);
    $index = findCameraIndex($cameras, $id);
    if ($index < 0) {
        return ['success' => false, 'error' => 'Camera not found.', 'code' => 404];
    }

    $camera = $cameras[$index];
    $ip = trim((string) ($camera['ipAddress'] ?? ''));
    $port = trim((string) ($camera['port'] ?? '554')) ?: '554';
    $username = trim((string) ($camera['username'] ?? ''));
    $password = (string) ($camera['password'] ?? '');

    $camera['streamType'] = $streamType;
    if ($ip !== '' && $username !== '' && $password !== '') {
        $camera['rtspUrl'] = buildRtspUrl($ip, $port, $username, $password, $streamType);
    }
    $camera['updatedAt'] = date('Y-m-d H:i:s');
    $cameras[$index] = $camera;

    if (!camerasSave($camerasFile, $cameras)) {
        return ['success' => false, 'error' => 'Failed to write cameras.json.', 'code' => 500];
    }

    return [
        'success' => true,
        'camera' => $camera,
        'streamType' => $streamType,
        'streamQualityLabel' => streamQualityLabel($streamType),
        'go2rtcStream' => go2rtcStreamName($camera),
        'message' => 'Stream quality updated to ' . streamQualityLabel($streamType) . '. Live view will refresh.',
        'updated_at' => camerasRevisionPayload($camerasFile)['updated_at'],
        'revision' => camerasRevisionPayload($camerasFile)['revision'],
    ];
}

function go2rtcStreamName(array $camera): string
{
    $cameraId = trim((string) ($camera['cameraId'] ?? $camera['id'] ?? 'cam'));
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $cameraId) ?: 'cam';
    return 'alertara_' . $safe;
}

function findCameraIndex(array $cameras, string $id): int
{
    foreach ($cameras as $index => $camera) {
        if ((string) ($camera['id'] ?? '') === $id) {
            return (int) $index;
        }
    }
    return -1;
}

function nextCameraPublicId(array $cameras): string
{
    $maxNum = 0;
    foreach ($cameras as $camera) {
        if (preg_match('/CAM-(\d+)/', (string) ($camera['cameraId'] ?? ''), $matches)) {
            $maxNum = max($maxNum, (int) $matches[1]);
        }
    }
    return 'CAM-' . str_pad((string) ($maxNum + 1), 3, '0', STR_PAD_LEFT);
}

function createCamera(string $camerasFile, array $data): array
{
    $cameras = camerasLoad($camerasFile);
    $ip = trim((string) ($data['ipAddress'] ?? ''));
    $port = trim((string) ($data['port'] ?? '554')) ?: '554';
    $username = trim((string) ($data['username'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $streamType = normalizeStreamType($data['streamType'] ?? 'mid');

    if ($ip === '' || $username === '' || trim((string) ($data['name'] ?? '')) === '' || trim((string) ($data['location'] ?? '')) === '') {
        return ['success' => false, 'error' => 'Name, location, IP address, and username are required.', 'code' => 400];
    }
    if ($password === '') {
        return ['success' => false, 'error' => 'Password is required for a new camera.', 'code' => 400];
    }

    $camera = [
        'id' => (string) time() . substr((string) mt_rand(100, 999), -3),
        'cameraId' => nextCameraPublicId($cameras),
        'name' => trim((string) $data['name']),
        'location' => trim((string) $data['location']),
        'ipAddress' => $ip,
        'port' => $port,
        'username' => $username,
        'password' => $password,
        'streamType' => $streamType,
        'rtspUrl' => buildRtspUrl($ip, $port, $username, $password, $streamType),
        'status' => trim((string) ($data['status'] ?? 'Online')) ?: 'Online',
        'description' => trim((string) ($data['description'] ?? '')),
        'createdAt' => date('Y-m-d H:i:s'),
        'updatedAt' => date('Y-m-d H:i:s'),
    ];

    $cameras[] = $camera;
    if (!camerasSave($camerasFile, $cameras)) {
        return ['success' => false, 'error' => 'Failed to write cameras.json.', 'code' => 500];
    }

    return [
        'success' => true,
        'camera' => $camera,
        'message' => 'Camera created. cameras.json updated.',
        'updated_at' => camerasRevisionPayload($camerasFile)['updated_at'],
        'revision' => camerasRevisionPayload($camerasFile)['revision'],
    ];
}

function updateCamera(string $camerasFile, array $data): array
{
    $id = trim((string) ($data['id'] ?? ''));
    if ($id === '') {
        return ['success' => false, 'error' => 'Camera id is required.', 'code' => 400];
    }

    $cameras = camerasLoad($camerasFile);
    $index = findCameraIndex($cameras, $id);
    if ($index < 0) {
        return ['success' => false, 'error' => 'Camera not found.', 'code' => 404];
    }

    $camera = $cameras[$index];
    $ip = trim((string) ($data['ipAddress'] ?? $camera['ipAddress'] ?? ''));
    $port = trim((string) ($data['port'] ?? $camera['port'] ?? '554')) ?: '554';
    $username = trim((string) ($data['username'] ?? $camera['username'] ?? ''));
    $password = array_key_exists('password', $data) && trim((string) $data['password']) !== ''
        ? (string) $data['password']
        : (string) ($camera['password'] ?? '');
    $streamType = normalizeStreamType($data['streamType'] ?? ($camera['streamType'] ?? 'main'));

    if ($ip === '' || $username === '' || trim((string) ($data['name'] ?? $camera['name'] ?? '')) === '') {
        return ['success' => false, 'error' => 'Name, IP address, and username are required.', 'code' => 400];
    }

    $camera['name'] = trim((string) ($data['name'] ?? $camera['name']));
    $camera['location'] = trim((string) ($data['location'] ?? $camera['location'] ?? ''));
    $camera['ipAddress'] = $ip;
    $camera['port'] = $port;
    $camera['username'] = $username;
    $camera['password'] = $password;
    $camera['streamType'] = $streamType;
    $camera['status'] = trim((string) ($data['status'] ?? $camera['status'] ?? 'Online')) ?: 'Online';
    $camera['description'] = trim((string) ($data['description'] ?? $camera['description'] ?? ''));
    $camera['rtspUrl'] = buildRtspUrl($ip, $port, $username, $password, $streamType);
    $camera['updatedAt'] = date('Y-m-d H:i:s');

    $cameras[$index] = $camera;
    if (!camerasSave($camerasFile, $cameras)) {
        return ['success' => false, 'error' => 'Failed to write cameras.json.', 'code' => 500];
    }

    return [
        'success' => true,
        'camera' => $camera,
        'message' => 'Camera updated. cameras.json saved — on-site detection will pick this up within a few seconds.',
        'updated_at' => camerasRevisionPayload($camerasFile)['updated_at'],
        'revision' => camerasRevisionPayload($camerasFile)['revision'],
    ];
}

function deleteCamera(string $camerasFile, string $id): array
{
    $id = trim($id);
    if ($id === '') {
        return ['success' => false, 'error' => 'Camera id is required.', 'code' => 400];
    }

    $cameras = camerasLoad($camerasFile);
    $before = count($cameras);
    $cameras = array_values(array_filter($cameras, static function ($camera) use ($id) {
        return (string) ($camera['id'] ?? '') !== $id;
    }));

    if (count($cameras) === $before) {
        return ['success' => false, 'error' => 'Camera not found.', 'code' => 404];
    }

    if (!camerasSave($camerasFile, $cameras)) {
        return ['success' => false, 'error' => 'Failed to write cameras.json.', 'code' => 500];
    }

    return [
        'success' => true,
        'message' => 'Camera deleted. cameras.json updated.',
        'updated_at' => date('c', filemtime($camerasFile) ?: time()),
    ];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}
$action = strtolower(trim((string) ($input['action'] ?? $_GET['action'] ?? '')));

if ($method === 'GET') {
    $cameras = camerasLoad($camerasFile);
    $revision = camerasRevisionPayload($camerasFile);
    echo json_encode([
        'success' => true,
        'cameras' => $cameras,
        'updated_at' => $revision['updated_at'],
        'revision' => $revision['revision'],
        'storage' => 'cameras.json',
    ]);
    exit;
}

// Prefer POST actions so shared hosting (Hostinger) does not block PUT/DELETE.
if ($method === 'POST') {
    if ($action === '' || $action === 'create') {
        // Empty action + no id => create; with id => update (compat)
        if ($action === '' && trim((string) ($input['id'] ?? '')) !== '') {
            $result = updateCamera($camerasFile, $input);
        } else {
            $result = createCamera($camerasFile, $input);
        }
    } elseif ($action === 'update') {
        $result = updateCamera($camerasFile, $input);
    } elseif ($action === 'delete') {
        $result = deleteCamera($camerasFile, (string) ($input['id'] ?? $_GET['id'] ?? ''));
    } elseif ($action === 'set_stream_quality') {
        $result = updateCameraStreamQuality($camerasFile, $input);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action. Use create, update, or delete.']);
        exit;
    }

    if (empty($result['success'])) {
        http_response_code((int) ($result['code'] ?? 400));
    }
    unset($result['code']);
    echo json_encode($result);
    exit;
}

if ($method === 'PUT') {
    $result = updateCamera($camerasFile, $input);
    if (empty($result['success'])) {
        http_response_code((int) ($result['code'] ?? 400));
    }
    unset($result['code']);
    echo json_encode($result);
    exit;
}

if ($method === 'DELETE') {
    $result = deleteCamera($camerasFile, (string) ($_GET['id'] ?? $input['id'] ?? ''));
    if (empty($result['success'])) {
        http_response_code((int) ($result['code'] ?? 400));
    }
    unset($result['code']);
    echo json_encode($result);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);

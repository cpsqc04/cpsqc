<?php
/**
 * Reolink encoding auto-detect / sync.
 *
 * Admin (session):
 *   POST { "action": "start", "cameraId"?: "..." }  — queue on-site probe
 *   GET  — current job status / last result
 *
 * On-site agent (upload key):
 *   GET  ?role=agent
 *   POST { "action": "claim"|"complete", ... }
 *
 * On complete, updates cameras.json streamType + encoding metadata.
 */

session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../includes/detection_env.php';

$root = dirname(__DIR__);
$jobPath = $root . DIRECTORY_SEPARATOR . 'camera_encoding_job.json';
$camerasFile = $root . DIRECTORY_SEPARATOR . 'cameras.json';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}
$action = strtolower(trim((string) ($input['action'] ?? $_GET['action'] ?? '')));
$role = strtolower(trim((string) ($_GET['role'] ?? $input['role'] ?? '')));
$method = $_SERVER['REQUEST_METHOD'];

function encodingJobNewId(): string
{
    try {
        return bin2hex(random_bytes(8));
    } catch (Exception $e) {
        return uniqid('enc_', true);
    }
}

function encodingJobRead(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function encodingJobWrite(string $path, array $job): void
{
    @file_put_contents($path, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function encodingBuildRtsp(string $ip, string $port, string $username, string $password, string $streamType): string
{
    $path = $streamType === 'main' ? 'h264Preview_01_main' : 'h264Preview_01_sub';
    return 'rtsp://' . rawurlencode($username) . ':' . rawurlencode($password) . '@' . $ip . ':' . $port . '/' . $path;
}

function encodingApplyToCameras(string $camerasFile, array $result, ?string $cameraId = null): array
{
    if (!is_file($camerasFile)) {
        return ['success' => false, 'message' => 'cameras.json missing'];
    }
    $cameras = json_decode((string) file_get_contents($camerasFile), true);
    if (!is_array($cameras) || $cameras === []) {
        return ['success' => false, 'message' => 'No cameras configured'];
    }

    $recommended = strtolower(trim((string) ($result['recommendedStream'] ?? '')));
    if ($recommended !== 'main' && $recommended !== 'sub') {
        return ['success' => false, 'message' => 'No recommended stream in probe result'];
    }

    $updated = 0;
    foreach ($cameras as &$cam) {
        $idMatch = $cameraId === null
            || $cameraId === ''
            || (string) ($cam['id'] ?? '') === $cameraId
            || (string) ($cam['cameraId'] ?? '') === $cameraId;
        if (!$idMatch) {
            continue;
        }
        $ip = trim((string) ($cam['ipAddress'] ?? ''));
        $port = trim((string) ($cam['port'] ?? '554')) ?: '554';
        $user = trim((string) ($cam['username'] ?? ''));
        $pass = (string) ($cam['password'] ?? '');
        $cam['streamType'] = $recommended;
        if ($ip !== '' && $user !== '') {
            $cam['rtspUrl'] = encodingBuildRtsp($ip, $port, $user, $pass, $recommended);
        }
        $cam['encoding'] = [
            'detectedAt' => $result['detectedAt'] ?? date('Y-m-d H:i:s'),
            'recommendedStream' => $recommended,
            'reason' => $result['reason'] ?? ($result['message'] ?? ''),
            'mainStream' => $result['mainStream'] ?? null,
            'subStream' => $result['subStream'] ?? null,
            'displayQuality' => $result['displayQuality'] ?? null,
        ];
        $cam['updatedAt'] = date('Y-m-d H:i:s');
        $updated++;
        if ($cameraId) {
            break;
        }
    }
    unset($cam);

    if ($updated < 1) {
        return ['success' => false, 'message' => 'Target camera not found'];
    }

    $json = json_encode(array_values($cameras), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || @file_put_contents($camerasFile, $json . "\n", LOCK_EX) === false) {
        return ['success' => false, 'message' => 'Failed to write cameras.json'];
    }

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

    return [
        'success' => true,
        'updated' => $updated,
        'streamType' => $recommended,
        'revision' => $revision['revision'],
    ];
}

function encodingRequireAdmin(): void
{
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

// --- Agent role ---
if ($role === 'agent') {
    requireCctvUploadKeyAuth();

    if ($method === 'GET') {
        $job = encodingJobRead($jobPath);
        $status = strtolower((string) ($job['status'] ?? ''));
        $hasJob = in_array($status, ['pending', 'running'], true);
        echo json_encode([
            'success' => true,
            'has_job' => $hasJob,
            'job' => $hasJob ? $job : null,
        ]);
        exit;
    }

    if ($method === 'POST' && $action === 'claim') {
        $job = encodingJobRead($jobPath);
        if (($job['id'] ?? '') !== ($input['id'] ?? '') || ($job['status'] ?? '') !== 'pending') {
            echo json_encode(['success' => false, 'message' => 'No pending encoding job']);
            exit;
        }
        $job['status'] = 'running';
        $job['claimed_at'] = date('c');
        encodingJobWrite($jobPath, $job);
        echo json_encode(['success' => true, 'job' => $job]);
        exit;
    }

    if ($method === 'POST' && $action === 'complete') {
        $job = encodingJobRead($jobPath);
        $jobId = (string) ($input['id'] ?? '');
        if ($jobId === '' || ($job['id'] ?? '') !== $jobId) {
            echo json_encode(['success' => false, 'message' => 'Job id mismatch']);
            exit;
        }

        $ok = !empty($input['success']);
        $result = [
            'success' => $ok,
            'message' => (string) ($input['message'] ?? ''),
            'reason' => (string) ($input['reason'] ?? $input['message'] ?? ''),
            'recommendedStream' => (string) ($input['recommendedStream'] ?? ''),
            'mainStream' => $input['mainStream'] ?? null,
            'subStream' => $input['subStream'] ?? null,
            'displayQuality' => $input['displayQuality'] ?? null,
            'detectedAt' => (string) ($input['detectedAt'] ?? date('Y-m-d H:i:s')),
            'elapsed_seconds' => $input['elapsed_seconds'] ?? null,
        ];

        $apply = ['success' => false];
        if ($ok) {
            $apply = encodingApplyToCameras(
                $camerasFile,
                $result,
                isset($job['cameraId']) ? (string) $job['cameraId'] : null
            );
        }

        $job['status'] = $ok && !empty($apply['success']) ? 'done' : 'error';
        $job['completed_at'] = date('c');
        $job['result'] = $result;
        $job['apply'] = $apply;
        $job['message'] = $ok
            ? ($apply['message'] ?? ('Synced stream: ' . ($result['recommendedStream'] ?: '?')))
            : ($result['message'] ?: 'Encoding probe failed');
        encodingJobWrite($jobPath, $job);

        echo json_encode([
            'success' => $job['status'] === 'done',
            'job' => $job,
            'message' => $job['message'],
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown agent action']);
    exit;
}

// --- Admin role ---
encodingRequireAdmin();

if ($method === 'GET') {
    $job = encodingJobRead($jobPath);
    echo json_encode([
        'success' => true,
        'job' => $job ?: null,
        'feed_mode' => getCctvFeedMode(),
    ]);
    exit;
}

if ($method === 'POST' && $action === 'start') {
    $cameraId = trim((string) ($input['cameraId'] ?? $input['id'] ?? ''));
    $job = [
        'id' => encodingJobNewId(),
        'status' => 'pending',
        'cameraId' => $cameraId !== '' ? $cameraId : null,
        'created_at' => date('c'),
        'message' => 'Waiting for on-site PC to probe Reolink encoding…',
    ];
    encodingJobWrite($jobPath, $job);
    echo json_encode([
        'success' => true,
        'job' => $job,
        'message' => 'Encoding probe queued. Keep start_detection_agent.bat running on the on-site PC.',
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Use action=start (admin) or role=agent.']);

<?php
/**
 * Camera LAN discovery job API.
 *
 * Admin (session):
 *   POST { "action": "start" }  — queue a scan (on-site agent) or run locally
 *   GET                         — current job status / results
 *
 * On-site agent (upload key):
 *   GET  ?role=agent            — pending job if any
 *   POST { "action": "claim"|"complete", ... } with X-CCTV-Upload-Key
 */

session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../includes/detection_env.php';

$root = dirname(__DIR__);
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}
$action = strtolower(trim((string) ($input['action'] ?? $_GET['action'] ?? '')));
$role = strtolower(trim((string) ($_GET['role'] ?? $input['role'] ?? '')));
$method = $_SERVER['REQUEST_METHOD'];

function newScanJobId(): string
{
    try {
        return bin2hex(random_bytes(8));
    } catch (Exception $e) {
        return uniqid('scan_', true);
    }
}

function runLocalCameraScan(string $root): array
{
    $script = $root . DIRECTORY_SEPARATOR . 'camera_discover.py';
    if (!is_file($script)) {
        return ['success' => false, 'message' => 'camera_discover.py is missing.'];
    }

    $outFile = $root . DIRECTORY_SEPARATOR . 'camera_scan_result_tmp.json';
    @unlink($outFile);

    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        $cmd = 'cd /d ' . escapeshellarg($root) . ' && py camera_discover.py --json --out '
            . escapeshellarg($outFile);
    } else {
        $cmd = 'cd ' . escapeshellarg($root) . ' && python3 camera_discover.py --json --out '
            . escapeshellarg($outFile);
    }

    $output = [];
    $code = 1;
    exec($cmd . ' 2>&1', $output, $code);

    $payload = null;
    if (is_file($outFile)) {
        $decoded = json_decode((string) file_get_contents($outFile), true);
        @unlink($outFile);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }

    if (!$payload) {
        // Fallback: parse stdout JSON
        $joined = trim(implode("\n", $output));
        $decoded = json_decode($joined, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }

    if (!$payload || empty($payload['success'])) {
        return [
            'success' => false,
            'message' => 'Local camera scan failed. Ensure Python (py) is installed.',
            'detail' => implode("\n", array_slice($output, -8)),
        ];
    }

    return $payload;
}

function publicScanJob(array $job): array
{
    return [
        'success' => true,
        'job' => [
            'id' => $job['id'] ?? null,
            'status' => $job['status'] ?? 'unknown',
            'mode' => $job['mode'] ?? null,
            'requested_at' => $job['requested_at'] ?? null,
            'started_at' => $job['started_at'] ?? null,
            'finished_at' => $job['finished_at'] ?? null,
            'message' => $job['message'] ?? null,
            'scanned_subnets' => $job['scanned_subnets'] ?? [],
            'count' => isset($job['cameras']) && is_array($job['cameras']) ? count($job['cameras']) : (int) ($job['count'] ?? 0),
            'cameras' => $job['cameras'] ?? [],
            'elapsed_seconds' => $job['elapsed_seconds'] ?? null,
            'note' => $job['note'] ?? null,
            'error' => $job['error'] ?? null,
        ],
        'feed_mode' => getCctvFeedMode(),
        'local_detection_enabled' => isLocalDetectionEnabled(),
    ];
}

// --- Agent endpoints (upload key) ---
if ($role === 'agent') {
    requireCctvUploadKeyAuth();

    if ($method === 'GET') {
        $job = readCameraScanJob();
        $status = (string) ($job['status'] ?? '');
        if ($status === 'pending' || $status === 'running') {
            echo json_encode([
                'success' => true,
                'has_job' => true,
                'job' => [
                    'id' => $job['id'] ?? null,
                    'status' => $status,
                    'requested_at' => $job['requested_at'] ?? null,
                ],
            ]);
        } else {
            echo json_encode(['success' => true, 'has_job' => false, 'job' => null]);
        }
        exit;
    }

    if ($method === 'POST' && $action === 'claim') {
        $job = readCameraScanJob();
        if (($job['status'] ?? '') !== 'pending') {
            echo json_encode(['success' => false, 'message' => 'No pending scan job.', 'has_job' => false]);
            exit;
        }
        $jobId = trim((string) ($input['id'] ?? ''));
        if ($jobId !== '' && $jobId !== ($job['id'] ?? '')) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Job id mismatch.']);
            exit;
        }
        $job['status'] = 'running';
        $job['started_at'] = date('c');
        $job['message'] = 'Scanning LAN Camera';
        writeCameraScanJob($job);
        echo json_encode(['success' => true, 'job' => ['id' => $job['id'], 'status' => 'running']]);
        exit;
    }

    if ($method === 'POST' && $action === 'complete') {
        $job = readCameraScanJob();
        $jobId = trim((string) ($input['id'] ?? ''));
        if ($jobId === '' || $jobId !== ($job['id'] ?? '')) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Job id mismatch or missing.']);
            exit;
        }

        $ok = !empty($input['success']);
        $job['status'] = $ok ? 'done' : 'error';
        $job['finished_at'] = date('c');
        $job['cameras'] = is_array($input['cameras'] ?? null) ? $input['cameras'] : [];
        $job['count'] = count($job['cameras']);
        $job['scanned_subnets'] = is_array($input['scanned_subnets'] ?? null) ? $input['scanned_subnets'] : [];
        $job['elapsed_seconds'] = $input['elapsed_seconds'] ?? null;
        $job['note'] = $input['note'] ?? null;
        $job['error'] = $ok ? null : trim((string) ($input['message'] ?? $input['error'] ?? 'Scan failed'));
        $job['message'] = $ok
            ? ('Found ' . $job['count'] . ' camera candidate(s).')
            : $job['error'];
        writeCameraScanJob($job);
        echo json_encode(['success' => true, 'job' => ['id' => $job['id'], 'status' => $job['status'], 'count' => $job['count']]]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown agent action.']);
    exit;
}

// --- Admin endpoints (session) ---
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($method === 'GET' || $action === 'status' || $action === '') {
    $job = readCameraScanJob();
    if ($job === []) {
        echo json_encode([
            'success' => true,
            'job' => null,
            'feed_mode' => getCctvFeedMode(),
            'local_detection_enabled' => isLocalDetectionEnabled(),
        ]);
        exit;
    }
    echo json_encode(publicScanJob($job));
    exit;
}

if ($method === 'POST' && $action === 'start') {
    $existing = readCameraScanJob();
    $existingStatus = (string) ($existing['status'] ?? '');
    if (in_array($existingStatus, ['pending', 'running'], true)) {
        $requestedAt = strtotime((string) ($existing['requested_at'] ?? '')) ?: 0;
        // Allow restart if stuck > 3 minutes
        if ($requestedAt > 0 && (time() - $requestedAt) < 180) {
            echo json_encode(publicScanJob($existing) + [
                'message' => 'A scan is already in progress.',
            ]);
            exit;
        }
    }

    $job = [
        'id' => newScanJobId(),
        'status' => 'pending',
        'mode' => isLocalDetectionEnabled() ? 'local' : 'remote',
        'requested_at' => date('c'),
        'started_at' => null,
        'finished_at' => null,
        'message' => isLocalDetectionEnabled()
            ? 'Scanning local network…'
            : 'Waiting for on-site detection agent to run the LAN scan…',
        'cameras' => [],
        'count' => 0,
        'scanned_subnets' => [],
        'error' => null,
    ];
    writeCameraScanJob($job);

    if (isLocalDetectionEnabled()) {
        $job['status'] = 'running';
        $job['started_at'] = date('c');
        $job['message'] = 'Scanning local network…';
        writeCameraScanJob($job);

        $result = runLocalCameraScan($root);
        if (!empty($result['success'])) {
            $job['status'] = 'done';
            $job['finished_at'] = date('c');
            $job['cameras'] = is_array($result['cameras'] ?? null) ? $result['cameras'] : [];
            $job['count'] = count($job['cameras']);
            $job['scanned_subnets'] = $result['scanned_subnets'] ?? [];
            $job['elapsed_seconds'] = $result['elapsed_seconds'] ?? null;
            $job['note'] = $result['note'] ?? null;
            $job['message'] = 'Found ' . $job['count'] . ' camera candidate(s).';
            $job['error'] = null;
        } else {
            $job['status'] = 'error';
            $job['finished_at'] = date('c');
            $job['error'] = $result['message'] ?? 'Scan failed';
            $job['message'] = $job['error'];
        }
        writeCameraScanJob($job);
        echo json_encode(publicScanJob($job));
        exit;
    }

    echo json_encode(publicScanJob($job) + [
        'message' => 'Scan queued. Keep start_detection_agent.bat running on the on-site PC.',
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action. Use start or GET status.']);

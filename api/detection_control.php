<?php

/**
 * Start / stop / heartbeat for local YOLO detection (detect.py).
 * Used by Open Surveillance so detection runs only while the page is in use.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$root = dirname(__DIR__);
$lockFile = $root . DIRECTORY_SEPARATOR . 'detect.lock';
$heartbeatFile = $root . DIRECTORY_SEPARATOR . 'detection_heartbeat.json';
$logFile = $root . DIRECTORY_SEPARATOR . 'detection_control.log';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}
$action = strtolower(trim($input['action'] ?? $_GET['action'] ?? 'status'));

function detectionPidIsAlive(int $pid): bool
{
    if ($pid <= 0) {
        return false;
    }
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        $out = [];
        exec('tasklist /FI "PID eq ' . $pid . '" /NH 2>nul', $out);
        $joined = implode(' ', $out);
        return strpos($joined, (string) $pid) !== false;
    }
    return function_exists('posix_kill') ? @posix_kill($pid, 0) : file_exists("/proc/{$pid}");
}

function readDetectionPid(string $lockFile): ?int
{
    if (!is_file($lockFile)) {
        return null;
    }
    $pid = (int) trim((string) @file_get_contents($lockFile));
    if ($pid <= 0) {
        return null;
    }
    if (!detectionPidIsAlive($pid)) {
        @unlink($lockFile);
        return null;
    }
    return $pid;
}

function writeDetectionHeartbeat(string $heartbeatFile, string $source = 'open-surveillance'): void
{
    $payload = [
        'updated_at' => microtime(true),
        'source' => $source,
        'updated_iso' => date('c'),
    ];
    @file_put_contents($heartbeatFile, json_encode($payload), LOCK_EX);
}

function stopDetectionProcesses(string $root, string $lockFile): int
{
    $killed = 0;
    $pid = readDetectionPid($lockFile);
    if ($pid !== null) {
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            exec('taskkill /F /PID ' . $pid . ' /T 2>nul', $out, $code);
            if ($code === 0) {
                $killed++;
            }
        } else {
            @posix_kill($pid, 15);
            $killed++;
        }
    }

    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        // Fallback: kill any remaining detect.py via WMIC/PowerShell
        exec('powershell -NoProfile -Command "Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -match \'detect\\.py\' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }" 2>nul');
    }

    @unlink($lockFile);
    return $killed;
}

function startDetectionProcess(string $root, string $logFile): bool
{
    $detectScript = $root . DIRECTORY_SEPARATOR . 'detect.py';
    if (!is_file($detectScript)) {
        return false;
    }

    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        $cmd = 'cd /d ' . escapeshellarg($root)
            . ' && (py detect.py >> ' . escapeshellarg($logFile) . ' 2>&1)';
        // start /B runs without a new console window attached to Apache
        pclose(popen('start /B cmd /C ' . escapeshellarg($cmd), 'r'));
        return true;
    }

    $cmd = 'cd ' . escapeshellarg($root) . ' && nohup python3 detect.py >> '
        . escapeshellarg($logFile) . ' 2>&1 &';
    exec($cmd);
    return true;
}

$pid = readDetectionPid($lockFile);
$running = $pid !== null;

if ($action === 'heartbeat') {
    writeDetectionHeartbeat($heartbeatFile);
    echo json_encode([
        'success' => true,
        'action' => 'heartbeat',
        'running' => $running,
        'pid' => $pid,
    ]);
    exit;
}

if ($action === 'status') {
    echo json_encode([
        'success' => true,
        'running' => $running,
        'pid' => $pid,
        'heartbeat_file' => is_file($heartbeatFile),
    ]);
    exit;
}

if ($action === 'stop') {
    $killed = stopDetectionProcesses($root, $lockFile);
    echo json_encode([
        'success' => true,
        'action' => 'stop',
        'stopped' => $killed > 0 || !$running,
        'message' => 'Detection stop requested.',
    ]);
    exit;
}

if ($action === 'start') {
    writeDetectionHeartbeat($heartbeatFile);

    if ($running) {
        echo json_encode([
            'success' => true,
            'action' => 'start',
            'already_running' => true,
            'pid' => $pid,
            'message' => 'Detection already running.',
        ]);
        exit;
    }

    $started = startDetectionProcess($root, $logFile);
    // Brief wait for lock file
    usleep(800000);
    $pid = readDetectionPid($lockFile);

    echo json_encode([
        'success' => $started,
        'action' => 'start',
        'running' => $pid !== null,
        'pid' => $pid,
        'message' => $started
            ? 'Detection start requested. Waiting for camera...'
            : 'Failed to start detection. Ensure Python is installed (py launcher).',
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action. Use start, stop, heartbeat, or status.']);

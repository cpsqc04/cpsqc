<?php
/**
 * Shared helpers to start/stop local detect.py (XAMPP on-site).
 */

require_once __DIR__ . '/detection_env.php';

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

function getDetectionLockPath(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'detect.lock';
}

function getDetectionControlLogPath(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'detection_control.log';
}

function readDetectionPid(?string $lockFile = null): ?int
{
    $lockFile = $lockFile ?: getDetectionLockPath();
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

function stopDetectionProcesses(?string $root = null, ?string $lockFile = null): int
{
    $root = $root ?: dirname(__DIR__);
    $lockFile = $lockFile ?: getDetectionLockPath();
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
        exec('powershell -NoProfile -Command "Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -match \'detect\\.py\' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }" 2>nul');
    }

    @unlink($lockFile);
    return $killed;
}

function startDetectionProcess(?string $root = null, ?string $logFile = null): bool
{
    $root = $root ?: dirname(__DIR__);
    $logFile = $logFile ?: getDetectionControlLogPath();
    $detectScript = $root . DIRECTORY_SEPARATOR . 'detect.py';
    if (!is_file($detectScript)) {
        return false;
    }

    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        // Use windowless pythonw/pyw so Windows Terminal does not pop open.
        $ps = 'Set-Location -LiteralPath ' . escapeshellarg($root) . '; '
            . '$log = ' . escapeshellarg($logFile) . '; '
            . '$pyw = Get-Command pyw -ErrorAction SilentlyContinue; '
            . 'if ($pyw) { $exe = "pyw"; $args = @("-3","detect.py") } '
            . 'else { $exe = "pythonw"; $args = @("detect.py") }; '
            . 'Start-Process -FilePath $exe -ArgumentList $args -WorkingDirectory '
            . escapeshellarg($root)
            . ' -WindowStyle Hidden -RedirectStandardOutput $log -RedirectStandardError $log -ErrorAction SilentlyContinue';
        $cmd = 'powershell -NoProfile -WindowStyle Hidden -Command ' . escapeshellarg($ps);
        pclose(popen($cmd, 'r'));
        return true;
    }

    $cmd = 'cd ' . escapeshellarg($root) . ' && nohup python3 detect.py >> '
        . escapeshellarg($logFile) . ' 2>&1 &';
    exec($cmd);
    return true;
}

/**
 * Ensure local detect.py is running (no-op in remote/Hostinger mode).
 */
function ensureLocalDetectionStarted(): array
{
    writeDetectionViewerHeartbeat('open-surveillance-page');

    if (!isLocalDetectionEnabled()) {
        return [
            'started' => false,
            'running' => false,
            'mode' => 'remote',
            'message' => 'Remote mode: on-site agent will start detect.py.',
        ];
    }

    $lockFile = getDetectionLockPath();
    $pid = readDetectionPid($lockFile);
    if ($pid !== null) {
        return [
            'started' => false,
            'running' => true,
            'pid' => $pid,
            'mode' => 'local',
            'message' => 'Detection already running.',
        ];
    }

    $ok = startDetectionProcess();
    usleep(400000);
    $pid = readDetectionPid($lockFile);

    return [
        'started' => $ok,
        'running' => $pid !== null,
        'pid' => $pid,
        'mode' => 'local',
        'message' => $ok ? 'Detection start requested.' : 'Failed to start detection.',
    ];
}

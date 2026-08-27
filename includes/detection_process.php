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

function hasConfiguredCameras(?string $root = null): bool
{
    $root = $root ?: dirname(__DIR__);
    $path = $root . DIRECTORY_SEPARATOR . 'cameras.json';
    if (!is_file($path)) {
        return false;
    }
    $raw = @file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($data) && count($data) > 0;
}

function detectionAgentProcessRunning(?string $root = null): bool
{
    $root = $root ?: dirname(__DIR__);
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        $out = [];
        // Single-quoted PHP string so $_. is not interpolated by PHP.
        $ps = '(Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -match \'detection_agent\\.py\' }).Count';
        exec('powershell -NoProfile -WindowStyle Hidden -Command ' . escapeshellarg($ps) . ' 2>nul', $out);
        return (int) trim(implode('', $out)) > 0;
    }

    $out = [];
    exec("pgrep -f 'detection_agent\\.py' 2>/dev/null", $out);
    return count($out) > 0;
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
        exec('powershell -NoProfile -Command "Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -match \'detection_agent\\.py\' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }" 2>nul');
    }

    @unlink($lockFile);
    return $killed;
}

/**
 * Start detection_agent.py (same stack as start_detection_agent.bat):
 * keeps detect.py alive and starts go2rtc for live view.
 */
function startDetectionProcess(?string $root = null, ?string $logFile = null): bool
{
    $root = $root ?: dirname(__DIR__);
    $logFile = $logFile ?: getDetectionControlLogPath();
    $agentScript = $root . DIRECTORY_SEPARATOR . 'detection_agent.py';
    $detectScript = $root . DIRECTORY_SEPARATOR . 'detect.py';
    $silentVbs = $root . DIRECTORY_SEPARATOR . 'start_detection_agent_silent.vbs';

    if (!is_file($agentScript) && !is_file($detectScript)) {
        return false;
    }

    if (detectionAgentProcessRunning($root) || readDetectionPid() !== null) {
        return true;
    }

    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        // Same launcher as start_detection_agent.bat (hidden, non-blocking).
        if (is_file($silentVbs)) {
            $cmd = 'cmd /c start "" /B cscript //nologo ' . escapeshellarg($silentVbs);
            pclose(popen($cmd, 'r'));
            return true;
        }

        $script = is_file($agentScript) ? 'detection_agent.py' : 'detect.py';
        $cmd = 'cmd /c start "" /B pyw -3 ' . escapeshellarg($script);
        pclose(popen('cd /d ' . escapeshellarg($root) . ' && ' . $cmd, 'r'));
        return true;
    }

    $script = is_file($agentScript) ? 'detection_agent.py' : 'detect.py';
    $cmd = 'cd ' . escapeshellarg($root) . ' && nohup python3 '
        . escapeshellarg($script) . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';
    exec($cmd);
    return true;
}

/**
 * Ensure local detection agent is running when cameras are configured
 * (no-op in remote/Hostinger mode).
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

    if (!hasConfiguredCameras()) {
        return [
            'started' => false,
            'running' => false,
            'mode' => 'local',
            'message' => 'No cameras configured — add a camera first.',
        ];
    }

    $lockFile = getDetectionLockPath();
    $pid = readDetectionPid($lockFile);
    $agentRunning = detectionAgentProcessRunning();
    if ($pid !== null || $agentRunning) {
        return [
            'started' => false,
            'running' => true,
            'pid' => $pid,
            'agent_running' => $agentRunning,
            'mode' => 'local',
            'message' => 'Detection already running.',
        ];
    }

    $ok = startDetectionProcess();
    usleep(600000);
    $pid = readDetectionPid($lockFile);
    $agentRunning = detectionAgentProcessRunning();

    return [
        'started' => $ok,
        'running' => $pid !== null || $agentRunning,
        'pid' => $pid,
        'agent_running' => $agentRunning,
        'mode' => 'local',
        'message' => $ok
            ? 'Detection agent start requested (same as start_detection_agent.bat).'
            : 'Failed to start detection agent. Ensure Python is installed (py launcher).',
    ];
}

<?php

/**
 * Start / stop / heartbeat for local YOLO detection (detect.py).
 * Open Surveillance uses start/heartbeat for the live view; monitoring stays always-on via the agent.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/detection_process.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$root = dirname(__DIR__);
$lockFile = getDetectionLockPath();
$heartbeatFile = getDetectionHeartbeatPath();
$logFile = getDetectionControlLogPath();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}
if ($input === [] && isset($_POST['action'])) {
    $input = ['action' => $_POST['action']];
}
$action = strtolower(trim($input['action'] ?? $_GET['action'] ?? 'status'));

function writeDetectionHeartbeat(string $heartbeatFile, string $source = 'open-surveillance'): void
{
    writeDetectionViewerHeartbeat($source);
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
        'local_detection_enabled' => isLocalDetectionEnabled(),
        'feed_mode' => getCctvFeedMode(),
    ]);
    exit;
}

if ($action === 'stop') {
    clearDetectionViewerHeartbeat('open-surveillance-stop');
    if (!isLocalDetectionEnabled()) {
        echo json_encode([
            'success' => true,
            'action' => 'stop',
            'stopped' => true,
            'feed_mode' => 'remote',
            'message' => 'Viewer closed.',
        ]);
        exit;
    }
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

    if (!isLocalDetectionEnabled()) {
        $viewer = getDetectionViewerStatus(90.0);
        echo json_encode([
            'success' => true,
            'action' => 'start',
            'already_running' => false,
            'running' => false,
            'pid' => null,
            'local_detection_enabled' => false,
            'feed_mode' => 'remote',
            'viewer_active' => $viewer['active'],
            'message' => 'Detection signaled.',
        ]);
        exit;
    }

    if (!hasConfiguredCameras($root)) {
        echo json_encode([
            'success' => false,
            'action' => 'start',
            'running' => false,
            'pid' => null,
            'message' => 'No cameras configured. Add a camera in Camera Management first.',
        ]);
        exit;
    }

    $agentRunning = detectionAgentProcessRunning($root);
    if ($running || $agentRunning) {
        echo json_encode([
            'success' => true,
            'action' => 'start',
            'already_running' => true,
            'pid' => $pid,
            'agent_running' => $agentRunning,
            'message' => 'Detection already running.',
        ]);
        exit;
    }

    $started = startDetectionProcess($root, $logFile);
    usleep(800000);
    $pid = readDetectionPid($lockFile);
    $agentRunning = detectionAgentProcessRunning($root);

    echo json_encode([
        'success' => $started,
        'action' => 'start',
        'running' => $pid !== null || $agentRunning,
        'pid' => $pid,
        'agent_running' => $agentRunning,
        'message' => $started
            ? 'Detection agent started. Waiting for camera…'
            : 'Failed to start detection. Ensure Python is installed (py launcher).',
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action. Use start, stop, heartbeat, or status.']);

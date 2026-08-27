<?php
/**
 * Low-latency WebRTC live view status (go2rtc).
 *
 * Admin (session):
 *   GET → { enabled, running, base_url, player_url, stream, ... }
 *
 * On-site agent (upload key):
 *   POST JSON status payload from go2rtc_manager / detection_agent
 */

session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../includes/detection_env.php';

$root = dirname(__DIR__);
$statusPath = $root . DIRECTORY_SEPARATOR . 'webrtc_status.json';
$method = $_SERVER['REQUEST_METHOD'];
$role = strtolower(trim((string) ($_GET['role'] ?? '')));

function webrtcStatusRead(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function webrtcStatusWrite(string $path, array $payload): bool
{
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return $json !== false && @file_put_contents($path, $json . "\n", LOCK_EX) !== false;
}

function webrtcConfiguredBaseUrl(): string
{
    $url = trim((string) ($_ENV['CCTV_WEBRTC_PUBLIC_URL'] ?? getenv('CCTV_WEBRTC_PUBLIC_URL') ?: ''));
    if ($url === '') {
        $url = trim((string) ($_ENV['CCTV_WEBRTC_URL'] ?? getenv('CCTV_WEBRTC_URL') ?: ''));
    }
    if ($url === '' && isLocalDetectionEnabled()) {
        $url = 'http://127.0.0.1:1984';
    }
    return rtrim($url, '/');
}

function webrtcBuildResponse(array $stored): array
{
    $base = trim((string) ($stored['base_url'] ?? ''));
    $configured = webrtcConfiguredBaseUrl();
    if ($base === '') {
        $base = $configured;
    }
    // Prefer explicit Hostinger/public URL when set (HTTPS tunnel for remote admins).
    if ($configured !== '' && $configured !== 'http://127.0.0.1:1984') {
        $base = $configured;
    }

    $stream = trim((string) ($stored['stream'] ?? 'alertara_live')) ?: 'alertara_live';
    $running = !empty($stored['running']);
    $updatedTs = isset($stored['updated_ts']) ? (float) $stored['updated_ts'] : 0.0;
    $age = $updatedTs > 0 ? max(0.0, microtime(true) - $updatedTs) : null;
    if ($age !== null && $age > 90) {
        $running = false;
    }

    $bases = [];
    if ($configured !== '') {
        $bases[] = $configured;
    }
    if ($base !== '' && !in_array($base, $bases, true)) {
        $bases[] = $base;
    }
    if (!empty($stored['base_urls']) && is_array($stored['base_urls'])) {
        foreach ($stored['base_urls'] as $b) {
            $b = rtrim((string) $b, '/');
            if ($b !== '' && !in_array($b, $bases, true)) {
                $bases[] = $b;
            }
        }
    }
    if (!empty($stored['tunnel_url'])) {
        $tunnel = rtrim((string) $stored['tunnel_url'], '/');
        if ($tunnel !== '' && !in_array($tunnel, $bases, true)) {
            array_unshift($bases, $tunnel);
        }
    }
    if (isLocalDetectionEnabled()) {
        foreach (['http://127.0.0.1:1984'] as $local) {
            if (!in_array($local, $bases, true)) {
                array_unshift($bases, $local);
            }
        }
    }

    // Prefer HTTPS (tunnel) first for Hostinger in-page embed.
    usort($bases, static function ($a, $b) {
        $ah = (strpos($a, 'https://') === 0) ? 0 : 1;
        $bh = (strpos($b, 'https://') === 0) ? 0 : 1;
        return $ah <=> $bh;
    });

    $primary = $bases[0] ?? '';
    $enabled = $primary !== '';
    $mkPlayer = static function (string $b) use ($stream): string {
        return $b . '/stream.html?src=' . rawurlencode($stream) . '&mode=mse,webrtc';
    };
    $playerUrls = array_map($mkPlayer, $bases);

    return [
        'success' => true,
        'enabled' => $enabled,
        'running' => $running && $enabled,
        'stream' => $stream,
        'base_url' => $primary,
        'base_urls' => $bases,
        'player_url' => $primary !== '' ? $mkPlayer($primary) : '',
        'player_urls' => $playerUrls,
        'localhost_player_url' => $mkPlayer('http://127.0.0.1:1984'),
        'lan_player_url' => (string) ($stored['lan_player_url'] ?? ''),
        'tunnel_url' => (string) ($stored['tunnel_url'] ?? ''),
        'webrtc_url' => $primary !== '' ? ($primary . '/api/webrtc?src=' . rawurlencode($stream)) : '',
        'camera_name' => $stored['camera_name'] ?? null,
        'camera_id' => $stored['camera_id'] ?? null,
        'error' => (string) ($stored['error'] ?? ''),
        'age_seconds' => $age,
        'feed_mode' => getCctvFeedMode(),
        'hint' => $enabled
            ? ($running
                ? 'Low-latency MSE/WebRTC live view is available (near Reolink app delay).'
                : 'Waiting for on-site go2rtc (start_detection_agent.bat).')
            : 'Set CCTV_WEBRTC_PUBLIC_URL (HTTPS tunnel) for remote live view, or open the LAN player link.',
    ];
}

// Agent publish
if ($role === 'agent' || ($method === 'POST' && isset($_SERVER['HTTP_X_CCTV_UPLOAD_KEY']))) {
    requireCctvUploadKeyAuth();
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'POST required']);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }
    $payload = [
        'success' => true,
        'enabled' => true,
        'running' => !empty($input['running']),
        'stream' => (string) ($input['stream'] ?? 'alertara_live'),
        'base_url' => rtrim((string) ($input['base_url'] ?? webrtcConfiguredBaseUrl()), '/'),
        'player_url' => (string) ($input['player_url'] ?? ''),
        'webrtc_url' => (string) ($input['webrtc_url'] ?? ''),
        'camera_name' => $input['camera_name'] ?? null,
        'camera_id' => $input['camera_id'] ?? null,
        'tunnel_url' => rtrim((string) ($input['tunnel_url'] ?? ''), '/'),
        'error' => (string) ($input['error'] ?? ''),
        'updated_at' => date('c'),
        'updated_ts' => microtime(true),
    ];
    if ($payload['player_url'] === '' && $payload['base_url'] !== '') {
        $payload['player_url'] = $payload['base_url'] . '/stream.html?src=' . rawurlencode($payload['stream']) . '&mode=webrtc,mse,hls';
    }
    webrtcStatusWrite($statusPath, $payload);
    echo json_encode(['success' => true, 'status' => webrtcBuildResponse($payload)]);
    exit;
}

// Admin read
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'GET required']);
    exit;
}

$stored = webrtcStatusRead($statusPath);

// Local XAMPP: if agent hasn't published yet, probe local go2rtc.
if (isLocalDetectionEnabled() && (empty($stored['running']) || empty($stored))) {
    $fp = @fsockopen('127.0.0.1', 1984, $errno, $errstr, 0.4);
    if (is_resource($fp)) {
        fclose($fp);
        $stored = array_merge($stored, [
            'running' => true,
            'stream' => 'alertara_live',
            'base_url' => webrtcConfiguredBaseUrl() ?: 'http://127.0.0.1:1984',
            'updated_ts' => microtime(true),
        ]);
    }
}

echo json_encode(webrtcBuildResponse($stored));

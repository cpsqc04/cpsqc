<?php
/**
 * CCTV detection environment helpers.
 * Keeps heavy Python/YOLO off Hostinger production by default.
 */

require_once __DIR__ . '/../db.php';

/**
 * True when PHP may spawn detect.py on this server (local XAMPP only by default).
 */
function isLocalDetectionEnabled(): bool
{
    $flag = strtolower(trim((string) ($_ENV['CCTV_DETECTION_ON_SERVER'] ?? getenv('CCTV_DETECTION_ON_SERVER') ?: '')));
    if ($flag === 'true' || $flag === '1' || $flag === 'yes') {
        return true;
    }
    if ($flag === 'false' || $flag === '0' || $flag === 'no') {
        return false;
    }

    return !isProduction();
}

/**
 * Shared secret for on-site PC → Hostinger frame uploads.
 */
function getCctvFrameUploadKey(): string
{
    return trim((string) ($_ENV['CCTV_FRAME_UPLOAD_KEY'] ?? getenv('CCTV_FRAME_UPLOAD_KEY') ?: ''));
}

/**
 * Human-readable mode for admin UI.
 */
function getCctvFeedMode(): string
{
    return isLocalDetectionEnabled() ? 'local' : 'remote';
}

/**
 * Absolute path to the Open Surveillance viewer heartbeat file.
 */
function getDetectionHeartbeatPath(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'detection_heartbeat.json';
}

/**
 * Mark Open Surveillance as actively viewing (wakes remote on-site agent).
 */
function writeDetectionViewerHeartbeat(string $source = 'open-surveillance'): void
{
    $payload = [
        'updated_at' => microtime(true),
        'source' => $source,
        'updated_iso' => date('c'),
        'viewer_active' => true,
    ];
    @file_put_contents(getDetectionHeartbeatPath(), json_encode($payload), LOCK_EX);
}

/**
 * Clear viewer-active flag when the admin leaves Open Surveillance.
 * On-site agent auto-stops detect.py shortly after this.
 */
function clearDetectionViewerHeartbeat(string $source = 'open-surveillance-leave'): void
{
    $path = getDetectionHeartbeatPath();
    $payload = [
        'updated_at' => microtime(true),
        'source' => $source,
        'updated_iso' => date('c'),
        'viewer_active' => false,
    ];
    @file_put_contents($path, json_encode($payload), LOCK_EX);
}

/**
 * Whether an admin is currently viewing Open Surveillance.
 *
 * @return array{active:bool,age_seconds:?float,source:?string,updated_iso:?string}
 */
function getDetectionViewerStatus(float $maxAgeSeconds = 90.0): array
{
    $path = getDetectionHeartbeatPath();
    if (!is_file($path)) {
        return [
            'active' => false,
            'age_seconds' => null,
            'source' => null,
            'updated_iso' => null,
        ];
    }

    $raw = @file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        return [
            'active' => false,
            'age_seconds' => null,
            'source' => null,
            'updated_iso' => null,
        ];
    }

    $updatedAt = isset($data['updated_at']) ? (float) $data['updated_at'] : 0.0;
    $age = $updatedAt > 0 ? max(0.0, microtime(true) - $updatedAt) : null;
    $flag = array_key_exists('viewer_active', $data) ? (bool) $data['viewer_active'] : true;
    $active = $flag && $age !== null && $age <= $maxAgeSeconds;

    return [
        'active' => $active,
        'age_seconds' => $age,
        'source' => isset($data['source']) ? (string) $data['source'] : null,
        'updated_iso' => isset($data['updated_iso']) ? (string) $data['updated_iso'] : null,
    ];
}

/**
 * Path for on-site camera LAN scan job (request + results).
 */
function getCameraScanJobPath(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'camera_scan_job.json';
}

/**
 * @return array<string,mixed>
 */
function readCameraScanJob(): array
{
    $path = getCameraScanJobPath();
    if (!is_file($path)) {
        return [];
    }
    $raw = @file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($data) ? $data : [];
}

/**
 * @param array<string,mixed> $job
 */
function writeCameraScanJob(array $job): void
{
    @file_put_contents(getCameraScanJobPath(), json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

/**
 * Authenticate CCTV upload key from headers / query (shared with frame upload).
 */
function requireCctvUploadKeyAuth(): void
{
    $expectedKey = getCctvFrameUploadKey();
    if ($expectedKey === '') {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'CCTV upload key is not configured on this server.']);
        exit;
    }

    $providedKey = trim((string) ($_SERVER['HTTP_X_CCTV_UPLOAD_KEY'] ?? ''));
    if ($providedKey === '') {
        $auth = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if (stripos($auth, 'Bearer ') === 0) {
            $providedKey = trim(substr($auth, 7));
        }
    }
    if ($providedKey === '') {
        $providedKey = trim((string) ($_GET['api_key'] ?? ''));
    }

    if ($providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid upload key']);
        exit;
    }
}

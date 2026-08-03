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

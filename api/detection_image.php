<?php
/**
 * Serve a cropped detection thumbnail from detected_objects/, or crop from the live frame via bbox.
 *
 * GET ?f=object_123.jpg
 * GET ?id=123
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$root = dirname(__DIR__);
$objectsDir = $root . DIRECTORY_SEPARATOR . 'detected_objects';

function sendJpegFile(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
    exit;
}

function loadDetections(string $root): array
{
    $path = $root . DIRECTORY_SEPARATOR . 'detections.json';
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded) || !isset($decoded['detections']) || !is_array($decoded['detections'])) {
        return [];
    }
    return $decoded['detections'];
}

function findLiveFrame(string $root): ?string
{
    foreach (['current_frame.jpg', 'current_frame_alt.jpg'] as $name) {
        $path = $root . DIRECTORY_SEPARATOR . $name;
        if (is_file($path) && filesize($path) > 500) {
            return $path;
        }
    }
    return null;
}

$file = basename(trim((string) ($_GET['f'] ?? '')));
$id = trim((string) ($_GET['id'] ?? ''));

if ($file !== '' && preg_match('/^object_\d+\.jpe?g$/i', $file)) {
    sendJpegFile($objectsDir . DIRECTORY_SEPARATOR . $file);
}

$detections = loadDetections($root);
$match = null;
if ($id !== '') {
    foreach ($detections as $det) {
        if ((string) ($det['id'] ?? '') === $id) {
            $match = $det;
            break;
        }
    }
} elseif ($file !== '') {
    foreach ($detections as $det) {
        $img = basename((string) ($det['image'] ?? ''));
        if ($img === $file) {
            $match = $det;
            break;
        }
    }
}

if ($match) {
    $rel = (string) ($match['image'] ?? '');
    if ($rel !== '') {
        $local = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
        sendJpegFile($local);
        sendJpegFile($objectsDir . DIRECTORY_SEPARATOR . basename($rel));
    }

    // Last resort: crop from the live frame using bbox (works on Hostinger after frame upload).
    $bbox = $match['bbox'] ?? null;
    $framePath = findLiveFrame($root);
    if (is_array($bbox) && $framePath && function_exists('imagecreatefromjpeg')) {
        $src = @imagecreatefromjpeg($framePath);
        if ($src) {
            $fw = imagesx($src);
            $fh = imagesy($src);
            $x1 = max(0, min($fw - 1, (int) ($bbox['x1'] ?? 0)));
            $y1 = max(0, min($fh - 1, (int) ($bbox['y1'] ?? 0)));
            $x2 = max($x1 + 1, min($fw, (int) ($bbox['x2'] ?? $fw)));
            $y2 = max($y1 + 1, min($fh, (int) ($bbox['y2'] ?? $fh)));
            $cw = $x2 - $x1;
            $ch = $y2 - $y1;
            $dst = imagecreatetruecolor(max(1, $cw), max(1, $ch));
            if ($dst) {
                imagecopy($dst, $src, 0, 0, $x1, $y1, $cw, $ch);
                header('Content-Type: image/jpeg');
                imagejpeg($dst, null, 82);
                imagedestroy($dst);
                imagedestroy($src);
                exit;
            }
            imagedestroy($src);
        }
    }
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not found';

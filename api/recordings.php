<?php
/**
 * CCTV recording playback API.
 *
 * GET ?action=list[&date=YYYY-MM-DD]
 * GET ?action=find&date=YYYY-MM-DD&start=HH:MM&end=HH:MM
 * GET ?action=stream&file=recording_YYYYMMDD_HHMMSS.mp4  (supports Range)
 * GET ?action=download&file=recording_YYYYMMDD_HHMMSS.mp4
 */

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Admin login required.']);
    exit;
}

require_once __DIR__ . '/recordings_helpers.php';

function jsonResponse(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function serveRecordingFile(string $filename, bool $allowLegacy, bool $forceDownload): void
{
    if (!isValidRecordingFilename($filename)) {
        jsonResponse(['success' => false, 'message' => 'Invalid recording file.'], 400);
    }

    $filepath = recordingsDirectory() . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($filepath)) {
        jsonResponse(['success' => false, 'message' => 'Recording not found.'], 404);
    }

    if (filesize($filepath) < RECORDING_MIN_BYTES) {
        jsonResponse(['success' => false, 'message' => 'Recording file is empty or incomplete.'], 404);
    }

    if (!$allowLegacy && !recordingIsPlayable($filepath)) {
        jsonResponse([
            'success' => false,
            'message' => 'This recording uses a legacy codec. Run: py api/convert_recordings.py',
        ], 415);
    }

    $size = filesize($filepath);
    $start = 0;
    $end = $size - 1;
    $length = $size;

    header('Content-Type: video/mp4');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=3600');
    header(
        'Content-Disposition: ' . ($forceDownload ? 'attachment' : 'inline') . '; filename="' . $filename . '"'
    );

    if (!$forceDownload && isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
        if ($matches[1] !== '') {
            $start = (int) $matches[1];
        }
        if ($matches[2] !== '') {
            $end = (int) $matches[2];
        }
        if ($end >= $size) {
            $end = $size - 1;
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header("Content-Range: bytes */{$size}");
            exit;
        }

        $length = $end - $start + 1;
        http_response_code(206);
        header("Content-Range: bytes {$start}-{$end}/{$size}");
    }

    header('Content-Length: ' . $length);

    $fp = fopen($filepath, 'rb');
    if (!$fp) {
        jsonResponse(['success' => false, 'message' => 'Unable to open recording.'], 500);
    }

    if ($start > 0) {
        fseek($fp, $start);
    }

    $remaining = $length;
    while ($remaining > 0 && !feof($fp)) {
        $chunkSize = min(8192, $remaining);
        $buffer = fread($fp, $chunkSize);
        if ($buffer === false) {
            break;
        }
        echo $buffer;
        $remaining -= strlen($buffer);
    }

    fclose($fp);
    exit;
}

function streamRecordingFile(string $filename): void
{
    serveRecordingFile($filename, false, false);
}

function downloadRecordingFile(string $filename): void
{
    serveRecordingFile($filename, true, true);
}

$action = strtolower(trim($_GET['action'] ?? 'list'));

if ($action === 'stream') {
    streamRecordingFile(basename($_GET['file'] ?? ''));
}

if ($action === 'download') {
    downloadRecordingFile(basename($_GET['file'] ?? ''));
}

if ($action === 'list') {
    cleanupExpiredRecordings();
    $date = trim($_GET['date'] ?? '');
    $segments = scanRecordingSegments($date !== '' ? $date : null, true);
    jsonResponse([
        'success' => true,
        'count' => count($segments),
        'recordings_dir' => RECORDINGS_DIR_NAME,
        'chunk_duration_seconds' => RECORDING_CHUNK_SECONDS,
        'retention_days' => RECORDING_RETENTION_DAYS,
        'data' => $segments,
    ]);
}

if ($action === 'find') {
    cleanupExpiredRecordings();
    $date = trim($_GET['date'] ?? '');
    $startTime = trim($_GET['start'] ?? '');
    $endTime = trim($_GET['end'] ?? '');

    if ($date === '') {
        jsonResponse(['success' => false, 'message' => 'date is required (YYYY-MM-DD).'], 400);
    }

    $reqStart = parseTimeOnDate($date, $startTime !== '' ? $startTime : '00:00:00');
    $reqEnd = parseTimeOnDate($date, $endTime !== '' ? $endTime : '23:59:59');
    if (!$reqStart || !$reqEnd) {
        jsonResponse(['success' => false, 'message' => 'Invalid date or time range.'], 400);
    }
    if ($reqEnd <= $reqStart) {
        jsonResponse(['success' => false, 'message' => 'End time must be after start time.'], 400);
    }

    $matches = [];
    foreach (scanRecordingSegments($date, true) as $segment) {
        $parsed = parseRecordingFilename($segment['filename']);
        if (!$parsed) {
            continue;
        }
        if (segmentsOverlap($parsed['start'], $parsed['end'], $reqStart, $reqEnd)) {
            $matches[] = $segment;
        }
    }

    usort($matches, static function ($a, $b) {
        return strcmp($a['start_at'], $b['start_at']);
    });

    jsonResponse([
        'success' => true,
        'count' => count($matches),
        'query' => [
            'date' => $date,
            'start' => $reqStart->format('H:i:s'),
            'end' => $reqEnd->format('H:i:s'),
        ],
        'data' => $matches,
    ]);
}

jsonResponse(['success' => false, 'message' => 'Unknown action. Use list, find, or stream.'], 400);

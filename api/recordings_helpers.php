<?php
/**
 * Recording file helpers for playback API.
 */

const RECORDING_MIN_BYTES = 1024;
const RECORDING_CHUNK_SECONDS = 300;
const RECORDING_RETENTION_DAYS = 30;
const RECORDINGS_DIR_NAME = 'recordings';

function recordingsDirectory(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . RECORDINGS_DIR_NAME;
}

function ensureRecordingsDirectory(): bool
{
    $dir = recordingsDirectory();
    if (is_dir($dir)) {
        return true;
    }

    return @mkdir($dir, 0755, true) || is_dir($dir);
}

function isValidRecordingFilename(string $filename): bool
{
    return (bool) preg_match('/^recording_\d{8}_\d{6}\.mp4$/', $filename);
}

function recordingCodecSample(string $filepath): string
{
    $size = filesize($filepath);
    if ($size === false || $size <= 0) {
        return '';
    }

    $headLen = (int) min(1048576, $size);
    $head = (string) file_get_contents($filepath, false, null, 0, $headLen);
    if ($size <= 1048576) {
        return $head;
    }

    $tailStart = max(0, $size - 1048576);
    $tail = (string) file_get_contents($filepath, false, null, $tailStart);

    return $head . $tail;
}

function recordingHasMoovAtom(string $filepath): bool
{
    if (!is_file($filepath)) {
        return false;
    }

    $sample = recordingCodecSample($filepath);
    return $sample !== '' && str_contains($sample, 'moov');
}

function recordingUsesLegacyCodec(string $filepath): bool
{
    if (!is_file($filepath)) {
        return false;
    }

    $sample = recordingCodecSample($filepath);
    if ($sample === '') {
        return true;
    }

    if (str_contains($sample, 'mp4v') || str_contains($sample, 'MP4V')) {
        return true;
    }

    return !(str_contains($sample, 'avc1') || str_contains($sample, 'h264') || str_contains($sample, 'H264'));
}

function recordingIsPlayable(string $filepath): bool
{
    if (!is_file($filepath)) {
        return false;
    }

    $size = filesize($filepath);
    if ($size === false || $size < RECORDING_MIN_BYTES) {
        return false;
    }

    // Incomplete OpenCV writes have mdat/avc1 but no moov until the writer is closed.
    if (!recordingHasMoovAtom($filepath)) {
        return false;
    }

    return !recordingUsesLegacyCodec($filepath);
}

function parseRecordingFilename(string $filename): ?array
{
    if (!preg_match('/^recording_(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})\.mp4$/', $filename, $matches)) {
        return null;
    }

    $start = DateTime::createFromFormat(
        'Y-m-d H:i:s',
        sprintf('%s-%s-%s %s:%s:%s', $matches[1], $matches[2], $matches[3], $matches[4], $matches[5], $matches[6])
    );
    if (!$start) {
        return null;
    }

    $end = clone $start;
    $end->modify('+' . RECORDING_CHUNK_SECONDS . ' seconds');

    return [
        'filename' => $filename,
        'start' => $start,
        'end' => $end,
    ];
}

function formatBytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    if ($bytes < 1073741824) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    return round($bytes / 1073741824, 2) . ' GB';
}

function formatRecordingSegment(array $parsed, string $filepath): array
{
    /** @var DateTime $start */
    $start = $parsed['start'];
    /** @var DateTime $end */
    $end = $parsed['end'];
    $sizeBytes = file_exists($filepath) ? (int) filesize($filepath) : 0;
    $playable = recordingIsPlayable($filepath);
    $legacyCodec = recordingUsesLegacyCodec($filepath);
    $incomplete = $sizeBytes >= RECORDING_MIN_BYTES && !recordingHasMoovAtom($filepath);

    return [
        'filename' => $parsed['filename'],
        'start_at' => $start->format('Y-m-d H:i:s'),
        'end_at' => $end->format('Y-m-d H:i:s'),
        'date' => $start->format('Y-m-d'),
        'start_time' => $start->format('H:i:s'),
        'end_time' => $end->format('H:i:s'),
        'label' => $start->format('M j, Y g:i A') . ' – ' . $end->format('g:i A'),
        'size_bytes' => $sizeBytes,
        'size_label' => formatBytes($sizeBytes),
        'playable' => $playable,
        'legacy_codec' => $legacyCodec && $sizeBytes >= RECORDING_MIN_BYTES && !$incomplete,
        'status' => $sizeBytes < RECORDING_MIN_BYTES
            ? 'empty'
            : ($incomplete ? 'recording' : ($playable ? 'ready' : 'legacy_codec')),
    ];
}

function scanRecordingSegments(?string $dateFilter = null, bool $includeEmpty = false): array
{
    ensureRecordingsDirectory();
    $dir = recordingsDirectory();
    if (!is_dir($dir)) {
        return [];
    }

    $segments = [];
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (!isValidRecordingFilename($entry)) {
            continue;
        }

        $filepath = $dir . DIRECTORY_SEPARATOR . $entry;
        if (!$includeEmpty && !recordingIsPlayable($filepath) && filesize($filepath) < RECORDING_MIN_BYTES) {
            continue;
        }

        $parsed = parseRecordingFilename($entry);
        if (!$parsed) {
            continue;
        }

        if ($dateFilter !== null && $dateFilter !== '' && $parsed['start']->format('Y-m-d') !== $dateFilter) {
            continue;
        }

        $segments[] = formatRecordingSegment($parsed, $filepath);
    }

    usort($segments, static function ($a, $b) {
        return strcmp($b['start_at'], $a['start_at']);
    });

    return $segments;
}

function parseTimeOnDate(string $date, string $time): ?DateTime
{
    $date = trim($date);
    $time = trim($time);
    if ($date === '' || $time === '') {
        return null;
    }

    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        $time .= ':00';
    }

    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time);
    return $dt ?: null;
}

function segmentsOverlap(DateTime $segStart, DateTime $segEnd, DateTime $reqStart, DateTime $reqEnd): bool
{
    return $segStart < $reqEnd && $segEnd > $reqStart;
}

function findRecordingSegmentsForWindow(string $date, string $startTime, string $endTime): array
{
    $reqStart = parseTimeOnDate($date, $startTime);
    $reqEnd = parseTimeOnDate($date, $endTime);
    if (!$reqStart || !$reqEnd || $reqEnd <= $reqStart) {
        return [];
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

    return $matches;
}

function cleanupExpiredRecordings(int $retentionDays = RECORDING_RETENTION_DAYS): int
{
    ensureRecordingsDirectory();
    $dir = recordingsDirectory();
    if (!is_dir($dir)) {
        return 0;
    }

    $stampFile = $dir . DIRECTORY_SEPARATOR . '.retention_cleanup';
    if (is_file($stampFile) && (time() - (int) filemtime($stampFile)) < 3600) {
        return 0;
    }

    $cutoff = (new DateTime())->modify('-' . max(1, $retentionDays) . ' days');
    $removed = 0;

    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '.retention_cleanup') {
            continue;
        }
        if (!isValidRecordingFilename($entry)) {
            continue;
        }

        $filepath = $dir . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($filepath)) {
            continue;
        }

        $size = filesize($filepath);
        $parsed = parseRecordingFilename($entry);
        $expired = false;
        if ($parsed) {
            /** @var DateTime $start */
            $start = $parsed['start'];
            $expired = $start < $cutoff;
        } else {
            $expired = filemtime($filepath) < $cutoff->getTimestamp();
        }

        if ($expired || ($size !== false && $size < RECORDING_MIN_BYTES)) {
            if (@unlink($filepath)) {
                $removed++;
            }
        }
    }

    @touch($stampFile);
    return $removed;
}

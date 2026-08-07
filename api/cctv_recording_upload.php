<?php
/**
 * Receive finalized CCTV recording MP4s from the on-site detection PC.
 *
 * POST multipart/form-data (whole file):
 *   recording — MP4 file (required)
 *   filename  — optional override (must match recording_YYYYMMDD_HHMMSS.mp4)
 *
 * POST multipart/form-data (chunked, for Hostinger upload limits):
 *   action=chunk
 *   filename, chunk_index, chunk_total, chunk
 *
 * Auth: header X-CCTV-Upload-Key or Authorization: Bearer {key}
 * Must match CCTV_FRAME_UPLOAD_KEY in .env on the server.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../includes/detection_env.php';
require_once __DIR__ . '/recordings_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$expectedKey = getCctvFrameUploadKey();
if ($expectedKey === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'CCTV upload is not configured on this server.']);
    exit;
}

$providedKey = trim((string) ($_SERVER['HTTP_X_CCTV_UPLOAD_KEY'] ?? ''));
if ($providedKey === '') {
    $auth = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (stripos($auth, 'Bearer ') === 0) {
        $providedKey = trim(substr($auth, 7));
    }
}

if ($providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid upload key']);
    exit;
}

$maxBytes = (int) ($_ENV['CCTV_RECORDING_UPLOAD_MAX_BYTES'] ?? getenv('CCTV_RECORDING_UPLOAD_MAX_BYTES') ?: 120_000_000);
if ($maxBytes < 5_000_000) {
    $maxBytes = 5_000_000;
}

function recordingUploadFail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function normalizeRecordingUploadName(string $name): string
{
    $name = basename(str_replace(["\0", '\\'], '', $name));
    if (!isValidRecordingFilename($name)) {
        recordingUploadFail('Invalid recording filename. Expected recording_YYYYMMDD_HHMMSS.mp4');
    }
    return $name;
}

function finalizeUploadedRecording(string $tempPath, string $filename): array
{
    if (!is_file($tempPath)) {
        recordingUploadFail('Upload temp file missing', 500);
    }

    $size = (int) filesize($tempPath);
    if ($size < RECORDING_MIN_BYTES) {
        @unlink($tempPath);
        recordingUploadFail('Recording file too small');
    }

    // Basic MP4 sanity: must look like media (ftyp / mdat / moov somewhere in ends).
    $head = (string) @file_get_contents($tempPath, false, null, 0, 64);
    if ($head === '' || (stripos($head, 'ftyp') === false && stripos($head, 'mdat') === false)) {
        @unlink($tempPath);
        recordingUploadFail('Invalid MP4 content');
    }

    ensureRecordingsDirectory();
    $dest = recordingsDirectory() . DIRECTORY_SEPARATOR . $filename;
    $staging = $dest . '.uploading';

    if (!@rename($tempPath, $staging)) {
        if (!@copy($tempPath, $staging)) {
            @unlink($tempPath);
            recordingUploadFail('Could not store recording', 500);
        }
        @unlink($tempPath);
    }

    if (is_file($dest)) {
        @unlink($dest);
    }
    if (!@rename($staging, $dest)) {
        @unlink($staging);
        recordingUploadFail('Could not finalize recording', 500);
    }

    $removed = cleanupExpiredRecordings();

    return [
        'success' => true,
        'message' => 'Recording saved',
        'filename' => $filename,
        'size_bytes' => $size,
        'playable' => recordingIsPlayable($dest),
        'retention_removed' => $removed,
        'updated_at' => date('c'),
    ];
}

$action = trim((string) ($_POST['action'] ?? 'upload'));

if ($action === 'chunk') {
    $filename = normalizeRecordingUploadName((string) ($_POST['filename'] ?? ''));
    $chunkIndex = (int) ($_POST['chunk_index'] ?? -1);
    $chunkTotal = (int) ($_POST['chunk_total'] ?? 0);
    if ($chunkIndex < 0 || $chunkTotal < 1 || $chunkIndex >= $chunkTotal) {
        recordingUploadFail('Invalid chunk metadata');
    }
    if (!isset($_FILES['chunk']) || !is_uploaded_file($_FILES['chunk']['tmp_name'])) {
        recordingUploadFail('Missing chunk upload');
    }
    $chunk = $_FILES['chunk'];
    if (($chunk['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        recordingUploadFail('Chunk upload error');
    }
    $chunkSize = (int) ($chunk['size'] ?? 0);
    if ($chunkSize < 1 || $chunkSize > $maxBytes) {
        recordingUploadFail('Invalid chunk size');
    }

    ensureRecordingsDirectory();
    $partsDir = recordingsDirectory() . DIRECTORY_SEPARATOR . '.upload_parts';
    if (!is_dir($partsDir) && !@mkdir($partsDir, 0755, true) && !is_dir($partsDir)) {
        recordingUploadFail('Could not create upload parts directory', 500);
    }

    $safeStem = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);
    $partPath = $partsDir . DIRECTORY_SEPARATOR . $safeStem . '.part' . $chunkIndex;
    if (!@move_uploaded_file($chunk['tmp_name'], $partPath)) {
        recordingUploadFail('Could not save chunk', 500);
    }

    // Wait until all parts exist, then assemble.
    for ($i = 0; $i < $chunkTotal; $i++) {
        $p = $partsDir . DIRECTORY_SEPARATOR . $safeStem . '.part' . $i;
        if (!is_file($p)) {
            echo json_encode([
                'success' => true,
                'message' => 'Chunk received',
                'filename' => $filename,
                'chunk_index' => $chunkIndex,
                'chunk_total' => $chunkTotal,
                'complete' => false,
            ]);
            exit;
        }
    }

    $assembled = $partsDir . DIRECTORY_SEPARATOR . $safeStem . '.assembled';
    $out = @fopen($assembled, 'wb');
    if (!$out) {
        recordingUploadFail('Could not assemble recording', 500);
    }
    $totalSize = 0;
    for ($i = 0; $i < $chunkTotal; $i++) {
        $p = $partsDir . DIRECTORY_SEPARATOR . $safeStem . '.part' . $i;
        $data = @file_get_contents($p);
        if ($data === false) {
            fclose($out);
            @unlink($assembled);
            recordingUploadFail('Could not read chunk part', 500);
        }
        $totalSize += strlen($data);
        if ($totalSize > $maxBytes) {
            fclose($out);
            @unlink($assembled);
            recordingUploadFail('Assembled recording exceeds max size');
        }
        fwrite($out, $data);
        @unlink($p);
    }
    fclose($out);

    $result = finalizeUploadedRecording($assembled, $filename);
    $result['chunked'] = true;
    echo json_encode($result);
    exit;
}

// Whole-file upload
if (!isset($_FILES['recording']) || !is_uploaded_file($_FILES['recording']['tmp_name'])) {
    recordingUploadFail('Missing recording upload');
}

$upload = $_FILES['recording'];
if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $err = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        recordingUploadFail('Recording exceeds server upload limit. Use chunked upload or raise upload_max_filesize.', 413);
    }
    recordingUploadFail('Upload error code ' . $err);
}

$size = (int) ($upload['size'] ?? 0);
if ($size < RECORDING_MIN_BYTES || $size > $maxBytes) {
    recordingUploadFail('Invalid recording size');
}

$filename = trim((string) ($_POST['filename'] ?? ''));
if ($filename === '') {
    $filename = (string) ($upload['name'] ?? '');
}
$filename = normalizeRecordingUploadName($filename);

ensureRecordingsDirectory();
$temp = recordingsDirectory() . DIRECTORY_SEPARATOR . $filename . '.recv';
if (!@move_uploaded_file($upload['tmp_name'], $temp)) {
    recordingUploadFail('Could not receive recording', 500);
}

echo json_encode(finalizeUploadedRecording($temp, $filename));

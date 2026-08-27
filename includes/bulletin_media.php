<?php

function bulletinMediaDirectory(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'bulletin';
}

function bulletinMediaPublicPath(string $filename): string
{
    return 'uploads/bulletin/' . ltrim(str_replace('\\', '/', $filename), '/');
}

function bulletinEnsureUploadDir(): string
{
    $dir = bulletinMediaDirectory();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create bulletin upload directory.');
    }
    return $dir;
}

function bulletinIsImageMime(string $mime): bool
{
    return in_array(strtolower($mime), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
}

function bulletinStoreUploadedFile(array $file, string $kind = 'media'): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed.');
    }

    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 12 * 1024 * 1024) {
        throw new RuntimeException('Each file must be 12 MB or smaller.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $originalName = (string) ($file['name'] ?? 'file');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $fileExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'zip'];

    if ($kind === 'media') {
        if (!bulletinIsImageMime($mime) || !in_array($ext, $imageExts, true)) {
            throw new RuntimeException('Media must be an image (JPG, PNG, GIF, or WEBP).');
        }
    } else {
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv',
            'application/zip', 'application/x-zip-compressed',
        ];
        if (!in_array($mime, $allowedMimes, true) && !in_array($ext, array_merge($imageExts, $fileExts), true)) {
            throw new RuntimeException('Unsupported attachment type.');
        }
    }

    $safeExt = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
    $filename = sprintf('%s_%s.%s', $kind, bin2hex(random_bytes(8)), $safeExt);
    $dest = bulletinEnsureUploadDir() . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Failed to save uploaded file.');
    }

    return bulletinMediaPublicPath($filename);
}

/**
 * Normalize $_FILES entry that may be single or multi-upload.
 * @return list<array>
 */
function bulletinNormalizeFilesArray(?array $filesField): array
{
    if ($filesField === null || !isset($filesField['name'])) {
        return [];
    }

    if (!is_array($filesField['name'])) {
        if (($filesField['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        return [$filesField];
    }

    $out = [];
    $count = count($filesField['name']);
    for ($i = 0; $i < $count; $i++) {
        if (($filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name' => $filesField['name'][$i],
            'type' => $filesField['type'][$i] ?? '',
            'tmp_name' => $filesField['tmp_name'][$i],
            'error' => $filesField['error'][$i],
            'size' => $filesField['size'][$i] ?? 0,
        ];
    }
    return $out;
}

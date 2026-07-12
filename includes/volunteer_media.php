<?php

function volunteerMediaDirectory(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'volunteers';
}

function volunteerMediaPublicPath(string $filename): string
{
    return 'uploads/volunteers/' . $filename;
}

function volunteerMediaIsDataUrl(?string $value): bool
{
    return is_string($value) && str_starts_with($value, 'data:image/');
}

function volunteerMediaOptimizeBinary(string $binary): string
{
    if (!function_exists('imagecreatefromstring')) {
        return $binary;
    }

    $image = @imagecreatefromstring($binary);
    if ($image === false) {
        return $binary;
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $maxWidth = 1280;

    if ($width > $maxWidth) {
        $newHeight = (int) round($height * ($maxWidth / $width));
        $resized = imagecreatetruecolor($maxWidth, $newHeight);
        if ($resized !== false) {
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
            $width = $maxWidth;
            $height = $newHeight;
        }
    }

    ob_start();
    imagejpeg($image, null, 82);
    imagedestroy($image);

    return (string) ob_get_clean();
}

function volunteerMediaStore(?string $value, string $type, int $memberId): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!volunteerMediaIsDataUrl($value)) {
        return $value;
    }

    if (!preg_match('#^data:image/(jpeg|jpg|png|gif|webp);base64,(.+)$#i', $value, $matches)) {
        throw new RuntimeException('Invalid image format. Please upload JPG or PNG images.');
    }

    $binary = base64_decode($matches[2], true);
    if ($binary === false) {
        throw new RuntimeException('Invalid image data.');
    }

    if (strlen($binary) > 8 * 1024 * 1024) {
        throw new RuntimeException('Image file is too large. Please upload images under 8 MB.');
    }

    $binary = volunteerMediaOptimizeBinary($binary);

    $dir = volunteerMediaDirectory();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create upload directory.');
    }

    $filename = sprintf('member_%d_%s.jpg', $memberId, preg_replace('/[^a-z0-9_]+/i', '_', $type));
    $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;

    if (file_put_contents($fullPath, $binary) === false) {
        throw new RuntimeException('Failed to save uploaded image.');
    }

    return volunteerMediaPublicPath($filename);
}

function volunteerMediaDelete(?string $publicPath): void
{
    if ($publicPath === null || $publicPath === '' || volunteerMediaIsDataUrl($publicPath)) {
        return;
    }

    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $publicPath);
    $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR);

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

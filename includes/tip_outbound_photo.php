<?php

/**
 * Normalize / compress tip photos for partner outbound payloads.
 */

function prepareTipOutboundPhoto(?string $photoData, int $maxChars = 900_000): array
{
    $raw = trim((string) $photoData);
    if ($raw === '') {
        return [
            'has_photo' => false,
            'photo_data' => '',
            'photo_base64' => '',
            'mime' => '',
        ];
    }

    $dataUrl = $raw;
    if (!str_starts_with($raw, 'data:')) {
        // Bare base64 from older clients — assume JPEG.
        $dataUrl = 'data:image/jpeg;base64,' . preg_replace('/\s+/', '', $raw);
    }

    // Always try to compress sizable images — partner hosts often 500 on multi‑MB JSON.
    if (strlen($dataUrl) > 180_000 || !str_contains(strtolower($dataUrl), 'image/jpeg')) {
        $compressed = tipOutboundCompressPhotoDataUrl($dataUrl, 1024, 72);
        if ($compressed !== '') {
            $dataUrl = $compressed;
        }
    }

    if (strlen($dataUrl) > $maxChars) {
        $compressed = tipOutboundCompressPhotoDataUrl($dataUrl, 800, 62);
        if ($compressed !== '' && strlen($compressed) < strlen($dataUrl)) {
            $dataUrl = $compressed;
        }
    }

    if (strlen($dataUrl) > $maxChars) {
        $compressed = tipOutboundCompressPhotoDataUrl($dataUrl, 640, 55);
        if ($compressed !== '' && strlen($compressed) <= $maxChars) {
            $dataUrl = $compressed;
        }
    }

    // If still too large for shared-host JSON posts, drop the photo rather than crash partners.
    if (strlen($dataUrl) > $maxChars) {
        return [
            'has_photo' => false,
            'photo_data' => '',
            'photo_base64' => '',
            'mime' => '',
            'omitted' => true,
        ];
    }

    $base64 = '';
    $mime = 'image/jpeg';
    if (preg_match('#^data:(image/[^;]+);base64,(.+)$#is', $dataUrl, $matches)) {
        $mime = strtolower(trim($matches[1]));
        $base64 = preg_replace('/\s+/', '', $matches[2]);
    }

    return [
        'has_photo' => $base64 !== '',
        'photo_data' => $dataUrl,
        'photo_base64' => $base64,
        'mime' => $mime,
    ];
}

function tipOutboundCompressPhotoDataUrl(string $dataUrl, int $maxWidth = 1280, int $quality = 78): string
{
    if (!function_exists('imagecreatefromstring')) {
        return '';
    }

    if (!preg_match('#^data:image/[^;]+;base64,(.+)$#is', $dataUrl, $matches)) {
        return '';
    }

    $binary = base64_decode($matches[1], true);
    if ($binary === false || $binary === '') {
        return '';
    }

    $image = @imagecreatefromstring($binary);
    if ($image === false) {
        return '';
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $maxWidth = max(320, $maxWidth);

    if ($width > $maxWidth) {
        $newHeight = (int) max(1, round($height * ($maxWidth / $width)));
        $resized = imagecreatetruecolor($maxWidth, $newHeight);
        if ($resized !== false) {
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }
    }

    ob_start();
    imagejpeg($image, null, max(40, min(90, $quality)));
    imagedestroy($image);
    $jpeg = (string) ob_get_clean();
    if ($jpeg === '') {
        return '';
    }

    return 'data:image/jpeg;base64,' . base64_encode($jpeg);
}

<?php

/**
 * Normalize / compress tip photos for partner outbound payloads.
 */

function prepareTipOutboundPhoto(?string $photoData, int $maxChars = 4_500_000): array
{
    $raw = trim((string) $photoData);
    if ($raw === '') {
        return [
            'has_photo' => false,
            'photo_data' => '',
        ];
    }

    $dataUrl = $raw;
    if (!str_starts_with($raw, 'data:')) {
        // Bare base64 from older clients — assume JPEG.
        $dataUrl = 'data:image/jpeg;base64,' . $raw;
    }

    if (strlen($dataUrl) <= $maxChars) {
        return [
            'has_photo' => true,
            'photo_data' => $dataUrl,
        ];
    }

    $compressed = tipOutboundCompressPhotoDataUrl($dataUrl);
    if ($compressed !== '' && (strlen($compressed) <= $maxChars || strlen($compressed) < strlen($dataUrl))) {
        return [
            'has_photo' => true,
            'photo_data' => $compressed,
        ];
    }

    // Still include original rather than silently dropping evidence.
    return [
        'has_photo' => true,
        'photo_data' => $dataUrl,
    ];
}

function tipOutboundCompressPhotoDataUrl(string $dataUrl): string
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
    $maxWidth = 1280;

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
    imagejpeg($image, null, 78);
    imagedestroy($image);
    $jpeg = (string) ob_get_clean();
    if ($jpeg === '') {
        return '';
    }

    return 'data:image/jpeg;base64,' . base64_encode($jpeg);
}

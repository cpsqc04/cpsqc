<?php

/**
 * Build the application base URL (scheme + host + app directory).
 */
function getAppBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $dir = str_replace('\\', '/', dirname($script));

    if (preg_match('#/api$#', $dir)) {
        $dir = dirname($dir);
    }

    if ($dir === '/' || $dir === '.') {
        $dir = '';
    }

    return rtrim("{$scheme}://{$host}{$dir}", '/');
}

function getNwMemberPortalUrl(): string
{
    return getAppBaseUrl() . '/nw-login.php';
}

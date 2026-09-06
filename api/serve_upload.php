<?php
/**
 * Serve files under uploads/ for logged-in admin or neighborhood watch members.
 * Used when the reverse proxy does not expose static /uploads URLs.
 */
session_start();

require_once __DIR__ . '/../includes/neighborhood-watcher-member-auth.php';
require_once __DIR__ . '/../includes/volunteer_media.php';

$isAdmin = !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isNwMember = isNwMemberLoggedIn();
if (!$isAdmin && !$isNwMember) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

$publicPath = trim((string) ($_GET['path'] ?? ''));
$fullPath = volunteerMediaResolvePath($publicPath);
if ($fullPath === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}

$extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'pdf' => 'application/pdf',
];
$contentType = $types[$extension] ?? 'application/octet-stream';

header('Content-Type: ' . $contentType);
header('Content-Length: ' . (string) filesize($fullPath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($fullPath);
exit;

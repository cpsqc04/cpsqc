<?php
/**
 * Clean in-page live embed (no video chrome / hover controls).
 * Open Surveillance iframes this same-origin page; media comes from go2rtc (LAN or HTTPS tunnel).
 */
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unauthorized';
    exit;
}

$base = trim((string) ($_GET['base'] ?? ''));
$stream = trim((string) ($_GET['src'] ?? 'alertara_live'));
if ($stream === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $stream)) {
    $stream = 'alertara_live';
}
// Only allow http(s) bases (LAN go2rtc or Cloudflare tunnel).
if ($base === '' || !preg_match('#^https?://[a-zA-Z0-9.-]+(?::\d+)?#', $base)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Missing stream base';
    exit;
}
$base = rtrim($base, '/');
$wsBase = preg_replace('#^http#', 'ws', $base);
$wsUrl = $wsBase . '/api/ws?src=' . rawurlencode($stream);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live</title>
    <style>
        html, body {
            margin: 0;
            width: 100%;
            height: 100%;
            background: #000;
            overflow: hidden;
        }
        video-stream, video {
            width: 100% !important;
            height: 100% !important;
            display: block;
            object-fit: contain;
            background: #000;
        }
        /* Kill native media chrome if any browser injects it */
        video::-webkit-media-controls { display: none !important; }
        video::-webkit-media-controls-enclosure { display: none !important; }
    </style>
    <script type="module" src="https://cdn.jsdelivr.net/gh/AlexxIT/go2rtc@v1.9.4/www/video-stream.js"></script>
</head>
<body>
<script type="module">
    const vs = document.createElement('video-stream');
    vs.background = true;
    vs.mode = 'mse,webrtc';
    vs.src = <?php echo json_encode($wsUrl, JSON_UNESCAPED_SLASHES); ?>;
    document.body.appendChild(vs);

    const lockChrome = () => {
        const video = vs.querySelector('video') || vs.video;
        if (!video) return;
        video.controls = false;
        video.removeAttribute('controls');
        video.disablePictureInPicture = true;
        video.setAttribute('controlslist', 'nodownload nofullscreen noremoteplayback');
        video.setAttribute('playsinline', '');
        video.muted = true;
        video.autoplay = true;
        try { video.play().catch(() => {}); } catch (e) {}
    };
    lockChrome();
    setInterval(lockChrome, 500);
</script>
</body>
</html>

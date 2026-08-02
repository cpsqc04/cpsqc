<?php

/**
 * AlertaraQC Partner API — simple public entry page for partner systems.
 *
 * Share this URL with partners:
 *   https://surveillance.alertaraqc.com/api/partner-api.php
 *
 * Optional: ?format=json  → full machine-readable catalog (pretty JSON)
 */

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api'));
$projectRoot = dirname($scriptDir);
$baseUrl = rtrim($scheme . '://' . $host . ($projectRoot === '/' ? '' : $projectRoot), '/');

$format = strtolower(trim((string) ($_GET['format'] ?? '')));
if ($format === 'json' || $format === 'catalog') {
    $_GET['pretty'] = '1';
    require __DIR__ . '/integration.php';
    exit;
}

$endpoints = [
    [
        'name' => 'Patrol Request',
        'method' => 'POST',
        'path' => '/api/patrol_requests_receive.php',
        'for' => 'Campaign & Disaster Preparedness — event patrol requests',
    ],
    [
        'name' => 'CCTV Footage Request',
        'method' => 'POST',
        'path' => '/api/cctv_requests_receive.php',
        'for' => 'Partner agencies — footage requests',
    ],
    [
        'name' => 'Awareness Event / Report',
        'method' => 'POST',
        'path' => '/api/awareness_events_receive.php',
        'for' => 'Campaign — event list & reports',
    ],
    [
        'name' => 'Crime Analytics Alert',
        'method' => 'POST',
        'path' => '/api/crime_analytics_alerts_receive.php',
        'for' => 'Crime Analytics — risk / hotspot alerts',
    ],
    [
        'name' => 'Complaint Status Update',
        'method' => 'POST',
        'path' => '/api/complaints_status_receive.php',
        'for' => 'Incident Reporting — status pushback',
    ],
];

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AlertaraQC Partner API</title>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($baseUrl . '/images/favicon.ico'); ?>">
    <style>
        :root {
            --teal: #2f6f6e;
            --teal-dark: #1f4f4e;
            --ink: #1a2423;
            --muted: #5b6b6a;
            --line: #d7e2e1;
            --bg: #f3f7f6;
            --card: #ffffff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top right, rgba(47, 111, 110, 0.12), transparent 40%),
                linear-gradient(180deg, #eaf2f1 0%, var(--bg) 40%, #eef3f2 100%);
            min-height: 100vh;
        }
        .wrap {
            max-width: 880px;
            margin: 0 auto;
            padding: 2.5rem 1.25rem 3rem;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.75rem;
        }
        .brand img {
            width: 72px;
            height: 72px;
            object-fit: contain;
        }
        .brand h1 {
            margin: 0;
            font-size: 1.75rem;
            line-height: 1.2;
            color: var(--teal-dark);
        }
        .brand p {
            margin: 0.25rem 0 0;
            color: var(--muted);
            font-size: 0.95rem;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 1.25rem 1.35rem;
            margin-bottom: 1rem;
            box-shadow: 0 8px 24px rgba(31, 79, 78, 0.06);
        }
        .card h2 {
            margin: 0 0 0.75rem;
            font-size: 1.05rem;
            color: var(--teal-dark);
        }
        .share-url {
            display: block;
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            background: #eef6f5;
            border: 1px dashed #9cbdbb;
            color: var(--teal-dark);
            font-family: Consolas, "Courier New", monospace;
            font-size: 0.92rem;
            word-break: break-all;
            text-decoration: none;
        }
        .notes {
            margin: 0;
            padding-left: 1.1rem;
            color: var(--muted);
            line-height: 1.55;
        }
        .endpoint {
            display: grid;
            gap: 0.35rem;
            padding: 0.9rem 0;
            border-top: 1px solid var(--line);
        }
        .endpoint:first-of-type { border-top: 0; padding-top: 0; }
        .endpoint-name {
            font-weight: 650;
            color: var(--ink);
        }
        .endpoint-for {
            color: var(--muted);
            font-size: 0.9rem;
        }
        .endpoint-url {
            font-family: Consolas, "Courier New", monospace;
            font-size: 0.84rem;
            color: var(--teal-dark);
            word-break: break-all;
        }
        .method {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #fff;
            background: var(--teal);
            border-radius: 999px;
            padding: 0.15rem 0.5rem;
            margin-right: 0.35rem;
            vertical-align: middle;
        }
        .links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
        }
        .links a {
            display: inline-block;
            text-decoration: none;
            color: #fff;
            background: var(--teal);
            border-radius: 8px;
            padding: 0.65rem 0.95rem;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .links a.secondary {
            background: #fff;
            color: var(--teal-dark);
            border: 1px solid var(--line);
        }
        .footer {
            margin-top: 1.25rem;
            color: var(--muted);
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">
            <img src="<?php echo htmlspecialchars($baseUrl . '/images/tara.png'); ?>" alt="AlertaraQC" onerror="this.style.display='none'">
            <div>
                <h1>AlertaraQC Partner API</h1>
                <p>Barangay San Agustin · Quezon City Surveillance System</p>
            </div>
        </div>

        <div class="card">
            <h2>Share this link</h2>
            <a class="share-url" href="<?php echo htmlspecialchars($baseUrl . '/api/partner-api.php'); ?>">
                <?php echo htmlspecialchars($baseUrl . '/api/partner-api.php'); ?>
            </a>
        </div>

        <div class="card">
            <h2>How to connect</h2>
            <ul class="notes">
                <li>Send JSON with header <strong>Content-Type: application/json</strong></li>
                <li>Inbound partner APIs are <strong>public</strong> — no API key required</li>
                <li>Use the endpoint that matches your module below</li>
            </ul>
        </div>

        <div class="card">
            <h2>Inbound endpoints (partners → AlertaraQC)</h2>
            <?php foreach ($endpoints as $ep): ?>
                <div class="endpoint">
                    <div class="endpoint-name">
                        <span class="method"><?php echo htmlspecialchars($ep['method']); ?></span>
                        <?php echo htmlspecialchars($ep['name']); ?>
                    </div>
                    <div class="endpoint-for"><?php echo htmlspecialchars($ep['for']); ?></div>
                    <div class="endpoint-url"><?php echo htmlspecialchars($baseUrl . $ep['path']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h2>More documentation</h2>
            <div class="links">
                <a href="<?php echo htmlspecialchars($baseUrl . '/api/partner-api.php?format=json'); ?>">Full JSON catalog</a>
                <a class="secondary" href="<?php echo htmlspecialchars($baseUrl . '/api/PARTNER_MODULES.md'); ?>">Module cheat sheet</a>
                <a class="secondary" href="<?php echo htmlspecialchars($baseUrl . '/api/API_INTEGRATION.md'); ?>">Full integration guide</a>
            </div>
        </div>

        <p class="footer">AlertaraQC · Community Policing and Safety Quality Control</p>
    </div>
</body>
</html>

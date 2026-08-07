<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/detection_process.php';

$cctvNavActive = 'open-surveillance';
$localDetectionEnabled = isLocalDetectionEnabled();
$cctvFeedMode = getCctvFeedMode();

// Start detection immediately when Open Surveillance is opened.
ensureLocalDetectionStarted();

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Open Surveillance - Alertara</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/admin-sidebar.css">
    <style>
        body { margin: 0; padding: 0; font-family: var(--font-family); background-color: var(--bg-color); display: flex; min-height: 100vh; }
        .sidebar { width: 320px; background: var(--tertiary-color); color: #fff; position: fixed; left: 0; top: 0; height: 100vh; overflow: hidden; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); z-index: 1000; transition: width 0.3s ease; display: flex; flex-direction: column; }
        .sidebar::-webkit-scrollbar { display: none; }
        .sidebar { -ms-overflow-style: none; scrollbar-width: none; }
        .sidebar.collapsed { width: 80px; }
        .sidebar-header { padding: 1.5rem 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; min-height: 160px; }
        .logo-container { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; transition: all 0.3s ease; }
        .logo-container a { text-decoration: none; display: block; transition: all 0.3s ease; }
        .logo-container a:hover { opacity: 0.8; transform: scale(1.05); }
        .logo-container img { height: 130px; width: 130px; object-fit: contain; transition: all 0.3s ease; }
        .sidebar.collapsed .logo-container img { height: 70px; width: 70px; }
        .sidebar-nav { padding: 0.5rem 0; overflow-y: auto; overflow-x: hidden; flex: 1; display: flex; flex-direction: column; min-height: 0; scrollbar-width: thin; scrollbar-color: rgba(255, 255, 255, 0.3) transparent; }
        .sidebar-nav::-webkit-scrollbar { width: 6px; }
        .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.3); border-radius: 3px; }
        .sidebar-nav::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.5); }
        .sidebar.collapsed .sidebar-nav { overflow-y: auto; overflow-x: hidden; display: flex !important; flex-direction: column; }
        .nav-module { margin-bottom: 0.125rem; display: block !important; visibility: visible !important; }
        .sidebar.collapsed .nav-module { display: block !important; visibility: visible !important; }
        .nav-module-header { display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.5rem; color: rgba(255, 255, 255, 0.9); cursor: pointer; transition: background-color 0.2s ease, padding 0.3s ease; font-weight: 500; user-select: none; white-space: normal; overflow: visible; font-size: 0.9rem; position: relative; gap: 0.75rem; line-height: 1.4; }
        .sidebar.collapsed .nav-module-header { padding: 0.75rem; justify-content: center; min-height: 48px; max-height: 48px; display: flex !important; visibility: visible !important; cursor: pointer; margin: 0.25rem 0.5rem; border-radius: 8px; position: relative; }
        .sidebar.collapsed .nav-module-header:hover { background: rgba(255, 255, 255, 0.1); }
        .nav-module-icon { font-size: 1.4rem; width: 28px; height: 28px; display: flex !important; align-items: center; justify-content: center; flex-shrink: 0; transition: font-size 0.3s ease; opacity: 1 !important; visibility: visible !important; position: relative; }
        .nav-module-icon i { font-size: 1.2rem; color: rgba(255, 255, 255, 0.9); }
        .sidebar.collapsed .nav-module-icon { font-size: 1.5rem; width: auto; height: auto; margin: 0; padding: 0; display: flex !important; opacity: 1 !important; visibility: visible !important; position: relative; transform: none; }
        .sidebar.collapsed .nav-module-icon i { font-size: 1.3rem; }
        .nav-module-header-text { flex: 1; transition: opacity 0.3s ease; opacity: 1; word-wrap: break-word; overflow-wrap: break-word; min-width: 0; }
        .sidebar.collapsed .nav-module-header-text { opacity: 0; width: 0; overflow: hidden; }
        .nav-module-header:hover { background: rgba(255, 255, 255, 0.08); color: #fff; }
        .nav-module-header .arrow { font-size: 0.7rem; transition: transform 0.3s ease, opacity 0.3s ease; color: rgba(255, 255, 255, 0.6); flex-shrink: 0; margin-left: 0.5rem; }
        .sidebar.collapsed .nav-module-header .arrow { opacity: 0; width: 0; overflow: hidden; margin: 0; }
        .nav-module.active .nav-module-header .arrow { transform: rotate(90deg); }
        .nav-module.active .nav-module-header { background: rgba(255, 255, 255, 0.1); color: #fff; }
        .nav-submodules { max-height: 0; overflow: hidden; transition: max-height 0.3s ease; background: rgba(0, 0, 0, 0.15); }
        .nav-module.active .nav-submodules { max-height: 500px; }
        .sidebar.collapsed .nav-submodules { display: none !important; }
        .sidebar.collapsed .nav-module.active .nav-submodules { display: none !important; }
        .nav-submodule { padding: 0.75rem 1.5rem 0.75rem 3.5rem; color: rgba(255, 255, 255, 0.75); text-decoration: none; display: flex; align-items: center; gap: 0.75rem; transition: all 0.2s ease; font-size: 0.85rem; white-space: nowrap; overflow: hidden; position: relative; }
        .sidebar.collapsed .nav-submodule { padding: 0.75rem; justify-content: center; min-height: 44px; }
        .nav-submodule-icon { font-size: 1.1rem; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all 0.3s ease; opacity: 1; }
        .nav-submodule-icon i { font-size: 0.95rem; color: rgba(255, 255, 255, 0.75); }
        .sidebar.collapsed .nav-submodule-icon { font-size: 1.4rem; width: auto; height: auto; margin: 0; display: flex !important; opacity: 1 !important; visibility: visible !important; }
        .sidebar.collapsed .nav-submodule-icon i { font-size: 1.2rem; }
        .nav-submodule-text { flex: 1; transition: opacity 0.3s ease; opacity: 1; }
        .sidebar.collapsed .nav-submodule-text { opacity: 0; width: 0; overflow: hidden; display: none; }
        .sidebar.collapsed .nav-module-header::after, .sidebar.collapsed .nav-submodule::after { content: attr(data-tooltip); position: absolute; left: 100%; top: 50%; transform: translateY(-50%); background: rgba(0, 0, 0, 0.9); color: #fff; padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.85rem; white-space: nowrap; opacity: 0; pointer-events: none; transition: opacity 0.2s ease; margin-left: 0.75rem; z-index: 2000; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); }
        .sidebar.collapsed .nav-module-header::before, .sidebar.collapsed .nav-submodule::before { content: ''; position: absolute; left: 100%; top: 50%; transform: translateY(-50%); border: 6px solid transparent; border-right-color: rgba(0, 0, 0, 0.9); opacity: 0; pointer-events: none; transition: opacity 0.2s ease; margin-left: 0.5rem; z-index: 2001; }
        .sidebar.collapsed .nav-module-header:hover::after, .sidebar.collapsed .nav-submodule:hover::after { opacity: 1; }
        .sidebar.collapsed .nav-module-header:hover::before, .sidebar.collapsed .nav-submodule:hover::before { opacity: 1; }
        .sidebar.collapsed .nav-module { margin-bottom: 0.25rem; display: block !important; visibility: visible !important; opacity: 1 !important; height: auto !important; position: relative; }
        .sidebar.collapsed .nav-module-header { border-radius: 8px; margin: 0.25rem 0.5rem; padding: 0.75rem; min-height: 48px; max-height: 48px; cursor: pointer; display: flex !important; visibility: visible !important; opacity: 1 !important; justify-content: center; align-items: center; position: relative; box-sizing: border-box; }
        .sidebar.collapsed .nav-module-header:hover { background: rgba(255, 255, 255, 0.15); }
        .sidebar.collapsed .nav-module.active .nav-module-header { background: rgba(76, 138, 137, 0.4); }
        .sidebar.collapsed .nav-module-icon { display: flex !important; visibility: visible !important; opacity: 1 !important; font-size: 1.5rem; position: relative; margin: 0; padding: 0; transform: none; }
        .nav-submodule:hover { background: rgba(255, 255, 255, 0.05); color: #fff; padding-left: 4rem; }
        .sidebar.collapsed .nav-submodule:hover { padding-left: 1rem; }
        .nav-submodule.active { background: rgba(76, 138, 137, 0.25); color: #4c8a89; border-left: 3px solid #4c8a89; font-weight: 500; }
        .sidebar.collapsed .nav-submodule.active { border-left: none; border-top: 3px solid #4c8a89; }
        .main-wrapper { margin-left: 320px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; transition: margin-left 0.3s ease; }
        body.sidebar-collapsed .main-wrapper { margin-left: 80px; }
        .top-header { background: var(--header-bg); padding: 1.5rem 2rem 1rem 2rem; display: flex; justify-content: space-between; align-items: flex-end; position: sticky; top: 0; z-index: 100; border-bottom: 1px solid var(--border-color); }
        .top-header-content { flex: 1; display: flex; align-items: center; gap: 1rem; }
        .user-info { display: flex; align-items: center; gap: 1rem; margin-left: 2rem; }
        .notification-container { position: relative; display: flex; align-items: center; }
        .notification-bell { position: relative; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: transparent; border: none; color: var(--text-color); font-size: 1.25rem; cursor: pointer; border-radius: 8px; transition: all 0.2s ease; }
        .notification-bell:hover { background: rgba(28, 37, 65, 0.05); color: var(--primary-color); }
        .notification-badge { position: absolute; top: 4px; right: 4px; background: #ef4444; color: white; font-size: 0.7rem; font-weight: 600; padding: 2px 6px; border-radius: 10px; min-width: 18px; text-align: center; display: none; }
        .notification-badge.show { display: block; }
        .notification-dropdown { position: absolute; top: calc(100% + 10px); right: 0; width: 380px; max-height: 500px; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15); display: none; flex-direction: column; z-index: 1000; overflow: hidden; }
        .notification-dropdown.show { display: flex; }
        .notification-header { padding: 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: var(--header-bg); }
        .notification-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--text-color); }
        .notification-header button { background: transparent; border: none; color: var(--primary-color); font-size: 0.85rem; cursor: pointer; padding: 0.25rem 0.5rem; border-radius: 4px; transition: background 0.2s ease; }
        .notification-header button:hover { background: rgba(76, 138, 137, 0.1); }
        .notification-list { flex: 1; overflow-y: auto; max-height: 400px; }
        .notification-item { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: background 0.2s ease; display: flex; gap: 0.75rem; position: relative; }
        .notification-item:hover { background: #f8f9fa; }
        .notification-item.unread { background: #f0f9ff; border-left: 3px solid var(--primary-color); }
        .notification-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .notification-icon.complaint { background: #fee2e2; color: #dc2626; }
        .notification-icon.tip { background: #fef3c7; color: #d97706; }
        .notification-icon.volunteer { background: #dbeafe; color: #2563eb; }
        .notification-icon.event { background: #d1fae5; color: #059669; }
        .notification-icon.login { background: #dbeafe; color: #2563eb; }
        .notification-icon.logout { background: #e0e7ff; color: #6366f1; }
        .notification-icon.cctv_request { background: #ede9fe; color: #7c3aed; }
        .notification-content { flex: 1; min-width: 0; }
        .notification-title { font-weight: 600; color: var(--text-color); font-size: 0.95rem; margin: 0 0 0.25rem 0; }
        .notification-message { color: var(--text-secondary); font-size: 0.85rem; margin: 0 0 0.5rem 0; line-height: 1.4; }
        .notification-time { color: var(--text-secondary); font-size: 0.75rem; }
        .notification-empty { padding: 3rem 1.5rem; text-align: center; color: var(--text-secondary); }
        .notification-empty i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.3; }
        .datetime-display { display: flex; align-items: center; gap: 0.75rem; color: var(--text-color); font-size: 0.9rem; font-weight: 500; margin-right: 1rem; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .datetime-display .date-part { color: var(--text-secondary); }
        .datetime-display .time-part { color: var(--text-color); font-weight: 600; }
        .sidebar-footer { margin-top: auto; padding: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.5rem; background: rgba(239, 68, 68, 0.1); color: rgba(255, 255, 255, 0.9); text-decoration: none; border-radius: 8px; font-size: 1rem; font-weight: 500; transition: all 0.2s ease; border: 1px solid rgba(239, 68, 68, 0.2); width: 100%; box-sizing: border-box; }
        .sidebar-logout-btn:hover { background: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.4); color: #fff; }
        .sidebar-logout-btn i { font-size: 1.1rem; flex-shrink: 0; }
        .sidebar-logout-btn span { flex: 1; transition: opacity 0.3s ease; }
        .sidebar.collapsed .sidebar-logout-btn span { opacity: 0; width: 0; overflow: hidden; }
        .sidebar.collapsed .sidebar-logout-btn { justify-content: center; padding: 0.875rem; }
        .content-area { padding: 2rem; flex: 1; background: #f5f5f5; }
        .content-burger-btn { background: transparent; border: none; color: var(--tertiary-color); width: 40px; height: 40px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; flex-shrink: 0; padding: 0; }
        .content-burger-btn:hover { background: rgba(28, 37, 65, 0.05); }
        .content-burger-btn span { display: block; width: 22px; height: 1.5px; background: var(--tertiary-color); position: relative; transition: all 0.3s ease; }
        .content-burger-btn span::before, .content-burger-btn span::after { content: ''; position: absolute; width: 22px; height: 1.5px; background: var(--tertiary-color); transition: all 0.3s ease; }
        .content-burger-btn span::before { top: -7px; }
        .content-burger-btn span::after { bottom: -7px; }
        .page-title { font-size: 2rem; font-weight: 700; color: var(--tertiary-color); margin: 0; }
        .page-content { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; box-shadow: 0 2px 8px var(--shadow); margin-top: 1.5rem; }
        .section-block { margin-bottom: 2.5rem; }
        .section-block:last-child { margin-bottom: 0; }
        .section-title { margin: 0 0 1rem; font-size: 1.15rem; font-weight: 600; color: var(--tertiary-color); display: flex; align-items: center; gap: 0.5rem; }
        .surveillance-panel { display: grid; gap: 1.25rem; }
        .surveillance-layout { display: grid; grid-template-columns: minmax(0, 1fr) minmax(300px, 400px); gap: 1.25rem; align-items: start; }
        .surveillance-meta { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between; }
        .live-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0.75rem; border-radius: 999px; font-size: 0.85rem; font-weight: 600; background: #e9ecef; color: #6c757d; }
        .live-badge.active { background: #d1e7dd; color: #0f5132; }
        .live-badge .dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
        .live-badge.active .dot { animation: pulse 1.2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
        .video-shell { background: #0f172a; border-radius: 12px; overflow: hidden; border: 1px solid var(--border-color); width: 100%; aspect-ratio: 16 / 9; max-height: 75vh; display: flex; align-items: center; justify-content: center; position: relative; }
        .video-shell .camera-feed { width: 100%; height: 100%; display: none; background: #000; object-fit: contain; object-position: center; image-rendering: auto; }
        .video-shell .camera-feed.active { display: block; }
        .video-shell .webrtc-frame {
            width: 100%;
            height: 100%;
            border: 0;
            display: none;
            background: #000;
            pointer-events: none; /* no video chrome / hover controls */
        }
        .video-shell .webrtc-frame.active { display: block; }
        .video-shell:fullscreen .webrtc-frame,
        .video-shell:-webkit-full-screen .webrtc-frame { width: 100%; height: 100%; }
        .fullscreen-btn { pointer-events: auto; }
        .live-mode-chip { display: inline-flex; align-items: center; gap: 0.35rem; margin-left: 0.5rem; padding: 0.2rem 0.55rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.02em; background: rgba(15, 23, 42, 0.08); color: var(--text-secondary); }
        .live-mode-chip.webrtc { background: rgba(76, 138, 137, 0.15); color: #0f766e; }
        .live-mode-chip.jpeg { background: rgba(234, 179, 8, 0.18); color: #854d0e; }
        .video-shell:fullscreen,
        .video-shell:-webkit-full-screen { border-radius: 0; border: none; aspect-ratio: auto; max-height: none; min-height: 100vh; width: 100vw; height: 100vh; background: #000; }
        .video-shell:fullscreen .camera-feed,
        .video-shell:-webkit-full-screen .camera-feed { width: 100%; height: 100%; object-fit: contain; }
        .fullscreen-btn { position: absolute; top: 0.75rem; right: 0.75rem; z-index: 5; width: 40px; height: 40px; border: none; border-radius: 8px; background: rgba(15, 23, 42, 0.72); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: background 0.2s ease, transform 0.2s ease; }
        .fullscreen-btn:hover { background: rgba(76, 138, 137, 0.9); transform: scale(1.05); }
        .fullscreen-btn:focus-visible { outline: 2px solid #4c8a89; outline-offset: 2px; }
        .feed-overlay { position: absolute; z-index: 3; pointer-events: none; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.9), 0 0 8px rgba(0,0,0,0.7); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; opacity: 0; transition: opacity 0.2s ease; }
        .feed-overlay.visible { opacity: 1; }
        .feed-overlay-camera { bottom: 0.85rem; right: 0.85rem; font-size: clamp(0.95rem, 1.8vw, 1.25rem); font-weight: 700; background: rgba(0,0,0,0.5); padding: 0.4rem 0.85rem; border-radius: 8px; max-width: min(70%, 420px); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .detection-overlay {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 4;
        }
        .video-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; color: rgba(255,255,255,0.75); text-align: center; padding: 2rem; position: absolute; inset: 0; z-index: 2; }
        .video-placeholder.hidden { display: none; }
        .video-placeholder i { font-size: 3rem; margin-bottom: 0.75rem; opacity: 0.8; }
        .surveillance-error { display: none; margin: 0; padding: 0.85rem 1rem; border-radius: 8px; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .surveillance-error.show { display: block; }
        .detection-panel {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
            max-height: calc(75vh + 2rem);
            display: flex;
            flex-direction: column;
            min-height: 420px;
            min-width: 0;
        }
        .detection-panel h3 {
            margin: 0 0 0.85rem;
            font-size: 1.05rem;
            color: var(--tertiary-color);
            font-weight: 700;
        }
        .detection-cards {
            display: grid;
            gap: 0.85rem;
            overflow-y: auto;
            padding-right: 0.25rem;
            flex: 1;
        }
        .detection-card {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.85rem;
            overflow: hidden;
        }
        .detection-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        .detection-card .tag {
            padding: 0.2rem 0.65rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #fff;
            background: var(--primary-color);
            text-transform: capitalize;
        }
        .detection-card .tag.plant { background: #059669; }
        .detection-card .tag.phone { background: #2563eb; }
        .detection-card .tag.backpack { background: #7c3aed; }
        .detection-card .tag.suitcase { background: #d97706; }
        .detection-card .tag.group { background: #7c3aed; }
        .detection-card .tag.crowd { background: #db2777; }
        .detection-card .tag.person { background: #0ea5e9; }
        .detection-card .tag.vehicle { background: #ea580c; }
        .detection-card .tag.animal { background: #ca8a04; }
        .detection-card .tag.suspicious-flag {
            background: #dc2626;
            margin-left: 0.35rem;
        }
        .detection-card.suspicious {
            border-color: #fecaca;
            background: #fff7f7;
        }
        .suspicious-banner {
            display: none;
            margin: 0 0 0.85rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            font-size: 0.92rem;
            font-weight: 600;
        }
        .suspicious-banner.show { display: block; }
        .detection-card-body {
            display: grid;
            grid-template-columns: 88px minmax(0, 1fr);
            gap: 0.85rem;
            align-items: start;
        }
        .detection-thumb {
            width: 88px;
            height: 110px;
            border-radius: 8px;
            object-fit: cover;
            background: #e2e8f0;
            border: 1px solid var(--border-color);
            display: block;
        }
        .detection-thumb.placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 1.5rem;
            height: 110px;
        }
        .detection-attrs {
            display: grid;
            gap: 0.4rem;
            font-size: 0.82rem;
            line-height: 1.4;
            min-width: 0;
        }
        .detection-attr {
            display: grid;
            grid-template-columns: 7.5rem minmax(0, 1fr);
            gap: 0.55rem;
            color: var(--text-color);
            align-items: start;
        }
        .detection-attr.wide {
            grid-template-columns: 1fr;
            gap: 0.2rem;
            padding: 0.45rem 0.55rem;
            background: rgba(15, 23, 42, 0.03);
            border-radius: 6px;
        }
        .detection-attr .label {
            color: var(--text-secondary);
            font-weight: 600;
        }
        .detection-attr .value {
            font-weight: 600;
            text-align: left;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        .detection-attr .value.capitalize {
            text-transform: capitalize;
        }
        .detection-attr.wide .value {
            font-weight: 500;
            color: var(--text-color);
        }
        .detection-empty {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
            padding: 1rem 0.25rem;
        }
        .video-column { min-width: 0; }
        .search-box { flex: 1; min-width: 200px; position: relative; }
        .search-box input { width: 100%; padding: 0.75rem 1rem 0.75rem 2.5rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; }
        .search-box input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1); }
        .search-box::before { content: "🔍"; position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 1rem; }
        .date-filter { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; }
        .date-filter label { font-size: 0.9rem; font-weight: 500; color: var(--text-color); white-space: nowrap; }
        .date-filter input[type="date"] { padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.95rem; font-family: var(--font-family); }
        .date-filter input[type="date"]:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1); }
        .table-container { overflow-x: auto; border-radius: 8px; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; background: var(--card-bg); }
        thead { background: var(--tertiary-color); color: #fff; }
        th { padding: 1rem; text-align: left; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-color); }
        tbody tr:hover { background: #f9f9f9; }
        tbody tr:last-child td { border-bottom: none; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.85rem; font-weight: 500; display: inline-block; }
        .status-at-hall { background: #d1e7dd; color: #0f5132; }
        .status-timed-out { background: #e9ecef; color: #6c757d; }
        @media (max-width: 1100px) {
            .surveillance-layout { grid-template-columns: 1fr; }
            .detection-panel { max-height: none; min-height: 0; }
        }
        @media (max-width: 768px) {
            .sidebar { width: 320px; transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.mobile-open { transform: translateX(0); }
            .sidebar.collapsed { width: 80px; transform: translateX(0); }
            .main-wrapper { margin-left: 0; }
            body.sidebar-collapsed .main-wrapper { margin-left: 80px; }
            .search-container { flex-direction: column; align-items: stretch; }
            .date-filter { width: 100%; }
            .date-filter input[type="date"] { flex: 1; }
        }
    </style>
    <link rel="stylesheet" href="css/mobile-responsive.css">
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <a href="index.php" style="display: block; cursor: pointer;">
                    <img src="images/tara.png" alt="Alertara Logo" style="display: block;">
                </a>
                <div class="user-name-display">
                    <?php echo htmlspecialchars(getAdminDisplayName()); ?>
                </div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <?php require __DIR__ . '/includes/admin_nav_dashboard.php'; ?>

            <?php if (isAdminUser()): ?>
            <div class="nav-module <?php echo (basename($_SERVER['PHP_SELF']) == 'users.php' || basename($_SERVER['PHP_SELF']) == 'login-history.php') ? 'active' : ''; ?>">
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="User Management">
                    <span class="nav-module-icon"><i class="fas fa-users-cog"></i></span>
                    <span class="nav-module-header-text">User Management</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="users.php" class="nav-submodule <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" data-tooltip="Users">
                        <span class="nav-submodule-icon"><i class="fas fa-users"></i></span>
                        <span class="nav-submodule-text">Users</span>
                    </a>
                    <a href="login-history.php" class="nav-submodule <?php echo basename($_SERVER['PHP_SELF']) == 'login-history.php' ? 'active' : ''; ?>" data-tooltip="Audit Trails">
                        <span class="nav-submodule-icon"><i class="fas fa-history"></i></span>
                        <span class="nav-submodule-text">Audit Trails</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <div class="nav-module">
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="Neighborhood Watch Coordination">
                    <span class="nav-module-icon"><i class="fas fa-users"></i></span>
                    <span class="nav-module-header-text">Neighborhood Watch Coordination</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <?php require __DIR__ . '/includes/neighborhood_watch_nav_submodules.php'; ?>
                </div>
            </div>
            <div class="nav-module active">
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="CCTV Surveillance System Management">
                    <span class="nav-module-icon"><i class="fas fa-video"></i></span>
                    <span class="nav-module-header-text">CCTV Surveillance System Management</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <?php $cctvNavActive = $cctvNavActive ?? ''; require __DIR__ . '/includes/cctv_nav_submodules.php'; ?>
                </div>
            </div>
            <div class="nav-module">
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="Community Complaint Logging and Resolution">
                    <span class="nav-module-icon"><i class="fas fa-file-alt"></i></span>
                    <span class="nav-module-header-text">Community Complaint Logging and Resolution</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="submit-complaint.php" class="nav-submodule" data-tooltip="Submit Complaint">
                        <span class="nav-submodule-icon"><i class="fas fa-edit"></i></span>
                        <span class="nav-submodule-text">Submit Complaint</span>
                    </a>
                    <a href="track-complaint.php" class="nav-submodule" data-tooltip="Track Complaint">
                        <span class="nav-submodule-icon"><i class="fas fa-search"></i></span>
                        <span class="nav-submodule-text">Track Complaint</span>
                    </a>
                </div>
            </div>
            <div class="nav-module">
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="Patrol Scheduling and Monitoring">
                    <span class="nav-module-icon"><i class="fas fa-walking"></i></span>
                    <span class="nav-module-header-text">Patrol Scheduling and Monitoring</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <?php $patrolNavActive = $patrolNavActive ?? ''; require __DIR__ . '/includes/patrol_nav_submodules.php'; ?>
                </div>
            </div>
            <div class="nav-module">
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="Awareness and Outreach Event Tracking">
                    <span class="nav-module-icon"><i class="fas fa-bullhorn"></i></span>
                    <span class="nav-module-header-text">Awareness and Outreach Event Tracking</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="event-list.php" class="nav-submodule" data-tooltip="Event List">
                        <span class="nav-submodule-icon"><i class="fas fa-list"></i></span>
                        <span class="nav-submodule-text">Event List</span>
                    </a>
                    <a href="event-reports.php" class="nav-submodule" data-tooltip="Event Reports">
                        <span class="nav-submodule-icon"><i class="fas fa-chart-line"></i></span>
                        <span class="nav-submodule-text">Event Reports</span>
                    </a>
                </div>
            </div>
            <div class="nav-module">
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="Anonymous Tip Line System">
                    <span class="nav-module-icon"><i class="fas fa-comments"></i></span>
                    <span class="nav-module-header-text">Anonymous Tip Line System</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="review-tip.php" class="nav-submodule" data-tooltip="Review Tip">
                        <span class="nav-submodule-icon"><i class="fas fa-eye"></i></span>
                        <span class="nav-submodule-text">Review Tip</span>
                    </a>
                </div>
            </div>
        </nav>

        <div class="sidebar-footer">
            <a href="logout.php" class="sidebar-logout-btn" data-tooltip="Logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <div class="main-wrapper">
        <header class="top-header">
            <div class="top-header-content">
                <button class="content-burger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
                    <span></span>
                </button>
                <h1 class="page-title">Open Surveillance</h1>
            </div>
            <div class="user-info">
                <div class="datetime-display">
                    <span class="date-part" id="currentDate"></span>
                    <span class="time-part" id="currentTime"></span>
                </div>
                <div class="notification-container">
                    <button class="notification-bell" type="button" onclick="toggleNotifications(event)" aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge" id="notificationBadge"></span>
                    </button>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h3>Notifications</h3>
                            <button onclick="markAllAsRead()">Mark all as read</button>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <div class="notification-empty">
                                <i class="fas fa-bell-slash"></i>
                                <p>No notifications</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="content-area">
            <div class="page-content">
                <div class="section-block surveillance-panel">
                    <div class="surveillance-meta">
                        <div>
                            <h2 class="section-title" style="margin-bottom:0.35rem;"><i class="fas fa-video"></i> Open Surveillance</h2>
                        </div>
                        <div style="display:flex;align-items:center;gap:0.35rem;flex-wrap:wrap;">
                            <span class="live-badge" id="liveBadge"><span class="dot"></span> Connecting</span>
                            <span class="live-mode-chip" id="liveModeChip" title="Live transport">…</span>
                        </div>
                    </div>

                    <div class="surveillance-layout">
                        <div class="video-column">
                            <div class="video-shell" id="videoShell">
                                <button type="button" class="fullscreen-btn" id="fullscreenBtn" title="Full screen" aria-label="Toggle full screen">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <iframe id="webrtcFeed" class="webrtc-frame" title="Low-latency live camera" allow="autoplay; fullscreen" referrerpolicy="no-referrer"></iframe>
                                <img id="cameraFeed" class="camera-feed" alt="Live surveillance feed with YOLO detection">
                                <div class="feed-overlay feed-overlay-camera" id="feedCameraName">Location</div>
                                <canvas id="detectionOverlay" class="detection-overlay" aria-hidden="true"></canvas>
                                <div class="video-placeholder" id="cameraPlaceholder">
                                    <i class="fas fa-camera"></i>
                                    <p id="cameraPlaceholderText">Connecting to camera…</p>
                                </div>
                            </div>
                        </div>

                        <div class="detection-panel">
                            <p id="suspiciousBanner" class="suspicious-banner"></p>
                            <h3><i class="fas fa-list"></i> Detected Objects</h3>
                            <div class="detection-cards" id="detectionList">
                                <p class="detection-empty">No objects detected yet.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const LOCAL_DETECTION_ENABLED = <?php echo $localDetectionEnabled ? 'true' : 'false'; ?>;
        const CCTV_FEED_MODE = <?php echo json_encode($cctvFeedMode); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
            updateDateTime();
            setInterval(updateDateTime, 1000);
            loadFeedCameraName();
            setCameraUiState('connecting');
            // Prefer low-latency WebRTC (go2rtc); JPEG relay is fallback only.
            startLiveView();
            ensureDetectionRunning();
            setInterval(pollDetections, 1000);
            // Heartbeat keeps the live page fresh; detection itself runs always-on via the agent.
            setInterval(sendDetectionHeartbeat, 5000);
            setInterval(ensureDetectionRunning, 15000);
            initFullscreen();
            initDetectionLifecycle();
            window.addEventListener('storage', function(e) {
                if (e.key === 'cameraConfigUpdated') {
                    startLiveView();
                    loadFeedCameraName();
                }
            });
            window.addEventListener('camera-config-updated', function() {
                startLiveView();
                loadFeedCameraName();
            });
        });

        async function detectionControl(action) {
            const res = await fetch('api/detection_control.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: action }),
                credentials: 'same-origin',
                cache: 'no-store',
            });
            return res.json();
        }

        async function ensureDetectionRunning() {
            try {
                const result = await detectionControl('start');
                if (result && result.message) {
                    console.log('Detection:', result.message);
                }
            } catch (e) {
                console.warn('Detection start failed', e);
            }
        }

        async function sendDetectionHeartbeat() {
            try {
                await detectionControl('heartbeat');
            } catch (e) {
                /* ignore */
            }
        }

        function signalDetectionStop() {
            try {
                const payload = JSON.stringify({ action: 'stop' });
                if (navigator.sendBeacon) {
                    const blob = new Blob([payload], { type: 'application/json' });
                    navigator.sendBeacon('api/detection_control.php', blob);
                    return;
                }
            } catch (e) {
                /* fall through */
            }
            try {
                fetch('api/detection_control.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'stop' }),
                    credentials: 'same-origin',
                    keepalive: true,
                    cache: 'no-store',
                });
            } catch (e) {
                /* ignore */
            }
        }

        function initDetectionLifecycle() {
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') {
                    ensureDetectionRunning();
                    sendDetectionHeartbeat();
                }
            });
            // Detection stays running after leaving this page (always-on monitoring).
        }

        function initFullscreen() {
            const videoShell = document.getElementById('videoShell');
            const fullscreenBtn = document.getElementById('fullscreenBtn');
            const feed = document.getElementById('cameraFeed');
            const webrtc = document.getElementById('webrtcFeed');
            if (!videoShell || !fullscreenBtn) return;

            const updateFullscreenIcon = () => {
                const isFullscreen = document.fullscreenElement === videoShell;
                const icon = fullscreenBtn.querySelector('i');
                if (icon) {
                    icon.className = isFullscreen ? 'fas fa-compress' : 'fas fa-expand';
                }
                fullscreenBtn.title = isFullscreen ? 'Exit full screen' : 'Full screen';
                fullscreenBtn.setAttribute('aria-label', fullscreenBtn.title);
            };

            const toggleFullscreen = async () => {
                try {
                    if (document.fullscreenElement === videoShell) {
                        await document.exitFullscreen();
                    } else {
                        await videoShell.requestFullscreen();
                    }
                } catch (e) {
                    console.warn('Fullscreen not available:', e);
                }
            };

            fullscreenBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleFullscreen();
            });

            if (feed) feed.addEventListener('dblclick', toggleFullscreen);
            if (webrtc) webrtc.addEventListener('dblclick', toggleFullscreen);
            document.addEventListener('fullscreenchange', updateFullscreenIcon);
        }

        let feedErrors = 0;
        let liveTransport = 'connecting'; // webrtc | jpeg | none | connecting
        let activeCamera = null;
        let liveEmbedBases = [];
        let liveEmbedBaseIndex = 0;
        let liveEmbedWatchTimer = null;
        let jpegFeedTimer = null;
        window.__detectionThumbFallback = function(img) {
            if (!img || !img.parentNode) return;
            const ph = document.createElement('div');
            ph.className = 'detection-thumb placeholder';
            ph.setAttribute('aria-hidden', 'true');
            ph.innerHTML = '<i class="fas fa-image"></i>';
            img.replaceWith(ph);
        };

        function setLiveModeChip(mode) {
            const chip = document.getElementById('liveModeChip');
            if (!chip) return;
            chip.classList.remove('webrtc', 'jpeg');
            if (mode === 'webrtc') {
                chip.textContent = 'WebRTC · low latency';
                chip.classList.add('webrtc');
                chip.title = 'Direct camera stream via go2rtc (near Reolink app delay)';
            } else if (mode === 'jpeg') {
                chip.textContent = 'JPEG relay';
                chip.classList.add('jpeg');
                chip.title = 'Showing uploaded frames while low-latency stream reconnects';
            } else if (mode === 'missing') {
                chip.textContent = 'No camera';
                chip.title = '';
            } else {
                chip.textContent = 'Connecting…';
                chip.title = '';
            }
        }

        function setCameraUiState(state) {
            // state: true/'live' | false/'offline' | 'connecting' | 'missing'
            const badge = document.getElementById('liveBadge');
            const placeholder = document.getElementById('cameraPlaceholder');
            const placeholderText = document.getElementById('cameraPlaceholderText');
            const feed = document.getElementById('cameraFeed');
            const webrtc = document.getElementById('webrtcFeed');
            const cameraOverlay = document.getElementById('feedCameraName');

            let mode = state;
            if (state === true) mode = 'live';
            if (state === false) mode = 'offline';

            const isLive = mode === 'live';
            const isConnecting = mode === 'connecting';
            const isMissing = mode === 'missing';
            const showingFeed = (webrtc && webrtc.classList.contains('active'));

            badge.classList.toggle('active', isLive);
            badge.innerHTML = '<span class="dot"></span> ' + (
                isMissing ? 'Camera not found'
                    : (isLive ? 'Live' : (isConnecting ? 'Connecting' : 'Offline'))
            );

            if (isMissing) {
                if (placeholderText) placeholderText.textContent = 'Camera not found';
                placeholder.classList.remove('hidden');
                if (feed) {
                    feed.classList.remove('active');
                    feed.removeAttribute('src');
                }
                if (webrtc) {
                    webrtc.classList.remove('active');
                    webrtc.removeAttribute('src');
                }
                if (cameraOverlay) {
                    cameraOverlay.textContent = 'Camera not found';
                    cameraOverlay.classList.add('visible');
                }
                setLiveModeChip('missing');
                return;
            }

            if (placeholderText) {
                placeholderText.textContent = isConnecting ? 'Connecting to camera…' : 'Camera offline';
            }

            if (isLive || (isConnecting && showingFeed)) {
                placeholder.classList.add('hidden');
            } else if (!showingFeed) {
                placeholder.classList.remove('hidden');
            }

            if (cameraOverlay) cameraOverlay.classList.toggle('visible', isLive || showingFeed || isMissing);
        }

        function stopLiveFeeds() {
            if (liveEmbedWatchTimer) {
                clearTimeout(liveEmbedWatchTimer);
                liveEmbedWatchTimer = null;
            }
            if (jpegFeedTimer) {
                clearInterval(jpegFeedTimer);
                jpegFeedTimer = null;
            }
            const webrtc = document.getElementById('webrtcFeed');
            const jpeg = document.getElementById('cameraFeed');
            if (webrtc) {
                webrtc.classList.remove('active');
                webrtc.removeAttribute('src');
            }
            if (jpeg) {
                jpeg.classList.remove('active');
                jpeg.removeAttribute('src');
            }
        }

        function startJpegRelayFeed() {
            const webrtc = document.getElementById('webrtcFeed');
            const jpeg = document.getElementById('cameraFeed');
            if (!jpeg) return false;
            if (webrtc) {
                webrtc.classList.remove('active');
                webrtc.removeAttribute('src');
            }
            if (jpegFeedTimer) {
                clearInterval(jpegFeedTimer);
                jpegFeedTimer = null;
            }
            liveTransport = 'jpeg';
            setLiveModeChip('jpeg');
            const refresh = () => {
                jpeg.src = 'api/current_frame.php?t=' + Date.now();
            };
            jpeg.onload = function() {
                jpeg.classList.add('active');
                document.getElementById('cameraPlaceholder').classList.add('hidden');
                setCameraUiState('live');
                applyLocationOverlay(activeCamera);
                feedErrors = 0;
            };
            jpeg.onerror = function() {
                feedErrors += 1;
                if (feedErrors >= 8) {
                    setCameraUiState('offline');
                }
            };
            refresh();
            jpegFeedTimer = setInterval(refresh, 350);
            return true;
        }

        function applyLiveEmbedBase(baseUrl, streamName) {
            const webrtc = document.getElementById('webrtcFeed');
            const jpeg = document.getElementById('cameraFeed');
            if (!webrtc || !baseUrl) return false;
            if (jpegFeedTimer) {
                clearInterval(jpegFeedTimer);
                jpegFeedTimer = null;
            }
            if (jpeg) {
                jpeg.classList.remove('active');
                jpeg.removeAttribute('src');
            }
            const embedUrl = buildCleanEmbedUrl(baseUrl, streamName);
            liveTransport = 'webrtc';
            setLiveModeChip('webrtc');
            webrtc.src = embedUrl;
            webrtc.classList.add('active');
            document.getElementById('cameraPlaceholder').classList.add('hidden');
            setCameraUiState('connecting');
            applyLocationOverlay(activeCamera);

            if (liveEmbedWatchTimer) clearTimeout(liveEmbedWatchTimer);
            liveEmbedWatchTimer = setTimeout(function() {
                if (liveTransport !== 'webrtc') return;
                // No playing ack yet — try next HTTPS/LAN base, then JPEG relay.
                liveEmbedBaseIndex += 1;
                if (liveEmbedBaseIndex < liveEmbedBases.length) {
                    applyLiveEmbedBase(liveEmbedBases[liveEmbedBaseIndex], streamName);
                    return;
                }
                startJpegRelayFeed();
            }, 12000);
            return true;
        }

        window.addEventListener('message', function(ev) {
            const data = ev && ev.data;
            if (!data || data.source !== 'alertara-live-embed') return;
            if (data.state === 'playing') {
                if (liveEmbedWatchTimer) {
                    clearTimeout(liveEmbedWatchTimer);
                    liveEmbedWatchTimer = null;
                }
                liveTransport = 'webrtc';
                setLiveModeChip('webrtc');
                setCameraUiState('live');
                applyLocationOverlay(activeCamera);
            } else if (data.state === 'error' && liveTransport === 'webrtc') {
                liveEmbedBaseIndex += 1;
                const streamName = (document.getElementById('webrtcFeed') && liveEmbedBases.length)
                    ? 'alertara_live'
                    : 'alertara_live';
                if (liveEmbedBaseIndex < liveEmbedBases.length) {
                    applyLiveEmbedBase(liveEmbedBases[liveEmbedBaseIndex], streamName);
                } else {
                    startJpegRelayFeed();
                }
            }
        });

        async function fetchActiveCamera() {
            try {
                const res = await fetch('api/cameras.php?t=' + Date.now(), { cache: 'no-store', credentials: 'same-origin' });
                const result = await res.json();
                const cameras = result.cameras || result.data || [];
                if (!Array.isArray(cameras) || cameras.length === 0) {
                    return null;
                }
                return cameras.find(function(cam) {
                    return String(cam.status || '').toLowerCase() === 'online';
                }) || cameras[0];
            } catch (e) {
                return null;
            }
        }

        function applyLocationOverlay(camera) {
            const el = document.getElementById('feedCameraName');
            if (!el) return;
            if (!camera) {
                el.textContent = 'Camera not found';
                return;
            }
            el.textContent = camera.location || camera.name || camera.cameraId || 'Location';
        }

        function buildCleanEmbedUrl(baseUrl, streamName) {
            const base = String(baseUrl || '').replace(/\/$/, '');
            if (!base) return '';
            const params = new URLSearchParams({
                base: base,
                src: streamName || 'alertara_live',
            });
            return 'live-embed.php?' + params.toString();
        }

        async function tryStartWebRtcFeed() {
            const webrtc = document.getElementById('webrtcFeed');
            if (!webrtc) return false;
            try {
                const res = await fetch('api/cctv_webrtc_status.php?t=' + Date.now(), {
                    cache: 'no-store',
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (!data || !data.success || !data.running) {
                    return false;
                }

                const pageHttps = window.location.protocol === 'https:';
                const hostLocal = /^(localhost|127\.0\.0\.1)$/i.test(window.location.hostname);
                let bases = [];
                if (hostLocal) bases.push('http://127.0.0.1:1984');
                if (data.tunnel_url) bases.push(String(data.tunnel_url).replace(/\/$/, ''));
                if (Array.isArray(data.base_urls)) {
                    data.base_urls.forEach(function(b) { bases.push(String(b).replace(/\/$/, '')); });
                }
                if (data.base_url) bases.push(String(data.base_url).replace(/\/$/, ''));
                if (hostLocal) bases.push('http://127.0.0.1:1984');
                bases = bases.filter(Boolean).filter(function(url, idx, arr) {
                    return arr.indexOf(url) === idx;
                });

                let embeddableBases = bases.filter(function(url) {
                    if (pageHttps && String(url).indexOf('http://') === 0) return false;
                    return true;
                });
                if (!embeddableBases.length && !pageHttps) {
                    embeddableBases = bases;
                }
                if (!embeddableBases.length) {
                    return false;
                }

                liveEmbedBases = embeddableBases;
                liveEmbedBaseIndex = 0;
                return applyLiveEmbedBase(liveEmbedBases[0], data.stream || 'alertara_live');
            } catch (e) {
                console.warn('WebRTC status check failed', e);
                return false;
            }
        }

        async function startLiveView() {
            setLiveModeChip('connecting');
            setCameraUiState('connecting');
            activeCamera = await fetchActiveCamera();
            if (!activeCamera) {
                liveTransport = 'none';
                stopLiveFeeds();
                setCameraUiState('missing');
                return;
            }
            applyLocationOverlay(activeCamera);
            const ok = await tryStartWebRtcFeed();
            if (!ok) {
                // Low-latency path unavailable — still show uploaded frames.
                if (startJpegRelayFeed()) {
                    return;
                }
                liveTransport = 'connecting';
                stopLiveFeeds();
                setCameraUiState('connecting');
                setTimeout(startLiveView, 5000);
            }
        }

        async function loadFeedCameraName() {
            activeCamera = await fetchActiveCamera();
            applyLocationOverlay(activeCamera);
            if (!activeCamera) {
                liveTransport = 'none';
                stopLiveFeeds();
                setCameraUiState('missing');
            }
        }

        function startCameraFeed() {
            startJpegRelayFeed();
        }

        // Re-check camera list + promote/repair WebRTC periodically.
        setInterval(async function() {
            const cam = await fetchActiveCamera();
            if (!cam) {
                if (liveTransport !== 'none') {
                    liveTransport = 'none';
                    stopLiveFeeds();
                    setCameraUiState('missing');
                }
                return;
            }
            activeCamera = cam;
            applyLocationOverlay(activeCamera);
            if (liveTransport !== 'webrtc') {
                await tryStartWebRtcFeed();
            }
        }, 8000);

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function formatDetectedTime(timestamp) {
            if (!timestamp) return '—';
            const dt = new Date(timestamp);
            if (Number.isNaN(dt.getTime())) return '—';
            return dt.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }

        function categoryLabel(category) {
            const labels = {
                person: 'Person',
                phone: 'Phone',
                backpack: 'Backpack',
                suitcase: 'Suitcase',
                plant: 'Plant',
                vehicle: 'Vehicle',
                animal: 'Animal',
                group: 'Group',
                crowd: 'Crowd',
                weapon: 'Weapon'
            };
            return labels[category] || (category ? category.charAt(0).toUpperCase() + category.slice(1) : 'Object');
        }

        function renderDetectionAttributes(item) {
            const cat = item.category || 'object';
            const confidence = ((item.confidence || 0) * 100).toFixed(1) + '%';
            const detectedAt = formatDetectedTime(item.timestamp);
            const wideLabels = { Reason: true };

            let rows;
            if (cat === 'person') {
                rows = [
                    ['Gender', item.gender || 'Unknown'],
                    ['Expression', item.expression || 'Calm'],
                    ['Mask', item.mask || 'No'],
                    ['Facial Hair', item.facial_hair || 'None'],
                    ['Earrings', item.earrings || 'No'],
                    ['Items Detected', item.items_detected || 'None'],
                    ['Clothes Color', item.clothes_color || 'Unknown'],
                    ['Confidence', confidence],
                    ['Detected', detectedAt]
                ];
            } else if (cat === 'group' || cat === 'crowd') {
                rows = [
                    ['People Count', String(item.people_count || item.group_size || '—')],
                    ['Confidence', confidence],
                    ['Detected', detectedAt]
                ];
                if (item.suspicious_reason) {
                    rows.unshift(['Activity', item.activity || 'Suspicious']);
                    rows.splice(1, 0, ['Reason', item.suspicious_reason]);
                }
            } else if (cat === 'backpack' || cat === 'suitcase') {
                rows = [
                    ['Type', item.class || categoryLabel(cat)],
                    ['Items Detected', item.items_detected || item.class || 'None'],
                    ['Confidence', confidence],
                    ['Detected', detectedAt]
                ];
                if (item.suspicious_reason) {
                    rows.unshift(['Activity', item.activity || 'Suspicious']);
                    rows.splice(1, 0, ['Reason', item.suspicious_reason]);
                }
            } else {
                rows = [
                    ['Type', item.class || categoryLabel(cat)],
                    ['Items Detected', item.items_detected || item.class || 'None'],
                    ['Confidence', confidence],
                    ['Detected', detectedAt]
                ];
            }

            return rows.map(function(pair) {
                return {
                    label: pair[0],
                    value: pair[1],
                    wide: !!wideLabels[pair[0]]
                };
            });
        }

        function detectionThumbHtml(item, cat) {
            const alt = escapeHtml(categoryLabel(cat));
            const placeholder = `<div class="detection-thumb placeholder" aria-hidden="true"><i class="fas fa-image"></i></div>`;
            const src = item.image_data || item.image || '';
            if (!src) return placeholder;
            const safeSrc = escapeHtml(src);
            const cacheBust = src.indexOf('data:image/') === 0
                ? ''
                : ((src.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now());
            return `<img class="detection-thumb" src="${safeSrc}${cacheBust}" alt="${alt}" loading="lazy" onerror="window.__detectionThumbFallback && window.__detectionThumbFallback(this)">`;
        }

        function renderDetectionCard(item) {
            const cat = item.category || item.class || 'object';
            const tagClass = ['plant', 'phone', 'backpack', 'suitcase', 'group', 'crowd', 'person', 'vehicle', 'animal'].includes(cat) ? cat : '';
            const attrs = renderDetectionAttributes(item);
            const thumb = detectionThumbHtml(item, cat);

            const attrHtml = attrs.map(function(row) {
                const valueClass = row.wide ? 'value' : 'value capitalize';
                return `
                <div class="detection-attr${row.wide ? ' wide' : ''}">
                    <span class="label">${escapeHtml(row.label)}:</span>
                    <span class="${valueClass}">${escapeHtml(row.value)}</span>
                </div>`;
            }).join('');

            return `
                <article class="detection-card${item.suspicious ? ' suspicious' : ''}">
                    <div class="detection-card-header">
                        <span class="tag ${tagClass}">${escapeHtml(categoryLabel(cat))}</span>
                        ${item.suspicious ? '<span class="tag suspicious-flag">Suspicious</span>' : ''}
                    </div>
                    <div class="detection-card-body">
                        ${thumb}
                        <div class="detection-attrs">${attrHtml}</div>
                    </div>
                </article>
            `;
        }

        let lastOverlayDetections = [];
        let lastOverlayFrameSize = { w: 0, h: 0 };

        function clearDetectionOverlay() {
            const canvas = document.getElementById('detectionOverlay');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            if (!ctx) return;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            lastOverlayDetections = [];
        }

        function detectionOverlayColor(category) {
            // User-facing boxes: green for phones/persons; keep accents for other classes.
            const colors = {
                person: '#22c55e',
                phone: '#22c55e',
                backpack: '#a855f7',
                suitcase: '#f59e0b',
                group: '#a855f7',
                crowd: '#ec4899',
                vehicle: '#f97316',
                animal: '#eab308',
                plant: '#10b981',
                weapon: '#ef4444'
            };
            return colors[category] || '#22c55e';
        }

        function drawDetectionOverlay(detections, frameWidth, frameHeight) {
            const canvas = document.getElementById('detectionOverlay');
            const shell = document.getElementById('videoShell');
            if (!canvas || !shell) return;

            const cssW = shell.clientWidth || 0;
            const cssH = shell.clientHeight || 0;
            if (cssW < 2 || cssH < 2) return;

            const dpr = window.devicePixelRatio || 1;
            canvas.width = Math.round(cssW * dpr);
            canvas.height = Math.round(cssH * dpr);
            canvas.style.width = cssW + 'px';
            canvas.style.height = cssH + 'px';

            const ctx = canvas.getContext('2d');
            if (!ctx) return;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, cssW, cssH);

            const fw = Number(frameWidth) || 0;
            const fh = Number(frameHeight) || 0;
            lastOverlayDetections = Array.isArray(detections) ? detections : [];
            lastOverlayFrameSize = { w: fw, h: fh };
            if (!lastOverlayDetections.length || fw < 1 || fh < 1) {
                return;
            }

            // object-fit: contain letterboxing inside the video shell
            const scale = Math.min(cssW / fw, cssH / fh);
            const drawW = fw * scale;
            const drawH = fh * scale;
            const offsetX = (cssW - drawW) / 2;
            const offsetY = (cssH - drawH) / 2;

            lastOverlayDetections.forEach(function(item) {
                const bbox = item && item.bbox;
                if (!bbox) return;
                const x1 = offsetX + (Number(bbox.x1) || 0) * scale;
                const y1 = offsetY + (Number(bbox.y1) || 0) * scale;
                const x2 = offsetX + (Number(bbox.x2) || 0) * scale;
                const y2 = offsetY + (Number(bbox.y2) || 0) * scale;
                const w = Math.max(2, x2 - x1);
                const h = Math.max(2, y2 - y1);
                const color = detectionOverlayColor(item.category || item.class || 'object');
                const label = categoryLabel(item.category || item.class || 'object');

                ctx.strokeStyle = color;
                ctx.lineWidth = 3;
                ctx.strokeRect(x1, y1, w, h);

                ctx.font = '600 13px Segoe UI, Tahoma, sans-serif';
                const text = label + (item.confidence ? (' ' + Math.round((item.confidence || 0) * 100) + '%') : '');
                const tw = ctx.measureText(text).width;
                const ty = Math.max(16, y1 - 6);
                ctx.fillStyle = color;
                ctx.fillRect(x1, ty - 14, tw + 10, 18);
                ctx.fillStyle = '#052e16';
                ctx.fillText(text, x1 + 5, ty);
            });
        }

        window.addEventListener('resize', function() {
            if (lastOverlayDetections.length) {
                drawDetectionOverlay(lastOverlayDetections, lastOverlayFrameSize.w, lastOverlayFrameSize.h);
            }
        });

        async function pollDetections() {
            try {
                if (!activeCamera || liveTransport === 'none') {
                    const list = document.getElementById('detectionList');
                    const banner = document.getElementById('suspiciousBanner');
                    if (list) list.innerHTML = '<p class="detection-empty">Camera not found — detection paused.</p>';
                    if (banner) {
                        banner.textContent = '';
                        banner.classList.remove('show');
                    }
                    clearDetectionOverlay();
                    return;
                }
                const res = await fetch('api/get_detections.php?t=' + Date.now());
                const data = await res.json();
                const detections = data.detections || [];

                const list = document.getElementById('detectionList');
                const banner = document.getElementById('suspiciousBanner');
                if (!detections.length) {
                    list.innerHTML = '<p class="detection-empty">No objects detected yet.</p>';
                    if (banner) {
                        banner.textContent = '';
                        banner.classList.remove('show');
                    }
                    clearDetectionOverlay();
                    return;
                }

                drawDetectionOverlay(detections, data.frame_width, data.frame_height);

                const suspicious = detections.filter(function(item) { return item.suspicious; });
                if (banner) {
                    if (suspicious.length) {
                        banner.textContent = 'Suspicious activity: ' + suspicious.length + ' alert(s) — crowds, groups, backpacks, or suitcases detected.';
                        banner.classList.add('show');
                    } else {
                        banner.textContent = '';
                        banner.classList.remove('show');
                    }
                }

                const priority = { person: 0, crowd: 1, group: 2, phone: 3, backpack: 4, suitcase: 5, weapon: 6, vehicle: 7, animal: 8, plant: 9 };
                const sorted = detections.slice().sort((a, b) => {
                    const pa = priority[a.category] ?? 99;
                    const pb = priority[b.category] ?? 99;
                    if (pa !== pb) return pa - pb;
                    return (b.confidence || 0) - (a.confidence || 0);
                });

                list.innerHTML = sorted.map(renderDetectionCard).join('');
            } catch (e) {
                console.error('Detection poll failed', e);
            }
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const isCollapsed = sidebar.classList.contains('collapsed');
            if (isCollapsed) {
                sidebar.classList.remove('collapsed');
                document.body.classList.remove('sidebar-collapsed');
            } else {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
            localStorage.setItem('sidebarCollapsed', !isCollapsed);
        }

        function toggleModule(element) {
            const sidebar = document.getElementById('sidebar');
            const module = element.closest('.nav-module');
            const isActive = module.classList.contains('active');
            const isCollapsed = sidebar.classList.contains('collapsed');
            if (isCollapsed) {
                sidebar.classList.remove('collapsed');
                document.body.classList.remove('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', 'false');
                document.querySelectorAll('.nav-module').forEach(m => { m.classList.remove('active'); });
                module.classList.add('active');
                const firstSubmodule = module.querySelector('.nav-submodule');
                if (firstSubmodule && firstSubmodule.href && firstSubmodule.href !== '#') {
                    window.location.href = firstSubmodule.href;
                }
                return;
            }
            document.querySelectorAll('.nav-module').forEach(m => { m.classList.remove('active'); });
            if (!isActive) { module.classList.add('active'); }
        }

        function updateDateTime() {
            const now = new Date();
            const dateStr = now.toLocaleDateString('en-US', {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
            const timeStr = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
            const dateEl = document.getElementById('currentDate');
            const timeEl = document.getElementById('currentTime');
            if (dateEl) dateEl.textContent = dateStr;
            if (timeEl) timeEl.textContent = timeStr;
        }
    </script>
    <?php require __DIR__ . '/includes/admin_notifications_script.php'; ?>
    <script src="js/mobile-shell.js"></script>
</body>
</html>

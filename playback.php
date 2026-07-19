<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/db.php';

$cctvNavActive = 'playback';

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Playback - Alertara</title>
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
        .search-container { display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap; }
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
        .playback-panel { display: grid; gap: 1.25rem; }
        .playback-filters { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end; }
        .filter-field { display: flex; flex-direction: column; gap: 0.35rem; min-width: 140px; }
        .filter-field label { font-size: 0.85rem; font-weight: 600; color: var(--text-color); }
        .filter-field select,
        .filter-field input { padding: 0.7rem 0.85rem; border: 1px solid var(--border-color); border-radius: 8px; font: inherit; box-sizing: border-box; }
        .filter-field select:focus,
        .filter-field input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1); }
        .btn-search { padding: 0.7rem 1.25rem; background: var(--primary-color); color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .btn-search:hover { background: #4ca8a6; }
        .playback-error { display: none; margin: 0; padding: 0.85rem 1rem; border-radius: 8px; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .playback-error.show { display: block; }
        .video-shell { background: #0f172a; border-radius: 12px; overflow: hidden; border: 1px solid var(--border-color); min-height: 360px; display: flex; align-items: center; justify-content: center; position: relative; }
        .video-shell video { width: 100%; max-height: 70vh; display: none; background: #000; object-fit: contain; }
        .video-shell video.active { display: block; }
        .video-shell:fullscreen { border-radius: 0; border: none; min-height: 100vh; width: 100vw; background: #000; }
        .video-shell:fullscreen video { max-height: 100vh; height: 100vh; width: 100vw; }
        .fullscreen-btn { position: absolute; top: 0.75rem; right: 0.75rem; z-index: 4; width: 40px; height: 40px; border: none; border-radius: 8px; background: rgba(15, 23, 42, 0.72); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
        .fullscreen-btn:hover { background: rgba(76, 138, 137, 0.9); }
        .video-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; color: rgba(255,255,255,0.75); text-align: center; padding: 2rem; min-height: 360px; position: absolute; inset: 0; z-index: 2; }
        .video-placeholder.hidden { display: none; }
        .video-placeholder i { font-size: 3rem; margin-bottom: 0.75rem; opacity: 0.8; }
        .segments-table td { font-size: 0.92rem; }
        .btn-play-segment { padding: 0.45rem 0.85rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; }
        .btn-play-segment:hover { background: #4ca8a6; }
        .btn-play-segment:disabled { background: #94a3b8; cursor: not-allowed; }
        .btn-download-segment { padding: 0.45rem 0.85rem; background: #475569; color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; margin-left: 0.35rem; }
        .btn-download-segment:hover { background: #334155; }
        .segment-badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .segment-badge.ready { background: #d1e7dd; color: #0f5132; }
        .segment-badge.legacy { background: #fff3cd; color: #856404; }
        .segment-badge.empty { background: #e9ecef; color: #6c757d; }
        .segment-badge.recording { background: #cff4fc; color: #055160; }
        .request-banner { margin: 0; padding: 0.85rem 1rem; border-radius: 8px; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; font-size: 0.9rem; }
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
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php" class="nav-module-header" data-tooltip="Dashboard" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.5rem; color: rgba(255, 255, 255, 0.9); cursor: pointer; transition: background-color 0.2s ease; font-weight: 500; user-select: none; gap: 0.75rem; <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'background: rgba(76, 138, 137, 0.25); border-left: 3px solid #4c8a89;' : ''; ?>">
                <span class="nav-module-icon"><i class="fas fa-home"></i></span>
                <span class="nav-module-header-text">Dashboard</span>
            </a>

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
                <h1 class="page-title">Playback</h1>
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
                <div class="section-block">
                    <h2 class="section-title"><i class="fas fa-history"></i> Recorded Footage Playback</h2>
                    <div class="playback-panel">
                        <p id="requestBanner" class="request-banner" style="display:none;"></p>
                        <p id="playbackError" class="playback-error"></p>

                        <div class="playback-filters">
                            <div class="filter-field">
                                <label for="cameraSelect">Camera</label>
                                <select id="cameraSelect">
                                    <option value="">Loading cameras...</option>
                                </select>
                            </div>
                            <div class="filter-field">
                                <label for="playbackDate">Date</label>
                                <input type="date" id="playbackDate">
                            </div>
                            <div class="filter-field">
                                <label for="playbackStart">Start time</label>
                                <input type="time" id="playbackStart">
                            </div>
                            <div class="filter-field">
                                <label for="playbackEnd">End time</label>
                                <input type="time" id="playbackEnd">
                            </div>
                            <button type="button" class="btn-search" onclick="searchRecordings()">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>

                        <div class="video-shell" id="videoShell">
                            <button type="button" class="fullscreen-btn" id="fullscreenBtn" title="Full screen" aria-label="Toggle full screen">
                                <i class="fas fa-expand"></i>
                            </button>
                            <video id="playbackVideo" controls playsinline></video>
                            <div class="video-placeholder" id="videoPlaceholder">
                                <i class="fas fa-film"></i>
                                <p id="placeholderText">Select a date and search for recordings, or choose a segment below.</p>
                            </div>
                        </div>

                        <div>
                            <h3 class="section-title" style="font-size:1rem;margin-bottom:0.75rem;"><i class="fas fa-list"></i> Available Segments</h3>
                            <div class="table-container segments-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Recording</th>
                                            <th>Start</th>
                                            <th>End</th>
                                            <th>Size</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="segmentsTableBody">
                                        <tr><td colspan="6" style="text-align:center;color:var(--text-secondary);">Loading segments...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
            updateDateTime();
            setInterval(updateDateTime, 1000);
            initPlayback();
            initFullscreen();
        });

        let segmentData = {};

        function initPlayback() {
            applyQueryParams();
            loadCameras();
            loadSegments();
            initSegmentActions();
        }

        function initSegmentActions() {
            const tbody = document.getElementById('segmentsTableBody');
            if (!tbody || tbody.dataset.actionsBound === '1') return;
            tbody.dataset.actionsBound = '1';
            tbody.addEventListener('click', function(e) {
                const playBtn = e.target.closest('.btn-play-segment');
                if (playBtn && !playBtn.disabled) {
                    playSegment(playBtn.dataset.filename || '');
                    return;
                }
                const downloadBtn = e.target.closest('.btn-download-segment');
                if (downloadBtn) {
                    downloadSegment(downloadBtn.dataset.filename || '');
                }
            });
        }

        function applyQueryParams() {
            const params = new URLSearchParams(window.location.search);
            const date = params.get('date');
            const start = params.get('start');
            const end = params.get('end');
            const requestId = params.get('request_id');

            if (date) document.getElementById('playbackDate').value = date;
            if (start) document.getElementById('playbackStart').value = start.length === 5 ? start : start.slice(0, 5);
            if (end) document.getElementById('playbackEnd').value = end.length === 5 ? end : end.slice(0, 5);

            if (requestId) {
                const banner = document.getElementById('requestBanner');
                banner.style.display = 'block';
                banner.innerHTML = '<i class="fas fa-link"></i> Opened from CCTV request <strong>' + escapeHtml(requestId) + '</strong>. Adjust the time range if needed, then click Search.';
            }

            window.__playbackCameraParam = params.get('camera') || '';
            window.__autoSearch = !!(date || start || end);
        }

        async function loadCameras() {
            const select = document.getElementById('cameraSelect');
            try {
                const res = await fetch('api/cameras.php');
                const result = await res.json();
                const cameras = result.cameras || result.data || [];
                if (!cameras.length) {
                    select.innerHTML = '<option value="">No cameras configured</option>';
                    return;
                }
                select.innerHTML = cameras.map(function(cam) {
                    const id = cam.cameraId || cam.camera_id || '';
                    const label = (cam.name || id) + (cam.location ? ' — ' + cam.location : '');
                    return '<option value="' + escapeHtml(id) + '">' + escapeHtml(label) + '</option>';
                }).join('');

                if (window.__playbackCameraParam) {
                    select.value = window.__playbackCameraParam;
                }
            } catch (e) {
                select.innerHTML = '<option value="">Unable to load cameras</option>';
            }
        }

        function showPlaybackError(message) {
            const el = document.getElementById('playbackError');
            if (!message) {
                el.textContent = '';
                el.classList.remove('show');
                return;
            }
            el.textContent = message;
            el.classList.add('show');
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        async function loadSegments() {
            const date = document.getElementById('playbackDate').value;
            const url = 'api/recordings.php?action=list' + (date ? '&date=' + encodeURIComponent(date) : '');
            await fetchAndRenderSegments(url, false);
            if (window.__autoSearch) {
                window.__autoSearch = false;
                searchRecordings();
            }
        }

        async function searchRecordings() {
            const date = document.getElementById('playbackDate').value;
            const start = document.getElementById('playbackStart').value;
            const end = document.getElementById('playbackEnd').value;

            showPlaybackError('');

            if (!date) {
                showPlaybackError('Please select a date to search.');
                return;
            }

            let url = 'api/recordings.php?action=find&date=' + encodeURIComponent(date);
            if (start) url += '&start=' + encodeURIComponent(start);
            if (end) url += '&end=' + encodeURIComponent(end);

            await fetchAndRenderSegments(url, true);
        }

        async function fetchAndRenderSegments(url, autoPlayFirst) {
            const tbody = document.getElementById('segmentsTableBody');
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-secondary);">Searching...</td></tr>';

            try {
                const res = await fetch(url);
                const result = await res.json();
                if (!result.success) throw new Error(result.message || 'Failed to load recordings');

                renderSegments(result.data || [], autoPlayFirst);

                if (!result.data || !result.data.length) {
                    showPlaybackError('No recordings found for the selected date and time. Make sure the detection script was running during that period.');
                }
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#b91c1c;">Failed to load recordings.</td></tr>';
                showPlaybackError(e.message || 'Failed to load recordings.');
            }
        }

        function segmentStatusBadge(item) {
            if (item.status === 'empty') return '<span class="segment-badge empty">Empty</span>';
            if (item.status === 'recording') return '<span class="segment-badge recording">Recording…</span>';
            if (item.legacy_codec) return '<span class="segment-badge legacy">Needs convert</span>';
            return '<span class="segment-badge ready">Ready</span>';
        }

        function renderSegments(segments, autoPlayFirst) {
            const tbody = document.getElementById('segmentsTableBody');
            segmentData = {};

            if (!segments.length) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-secondary);">No recordings found.</td></tr>';
                return;
            }

            tbody.innerHTML = segments.map(function(item) {
                segmentData[item.filename] = item;
                const canPlay = !!item.playable;
                return `
                    <tr>
                        <td>${escapeHtml(item.filename)}</td>
                        <td>${escapeHtml(item.start_at)}</td>
                        <td>${escapeHtml(item.end_at)}</td>
                        <td>${escapeHtml(item.size_label)}</td>
                        <td>${segmentStatusBadge(item)}</td>
                        <td>
                            <button type="button" class="btn-play-segment" data-filename="${escapeHtml(item.filename)}" ${canPlay ? '' : 'disabled'}>Play</button>
                            <button type="button" class="btn-download-segment" data-filename="${escapeHtml(item.filename)}">Download</button>
                        </td>
                    </tr>
                `;
            }).join('');

            const firstPlayable = segments.find(function(item) { return item.playable; });
            if (autoPlayFirst && firstPlayable) {
                playSegment(firstPlayable.filename);
            } else if (autoPlayFirst && segments.some(function(item) { return item.legacy_codec; })) {
                showPlaybackError('Matching recordings use a legacy codec. Run: py api/convert_recordings.py');
            }
        }

        function downloadSegment(filename) {
            window.location.href = 'api/recordings.php?action=download&file=' + encodeURIComponent(filename);
        }

        function playSegment(filename) {
            const segment = segmentData[filename];
            if (!segment) return;

            if (!segment.playable) {
                if (segment.status === 'recording') {
                    showPlaybackError('This segment is still recording. Wait until the 5-minute chunk finishes, then play again.');
                } else if (segment.legacy_codec) {
                    showPlaybackError('This recording needs conversion first. Run: py api/convert_recordings.py');
                } else {
                    showPlaybackError('This recording file is empty or incomplete.');
                }
                return;
            }

            const video = document.getElementById('playbackVideo');
            const placeholder = document.getElementById('videoPlaceholder');
            const src = 'api/recordings.php?action=stream&file=' + encodeURIComponent(filename);

            showPlaybackError('');
            video.pause();
            video.removeAttribute('src');
            video.load();
            video.src = src;
            video.classList.add('active');
            placeholder.classList.add('hidden');

            video.onloadeddata = function() {
                video.play().catch(function() {});
            };
            video.onerror = function() {
                video.classList.remove('active');
                placeholder.classList.remove('hidden');
                showPlaybackError('Unable to play this recording in the browser. Try Download and open it in VLC.');
            };

            video.load();
        }

        function initFullscreen() {
            const videoShell = document.getElementById('videoShell');
            const fullscreenBtn = document.getElementById('fullscreenBtn');
            const video = document.getElementById('playbackVideo');
            if (!videoShell || !fullscreenBtn) return;

            const updateIcon = function() {
                const isFullscreen = document.fullscreenElement === videoShell;
                const icon = fullscreenBtn.querySelector('i');
                if (icon) icon.className = isFullscreen ? 'fas fa-compress' : 'fas fa-expand';
            };

            fullscreenBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (document.fullscreenElement === videoShell) {
                    document.exitFullscreen();
                } else {
                    videoShell.requestFullscreen();
                }
            });

            video.addEventListener('dblclick', function() {
                if (document.fullscreenElement === videoShell) {
                    document.exitFullscreen();
                } else {
                    videoShell.requestFullscreen();
                }
            });

            document.addEventListener('fullscreenchange', updateIcon);
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

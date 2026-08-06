<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/db.php';

$patrolNavActive = 'patrol-request';

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Patrol Request - Alertara</title>
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
        .notification-icon.patrol_request { background: #dbeafe; color: #1d4ed8; }
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
        .status-pending { background: #fff3cd; color: #856404; }
        .status-under-review { background: #cff4fc; color: #055160; }
        .status-approved { background: #d1e7dd; color: #0f5132; }
        .status-scheduled { background: #d1e7dd; color: #0f5132; }
        .status-rejected { background: #f8d7da; color: #842029; }
        .status-cancelled { background: #e9ecef; color: #6c757d; }
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-view, .btn-manage, .btn-link, .btn-assign, .btn-decline { padding: 0.5rem 1rem; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; color: #fff; background: var(--primary-color); text-decoration: none; display: inline-flex; align-items: center; }
        .btn-manage { background: var(--primary-color); }
        .btn-assign { background: var(--primary-color); }
        .btn-assign:hover { background: #4ca8a6; }
        .btn-decline { background: #b91c1c; }
        .btn-link { background: var(--primary-color); }
        .btn-link:hover { background: #4ca8a6; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: var(--card-bg); border-radius: 12px; padding: 2rem; width: 90%; max-width: 760px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color); }
        .modal-header h2 { margin: 0; color: var(--tertiary-color); }
        .close-modal { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary); }
        .detail-row { display: grid; grid-template-columns: 180px 1fr; gap: 0.75rem; margin-bottom: 0.85rem; }
        .detail-label { font-weight: 600; color: var(--text-secondary); }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; box-sizing: border-box; font: inherit; }
        .form-group select[multiple] { min-height: 220px; }
        .form-group small { display: block; margin-top: 0.35rem; color: var(--text-secondary); font-size: 0.85rem; }
        .form-actions { display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; }
        .btn-save { background: var(--primary-color); color: #fff; border: none; padding: 0.75rem 1.25rem; border-radius: 8px; cursor: pointer; }
        .btn-cancel { background: #e9ecef; color: var(--text-color); border: none; padding: 0.75rem 1.25rem; border-radius: 8px; cursor: pointer; }
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
            <div class="nav-module">
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
            <div class="nav-module active">
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
                <h1 class="page-title">Patrol Request</h1>
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
                    <div class="search-container">
                        <div class="search-box">
                            <input type="text" id="searchInput" placeholder="Search by request ID, event, or location..." oninput="filterRequests()">
                        </div>
                        <div class="date-filter">
                            <label for="dateFilter">Date:</label>
                            <input type="date" id="dateFilter" onchange="filterRequests()">
                        </div>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Event</th>
                                    <th>Event Date / Time</th>
                                    <th>Location</th>
                                    <th>Assigned / Needed</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="requestsTableBody">
                                <tr><td colspan="8" style="text-align:center;color:var(--text-secondary);">Loading requests...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Patrol Request Details</h2>
                <button class="close-modal" onclick="closeViewModal()">&times;</button>
            </div>
            <div id="viewDetails"></div>
            <div class="form-actions" id="viewRequestActions" style="display:none;">
                <button type="button" class="btn-decline" id="declineRequestBtn" onclick="declineRequest()">Decline</button>
                <button type="button" class="btn-assign" id="assignPersonnelBtn" onclick="openAssignPatrolModal()">Assign Personnel</button>
            </div>
        </div>
    </div>

    <div id="assignModal" class="modal">
        <div class="modal-content" style="max-width:640px;">
            <div class="modal-header">
                <h2>Assign Patrol</h2>
                <button class="close-modal" onclick="closeAssignPatrolModal()">&times;</button>
            </div>
            <p class="manage-request-ref" id="assignRequestRef" style="margin:0 0 0.75rem;color:var(--tertiary-color);font-weight:600;"></p>
            <div id="assignRequestDetails" style="margin-bottom:1rem;"></div>
            <form id="assignPatrolForm" onsubmit="savePatrolRequestAssignment(event)">
                <input type="hidden" id="assignRequestDbId" value="">
                <div id="assignPatrolSlots"></div>
                <small id="assignPatrolHint" style="display:block;margin:0.35rem 0 1rem;color:var(--text-secondary);font-size:0.85rem;">Only personnel currently at the barangay hall (timed in today) are shown. Their My Schedule will include the request details above.</small>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeAssignPatrolModal()">Cancel</button>
                    <button type="submit" class="btn-save" id="assignSaveBtn">Assign</button>
                </div>
            </form>
        </div>
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
            loadRequests();
        });

        let requestData = {};
        let allRequests = [];
        let currentViewRequestId = null;

        function groupLabel(item) {
            return item.source_group_label || item.source_group || '—';
        }

        function formatRequestingUnit(value) {
            if (!value) return '';
            return String(value).replace(/\s*\(Group\s*\d+\)\s*$/i, '').trim();
        }

        function formatEventWindow(item) {
            const start = formatTime(item.event_start_time);
            const end = item.event_end_time ? formatTime(item.event_end_time) : '—';
            return formatDate(item.event_date) + '<br><small>' + start + (end !== '—' ? ' - ' + end : '') + '</small>';
        }

        function statusClass(status) {
            return String(status || '').toLowerCase().replace(/\s+/g, '-');
        }

        function formatAssignedPersonnel(item) {
            const personnel = item.assigned_personnel || [];
            if (!personnel.length) {
                return item.patrols_assigned != null ? String(item.patrols_assigned) : '—';
            }
            return personnel.map(function(person) {
                return person.bpso_personnel_id + ' - ' + person.personnel_name;
            }).join(', ');
        }

        function formatTime(value) {
            if (!value) return '—';
            return String(value).slice(0, 5);
        }

        function formatDate(value) {
            if (!value) return '—';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        function canActOnRequest(item) {
            const status = String(item.status || '').toLowerCase();
            return !['rejected', 'cancelled', 'scheduled'].includes(status);
        }

        async function loadRequests() {
            try {
                const res = await fetch('api/patrol_requests.php');
                const result = await res.json();
                if (!result.success) throw new Error(result.message || 'Failed to load');
                allRequests = result.data || [];
                requestData = {};
                allRequests.forEach(item => { requestData[item.request_id] = item; });
                filterRequests();
                const urlId = new URLSearchParams(window.location.search).get('id');
                if (urlId && requestData[urlId]) {
                    viewRequest(urlId);
                }
            } catch (e) {
                console.error(e);
                document.getElementById('requestsTableBody').innerHTML = '<tr><td colspan="8" style="text-align:center;color:#b91c1c;">Failed to load patrol requests.</td></tr>';
            }
        }

        function filterRequests() {
            const query = document.getElementById('searchInput').value.trim().toLowerCase();
            const dateFilter = document.getElementById('dateFilter').value;
            const tbody = document.getElementById('requestsTableBody');
            tbody.innerHTML = '';

            const filtered = allRequests.filter(item => {
                const haystack = [
                    item.request_id, item.source_group, item.source_group_label, item.requesting_unit,
                    item.contact_person, item.contact_number, item.event_name, item.event_location, item.status
                ].join(' ').toLowerCase();
                const matchesQuery = query === '' || haystack.includes(query);
                const matchesDate = dateFilter === '' || String(item.event_date || '').startsWith(dateFilter) || String(item.submitted_at || '').startsWith(dateFilter);
                return matchesQuery && matchesDate;
            });

            if (!filtered.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-secondary);">No patrol requests found.</td></tr>';
                return;
            }

            filtered.forEach(item => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${item.request_id}</td>
                    <td>${item.event_name}<br><small>${formatRequestingUnit(item.requesting_unit)}</small></td>
                    <td>${formatEventWindow(item)}</td>
                    <td>${item.event_location}</td>
                    <td>${(item.patrols_assigned != null ? item.patrols_assigned : 0) + ' / ' + item.patrols_needed}</td>
                    <td><span class="status-badge status-${statusClass(item.status)}">${item.status}</span></td>
                    <td>${formatDate(item.submitted_at)}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-view" onclick="viewRequest('${item.request_id}')">View</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function viewRequest(requestId) {
            const item = requestData[requestId];
            if (!item) return;
            currentViewRequestId = requestId;
            const endTime = item.event_end_time ? formatTime(item.event_end_time) : '—';
            document.getElementById('viewDetails').innerHTML = `
                <div class="detail-row"><span class="detail-label">Request ID</span><span>${item.request_id}</span></div>
                <div class="detail-row"><span class="detail-label">Source Group</span><span>${groupLabel(item)}</span></div>
                <div class="detail-row"><span class="detail-label">Reference ID</span><span>${item.source_reference_id || '—'}</span></div>
                <div class="detail-row"><span class="detail-label">Requesting Unit</span><span>${formatRequestingUnit(item.requesting_unit)}</span></div>
                <div class="detail-row"><span class="detail-label">Contact</span><span>${item.contact_person}${item.contact_position ? ' (' + item.contact_position + ')' : ''}</span></div>
                <div class="detail-row"><span class="detail-label">Event Name</span><span>${item.event_name}</span></div>
                <div class="detail-row"><span class="detail-label">Event Date / Time</span><span>${formatDate(item.event_date)} ${formatTime(item.event_start_time)}${endTime !== '—' ? ' - ' + endTime : ''}</span></div>
                <div class="detail-row"><span class="detail-label">Event Location</span><span>${item.event_location}</span></div>
                <div class="detail-row"><span class="detail-label">Patrols Needed</span><span>${item.patrols_needed}</span></div>
                <div class="detail-row"><span class="detail-label">Event Description</span><span>${item.event_description || '—'}</span></div>
                <div class="detail-row"><span class="detail-label">Special Instructions</span><span>${item.special_instructions || '—'}</span></div>
                <div class="detail-row"><span class="detail-label">Status</span><span>${item.status}</span></div>
                <div class="detail-row"><span class="detail-label">Patrols Assigned</span><span>${formatAssignedPersonnel(item)}</span></div>
                <div class="detail-row"><span class="detail-label">Review Notes</span><span>${item.review_notes || '—'}</span></div>
            `;

            const actions = document.getElementById('viewRequestActions');
            const canAct = canActOnRequest(item);
            actions.style.display = canAct ? 'flex' : 'none';
            document.getElementById('assignPersonnelBtn').disabled = !canAct;
            document.getElementById('declineRequestBtn').disabled = !canAct;

            document.getElementById('viewModal').classList.add('active');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
            currentViewRequestId = null;
        }

        function getRemainingAssignSlots(item) {
            const needed = Number(item.patrols_needed || 0);
            const alreadyAssigned = Array.isArray(item.assigned_patrol_ids)
                ? item.assigned_patrol_ids.length
                : Number(item.patrols_assigned || 0);
            if (needed <= 0) return 1;
            return Math.max(needed - alreadyAssigned, 0);
        }

        function normalizeDutyShift(value) {
            const raw = String(value || '').trim().toLowerCase();
            if (raw.indexOf('night') !== -1) return 'Night Shift';
            if (raw.indexOf('day') !== -1) return 'Day Shift';
            return '';
        }

        function buildRequestDetailRows(item) {
            const endTime = item.event_end_time ? formatTime(item.event_end_time) : '—';
            const alreadyAssigned = Array.isArray(item.assigned_patrol_ids)
                ? item.assigned_patrol_ids.length
                : Number(item.patrols_assigned || 0);
            return [
                ['Request ID', item.request_id],
                ['Source Group', groupLabel(item)],
                ['Reference ID', item.source_reference_id || '—'],
                ['Requesting Unit', formatRequestingUnit(item.requesting_unit) || '—'],
                ['Contact', (item.contact_person || '—') + (item.contact_position ? ' (' + item.contact_position + ')' : '')],
                ['Event Name', item.event_name || '—'],
                ['Event Date / Time', formatDate(item.event_date) + ' ' + formatTime(item.event_start_time) + (endTime !== '—' ? ' - ' + endTime : '')],
                ['Event Location', item.event_location || '—'],
                ['Patrols Needed', String(item.patrols_needed || 0)],
                ['Already Assigned', String(alreadyAssigned)],
                ['Event Description', item.event_description || '—'],
                ['Special Instructions', item.special_instructions || '—'],
                ['Status', item.status || '—']
            ];
        }

        function renderAssignRequestDetails(item) {
            document.getElementById('assignRequestDetails').innerHTML = buildRequestDetailRows(item).map(function(row) {
                return '<div class="detail-row"><span class="detail-label">' + row[0] + '</span><span>' + row[1] + '</span></div>';
            }).join('');
        }

        function buildAssignmentNotes(item) {
            return buildRequestDetailRows(item).map(function(row) {
                return row[0] + ': ' + row[1];
            }).join('\n');
        }

        let assignPersonnelCandidates = [];

        async function openAssignPatrolModal() {
            const item = requestData[currentViewRequestId];
            if (!item) return;
            if (!canActOnRequest(item)) {
                alert('This patrol request can no longer be assigned.');
                return;
            }

            const remaining = getRemainingAssignSlots(item);
            if (Number(item.patrols_needed || 0) > 0 && remaining <= 0) {
                alert('All required patrol personnel for this request have already been assigned.');
                return;
            }

            document.getElementById('assignRequestDbId').value = item.id;
            document.getElementById('assignRequestRef').textContent = 'Request ID: ' + item.request_id;
            renderAssignRequestDetails(item);
            document.getElementById('assignPatrolHint').textContent = remaining > 1
                ? ('Only personnel currently at the barangay hall (timed in today) are shown. Choose ' + remaining + ' different personnel below. Request details will appear in their My Schedule.')
                : 'Only personnel currently at the barangay hall (timed in today) are shown. Request details will appear in their My Schedule.';

            await loadAssignPersonnelOptions(item, remaining);
            closeViewModal();
            document.getElementById('assignModal').classList.add('active');
        }

        function closeAssignPatrolModal() {
            document.getElementById('assignModal').classList.remove('active');
            document.getElementById('assignPatrolForm').reset();
            document.getElementById('assignRequestDbId').value = '';
            document.getElementById('assignRequestDetails').innerHTML = '';
            document.getElementById('assignPatrolSlots').innerHTML = '';
            assignPersonnelCandidates = [];
        }

        function buildPersonnelOptionHtml(personnel, selectedId) {
            const status = String(personnel.status || 'Available').trim() || 'Available';
            const dutyShift = normalizeDutyShift(personnel.duty_shift || personnel.schedule || '');
            const disabled = (status !== 'Available' || !dutyShift) ? ' disabled' : '';
            const selected = String(personnel.id) === String(selectedId || '') ? ' selected' : '';
            return '<option value="' + personnel.id + '" data-duty-shift="' + dutyShift + '" data-status="' + status + '"' + disabled + selected + '>'
                + personnel.bpso_personnel_id + ' - ' + personnel.personnel_name + ' (' + status + ')'
                + '</option>';
        }

        function getAssignSlotSelects() {
            return Array.from(document.querySelectorAll('#assignPatrolSlots select.assign-patrol-slot'));
        }

        function syncAssignSlotOptions() {
            const selects = getAssignSlotSelects();
            const selectedIds = selects.map(function(select) { return String(select.value || ''); });

            selects.forEach(function(select, index) {
                const currentValue = String(select.value || '');
                const takenByOthers = new Set(
                    selectedIds.filter(function(id, i) { return id && i !== index; })
                );

                select.innerHTML = '<option value="">Select BPSO Personnel</option>'
                    + assignPersonnelCandidates.map(function(personnel) {
                        if (takenByOthers.has(String(personnel.id)) && String(personnel.id) !== currentValue) {
                            return '';
                        }
                        return buildPersonnelOptionHtml(personnel, currentValue);
                    }).join('');
            });
        }

        async function loadAssignPersonnelOptions(item, remainingSlots) {
            const container = document.getElementById('assignPatrolSlots');
            container.innerHTML = '';
            assignPersonnelCandidates = [];

            const alreadyAssigned = new Set(
                (Array.isArray(item.assigned_patrol_ids) ? item.assigned_patrol_ids : [])
                    .map(function(id) { return String(id); })
            );

            try {
                const [patrolResponse, hallResponse] = await Promise.all([
                    fetch('api/patrols.php'),
                    fetch('api/bpso_attendance.php?view=at_hall')
                ]);
                const result = await patrolResponse.json();
                const hallResult = await hallResponse.json();
                if (!result.success || !result.data) {
                    container.innerHTML = '<div class="form-group"><select disabled><option>No personnel available</option></select></div>';
                    return;
                }

                const atHallIds = new Set(
                    (hallResult.success ? (hallResult.data || []) : [])
                        .map(function(row) { return String(row.patrol_id); })
                );

                assignPersonnelCandidates = result.data.filter(function(p) {
                    return atHallIds.has(String(p.id)) && !alreadyAssigned.has(String(p.id));
                });

                if (!assignPersonnelCandidates.length) {
                    container.innerHTML = '<div class="form-group"><select disabled><option>No personnel currently at the barangay hall</option></select></div>';
                    return;
                }

                const slotCount = Math.max(1, Number(remainingSlots) || 1);
                for (let i = 0; i < slotCount; i++) {
                    const group = document.createElement('div');
                    group.className = 'form-group';
                    const label = document.createElement('label');
                    label.setAttribute('for', 'assignPatrolSelect' + i);
                    label.textContent = slotCount > 1
                        ? ('Assign BPSO Personnel ' + (i + 1) + ' of ' + slotCount)
                        : 'Assign BPSO Personnel';
                    const select = document.createElement('select');
                    select.id = 'assignPatrolSelect' + i;
                    select.className = 'assign-patrol-slot';
                    select.required = true;
                    select.innerHTML = '<option value="">Select BPSO Personnel</option>';
                    select.addEventListener('change', syncAssignSlotOptions);
                    group.appendChild(label);
                    group.appendChild(select);
                    container.appendChild(group);
                }

                syncAssignSlotOptions();
            } catch (e) {
                console.error('Error loading BPSO personnel:', e);
                container.innerHTML = '<div class="form-group"><select disabled><option>Failed to load personnel</option></select></div>';
            }
        }

        async function savePatrolRequestAssignment(event) {
            event.preventDefault();
            const dbId = parseInt(document.getElementById('assignRequestDbId').value, 10);
            const item = allRequests.find(function(r) { return Number(r.id) === dbId; });
            if (!item || !dbId) {
                alert('Patrol request not found.');
                return;
            }

            const selects = getAssignSlotSelects();
            const selected = [];
            const seen = new Set();

            for (let i = 0; i < selects.length; i++) {
                const select = selects[i];
                const option = select.options[select.selectedIndex];
                const patrolId = parseInt(select.value, 10);
                if (!patrolId || !option) {
                    alert('Please select BPSO personnel for every slot.');
                    return;
                }
                if (seen.has(patrolId)) {
                    alert('Each slot must use a different BPSO personnel.');
                    return;
                }
                if ((option.dataset.status || '') !== 'Available') {
                    alert('Only personnel with Available status can be assigned.');
                    return;
                }
                const shift = normalizeDutyShift(option.dataset.dutyShift || '');
                if (!shift) {
                    alert('Selected personnel must have a fixed duty shift (Day Shift or Night Shift).');
                    return;
                }
                seen.add(patrolId);
                selected.push({ id: patrolId, shift: shift });
            }

            if (!selected.length) {
                alert('Please select BPSO personnel to assign.');
                return;
            }

            const remaining = getRemainingAssignSlots(item);
            if (selected.length > remaining) {
                alert('You can assign at most ' + remaining + ' more personnel for this request.');
                return;
            }

            const assigned = Array.isArray(item.assigned_patrol_ids)
                ? item.assigned_patrol_ids.map(Number)
                : [];
            selected.forEach(function(entry) {
                if (!assigned.includes(entry.id)) assigned.push(entry.id);
            });

            const needed = Number(item.patrols_needed || 0);
            const status = (needed > 0 && assigned.length >= needed) ? 'Scheduled' : 'Approved';
            const location = String(item.event_location || '').trim() || 'Patrol Request';
            const scheduleDate = String(item.event_date || '').slice(0, 10) || new Date().toISOString().slice(0, 10);
            const notes = buildAssignmentNotes(item);
            const saveBtn = document.getElementById('assignSaveBtn');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Assigning...';

            try {
                for (let i = 0; i < selected.length; i++) {
                    const entry = selected[i];
                    const scheduleRes = await fetch('api/patrol_schedules.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'create',
                            patrol_id: entry.id,
                            schedule_date: scheduleDate,
                            shift: entry.shift,
                            patrol_zone: location,
                            route: location,
                            location: location,
                            notes: notes
                        })
                    });
                    const scheduleResult = await scheduleRes.json();
                    if (!scheduleResult.success) {
                        throw new Error(scheduleResult.message || 'Failed to create patrol schedule.');
                    }
                }

                const res = await fetch('api/patrol_requests.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'manage',
                        id: dbId,
                        status: status,
                        assigned_patrol_ids: assigned,
                        review_notes: item.review_notes || ('Assigned from Patrol Request for ' + item.request_id + '.'),
                        scheduling_notes: 'Assigned in Patrol Request module for ' + item.request_id,
                        skip_assignment_notifications: true
                    })
                });
                const result = await res.json();
                if (!result.success) {
                    throw new Error(result.message || 'Failed to update patrol request.');
                }
                closeAssignPatrolModal();
                await loadRequests();
                alert(status === 'Scheduled'
                    ? 'All required personnel assigned. Request marked as Scheduled.'
                    : 'Personnel assigned successfully. Assign again for remaining slots if needed.');
            } catch (err) {
                alert(err.message || 'Failed to assign personnel.');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Assign';
            }
        }

        async function declineRequest() {
            const item = requestData[currentViewRequestId];
            if (!item) return;
            if (!confirm('Decline this patrol request?')) return;

            try {
                const res = await fetch('api/patrol_requests.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'manage',
                        id: item.id,
                        status: 'Rejected',
                        review_notes: item.review_notes || 'Declined by admin.',
                        assigned_patrol_ids: item.assigned_patrol_ids || []
                    })
                });
                const result = await res.json();
                if (!result.success) {
                    throw new Error(result.message || 'Failed to decline request.');
                }
                closeViewModal();
                await loadRequests();
                alert('Patrol request declined.');
            } catch (err) {
                alert(err.message || 'Failed to decline request.');
            }
        }

        window.onclick = function(event) {
            if (event.target === document.getElementById('viewModal')) closeViewModal();
            if (event.target === document.getElementById('assignModal')) closeAssignPatrolModal();
        };

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

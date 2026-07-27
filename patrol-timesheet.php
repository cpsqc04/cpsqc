<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/db.php';

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Patrol Timesheet Record - Alertara</title>
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
        .date-filter { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; }
        .date-filter label { font-size: 0.9rem; font-weight: 500; color: var(--text-color); white-space: nowrap; }
        .date-filter input[type="date"] { padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.95rem; font-family: var(--font-family); }
        .date-filter input[type="date"]:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1); }
        .btn-export { padding: 0.75rem 1.25rem; background: var(--primary-color); color: #fff; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; white-space: nowrap; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-export:hover { background: #4ca8a6; }
        .table-container { overflow-x: auto; border-radius: 8px; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; background: var(--card-bg); }
        thead { background: var(--tertiary-color); color: #fff; }
        th { padding: 1rem; text-align: left; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-color); }
        tbody tr:hover { background: #f9f9f9; }
        tbody tr:last-child td { border-bottom: none; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.85rem; font-weight: 500; display: inline-block; }
        .status-at-hall, .status-clocked-on { background: #d1e7dd; color: #0f5132; }
        .status-timed-out, .status-clocked-out { background: #e9ecef; color: #6c757d; }
        .btn-view-timesheet { padding: 0.45rem 0.85rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 0.82rem; font-weight: 600; cursor: pointer; }
        .btn-view-timesheet:hover { background: #4ca8a6; }
        .timesheet-filters { display: flex; gap: 0.75rem; margin-bottom: 1rem; align-items: flex-end; flex-wrap: wrap; }
        .timesheet-filters .filter-field { display: flex; flex-direction: column; gap: 0.35rem; min-width: 160px; }
        .timesheet-filters .filter-field.personnel-field { min-width: 260px; flex: 1; }
        .timesheet-filters label { font-size: 0.85rem; font-weight: 500; color: var(--text-secondary); }
        .timesheet-filters select,
        .timesheet-filters input[type="date"],
        .timesheet-filters input[type="text"],
        .timesheet-filters input[type="search"] {
            padding: 0.65rem 0.85rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            font-family: inherit;
            background: #fff;
        }
        .personnel-picker { position: relative; display: flex; flex-direction: column; gap: 0.45rem; }
        .personnel-picker input[type="text"],
        .personnel-picker input[type="search"] {
            width: 100%;
            box-sizing: border-box;
            padding: 0.65rem 0.85rem;
            background: #fff;
            -webkit-appearance: none;
            appearance: none;
        }
        .personnel-picker input[type="search"]::-webkit-search-decoration,
        .personnel-picker input[type="search"]::-webkit-search-cancel-button,
        .personnel-picker input[type="search"]::-webkit-search-results-button,
        .personnel-picker input[type="search"]::-webkit-search-results-decoration {
            display: none;
            -webkit-appearance: none;
        }
        .personnel-suggestions {
            display: none;
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 40;
            max-height: 240px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        .personnel-suggestions.open { display: block; }
        .personnel-suggestion-item {
            width: 100%;
            border: none;
            background: #fff;
            text-align: left;
            padding: 0.75rem 0.9rem;
            cursor: pointer;
            font: inherit;
            color: var(--text-color);
            border-bottom: 1px solid #f1f5f9;
        }
        .personnel-suggestion-item:last-child { border-bottom: none; }
        .personnel-suggestion-item:hover,
        .personnel-suggestion-item.active { background: rgba(76, 138, 137, 0.12); }
        .personnel-suggestion-empty {
            padding: 0.85rem 0.9rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        .personnel-selected-chip {
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.55rem 0.75rem;
            border-radius: 8px;
            background: rgba(76, 138, 137, 0.12);
            color: var(--tertiary-color);
            font-size: 0.9rem;
            font-weight: 600;
        }
        .personnel-selected-chip.show { display: flex; }
        .personnel-selected-chip button {
            border: none;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
            padding: 0;
        }
        .timesheet-filters .filter-hint { width: 100%; margin: 0; color: var(--text-secondary); font-size: 0.8rem; }
        .btn-apply-filter { padding: 0.65rem 1rem; background: var(--primary-color); color: #fff; border: none; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; }
        .btn-apply-filter:hover { background: #4ca8a6; }
        .personnel-timesheet-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.75rem; }
        .personnel-timesheet-meta { color: var(--text-secondary); font-size: 0.9rem; }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow: auto; }
        .modal.active { display: block; }
        .modal-content { background: var(--card-bg); margin: 4% auto; padding: 1.5rem; border-radius: 12px; width: 92%; max-width: 980px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 2px solid var(--border-color); }
        .modal-header h2 { margin: 0; color: var(--tertiary-color); font-size: 1.25rem; }
        .close-modal { background: none; border: none; font-size: 1.75rem; cursor: pointer; color: #aaa; line-height: 1; }
        .close-modal:hover { color: #333; }
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
                    <?php $patrolNavActive = 'patrol-timesheet'; require __DIR__ . '/includes/patrol_nav_submodules.php'; ?>
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
                <h1 class="page-title">Patrol Timesheet Record</h1>
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
                    <h2 class="section-title"><i class="fas fa-clock"></i> Patrol Timesheet Record</h2>
                    <div class="timesheet-filters">
                        <div class="filter-field personnel-field">
                            <label for="personnelSearch">Personnel</label>
                            <div class="personnel-picker">
                                <input type="hidden" id="timesheetPersonnel" value="">
                                <input type="text" id="personnelSearch" placeholder="Search by ID or name..." autocomplete="off"
                                    oninput="onPersonnelSearchInput()"
                                    onfocus="onPersonnelSearchInput()"
                                    onkeydown="onPersonnelSearchKeydown(event)">
                                <div class="personnel-selected-chip" id="personnelSelectedChip">
                                    <span id="personnelSelectedLabel"></span>
                                    <button type="button" onclick="clearSelectedPersonnel()" aria-label="Clear selected personnel">&times;</button>
                                </div>
                                <div class="personnel-suggestions" id="personnelSuggestions" role="listbox"></div>
                            </div>
                        </div>
                        <div class="filter-field">
                            <label for="adminTimesheetFrom">Date From</label>
                            <input type="date" id="adminTimesheetFrom">
                        </div>
                        <div class="filter-field">
                            <label for="adminTimesheetTo">Date To</label>
                            <input type="date" id="adminTimesheetTo">
                        </div>
                        <button type="button" class="btn-apply-filter" onclick="loadAdminPersonnelTimesheet()">
                            <i class="fas fa-search"></i> View Timesheet
                        </button>
                    </div>
                    <div class="personnel-timesheet-header">
                        <div class="personnel-timesheet-meta" id="adminTimesheetMeta">Select a personnel and date range to view their timesheet.</div>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Duty</th>
                                    <th>Clock On</th>
                                    <th>Clock Out</th>
                                    <th>Duration</th>
                                    <th>Overtime</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="adminTimesheetBody">
                                <tr><td colspan="7" style="text-align:center;padding:2rem;color:#666;">No timesheet loaded yet.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let personnelList = [];
        let timesheetRows = [];
        let timesheetRefreshTimer = null;
        let suggestionHighlightIndex = -1;

        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }

            initAdminTimesheetRange();
            document.addEventListener('click', function(event) {
                const picker = document.querySelector('.personnel-picker');
                if (picker && !picker.contains(event.target)) {
                    hidePersonnelSuggestions();
                }
            });

            loadPersonnelOptions().then(() => {
                const params = new URLSearchParams(window.location.search);
                const patrolId = params.get('patrol_id');
                if (patrolId) {
                    const selected = personnelList.find(p => String(p.id) === String(patrolId));
                    if (selected) {
                        selectPersonnel(selected, false);
                        loadAdminPersonnelTimesheet();
                    }
                }
            });
        });

        function todayString() {
            const now = new Date();
            const y = now.getFullYear();
            const m = String(now.getMonth() + 1).padStart(2, '0');
            const d = String(now.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        function shiftDateString(days) {
            const now = new Date();
            now.setDate(now.getDate() + days);
            const y = now.getFullYear();
            const m = String(now.getMonth() + 1).padStart(2, '0');
            const d = String(now.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = String(text);
            return div.innerHTML;
        }

        function formatDateTime(value) {
            if (!value) return '';
            const match = String(value).match(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
            if (!match) return escapeHtml(value);
            const iso = `${match[1]}-${match[2]}-${match[3]}T${match[4]}:${match[5]}:${match[6] || '00'}+08:00`;
            const date = new Date(iso);
            if (Number.isNaN(date.getTime())) return escapeHtml(value);
            return date.toLocaleString('en-US', {
                timeZone: 'Asia/Manila',
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        }

        function parseManilaDate(value) {
            if (!value) return null;
            const match = String(value).match(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
            if (!match) return null;
            const date = new Date(`${match[1]}-${match[2]}-${match[3]}T${match[4]}:${match[5]}:${match[6] || '00'}+08:00`);
            return Number.isNaN(date.getTime()) ? null : date;
        }

        function formatOvertimeClock(overtimeMinutes) {
            const total = Math.max(0, Math.floor(overtimeMinutes || 0));
            const hours = Math.floor(total / 60);
            const mins = total % 60;
            return String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0');
        }

        function computeLiveOvertimeLabel(row) {
            const start = parseManilaDate(row.time_in);
            if (!start) return '00:00';
            const end = row.time_out ? parseManilaDate(row.time_out) : new Date();
            if (!end) return '00:00';
            const totalMinutes = Math.max(0, Math.floor((end.getTime() - start.getTime()) / 60000));
            const overtimeMinutes = Math.max(0, totalMinutes - (8 * 60));
            if (overtimeMinutes <= 0) return '00:00';
            const label = formatOvertimeClock(overtimeMinutes);
            return row.time_out ? label : (label + ' (running)');
        }

        function statusClass(label) {
            const normalized = String(label || '').toLowerCase();
            if (normalized.includes('clocked on') || normalized === 'at hall') return 'status-clocked-on';
            return 'status-clocked-out';
        }

        function initAdminTimesheetRange() {
            const from = shiftDateString(-9);
            const to = todayString();
            const fromEl = document.getElementById('adminTimesheetFrom');
            const toEl = document.getElementById('adminTimesheetTo');
            if (fromEl) {
                fromEl.max = to;
                fromEl.value = from;
            }
            if (toEl) {
                toEl.max = to;
                toEl.value = to;
            }
        }

        function getDateRangeOrError(fromId, toId) {
            const dateFrom = (document.getElementById(fromId)?.value || '').trim();
            const dateTo = (document.getElementById(toId)?.value || '').trim();
            if (!dateFrom || !dateTo) return { error: 'Please select both Date From and Date To.' };
            const from = new Date(dateFrom + 'T00:00:00');
            const to = new Date(dateTo + 'T00:00:00');
            if (Number.isNaN(from.getTime()) || Number.isNaN(to.getTime())) return { error: 'Invalid date range.' };
            if (from > to) return { error: 'Date From cannot be later than Date To.' };
            const daySpan = Math.floor((to - from) / 86400000) + 1;
            if (daySpan > 10) return { error: 'Date range is limited to a maximum of 10 days.' };
            return { dateFrom, dateTo };
        }

        function renderTimesheetRows(rows) {
            if (!rows.length) {
                return '<tr><td colspan="7" style="text-align:center;padding:2rem;color:#666;">No timesheet records found for this date range.</td></tr>';
            }
            return rows.map(row => `
                <tr>
                    <td>${escapeHtml(row.attendance_date || '')}</td>
                    <td>${escapeHtml(row.duty || '')}</td>
                    <td>${formatDateTime(row.time_in)}</td>
                    <td>${formatDateTime(row.time_out)}</td>
                    <td>${escapeHtml(row.patrol_duration_label || '')}</td>
                    <td class="overtime-cell" data-time-in="${escapeHtml(row.time_in || '')}" data-time-out="${escapeHtml(row.time_out || '')}">${escapeHtml(computeLiveOvertimeLabel(row))}</td>
                    <td><span class="status-badge ${statusClass(row.status_label)}">${escapeHtml(row.status_label || '')}</span></td>
                </tr>
            `).join('');
        }

        function refreshRunningOvertimeCells() {
            document.querySelectorAll('.overtime-cell').forEach(cell => {
                const timeIn = cell.getAttribute('data-time-in');
                const timeOut = cell.getAttribute('data-time-out');
                cell.textContent = computeLiveOvertimeLabel({ time_in: timeIn, time_out: timeOut || null });
            });
        }

        function personnelLabel(person) {
            return (person.bpso_personnel_id || '-') + ' - ' + (person.personnel_name || 'Unnamed');
        }

        function hidePersonnelSuggestions() {
            const box = document.getElementById('personnelSuggestions');
            box.classList.remove('open');
            box.innerHTML = '';
            suggestionHighlightIndex = -1;
        }

        function getFilteredPersonnel(query) {
            const q = (query || '').trim().toLowerCase();
            if (!q) return [];
            return personnelList.filter(p => {
                const haystack = ((p.bpso_personnel_id || '') + ' ' + (p.personnel_name || '')).toLowerCase();
                return haystack.includes(q);
            }).slice(0, 12);
        }

        function renderPersonnelSuggestions(query) {
            const box = document.getElementById('personnelSuggestions');
            const filtered = getFilteredPersonnel(query);

            if (!(query || '').trim()) {
                hidePersonnelSuggestions();
                return;
            }

            if (filtered.length === 0) {
                box.innerHTML = '<div class="personnel-suggestion-empty">No matching personnel</div>';
                box.classList.add('open');
                suggestionHighlightIndex = -1;
                return;
            }

            box.innerHTML = filtered.map((p, index) => `
                <button type="button" class="personnel-suggestion-item" role="option" data-index="${index}" data-id="${p.id}" onclick="selectPersonnelById(${Number(p.id)})">
                    ${escapeHtml(personnelLabel(p))}
                </button>
            `).join('');
            box.classList.add('open');
            suggestionHighlightIndex = -1;
        }

        function onPersonnelSearchInput() {
            const input = document.getElementById('personnelSearch');
            const hidden = document.getElementById('timesheetPersonnel');
            const selected = personnelList.find(p => String(p.id) === String(hidden.value));
            if (selected && input.value.trim() !== personnelLabel(selected)) {
                hidden.value = '';
                document.getElementById('personnelSelectedChip').classList.remove('show');
            }
            renderPersonnelSuggestions(input.value);
        }

        function onPersonnelSearchKeydown(event) {
            const box = document.getElementById('personnelSuggestions');
            if (!box.classList.contains('open')) return;
            const items = Array.from(box.querySelectorAll('.personnel-suggestion-item'));
            if (!items.length) return;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                suggestionHighlightIndex = (suggestionHighlightIndex + 1) % items.length;
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                suggestionHighlightIndex = suggestionHighlightIndex <= 0 ? items.length - 1 : suggestionHighlightIndex - 1;
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (suggestionHighlightIndex >= 0 && items[suggestionHighlightIndex]) {
                    items[suggestionHighlightIndex].click();
                } else if (items[0]) {
                    items[0].click();
                }
                return;
            } else if (event.key === 'Escape') {
                hidePersonnelSuggestions();
                return;
            } else {
                return;
            }

            items.forEach((item, index) => {
                item.classList.toggle('active', index === suggestionHighlightIndex);
            });
        }

        function selectPersonnelById(patrolId) {
            const selected = personnelList.find(p => String(p.id) === String(patrolId));
            if (selected) selectPersonnel(selected, true);
        }

        function selectPersonnel(person, hideList = true) {
            document.getElementById('timesheetPersonnel').value = String(person.id);
            document.getElementById('personnelSearch').value = personnelLabel(person);
            document.getElementById('personnelSelectedLabel').textContent = personnelLabel(person);
            document.getElementById('personnelSelectedChip').classList.add('show');
            if (hideList) hidePersonnelSuggestions();
        }

        function clearSelectedPersonnel() {
            document.getElementById('timesheetPersonnel').value = '';
            document.getElementById('personnelSearch').value = '';
            document.getElementById('personnelSelectedLabel').textContent = '';
            document.getElementById('personnelSelectedChip').classList.remove('show');
            hidePersonnelSuggestions();
            document.getElementById('personnelSearch').focus();
        }

        async function loadPersonnelOptions() {
            try {
                const response = await fetch('api/patrols.php');
                const result = await response.json();
                personnelList = result.success ? (result.data || []) : [];
            } catch (e) {
                personnelList = [];
            }
        }

        async function fetchPersonnelTimesheet(patrolId, dateFrom, dateTo) {
            const url = 'api/bpso_attendance.php?view=history'
                + '&patrol_id=' + encodeURIComponent(patrolId)
                + '&date_from=' + encodeURIComponent(dateFrom)
                + '&date_to=' + encodeURIComponent(dateTo);
            const response = await fetch(url);
            return response.json();
        }

        async function loadAdminPersonnelTimesheet() {
            const tbody = document.getElementById('adminTimesheetBody');
            const meta = document.getElementById('adminTimesheetMeta');
            const patrolId = document.getElementById('timesheetPersonnel').value;
            const range = getDateRangeOrError('adminTimesheetFrom', 'adminTimesheetTo');

            if (!patrolId) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:#666;">Please select a personnel.</td></tr>';
                return;
            }
            if (range.error) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:2rem;color:#666;">${escapeHtml(range.error)}</td></tr>`;
                return;
            }

            const selected = personnelList.find(p => String(p.id) === String(patrolId));
            meta.textContent = selected
                ? `Timesheet for ${selected.personnel_name || 'Personnel'} (${selected.bpso_personnel_id || '-'}) - ${range.dateFrom} to ${range.dateTo}`
                : `Timesheet - ${range.dateFrom} to ${range.dateTo}`;

            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:#666;">Loading timesheet...</td></tr>';
            try {
                const result = await fetchPersonnelTimesheet(patrolId, range.dateFrom, range.dateTo);
                if (!result.success) {
                    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:2rem;color:#666;">${escapeHtml(result.message || 'Failed to load timesheet.')}</td></tr>`;
                    return;
                }
                timesheetRows = result.data || [];
                tbody.innerHTML = renderTimesheetRows(timesheetRows);
                if (timesheetRefreshTimer) clearInterval(timesheetRefreshTimer);
                timesheetRefreshTimer = setInterval(refreshRunningOvertimeCells, 30000);
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:#666;">Failed to load timesheet.</td></tr>';
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
            refreshRunningOvertimeCells();
        }

        updateDateTime();
        setInterval(updateDateTime, 1000);
    </script>
    <?php require __DIR__ . '/includes/admin_notifications_script.php'; ?>
    <script src="js/mobile-shell.js"></script>
</body>
</html>

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
    <title>Patrol Logs - Alertara</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/admin-sidebar.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="js/alertara-report-export.js"></script>
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
        .user-name-display { color: rgba(255, 255, 255, 0.9); font-size: 0.95rem; font-weight: 500; text-align: center; padding: 0.5rem 1rem; transition: all 0.3s ease; word-break: break-word; max-width: 100%; }
        .sidebar.collapsed .user-name-display { opacity: 0; height: 0; padding: 0; overflow: hidden; font-size: 0; }
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
        .user-info span { color: var(--text-color); font-weight: 500; }
        
        /* Notification Bell */
        .notification-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .notification-bell {
            position: relative;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            color: var(--text-color);
            font-size: 1.25rem;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .notification-bell:hover {
            background: rgba(28, 37, 65, 0.05);
            color: var(--primary-color);
        }
        
        .notification-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            background: #ef4444;
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
            display: none;
        }
        
        .notification-badge.show {
            display: block;
        }
        
        .notification-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 380px;
            max-height: 500px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            display: none;
            flex-direction: column;
            z-index: 1000;
            overflow: hidden;
        }
        
        .notification-dropdown.show {
            display: flex;
        }
        
        .notification-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--header-bg);
        }
        
        .notification-header h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .notification-header button {
            background: transparent;
            border: none;
            color: var(--primary-color);
            font-size: 0.85rem;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            transition: background 0.2s ease;
        }
        
        .notification-header button:hover {
            background: rgba(76, 138, 137, 0.1);
        }
        
        .notification-list {
            flex: 1;
            overflow-y: auto;
            max-height: 400px;
        }
        
        .notification-item {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.2s ease;
            display: flex;
            gap: 0.75rem;
            position: relative;
        }
        
        .notification-item:hover {
            background: #f8f9fa;
        }
        
        .notification-item.unread {
            background: #f0f9ff;
            border-left: 3px solid var(--primary-color);
        }
        
        .notification-item.unread::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 6px;
            height: 6px;
            background: var(--primary-color);
            border-radius: 50%;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        
        .notification-icon.complaint {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .notification-icon.tip {
            background: #fef3c7;
            color: #d97706;
        }
        
        .notification-icon.volunteer {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .notification-icon.event {
            background: #d1fae5;
            color: #059669;
        }
        
        .notification-icon.login {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .notification-icon.logout {
            background: #e0e7ff;
            color: #6366f1;
        }
        
        .notification-content {
            flex: 1;
            min-width: 0;
        }
        
        .notification-title {
            font-weight: 600;
            color: var(--text-color);
            font-size: 0.95rem;
            margin: 0 0 0.25rem 0;
        }
        
        .notification-message {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin: 0 0 0.5rem 0;
            line-height: 1.4;
        }
        
        .notification-time {
            color: var(--text-secondary);
            font-size: 0.75rem;
        }
        
        .notification-empty {
            padding: 3rem 1.5rem;
            text-align: center;
            color: var(--text-secondary);
        }
        
        .notification-empty i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
        
        /* Date and Time Display */
        .datetime-display {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-color);
            font-size: 0.9rem;
            font-weight: 500;
            margin-right: 1rem;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .datetime-display .date-part {
            color: var(--text-secondary);
        }
        
        .datetime-display .time-part {
            color: var(--text-color);
            font-weight: 600;
        }
        
        /* Sidebar Logout Button */
        .sidebar-footer { margin-top: auto; padding: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.5rem; background: rgba(239, 68, 68, 0.1); color: rgba(255, 255, 255, 0.9); text-decoration: none; border-radius: 8px; font-size: 1rem; font-weight: 500; transition: all 0.2s ease; border: 1px solid rgba(239, 68, 68, 0.2); width: 100%; box-sizing: border-box; }
        .sidebar-logout-btn:hover { background: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.4); color: #fff; }
        .sidebar-logout-btn i { font-size: 1.1rem; flex-shrink: 0; }
        .sidebar-logout-btn span { flex: 1; transition: opacity 0.3s ease; }
        .sidebar.collapsed .sidebar-logout-btn span { opacity: 0; width: 0; overflow: hidden; }
        .sidebar.collapsed .sidebar-logout-btn { justify-content: center; padding: 0.875rem; }
        .logout-btn { padding: 0.5rem 1rem; background: var(--primary-color); color: #fff; text-decoration: none; border-radius: 6px; font-size: 0.9rem; transition: background 0.2s ease; display: none; }
        .logout-btn:hover { background: #4ca8a6; }
        .content-area { padding: 2rem; flex: 1; background: #f5f5f5; }
        .content-burger-btn { background: transparent; border: none; color: var(--tertiary-color); width: 40px; height: 40px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; flex-shrink: 0; padding: 0; }
        .content-burger-btn:hover { background: rgba(28, 37, 65, 0.05); }
        .content-burger-btn span { display: block; width: 22px; height: 1.5px; background: var(--tertiary-color); position: relative; transition: all 0.3s ease; }
        .content-burger-btn span::before, .content-burger-btn span::after { content: ''; position: absolute; width: 22px; height: 1.5px; background: var(--tertiary-color); transition: all 0.3s ease; }
        .content-burger-btn span::before { top: -7px; }
        .content-burger-btn span::after { bottom: -7px; }
        .page-title { font-size: 2rem; font-weight: 700; color: var(--tertiary-color); margin: 0; }
        .page-content { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; box-shadow: 0 2px 8px var(--shadow); margin-top: 1.5rem; }
        .logs-toolbar { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1.5rem; }
        .search-box { flex: 1; min-width: 220px; position: relative; margin-bottom: 0; }
        .search-box input { width: 100%; padding: 0.75rem 1rem 0.75rem 2.5rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; }
        .search-box input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1); }
        .search-box::before { content: "🔍"; position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 1rem; }
        .table-container { overflow-x: auto; border-radius: 8px; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; background: var(--card-bg); }
        thead { background: var(--tertiary-color); color: #fff; }
        th { padding: 1rem; text-align: left; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-color); }
        tbody tr:hover { background: #f9f9f9; }
        tbody tr:last-child td { border-bottom: none; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.85rem; font-weight: 500; display: inline-block; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-resolved { background: #d1e7dd; color: #0f5132; }
        .btn-view { padding: 0.5rem 1rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; margin-right: 0.5rem; }
        .btn-view:hover { background: #4ca8a6; }
        .btn-export {
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn-export:hover { background: #4ca8a6; }
        .btn-campaign {
            padding: 0.75rem 1.25rem;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-campaign:hover { background: #4ca8a6; }
        .btn-campaign:disabled { opacity: 0.55; cursor: not-allowed; }
        .badge-sent { display: inline-block; margin-left: 0.35rem; padding: 0.1rem 0.45rem; border-radius: 999px; background: #d1fae5; color: #065f46; font-size: 0.7rem; font-weight: 700; }
        .campaign-field { margin-bottom: 1rem; }
        .campaign-field label { display: block; font-weight: 600; margin-bottom: 0.4rem; color: var(--text-color); }
        .campaign-field select, .campaign-field textarea, .campaign-field input[type="text"] {
            width: 100%; padding: 0.7rem; border: 1px solid var(--border-color); border-radius: 8px; font: inherit; box-sizing: border-box;
        }
        .campaign-field textarea { min-height: 90px; resize: vertical; }
        .theme-checks { display: flex; flex-wrap: wrap; gap: 0.75rem 1.25rem; }
        .theme-checks label { font-weight: 500; display: flex; align-items: center; gap: 0.4rem; }
        .selected-report-list { max-height: 160px; overflow: auto; border: 1px solid var(--border-color); border-radius: 8px; padding: 0.75rem; background: #f8fafc; font-size: 0.9rem; }
        #logsTable th.col-select, #logsTable td.col-select { width: 42px; text-align: center; }
        body:not(.campaign-select-mode):not(.export-select-mode) #logsTable .col-select { display: none; }
        body:not(.campaign-select-mode) .campaign-select-only { display: none !important; }
        body:not(.export-select-mode) .export-select-only { display: none !important; }
        body:not(.campaign-select-mode):not(.export-select-mode) .select-mode-only { display: none !important; }
        body.campaign-select-mode .campaign-enter-only,
        body.export-select-mode .campaign-enter-only,
        body.campaign-select-mode .export-enter-only,
        body.export-select-mode .export-enter-only { display: none !important; }
        body.campaign-select-mode #logsTable tbody tr:not(.empty-row):hover,
        body.export-select-mode #logsTable tbody tr:not(.empty-row):hover { background: #f0fdfa; }
        .logs-toolbar .btn-export {
            padding: 0.75rem 1.25rem;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .logs-toolbar .btn-export:hover:not(:disabled) { background: #4ca8a6; }
        .logs-toolbar .btn-export:disabled { opacity: 0.55; cursor: not-allowed; }
        .log-photo {
            max-width: 280px;
            max-height: 200px;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 8px;
            margin-top: 0.5rem;
            display: block;
            border: 1px solid var(--border-color);
            background: #f8f9fa;
            cursor: pointer;
        }
        .log-photo:hover { opacity: 0.92; }
        .log-photo-missing { color: var(--text-secondary); font-style: italic; }
        .action-buttons { display: flex; gap: 0.5rem; align-items: center; }
        .status-in-progress { background: #cfe2ff; color: #084298; }
        .status-completed { background: #d1e7dd; color: #0f5132; }
        .status-scheduled { background: #fff3cd; color: #856404; }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); overflow: auto; }
        .modal-content { background-color: var(--card-bg); margin: 5% auto; padding: 2rem; border: 1px solid var(--border-color); border-radius: 12px; width: 90%; max-width: 700px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid var(--border-color); }
        .modal-header h2 { margin: 0; color: var(--tertiary-color); font-size: 1.5rem; }
        .close { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; transition: color 0.2s ease; }
        .close:hover { color: var(--tertiary-color); }
        .log-details { line-height: 1.8; }
        .log-details p { margin-bottom: 1rem; }
        .log-details strong { color: var(--tertiary-color); }
        .form-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); }
        .btn-cancel { padding: 0.75rem 1.5rem; background: #6c757d; color: #fff; border: none; border-radius: 8px; font-size: 0.95rem; cursor: pointer; transition: all 0.2s ease; }
        .btn-cancel:hover { background: #5a6268; }
        @media (max-width: 768px) {
            .sidebar { width: 320px; transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.mobile-open { transform: translateX(0); }
            .sidebar.collapsed { width: 80px; transform: translateX(0); }
            .main-wrapper { margin-left: 0; }
            body.sidebar-collapsed .main-wrapper { margin-left: 80px; }
            .modal-content { width: 95%; margin: 10% auto; padding: 1.5rem; }
            .logs-toolbar { flex-direction: column; align-items: stretch; }
            .btn-campaign, .btn-export, .btn-cancel { width: 100%; justify-content: center; }
        }
    </style>
    <link rel="stylesheet" href="css/mobile-responsive.css">
    <link rel="stylesheet" href="css/table-pagination.css">
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
            
            <!-- User Management Module (Admin Only) -->
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
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="CCTV Monitoring System">
                    <span class="nav-module-icon"><i class="fas fa-video"></i></span>
                    <span class="nav-module-header-text">CCTV Monitoring System</span>
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
                    <?php $patrolNavActive = 'patrol-logs'; require __DIR__ . '/includes/patrol_nav_submodules.php'; ?>
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
        
        <!-- Sidebar Footer with Logout -->
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
                <h1 class="page-title">Patrol Logs</h1>
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
                <div class="logs-toolbar">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search patrol logs by date, patrol, or incident..." onkeyup="filterLogs()">
                    </div>
                    <button type="button" class="btn-export export-enter-only" id="btnEnterLogExportSelect" onclick="enterLogExportSelectMode()">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                    <button type="button" class="btn-export export-select-only" id="btnExportSelectedLogs" onclick="exportSelectedLogs()" disabled>
                        <i class="fas fa-file-export"></i> Export Selected
                    </button>
                    <button type="button" class="btn-campaign campaign-enter-only" id="btnEnterCampaignSelect" onclick="enterCampaignSelectMode()">Send to Campaign</button>
                    <button type="button" class="btn-campaign campaign-select-only" id="btnOpenCampaignForward" onclick="openCampaignForwardModal()" disabled>Send to Campaign</button>
                    <button type="button" class="btn-cancel select-mode-only" onclick="exitActiveLogSelectMode()">Cancel</button>
                </div>
                <div class="table-container">
                    <table id="logsTable">
                        <thead>
                            <tr>
                                <th class="col-select"><input type="checkbox" id="selectAllLogs" title="Select all" onchange="toggleSelectAll(this)"></th>
                                <th>Date & Time</th>
                                <th>Patrol</th>
                                <th>Route</th>
                                <th>Incidents</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="logsTableBody">
                            <tr class="empty-row"><td colspan="7" style="text-align:center;padding:2rem;color:#666;">Loading patrol logs...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-pagination">
                    <div class="page-info" id="logsPageInfo">Page 1 of 1</div>
                    <div class="page-buttons">
                        <button type="button" id="logsPrevBtn" onclick="changeLogsPage(-1)" disabled>Previous</button>
                        <button type="button" id="logsNextBtn" onclick="changeLogsPage(1)" disabled>Next</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- View Patrol Log Modal -->
    <div id="viewLogModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Patrol Log Details</h2>
                <span class="close" onclick="closeViewLogModal()">&times;</span>
            </div>
            <div id="viewLogContent" class="log-details">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeViewLogModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Forward to Campaign Modal -->
    <div id="campaignForwardModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Send Youth Campaign Recommendation</h2>
                <span class="close" onclick="closeCampaignForwardModal()">&times;</span>
            </div>
            <p style="margin-top:0;color:var(--text-secondary);line-height:1.5;">
                Selected patrol reports will be packaged with the youth curfew ordinance (17 below, 10pm–8am) and sent to Campaign so they can create youth, sports, and cultural development programs.
            </p>
            <div class="campaign-field">
                <label>Selected patrol reports</label>
                <div id="campaignSelectedList" class="selected-report-list"></div>
            </div>
            <div class="campaign-field">
                <label>Themes</label>
                <div class="theme-checks">
                    <label><input type="checkbox" class="theme-check" value="youth" checked> Youth</label>
                    <label><input type="checkbox" class="theme-check" value="sports" checked> Sports</label>
                    <label><input type="checkbox" class="theme-check" value="cultural" checked> Cultural</label>
                </div>
            </div>
            <div class="campaign-field">
                <label for="campaignBulletinSelect">Bulletin ordinance (optional)</label>
                <select id="campaignBulletinSelect">
                    <option value="">Use default curfew summary (no bulletin link)</option>
                </select>
            </div>
            <div class="campaign-field">
                <label for="campaignTitle">Title (optional)</label>
                <input type="text" id="campaignTitle" placeholder="Youth Sports & Cultural Development Recommendation">
            </div>
            <div class="campaign-field">
                <label for="campaignRationale">Additional notes (optional)</label>
                <textarea id="campaignRationale" placeholder="Extra context for Campaign..."></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeCampaignForwardModal()">Cancel</button>
                <button type="button" class="btn-campaign" id="btnConfirmCampaignForward" onclick="confirmCampaignForward()">Forward to Campaign</button>
            </div>
        </div>
    </div>

    <script src="js/photo-lightbox.js"></script>
    <script src="js/table-pagination.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
            loadPatrolLogs();
        });
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
        // Patrol log data loaded from database
        let patrolLogData = {};
        let allPatrolLogs = [];
        let pendingCampaignIds = [];
        let campaignSelectMode = false;
        const logsPager = AlertaraTablePager.create({
            pageSize: 10,
            pageInfoId: 'logsPageInfo',
            prevBtnId: 'logsPrevBtn',
            nextBtnId: 'logsNextBtn',
            itemLabel: 'logs'
        });

        function getFilteredPatrolLogs() {
            const input = document.getElementById('searchInput');
            const filter = (input && input.value ? input.value : '').toLowerCase().trim();
            if (!filter) return allPatrolLogs.slice();
            return allPatrolLogs.filter(function(row) {
                const haystack = [
                    row.date,
                    row.time,
                    row.personnel_name,
                    row.route,
                    row.incidents,
                    row.status,
                    row.details,
                    row.location
                ].join(' ').toLowerCase();
                return haystack.indexOf(filter) > -1;
            });
        }

        function filterLogs() {
            logsPager.reset();
            renderPatrolLogsTable();
        }

        function changeLogsPage(delta) {
            logsPager.change(delta, getFilteredPatrolLogs().length);
            renderPatrolLogsTable();
        }

        function renderPatrolLogsTable() {
            const tableBody = document.getElementById('logsTableBody');
            const filtered = getFilteredPatrolLogs();
            const pageRows = logsPager.slice(filtered);

            if (filtered.length === 0) {
                const emptyMsg = allPatrolLogs.length === 0
                    ? 'No patrol logs yet.'
                    : 'No patrol logs match your search.';
                tableBody.innerHTML = '<tr class="empty-row"><td colspan="7" style="text-align:center;padding:2rem;color:#666;">' + emptyMsg + '</td></tr>';
                updateCampaignButtonState();
                updateLogExportButtonState();
                return;
            }

            tableBody.innerHTML = pageRows.map(function(row) {
                const dateTime = `${row.date}${row.time ? ' ' + row.time : ''}`;
                const alreadySent = !!row.campaign_forwarded_at;
                const badges = alreadySent ? '<span class="badge-sent">Sent to Campaign</span>' : '';
                const disabledAttr = alreadySent ? ' disabled' : '';
                return `<tr data-log-id="${row.id}">
                    <td class="col-select"><input type="checkbox" class="log-select" value="${row.id}" onchange="onLogSelectChange()"${disabledAttr}></td>
                    <td>${escapeHtml(dateTime)}${badges}</td>
                    <td>${escapeHtml(row.personnel_name)}</td>
                    <td>${escapeHtml(row.route)}</td>
                    <td>${escapeHtml(row.incidents || 'None')}</td>
                    <td><span class="status-badge ${statusClass(row.status)}">${escapeHtml(row.status)}</span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-view" onclick="viewLog('${row.id}')">View</button>
                        </div>
                    </td>
                </tr>`;
            }).join('');

            const master = document.getElementById('selectAllLogs');
            if (master) master.checked = false;
            updateCampaignButtonState();
            updateLogExportButtonState();
            syncLogCheckboxAvailability();
        }

        function statusClass(status) {
            if (status === 'Completed') return 'status-completed';
            if (status === 'In Progress') return 'status-in-progress';
            return 'status-scheduled';
        }

        function clearLogSelections() {
            const master = document.getElementById('selectAllLogs');
            if (master) master.checked = false;
            document.querySelectorAll('.log-select').forEach(function(cb) { cb.checked = false; });
        }

        function syncLogCheckboxAvailability() {
            const exportMode = document.body.classList.contains('export-select-mode');
            document.querySelectorAll('#logsTableBody tr[data-log-id]').forEach(function(row) {
                const cb = row.querySelector('.log-select');
                if (!cb) return;
                const log = patrolLogData[row.getAttribute('data-log-id')];
                cb.disabled = exportMode ? false : !!(log && log.campaign_forwarded_at);
            });
        }

        function onLogSelectChange() {
            updateCampaignButtonState();
            updateLogExportButtonState();
        }

        function exitActiveLogSelectMode() {
            if (document.body.classList.contains('export-select-mode')) {
                exitLogExportSelectMode();
            } else {
                exitCampaignSelectMode();
            }
        }

        function enterLogExportSelectMode() {
            exitCampaignSelectMode();
            document.body.classList.add('export-select-mode');
            clearLogSelections();
            syncLogCheckboxAvailability();
            updateLogExportButtonState();
        }

        function exitLogExportSelectMode() {
            document.body.classList.remove('export-select-mode');
            clearLogSelections();
            syncLogCheckboxAvailability();
            updateLogExportButtonState();
        }

        function updateLogExportButtonState() {
            const btn = document.getElementById('btnExportSelectedLogs');
            if (!btn) return;
            const selected = getSelectedLogIds().length;
            const active = document.body.classList.contains('export-select-mode');
            btn.disabled = !active || selected === 0;
            btn.innerHTML = selected > 0
                ? ('<i class="fas fa-file-export"></i> Export Selected (' + selected + ')')
                : '<i class="fas fa-file-export"></i> Export Selected';
        }

        function enterCampaignSelectMode() {
            exitLogExportSelectMode();
            campaignSelectMode = true;
            document.body.classList.add('campaign-select-mode');
            clearLogSelections();
            syncLogCheckboxAvailability();
            updateCampaignButtonState();
        }

        function exitCampaignSelectMode() {
            campaignSelectMode = false;
            document.body.classList.remove('campaign-select-mode');
            clearLogSelections();
            syncLogCheckboxAvailability();
            updateCampaignButtonState();
        }

        function updateCampaignButtonState() {
            const checked = document.querySelectorAll('.log-select:checked:not(:disabled)');
            const btn = document.getElementById('btnOpenCampaignForward');
            if (btn) {
                btn.disabled = !campaignSelectMode || checked.length === 0;
            }
        }

        function toggleSelectAll(master) {
            document.querySelectorAll('.log-select:not(:disabled)').forEach(function(cb) {
                cb.checked = master.checked;
            });
            onLogSelectChange();
        }

        function getSelectedLogIds() {
            return Array.from(document.querySelectorAll('.log-select:checked:not(:disabled)'))
                .map(function(cb) { return Number(cb.value); })
                .filter(function(id) { return id > 0; });
        }

        async function loadPatrolLogs() {
            const tableBody = document.getElementById('logsTableBody');
            try {
                const response = await fetch('api/patrol_logs.php');
                const result = await response.json();

                if (!result.success) {
                    tableBody.innerHTML = '<tr class="empty-row"><td colspan="7" style="text-align:center;padding:2rem;color:#666;">Failed to load patrol logs.</td></tr>';
                    return;
                }

                patrolLogData = {};
                allPatrolLogs = result.data || [];
                allPatrolLogs.forEach(function(row) {
                    patrolLogData[row.id] = {
                        id: String(row.id),
                        date: row.date,
                        time: row.time || '',
                        personnel_name: row.personnel_name,
                        route: row.route,
                        status: row.status,
                        incidents: row.incidents || 'None',
                        details: row.details || '',
                        location: row.location || '',
                        documentation_photo: row.documentation_photo || '',
                        has_documentation_photo: !!(row.has_documentation_photo || row.documentation_photo),
                        campaign_forwarded_at: row.campaign_forwarded_at || null,
                        campaign_reference_id: row.campaign_reference_id || ''
                    };
                });
                logsPager.reset();
                renderPatrolLogsTable();
            } catch (e) {
                console.error('Error loading patrol logs:', e);
                tableBody.innerHTML = '<tr class="empty-row"><td colspan="7" style="text-align:center;padding:2rem;color:#666;">Error loading patrol logs.</td></tr>';
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        async function ensurePatrolLogPhoto(logId) {
            const log = patrolLogData[logId];
            if (!log) return null;
            if (log.documentation_photo && String(log.documentation_photo).indexOf('data:image/') === 0) {
                return log.documentation_photo;
            }
            if (!log.has_documentation_photo) return null;
            try {
                const response = await fetch('api/patrol_logs.php?id=' + encodeURIComponent(logId));
                const result = await response.json();
                if (!result.success || !result.data) return null;
                const photo = result.data.documentation_photo || '';
                if (photo) {
                    log.documentation_photo = photo;
                    patrolLogData[logId].documentation_photo = photo;
                }
                return photo || null;
            } catch (e) {
                return null;
            }
        }

        async function viewPatrolPhoto(logId) {
            const photoSrc = await ensurePatrolLogPhoto(logId);
            if (!photoSrc || String(photoSrc).indexOf('data:image/') !== 0) {
                alert('No documentation photo available for this report.');
                return;
            }
            if (window.AlertaraPhotoLightbox) {
                AlertaraPhotoLightbox.open(photoSrc, 'Documentation photo');
                return;
            }
            alert('Photo viewer is unavailable.');
        }

        async function viewLog(id) {
            const log = patrolLogData[id];
            if (!log) {
                alert('Log not found');
                return;
            }

            const statusClassName = statusClass(log.status);
            const sentNote = log.campaign_forwarded_at
                ? `<p><strong>Campaign:</strong> Sent ${escapeHtml(new Date(log.campaign_forwarded_at).toLocaleString())}${log.campaign_reference_id ? ' — Ref: ' + escapeHtml(log.campaign_reference_id) : ''}</p>`
                : '';

            let photoHtml = '<p><strong>Documentation Photo:</strong><br><span class="log-photo-missing">No photo uploaded</span></p>';
            if (log.has_documentation_photo || log.documentation_photo) {
                const photoSrc = await ensurePatrolLogPhoto(id);
                if (photoSrc && String(photoSrc).indexOf('data:image/') === 0) {
                    photoHtml = '<p><strong>Documentation Photo:</strong><br>'
                        + '<img src="' + photoSrc + '" alt="Documentation photo" class="log-photo" '
                        + 'onclick="viewPatrolPhoto(\'' + String(id).replace(/'/g, '') + '\')" title="Click to view full size"></p>';
                }
            }

            const content = `
                <p><strong>Date:</strong> ${escapeHtml(log.date)}</p>
                <p><strong>Time:</strong> ${escapeHtml(log.time)}</p>
                <p><strong>Patrol:</strong> ${escapeHtml(log.personnel_name)}</p>
                <p><strong>Route:</strong> ${escapeHtml(log.route)}</p>
                <p><strong>Location:</strong> ${escapeHtml(log.location)}</p>
                <p><strong>Status:</strong> <span class="status-badge ${statusClassName}">${escapeHtml(log.status)}</span></p>
                <p><strong>Incidents:</strong> ${escapeHtml(log.incidents)}</p>
                <p><strong>Details:</strong><br>${escapeHtml(log.details)}</p>
                ${photoHtml}
                ${sentNote}
            `;

            document.getElementById('viewLogContent').innerHTML = content;
            document.getElementById('viewLogModal').style.display = 'block';
        }

        function closeViewLogModal() {
            document.getElementById('viewLogModal').style.display = 'none';
        }

        async function loadBulletinOptions() {
            const select = document.getElementById('campaignBulletinSelect');
            if (!select) return;
            select.innerHTML = '<option value="">Use default curfew summary (no bulletin link)</option>';
            try {
                const res = await fetch('api/bulletin_board.php?status=active');
                const result = await res.json();
                const posts = result.data || result.posts || [];
                if (!Array.isArray(posts)) return;
                posts.forEach(function(post) {
                    const title = String(post.title || 'Untitled');
                    const hay = (title + ' ' + (post.body || '')).toLowerCase();
                    const likelyOrdinance = /curfew|youth|ordinance|loiter|17|kabataan/.test(hay);
                    const opt = document.createElement('option');
                    opt.value = String(post.id);
                    opt.textContent = (likelyOrdinance ? '★ ' : '') + title;
                    if (likelyOrdinance) {
                        select.appendChild(opt);
                        if (!select.dataset.autoSelected) {
                            select.value = String(post.id);
                            select.dataset.autoSelected = '1';
                        }
                    } else {
                        select.appendChild(opt);
                    }
                });
            } catch (e) {
                console.warn('Could not load bulletin posts', e);
            }
        }

        function openCampaignForwardModal(ids) {
            pendingCampaignIds = Array.isArray(ids) && ids.length ? ids : getSelectedLogIds();
            if (!pendingCampaignIds.length) {
                alert('Select at least one patrol report.');
                return;
            }

            const list = document.getElementById('campaignSelectedList');
            list.innerHTML = pendingCampaignIds.map(function(id) {
                const log = patrolLogData[id];
                if (!log) return '';
                return '<div>#' + escapeHtml(String(id)) + ' — ' + escapeHtml(log.date + (log.time ? ' ' + log.time : ''))
                    + ' / ' + escapeHtml(log.personnel_name)
                    + ' — ' + escapeHtml(log.incidents || 'No incident note') + '</div>';
            }).join('');

            document.getElementById('campaignTitle').value = '';
            document.getElementById('campaignRationale').value = '';
            document.querySelectorAll('.theme-check').forEach(function(cb) { cb.checked = true; });
            const select = document.getElementById('campaignBulletinSelect');
            if (select) {
                delete select.dataset.autoSelected;
            }
            loadBulletinOptions();
            document.getElementById('campaignForwardModal').style.display = 'block';
        }

        function closeCampaignForwardModal() {
            document.getElementById('campaignForwardModal').style.display = 'none';
            pendingCampaignIds = [];
        }

        async function confirmCampaignForward() {
            if (!pendingCampaignIds.length) {
                alert('No patrol reports selected.');
                return;
            }

            const themes = Array.from(document.querySelectorAll('.theme-check:checked')).map(function(cb) { return cb.value; });
            if (!themes.length) {
                alert('Select at least one theme (Youth, Sports, or Cultural).');
                return;
            }

            const btn = document.getElementById('btnConfirmCampaignForward');
            btn.disabled = true;
            btn.textContent = 'Sending...';

            try {
                const payload = {
                    patrol_log_ids: pendingCampaignIds,
                    themes: themes,
                    bulletin_post_id: Number(document.getElementById('campaignBulletinSelect').value || 0) || null,
                    title: document.getElementById('campaignTitle').value.trim(),
                    rationale: document.getElementById('campaignRationale').value.trim(),
                    priority: 'medium'
                };

                const res = await fetch('api/send_to_campaign.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await res.json();
                if (!result.success) {
                    alert(result.message || 'Failed to forward to Campaign.');
                    return;
                }

                const ref = result.data && result.data.campaign_reference_id ? ('\nReference: ' + result.data.campaign_reference_id) : '';
                alert((result.message || 'Forwarded to Campaign.') + ref);
                closeCampaignForwardModal();
                exitCampaignSelectMode();
                await loadPatrolLogs();
            } catch (e) {
                console.error(e);
                alert('Network error while forwarding to Campaign.');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Forward to Campaign';
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('viewLogModal');
            if (event.target === modal) {
                closeViewLogModal();
            }
            const campaignModal = document.getElementById('campaignForwardModal');
            if (event.target === campaignModal) {
                closeCampaignForwardModal();
            }
        }

        function logToExportSection(log) {
            return {
                fields: [
                    { label: 'Date', value: log.date },
                    { label: 'Time', value: log.time },
                    { label: 'Officer', value: log.personnel_name },
                    { label: 'Route', value: log.route },
                    { label: 'Location', value: log.location },
                    { label: 'Status', value: log.status },
                    { label: 'Incidents', value: log.incidents || 'None' }
                ],
                blocks: [
                    { label: 'Details', value: log.details || '' }
                ],
                images: [
                    { label: 'Documentation Photo', src: log.documentation_photo || '' }
                ]
            };
        }

        async function exportSelectedLogs() {
            if (!document.body.classList.contains('export-select-mode')) {
                enterLogExportSelectMode();
                return;
            }
            const ids = getSelectedLogIds();
            if (!ids.length) {
                alert('Please select at least one patrol log to export.');
                return;
            }
            const logs = ids.map(function(id) { return patrolLogData[id]; }).filter(Boolean);
            if (!logs.length) {
                alert('Selected logs could not be found. Please refresh and try again.');
                return;
            }
            if (!window.AlertaraReportExport) {
                alert('Export helper not loaded. Please refresh the page.');
                return;
            }
            try {
                const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
                const fileName = logs.length === 1
                    ? ('patrol_log_' + String(logs[0].personnel_name || 'officer').replace(/\s+/g, '_') + '_' + logs[0].date + '.docx')
                    : ('patrol_logs_' + logs.length + '_' + stamp + '.docx');
                await AlertaraReportExport.downloadReport({
                    title: logs.length > 1 ? 'PATROL LOG REPORTS' : 'PATROL LOG REPORT',
                    fileName: fileName,
                    sections: logs.map(logToExportSection)
                });
                exitLogExportSelectMode();
                alert('Patrol log export saved as ' + fileName + '!');
            } catch (error) {
                console.error('Error generating DOCX:', error);
                alert(error.message || 'Error generating DOCX file. Please try again.');
            }
        }
        
        // Date and Time Display
        function updateDateTime() {
            const now = new Date();
            const dateOptions = { 
                weekday: 'short', 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            };
            const timeOptions = { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            };
            
            const dateStr = now.toLocaleDateString('en-US', dateOptions);
            const timeStr = now.toLocaleTimeString('en-US', timeOptions);
            
            const dateEl = document.getElementById('currentDate');
            const timeEl = document.getElementById('currentTime');
            
            if (dateEl) dateEl.textContent = dateStr;
            if (timeEl) timeEl.textContent = timeStr;
        }
        
        // Update date/time immediately and then every second
        updateDateTime();
        setInterval(updateDateTime, 1000);
    </script>
    <?php require __DIR__ . '/includes/admin_notifications_script.php'; ?>
    <script src="js/mobile-shell.js"></script>
</body>
</html>


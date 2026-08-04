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
    <title>Review Tip - Alertara</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/admin-sidebar.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
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
        .tips-toolbar { display: flex; gap: 0.75rem; margin-bottom: 1.5rem; align-items: center; flex-wrap: wrap; }
        .search-box { flex: 1; min-width: 220px; position: relative; margin-bottom: 0; }
        .search-box input { width: 100%; padding: 0.75rem 1rem 0.75rem 2.5rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; }
        .search-box input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1); }
        .search-box::before { content: "🔍"; position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 1rem; }
        .btn-export-tips, .btn-cancel-export {
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            white-space: nowrap;
            flex-shrink: 0;
            transition: background 0.2s ease;
            color: #fff;
        }
        .btn-export-tips { background: var(--primary-color); }
        .btn-export-tips:hover:not(:disabled) { background: #4ca8a6; }
        .btn-export-tips:disabled { opacity: 0.55; cursor: not-allowed; }
        .btn-cancel-export { background: #6c757d; }
        .btn-cancel-export:hover { background: #5a6268; }
        .col-select { width: 44px; text-align: center; }
        .col-select input { width: 16px; height: 16px; cursor: pointer; accent-color: var(--primary-color); }
        body:not(.tip-export-select-mode) #tipsTable .col-select { display: none; }
        body:not(.tip-export-select-mode) .tip-export-select-only { display: none !important; }
        body.tip-export-select-mode .tip-export-enter-only { display: none !important; }
        body.tip-export-select-mode #tipsTable tbody tr:hover { background: #f8fafc; }
        .table-container { overflow-x: auto; border-radius: 8px; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; background: var(--card-bg); }
        thead { background: var(--tertiary-color); color: #fff; }
        th { padding: 1rem; text-align: left; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-color); }
        tbody tr:hover { background: #f9f9f9; }
        tbody tr:last-child td { border-bottom: none; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.85rem; font-weight: 500; display: inline-block; }
        .status-new { background: #e0f2fe; color: #075985; }
        .status-assigned { background: #fef3c7; color: #92400e; }
        .status-resolved { background: #d1e7dd; color: #0f5132; }
        .outcome-badge { padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.82rem; font-weight: 500; display: inline-block; }
        .outcome-none { background: #f3f4f6; color: #4b5563; }
        .outcome-investigating { background: #dbeafe; color: #1e40af; }
        .outcome-success { background: #d1fae5; color: #065f46; }
        .outcome-arrest { background: #fee2e2; color: #991b1b; }
        .outcome-unfounded { background: #ede9fe; color: #5b21b6; }
        .action-buttons { display: flex; gap: 0.5rem; }
        .btn-view, .btn-manage {
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .btn-view:hover { background: #4ca8a6; }
        .btn-manage { background: #ff9800; }
        .btn-manage:hover { background: #f57c00; }
        .manage-tip-ref { margin: 0 0 0.35rem; color: var(--tertiary-color); font-weight: 600; }
        .manage-tip-meta { margin: 0 0 1.25rem; color: var(--text-secondary); font-size: 0.92rem; line-height: 1.5; }
        .manage-tip-actions { display: flex; flex-direction: column; gap: 0.75rem; }
        .manage-tip-actions button {
            width: 100%;
            padding: 0.85rem 1rem;
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            transition: background 0.2s ease, transform 0.15s ease;
        }
        .manage-tip-actions button:hover { transform: translateY(-1px); }
        .manage-tip-actions button:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }
        .manage-btn-assign { background: var(--primary-color); }
        .manage-btn-assign:hover:not(:disabled) { background: #4ca8a6; }
        .manage-btn-incident { background: var(--secondary-color); }
        .manage-btn-incident:hover:not(:disabled) { background: #2d3f54; }
        .manage-btn-agency { background: var(--tertiary-color); }
        .manage-btn-agency:hover:not(:disabled) { background: #141c30; }
        @media (max-width: 768px) {
            .tips-toolbar { flex-direction: column; align-items: stretch; }
            .btn-export-tips, .btn-cancel-export { width: 100%; justify-content: center; }
        }
        .tip-photo-thumbnail { width: 60px; height: 60px; object-fit: cover; border-radius: 6px; cursor: pointer; border: 2px solid var(--border-color); transition: transform 0.2s ease, border-color 0.2s ease; }
        .tip-photo-thumbnail:hover { transform: scale(1.1); border-color: var(--primary-color); }
        .tip-photo-placeholder { width: 60px; height: 60px; background: #f0f0f0; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 0.75rem; border: 2px solid var(--border-color); }
        .tip-photo-full { max-width: 100%; max-height: 400px; border-radius: 8px; margin-top: 1rem; border: 1px solid var(--border-color); cursor: pointer; }
        .tip-photo-full:hover { opacity: 0.9; }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); overflow: auto; }
        .modal-content { background-color: var(--card-bg); margin: 5% auto; padding: 2rem; border: 1px solid var(--border-color); border-radius: 12px; width: 90%; max-width: 700px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid var(--border-color); }
        .modal-header h2 { margin: 0; color: var(--tertiary-color); font-size: 1.5rem; }
        .close { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; transition: color 0.2s ease; }
        .close:hover { color: var(--tertiary-color); }
        .tip-details { line-height: 1.8; }
        .tip-details p { margin-bottom: 1rem; }
        .tip-details strong { color: var(--tertiary-color); }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-color); font-weight: 500; font-size: 0.95rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.95rem; font-family: var(--font-family); transition: all 0.2s ease; box-sizing: border-box; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1); }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .form-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); }
        .btn-cancel { padding: 0.75rem 1.5rem; background: #e5e5e5; color: var(--text-color); border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; }
        .btn-cancel:hover { background: #d5d5d5; }
        .btn-save { padding: 0.75rem 1.5rem; background: var(--primary-color); color: #fff; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
        .btn-save:hover { background: #4ca8a6; }
        @media (max-width: 768px) { .sidebar { width: 320px; transform: translateX(-100%); transition: transform 0.3s ease; } .sidebar.mobile-open { transform: translateX(0); } .sidebar.collapsed { width: 80px; transform: translateX(0); } .main-wrapper { margin-left: 0; } body.sidebar-collapsed .main-wrapper { margin-left: 80px; } .modal-content { width: 95%; margin: 10% auto; padding: 1.5rem; } }
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
            <div class="nav-module active">
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="Anonymous Tip Line System">
                    <span class="nav-module-icon"><i class="fas fa-comments"></i></span>
                    <span class="nav-module-header-text">Anonymous Tip Line System</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="review-tip.php" class="nav-submodule active" data-tooltip="Review Tip">
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
                <h1 class="page-title">Review Tip</h1>
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
                <div class="tips-toolbar">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search tips by ID, timestamp, location, or description..." onkeyup="filterTips()">
                    </div>
                    <button type="button" class="btn-export-tips tip-export-enter-only" id="btnEnterTipExportSelect" onclick="enterTipExportSelectMode()">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                    <button type="button" class="btn-export-tips tip-export-select-only" id="btnExportSelectedTips" onclick="exportSelectedTips()" disabled>
                        <i class="fas fa-file-export"></i> Export Selected
                    </button>
                    <button type="button" class="btn-cancel-export tip-export-select-only" onclick="exitTipExportSelectMode()">Cancel</button>
                </div>
                <div class="table-container">
                    <table id="tipsTable">
                        <thead>
                            <tr>
                                <th class="col-select"><input type="checkbox" id="selectAllTips" title="Select all visible tips" onchange="toggleSelectAllTips(this)"></th>
                                <th>Tip ID</th>
                                <th>Timestamp</th>
                                <th>Location</th>
                                <th>Photo</th>
                                <th>Tip Description</th>
                                <th>Assigned To</th>
                                <th title="Set by the assigned BPSO patrol in their tip report">Outcome</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tipsTableBody">
                            <!-- Tips will be loaded from database via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- View Tip Modal (details only) -->
    <div id="viewTipModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Tip Details</h2>
                <span class="close" onclick="closeViewTipModal()">&times;</span>
            </div>
            <div id="viewTipContent" class="tip-details"></div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeViewTipModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Manage Tip Modal -->
    <div id="manageTipModal" class="modal">
        <div class="modal-content" style="max-width: 520px;">
            <div class="modal-header">
                <h2>Manage Tip</h2>
                <span class="close" onclick="closeManageTipModal()">&times;</span>
            </div>
            <p class="manage-tip-ref" id="manageTipRef"></p>
            <p class="manage-tip-meta" id="manageTipMeta"></p>
            <div class="manage-tip-actions">
                <button type="button" class="manage-btn-assign" id="manageAssignBtn" onclick="manageAssignPatrol()">
                    <i class="fas fa-user-check"></i> Assign Patrol
                </button>
                <button type="button" class="manage-btn-incident" id="manageIncidentBtn" onclick="manageSendIncident()">
                    <i class="fas fa-file-alt"></i> Send to Incident Logging
                </button>
                <button type="button" class="manage-btn-agency" id="manageAgencyBtn" onclick="manageSendAgency()">
                    <i class="fas fa-shield-alt"></i> Send to Inter-Agency
                </button>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeManageTipModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Assign Patrol Modal -->
    <div id="assignTipModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Assign Patrol</h2>
                <span class="close" onclick="closeAssignTipModal()">&times;</span>
            </div>
            <p id="assignTipSummary" style="margin:0 0 1rem 0;color:var(--text-secondary);line-height:1.5;"></p>
            <div class="form-group">
                <label for="assignTipPatrol">Available BPSO Personnel *</label>
                <select id="assignTipPatrol" required>
                    <option value="">Loading personnel...</option>
                </select>
                <p style="margin:0.5rem 0 0;font-size:0.85rem;color:var(--text-secondary);">Only Available personnel currently timed in at the barangay hall can be assigned.</p>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeAssignTipModal()">Cancel</button>
                <button type="button" class="btn-save" onclick="submitAssignTip()">
                    <i class="fas fa-user-check"></i> Assign Patrol
                </button>
            </div>
        </div>
    </div>

    <!-- Inter-Agency / Police Backup Modal -->
    <div id="agencyTipModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Send to Inter-Agency</h2>
                <span class="close" onclick="closeAgencyTipModal()">&times;</span>
            </div>
            <p style="margin:0 0 1rem 0;color:var(--text-secondary);line-height:1.5;">Request police backup assistance. BPSO remains responsible for the final tip report.</p>
            <div class="form-group">
                <label for="agencyBackupReason">Reason for police backup</label>
                <textarea id="agencyBackupReason" placeholder="Describe why police assistance is needed..."></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeAgencyTipModal()">Cancel</button>
                <button type="button" class="btn-save" onclick="submitAgencyTip()">
                    <i class="fas fa-shield-alt"></i> Request Backup
                </button>
            </div>
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
            initializeTipData();
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
        let tipExportSelectMode = false;

        function filterTips() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('tipsTableBody');
            const rows = table.getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent || row.innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                    const cb = row.querySelector('.tip-select');
                    if (cb) cb.checked = false;
                }
            }
            syncSelectAllTipsState();
            updateExportTipsButtonState();
        }

        function enterTipExportSelectMode() {
            tipExportSelectMode = true;
            document.body.classList.add('tip-export-select-mode');
            const master = document.getElementById('selectAllTips');
            if (master) {
                master.checked = false;
                master.indeterminate = false;
            }
            document.querySelectorAll('.tip-select').forEach(cb => { cb.checked = false; });
            updateExportTipsButtonState();
        }

        function exitTipExportSelectMode() {
            tipExportSelectMode = false;
            document.body.classList.remove('tip-export-select-mode');
            const master = document.getElementById('selectAllTips');
            if (master) {
                master.checked = false;
                master.indeterminate = false;
            }
            document.querySelectorAll('.tip-select').forEach(cb => { cb.checked = false; });
            updateExportTipsButtonState();
        }

        function getVisibleTipCheckboxes() {
            return Array.from(document.querySelectorAll('#tipsTableBody tr'))
                .filter(row => row.style.display !== 'none')
                .map(row => row.querySelector('.tip-select'))
                .filter(Boolean);
        }

        function toggleSelectAllTips(master) {
            getVisibleTipCheckboxes().forEach(cb => {
                cb.checked = !!master.checked;
            });
            updateExportTipsButtonState();
        }

        function syncSelectAllTipsState() {
            const master = document.getElementById('selectAllTips');
            if (!master) return;
            const boxes = getVisibleTipCheckboxes();
            if (!boxes.length) {
                master.checked = false;
                master.indeterminate = false;
                return;
            }
            const checkedCount = boxes.filter(cb => cb.checked).length;
            master.checked = checkedCount === boxes.length;
            master.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
        }

        function updateExportTipsButtonState() {
            const btn = document.getElementById('btnExportSelectedTips');
            if (!btn) return;
            const selected = document.querySelectorAll('#tipsTableBody .tip-select:checked').length;
            btn.disabled = !tipExportSelectMode || selected === 0;
            btn.innerHTML = selected > 0
                ? `<i class="fas fa-file-export"></i> Export Selected (${selected})`
                : `<i class="fas fa-file-export"></i> Export Selected`;
        }

        function getSelectedTipIds() {
            return Array.from(document.querySelectorAll('#tipsTableBody .tip-select:checked'))
                .map(cb => cb.value);
        }
        // Tip data storage (loaded from database)
        let tipData = {};
        
        // Load tips from database
        async function loadTips() {
            try {
                const response = await fetch('api/tips.php');
                const result = await response.json();
                
                if (!result.success) {
                    console.error(result.message || 'Failed to load tips');
                    return;
                }
                
                const tips = result.data || [];
                const tbody = document.getElementById('tipsTableBody');
                tbody.innerHTML = '';
                
                // Store tips by id for easy lookup
                tipData = {};
                tips.forEach(tip => {
                    tipData[tip.id] = tip;
                });
                
                // Populate table
                tips.forEach(tip => {
                    addTipTableRow(tip.id);
                });
                if (tipExportSelectMode) {
                    exitTipExportSelectMode();
                } else {
                    updateExportTipsButtonState();
                }
            } catch (e) {
                console.error('Error loading tips:', e);
            }
        }
        
        function initializeTipData() {
            loadTips();
        }
        
        function escapeHtml(text) {
            return String(text ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatTipTimestamp(value) {
            if (!value) return '';
            return new Date(value).toLocaleString('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            }).replace(',', '');
        }

        function displayTipOutcome(tip) {
            if (!tip) return 'No Outcome Yet';
            // Outcome is owned by the assigned patrol's report — not admin-editable.
            if (!tip.assigned_patrol_id) {
                return 'No Outcome Yet';
            }
            return tip.outcome || 'No Outcome Yet';
        }

        function getOutcomeBadgeClass(outcome) {
            switch (outcome) {
                case 'Under Investigation':
                    return 'outcome-investigating';
                case 'Investigation Successful':
                    return 'outcome-success';
                case 'Arrest Made':
                    return 'outcome-arrest';
                case 'Unfounded / No Action':
                    return 'outcome-unfounded';
                default:
                    return 'outcome-none';
            }
        }

        function addTipTableRow(id) {
            const tip = tipData[id];
            if (!tip) return;

            const tableBody = document.getElementById('tipsTableBody');
            const row = document.createElement('tr');
            row.setAttribute('data-tip-id', id);

            const outcomeText = displayTipOutcome(tip);
            const outcomeClass = getOutcomeBadgeClass(outcomeText);
            const timestamp = formatTipTimestamp(tip.submitted_at);
            const assignedTo = tip.assigned_to || '—';

            let photoCell = '<div class="tip-photo-placeholder">No Photo</div>';
            if (tip.photo_data) {
                const thumbnailId = 'tip-thumbnail-' + id;
                photoCell = `<img id="${thumbnailId}" src="${tip.photo_data}" alt="Tip Photo" class="tip-photo-thumbnail">`;
            }

            row.innerHTML = `
                <td class="col-select"><input type="checkbox" class="tip-select" value="${escapeHtml(String(id))}" onchange="onTipSelectChange()"></td>
                <td>${escapeHtml(tip.tip_id || '')}</td>
                <td>${escapeHtml(timestamp)}</td>
                <td>${escapeHtml(tip.location || '')}</td>
                <td>${photoCell}</td>
                <td>${escapeHtml(tip.description || '')}</td>
                <td>${escapeHtml(assignedTo)}</td>
                <td><span class="outcome-badge ${outcomeClass}">${escapeHtml(outcomeText)}</span></td>
                <td>
                    <div class="action-buttons">
                        <button type="button" class="btn-view" onclick="viewTip('${id}')">View</button>
                        <button type="button" class="btn-manage" onclick="openManageTipModal('${id}')">Manage</button>
                    </div>
                </td>
            `;

            tableBody.appendChild(row);

            if (tip.photo_data) {
                const thumbnailId = 'tip-thumbnail-' + id;
                setTimeout(() => {
                    const thumbnailElement = document.getElementById(thumbnailId);
                    if (thumbnailElement) {
                        thumbnailElement.addEventListener('click', function() {
                            viewPhotoFull(tip.photo_data);
                        });
                    }
                }, 100);
            }
        }

        let currentTipId = null;

        function viewTip(id) {
            const tip = tipData[id];
            if (!tip) {
                alert('Tip not found');
                return;
            }

            currentTipId = id;
            const timestamp = formatTipTimestamp(tip.submitted_at);

            let photoHtml = '';
            if (tip.photo_data) {
                const photoId = 'tip-photo-' + id;
                photoHtml = `<p><strong>Photo:</strong></p><img id="${photoId}" src="${tip.photo_data}" alt="Tip Photo" class="tip-photo-full" style="cursor: pointer;">`;
            }

            const reportHtml = tip.resolution_report
                ? `<p><strong>BPSO Report:</strong><br>${escapeHtml(tip.resolution_report)}</p>`
                : '';

            document.getElementById('viewTipContent').innerHTML = `
                <p><strong>Tip ID:</strong> ${escapeHtml(tip.tip_id || '')}</p>
                <p><strong>Timestamp:</strong> ${escapeHtml(timestamp)}</p>
                <p><strong>Location:</strong> ${escapeHtml(tip.location || '')}</p>
                <p><strong>Assigned To:</strong> ${escapeHtml(tip.assigned_to || 'Not assigned')}</p>
                <p><strong>Outcome:</strong> ${escapeHtml(displayTipOutcome(tip))} <span style="color:var(--text-secondary);font-size:0.85rem;">(from assigned patrol report)</span></p>
                <p><strong>Tip Description:</strong><br>${escapeHtml(tip.description || '')}</p>
                ${reportHtml}
                ${photoHtml}
            `;

            if (tip.photo_data) {
                const photoId = 'tip-photo-' + id;
                setTimeout(() => {
                    const photoElement = document.getElementById(photoId);
                    if (photoElement) {
                        photoElement.addEventListener('click', function() {
                            viewPhotoFull(tip.photo_data);
                        });
                    }
                }, 100);
            }

            document.getElementById('viewTipModal').style.display = 'block';
        }

        function closeViewTipModal() {
            document.getElementById('viewTipModal').style.display = 'none';
            currentTipId = null;
        }

        function openManageTipModal(id) {
            const tip = tipData[id];
            if (!tip) {
                alert('Tip not found');
                return;
            }
            currentTipId = id;
            document.getElementById('manageTipRef').textContent = tip.tip_id || ('Tip #' + id);
            document.getElementById('manageTipMeta').textContent =
                (tip.location || 'No location') +
                ' · Assigned: ' + (tip.assigned_to || 'Not assigned') +
                ' · Outcome: ' + displayTipOutcome(tip);

            const incidentBtn = document.getElementById('manageIncidentBtn');
            const agencyBtn = document.getElementById('manageAgencyBtn');
            const assignBtn = document.getElementById('manageAssignBtn');

            incidentBtn.disabled = !!tip.forwarded_at;
            incidentBtn.innerHTML = tip.forwarded_at
                ? '<i class="fas fa-check"></i> Already sent to Incident Logging'
                : '<i class="fas fa-file-alt"></i> Send to Incident Logging';

            agencyBtn.disabled = !!tip.backup_requested_at;
            agencyBtn.innerHTML = tip.backup_requested_at
                ? '<i class="fas fa-check"></i> Police backup already requested'
                : '<i class="fas fa-shield-alt"></i> Send to Inter-Agency';

            assignBtn.disabled = tip.status === 'Resolved';
            assignBtn.innerHTML = tip.status === 'Resolved'
                ? '<i class="fas fa-ban"></i> Tip already resolved'
                : '<i class="fas fa-user-check"></i> Assign Patrol';

            document.getElementById('manageTipModal').style.display = 'block';
        }

        function closeManageTipModal() {
            document.getElementById('manageTipModal').style.display = 'none';
        }

        function manageAssignPatrol() {
            if (!currentTipId) return;
            closeManageTipModal();
            openAssignTipModal(currentTipId);
        }

        function manageSendIncident() {
            if (!currentTipId) return;
            sendTipToIncident(currentTipId).then(() => {
                if (tipData[currentTipId]) openManageTipModal(currentTipId);
            });
        }

        function manageSendAgency() {
            if (!currentTipId) return;
            closeManageTipModal();
            openAgencyTipModal(currentTipId);
        }

        function onTipSelectChange() {
            syncSelectAllTipsState();
            updateExportTipsButtonState();
        }

        async function openAssignTipModal(id) {
            const tip = tipData[id];
            if (!tip) {
                alert('Tip not found');
                return;
            }
            if (tip.status === 'Resolved') {
                alert('This tip is already resolved. Clear or reassign is not available from here.');
                return;
            }

            currentTipId = id;
            document.getElementById('assignTipSummary').textContent =
                `${tip.tip_id || ''} — ${tip.location || ''}`;

            const select = document.getElementById('assignTipPatrol');
            select.innerHTML = '<option value="">Loading available personnel...</option>';
            document.getElementById('assignTipModal').style.display = 'block';

            try {
                const [patrolResponse, hallResponse] = await Promise.all([
                    fetch('api/patrols.php'),
                    fetch('api/bpso_attendance.php?view=at_hall')
                ]);
                const patrolResult = await patrolResponse.json();
                const hallResult = await hallResponse.json();
                const atHallIds = new Set(
                    (hallResult.success ? (hallResult.data || []) : []).map(row => String(row.patrol_id))
                );

                select.innerHTML = '<option value="">Select BPSO Personnel</option>';
                const officers = (patrolResult.success ? (patrolResult.data || []) : [])
                    .filter(officer => atHallIds.has(String(officer.id)));

                if (!officers.length) {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'No personnel at barangay hall';
                    option.disabled = true;
                    select.appendChild(option);
                    return;
                }

                officers.forEach(officer => {
                    const option = document.createElement('option');
                    option.value = officer.id;
                    const status = officer.status || 'Unknown';
                    option.textContent = `${officer.bpso_personnel_id} - ${officer.personnel_name} (${status})`;
                    if (status !== 'Available' && String(officer.id) !== String(tip.assigned_patrol_id || '')) {
                        option.disabled = true;
                    }
                    if (String(officer.id) === String(tip.assigned_patrol_id || '')) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            } catch (e) {
                console.error(e);
                select.innerHTML = '<option value="">Failed to load personnel</option>';
            }
        }

        function closeAssignTipModal() {
            document.getElementById('assignTipModal').style.display = 'none';
            currentTipId = null;
        }

        async function submitAssignTip() {
            if (!currentTipId) return;
            const patrolId = parseInt(document.getElementById('assignTipPatrol').value, 10) || 0;
            if (patrolId <= 0) {
                alert('Please select Available BPSO personnel.');
                return;
            }

            try {
                const response = await fetch('api/tips.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'assign',
                        id: parseInt(currentTipId, 10),
                        assigned_patrol_id: patrolId
                    })
                });
                const result = await response.json();
                if (!result.success) {
                    alert(result.message || 'Failed to assign patrol.');
                    return;
                }
                alert(result.message || 'Tip assigned successfully.');
                closeAssignTipModal();
                await loadTips();
            } catch (e) {
                console.error(e);
                alert('Failed to assign patrol. Please try again.');
            }
        }

        async function sendTipToIncident(id) {
            const tip = tipData[id];
            if (!tip) return;
            if (tip.forwarded_at) {
                alert('This tip was already sent to Incident Logging.');
                return;
            }
            if (!confirm(`Send tip ${tip.tip_id} to Incident Logging?`)) return;

            try {
                const response = await fetch('api/send_to_incident_reporting.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: tip.id, tip_id: tip.tip_id })
                });
                const result = await response.json();
                if (!result.success) {
                    alert(result.message || 'Failed to send tip.');
                    return;
                }
                tip.forwarded_at = result.data?.forwarded_at || new Date().toISOString();
                tip.blotter_reference_id = result.data?.blotter_reference_id || tip.blotter_reference_id;
                alert(result.message || 'Tip sent to Incident Logging.');
            } catch (e) {
                console.error(e);
                alert('Failed to send tip to Incident Logging.');
            }
        }

        function openAgencyTipModal(id) {
            const tip = tipData[id];
            if (!tip) return;
            if (tip.backup_requested_at) {
                alert('Police backup was already requested for this tip.');
                return;
            }
            currentTipId = id;
            document.getElementById('agencyBackupReason').value = tip.police_backup_reason || tip.description || '';
            document.getElementById('agencyTipModal').style.display = 'block';
        }

        function closeAgencyTipModal() {
            document.getElementById('agencyTipModal').style.display = 'none';
            currentTipId = null;
        }

        async function submitAgencyTip() {
            if (!currentTipId) return;
            const tip = tipData[currentTipId];
            if (!tip) return;

            const reason = document.getElementById('agencyBackupReason').value.trim();
            try {
                const response = await fetch('api/send_to_emergency_response.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: tip.id,
                        tip_id: tip.tip_id,
                        police_backup_reason: reason
                    })
                });
                const result = await response.json();
                if (!result.success) {
                    alert(result.message || 'Failed to request police backup.');
                    return;
                }
                tip.backup_requested_at = result.data?.backup_requested_at || new Date().toISOString();
                tip.police_backup_reason = reason;
                tip.emergency_response_reference_id = result.data?.emergency_response_reference_id || tip.emergency_response_reference_id;
                alert(result.message || 'Police backup requested. BPSO must still submit the final tip report.');
                closeAgencyTipModal();
            } catch (e) {
                console.error(e);
                alert('Failed to send tip to Inter-Agency.');
            }
        }

        function tipToExportPayload(tip) {
            return {
                tipId: tip.tip_id || '',
                timestamp: formatTipTimestamp(tip.submitted_at),
                location: tip.location || '',
                description: tip.description || '',
                assignedTo: tip.assigned_to || 'Not assigned',
                outcome: displayTipOutcome(tip),
                resolutionReport: tip.resolution_report || ''
            };
        }

        async function exportSelectedTips() {
            if (!tipExportSelectMode) {
                enterTipExportSelectMode();
                return;
            }
            const ids = getSelectedTipIds();
            if (!ids.length) {
                alert('Please select at least one tip to export.');
                return;
            }
            const tips = ids.map(id => tipData[id]).filter(Boolean);
            if (!tips.length) {
                alert('Selected tips could not be found. Please refresh and try again.');
                return;
            }
            try {
                await exportTipsToWord(tips.map(tipToExportPayload));
                exitTipExportSelectMode();
            } catch (e) {
                console.error(e);
                alert('Failed to export selected tips.');
            }
        }

        async function exportTipsToWord(tips) {
            if (typeof JSZip === 'undefined') {
                alert('Export library not loaded. Please refresh the page.');
                return;
            }
            if (!Array.isArray(tips) || !tips.length) return;

            const zip = new JSZip();
            const escapeXml = (value) => {
                if (!value) return '';
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&apos;');
            };

            const nl = String.fromCharCode(10);
            const fieldLine = (label, value) => (
                '        <w:p>' + nl +
                '            <w:r>' + nl +
                '                <w:rPr><w:b/></w:rPr>' + nl +
                '                <w:t>' + escapeXml(label) + ':</w:t>' + nl +
                '            </w:r>' + nl +
                '            <w:r>' + nl +
                '                <w:t> ' + escapeXml(value) + '</w:t>' + nl +
                '            </w:r>' + nl +
                '        </w:p>' + nl
            );

            const blockLine = (label, value) => (
                '        <w:p>' + nl +
                '            <w:pPr>' + nl +
                '                <w:spacing w:before="200"/>' + nl +
                '            </w:pPr>' + nl +
                '            <w:r>' + nl +
                '                <w:rPr><w:b/></w:rPr>' + nl +
                '                <w:t>' + escapeXml(label) + ':</w:t>' + nl +
                '            </w:r>' + nl +
                '        </w:p>' + nl +
                '        <w:p>' + nl +
                '            <w:r>' + nl +
                '                <w:t>' + escapeXml(value) + '</w:t>' + nl +
                '            </w:r>' + nl +
                '        </w:p>' + nl
            );

            let body = '';
            tips.forEach((tip, index) => {
                if (index > 0) {
                    body +=
                        '        <w:p>' + nl +
                        '            <w:pPr>' + nl +
                        '                <w:spacing w:before="400" w:after="200"/>' + nl +
                        '            </w:pPr>' + nl +
                        '            <w:r><w:t>---</w:t></w:r>' + nl +
                        '        </w:p>' + nl;
                }
                body += fieldLine('Tip ID', tip.tipId || '');
                body += fieldLine('Timestamp', tip.timestamp || '');
                body += fieldLine('Location', tip.location || '');
                body += fieldLine('Assigned To', tip.assignedTo || 'Not assigned');
                body += fieldLine('Outcome', tip.outcome || 'No Outcome Yet');
                body += blockLine('Tip Description', tip.description || '');
                if (tip.resolutionReport) {
                    body += blockLine('BPSO Report', tip.resolutionReport);
                }
            });

            const xmlDecl = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>' + nl;
            const contentTypes =
                xmlDecl +
                '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' + nl +
                '    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' + nl +
                '    <Default Extension="xml" ContentType="application/xml"/>' + nl +
                '    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>' + nl +
                '    <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>' + nl +
                '</Types>';

            const title = tips.length > 1 ? 'TIP REPORTS' : 'TIP REPORT';
            const documentXml =
                xmlDecl +
                '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' + nl +
                '    <w:body>' + nl +
                '        <w:p>' + nl +
                '            <w:pPr>' + nl +
                '                <w:jc w:val="center"/>' + nl +
                '                <w:spacing w:after="400"/>' + nl +
                '            </w:pPr>' + nl +
                '            <w:r>' + nl +
                '                <w:rPr>' + nl +
                '                    <w:b/>' + nl +
                '                    <w:sz w:val="32"/>' + nl +
                '                </w:rPr>' + nl +
                '                <w:t>' + title + '</w:t>' + nl +
                '            </w:r>' + nl +
                '        </w:p>' + nl +
                '        <w:p>' + nl +
                '            <w:pPr>' + nl +
                '                <w:jc w:val="center"/>' + nl +
                '                <w:spacing w:after="600"/>' + nl +
                '            </w:pPr>' + nl +
                '            <w:r>' + nl +
                '                <w:t>Barangay San Agustin, Quezon City</w:t>' + nl +
                '            </w:r>' + nl +
                '        </w:p>' + nl +
                body +
                '        <w:p>' + nl +
                '            <w:pPr>' + nl +
                '                <w:jc w:val="right"/>' + nl +
                '                <w:spacing w:before="600"/>' + nl +
                '            </w:pPr>' + nl +
                '            <w:r>' + nl +
                '                <w:t>Generated on: ' + escapeXml(new Date().toLocaleString()) + '</w:t>' + nl +
                '            </w:r>' + nl +
                '        </w:p>' + nl +
                '    </w:body>' + nl +
                '</w:document>';

            const stylesXml =
                xmlDecl +
                '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' + nl +
                '    <w:style w:type="paragraph" w:styleId="Normal">' + nl +
                '        <w:name w:val="Normal"/>' + nl +
                '        <w:qFormat/>' + nl +
                '    </w:style>' + nl +
                '</w:styles>';

            const rels =
                xmlDecl +
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' + nl +
                '    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>' + nl +
                '</Relationships>';

            const wordRels =
                xmlDecl +
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' + nl +
                '    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' + nl +
                '</Relationships>';

            zip.file('[Content_Types].xml', contentTypes);
            zip.file('word/document.xml', documentXml);
            zip.file('word/styles.xml', stylesXml);
            zip.file('_rels/.rels', rels);
            zip.file('word/_rels/document.xml.rels', wordRels);

            const blob = await zip.generateAsync({
                type: 'blob',
                mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            });
            const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
            const fileName = tips.length === 1
                ? ('tip_report_' + (tips[0].tipId || 'tip') + '_' + stamp + '.docx')
                : ('tip_reports_' + tips.length + '_' + stamp + '.docx');

            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);
        }


        window.onclick = function(event) {
            const viewModal = document.getElementById('viewTipModal');
            const manageModal = document.getElementById('manageTipModal');
            const assignModal = document.getElementById('assignTipModal');
            const agencyModal = document.getElementById('agencyTipModal');
            if (event.target === viewModal) closeViewTipModal();
            if (event.target === manageModal) closeManageTipModal();
            if (event.target === assignModal) closeAssignTipModal();
            if (event.target === agencyModal) closeAgencyTipModal();
        }

        function viewPhotoFull(photoSrc) {
            if (!photoSrc) {
                alert('No photo available');
                return;
            }
            
            const modal = document.createElement('div');
            modal.id = 'fullscreen-photo-modal';
            modal.style.cssText = 'position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.95); display: flex; align-items: center; justify-content: center; animation: fadeIn 0.3s ease;';
            
            const img = document.createElement('img');
            img.src = photoSrc;
            img.style.cssText = 'max-width: 95%; max-height: 95%; border-radius: 8px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5); object-fit: contain; cursor: zoom-out;';
            img.onclick = function(e) { 
                e.stopPropagation(); 
            };
            img.onerror = function() {
                alert('Failed to load photo');
                document.body.removeChild(modal);
                if (escHandler) document.removeEventListener('keydown', escHandler);
            };
            
            // Add close button
            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.cssText = 'position: absolute; top: 20px; right: 30px; background: rgba(255, 255, 255, 0.2); color: #fff; border: none; font-size: 40px; width: 50px; height: 50px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s ease; z-index: 3001;';
            closeBtn.onmouseover = function() { this.style.background = 'rgba(255, 255, 255, 0.3)'; };
            closeBtn.onmouseout = function() { this.style.background = 'rgba(255, 255, 255, 0.2)'; };
            
            // Add ESC key listener
            const escHandler = function(e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    const existingModal = document.getElementById('fullscreen-photo-modal');
                    if (existingModal) {
                        document.body.removeChild(existingModal);
                        document.removeEventListener('keydown', escHandler);
                    }
                }
            };
            
            const closeModal = function() {
                document.body.removeChild(modal);
                document.removeEventListener('keydown', escHandler);
            };
            
            modal.onclick = closeModal;
            closeBtn.onclick = function(e) {
                e.stopPropagation();
                closeModal();
            };
            document.addEventListener('keydown', escHandler);
            
            modal.appendChild(closeBtn);
            modal.appendChild(img);
            document.body.appendChild(modal);
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


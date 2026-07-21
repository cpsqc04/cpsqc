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
    <title>Patrol Schedule - Alertara</title>
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
        .search-container { display: flex; gap: 1rem; margin-bottom: 1.5rem; align-items: center; }
        .search-box { flex: 1; position: relative; }
        .search-box input { width: 100%; padding: 0.75rem 1rem 0.75rem 2.5rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.95rem; transition: all 0.2s ease; }
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
        .btn-view { padding: 0.5rem 1rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; }
        .btn-view:hover { background: #4ca8a6; }
        .btn-add { padding: 0.75rem 1.5rem; background: var(--primary-color); color: #fff; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 0.5rem; white-space: nowrap; flex-shrink: 0; }
        .btn-add:hover { background: #4ca8a6; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(76, 138, 137, 0.3); }
        .btn-add i { font-size: 1rem; }
        .btn-edit { padding: 0.5rem 1rem; background: #ffc107; color: #000; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; margin-right: 0.5rem; }
        .btn-edit:hover { background: #e0a800; }
        .btn-delete { padding: 0.5rem 1rem; background: #dc3545; color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; }
        .btn-delete:hover { background: #c82333; }
        .action-buttons { display: flex; gap: 0.5rem; align-items: center; }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); overflow: auto; }
        .modal-content { background-color: var(--card-bg); margin: 5% auto; padding: 2rem; border: 1px solid var(--border-color); border-radius: 12px; width: 90%; max-width: 600px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid var(--border-color); }
        .modal-header h2 { margin: 0; color: var(--tertiary-color); font-size: 1.5rem; }
        .close { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; transition: color 0.2s ease; }
        .close:hover { color: var(--tertiary-color); }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-color); font-weight: 500; font-size: 0.95rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.95rem; font-family: var(--font-family); transition: all 0.2s ease; box-sizing: border-box; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1); }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .form-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); }
        .btn-cancel { padding: 0.75rem 1.5rem; background: #6c757d; color: #fff; border: none; border-radius: 8px; font-size: 0.95rem; cursor: pointer; transition: all 0.2s ease; }
        .btn-cancel:hover { background: #5a6268; }
        .btn-save { padding: 0.75rem 1.5rem; background: var(--primary-color); color: #fff; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
        .btn-save:hover { background: #4ca8a6; }
        .status-in-progress { background: #cfe2ff; color: #084298; }
        .status-completed { background: #d1e7dd; color: #0f5132; }
        .risk-alerts-panel { margin-bottom: 1.5rem; padding: 1.25rem 1.5rem; border: 1px solid var(--border-color); border-radius: 12px; background: #fff; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04); }
        .risk-alerts-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .risk-alerts-header h3 { margin: 0 0 0.25rem 0; color: var(--tertiary-color); font-size: 1.15rem; }
        .risk-alerts-subtitle { color: var(--text-secondary); font-size: 0.9rem; }
        .risk-alerts-refresh { padding: 0.45rem 0.85rem; background: transparent; border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-color); cursor: pointer; font-size: 0.85rem; }
        .risk-alerts-refresh:hover { background: #f5f5f5; }
        .risk-alerts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }
        .risk-alert-card { border: 1px solid var(--border-color); border-radius: 10px; padding: 1rem; background: #fafafa; display: flex; flex-direction: column; gap: 0.65rem; }
        .risk-alert-card-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 0.75rem; }
        .risk-alert-title { font-weight: 600; color: var(--tertiary-color); font-size: 0.98rem; margin: 0; }
        .risk-alert-type { color: var(--text-secondary); font-size: 0.82rem; }
        .risk-alert-location { color: var(--text-color); font-size: 0.92rem; line-height: 1.45; }
        .risk-alert-condition { color: var(--text-secondary); font-size: 0.85rem; font-style: italic; }
        .risk-alert-meta { color: var(--text-secondary); font-size: 0.8rem; }
        .severity-badge { padding: 0.2rem 0.55rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.03em; white-space: nowrap; }
        .severity-critical { background: #fee2e2; color: #b91c1c; }
        .severity-high { background: #ffedd5; color: #c2410c; }
        .severity-medium { background: #fef3c7; color: #a16207; }
        .severity-low { background: #e5e7eb; color: #4b5563; }
        .btn-assign-hotspot { margin-top: auto; padding: 0.55rem 0.9rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
        .btn-assign-hotspot:hover { background: #4ca8a6; }
        .risk-alerts-empty { padding: 1.25rem; text-align: center; color: var(--text-secondary); background: #f9fafb; border-radius: 8px; border: 1px dashed var(--border-color); }
        @media (max-width: 768px) { .sidebar { width: 320px; transform: translateX(-100%); transition: transform 0.3s ease; } .sidebar.mobile-open { transform: translateX(0); } .sidebar.collapsed { width: 80px; transform: translateX(0); } .main-wrapper { margin-left: 0; } body.sidebar-collapsed .main-wrapper { margin-left: 80px; } .modal-content { width: 95%; margin: 10% auto; padding: 1.5rem; } .search-container { flex-direction: column; } .btn-add { width: 100%; justify-content: center; } }
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
            <!-- Dashboard Link -->
            <a href="index.php" class="nav-module-header" data-tooltip="Dashboard" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.5rem; color: rgba(255, 255, 255, 0.9); cursor: pointer; transition: background-color 0.2s ease; font-weight: 500; user-select: none; gap: 0.75rem; <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'background: rgba(76, 138, 137, 0.25); border-left: 3px solid #4c8a89;' : ''; ?>">
                <span class="nav-module-icon"><i class="fas fa-home"></i></span>
                <span class="nav-module-header-text">Dashboard</span>
            </a>
            
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
            <div class="nav-module active">
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="Patrol Scheduling and Monitoring">
                    <span class="nav-module-icon"><i class="fas fa-walking"></i></span>
                    <span class="nav-module-header-text">Patrol Scheduling and Monitoring</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <?php $patrolNavActive = 'patrol-schedule'; require __DIR__ . '/includes/patrol_nav_submodules.php'; ?>
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
                <h1 class="page-title">Patrol Schedule</h1>
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
                <div class="risk-alerts-panel" id="riskAlertsPanel">
                    <div class="risk-alerts-header">
                        <div>
                            <h3><i class="fas fa-exclamation-triangle" style="color:#dc2626;margin-right:0.4rem;"></i>High-Risk Areas</h3>
                        </div>
                        <button type="button" class="risk-alerts-refresh" onclick="loadRiskAlerts()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    <div id="riskAlertsList">
                        <div class="risk-alerts-empty">Loading high-risk alerts...</div>
                    </div>
                </div>
                <div class="search-container">
                    <div class="search-box">
<<<<<<< HEAD
                        <input type="text" id="searchInput" placeholder="Search by personnel, patrol zone, shift, status, or date..." onkeyup="filterPatrols()">
=======
                        <input type="text" id="searchInput" placeholder="Search by personnel, route, or status..." onkeyup="filterPatrols()">
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                    </div>
                    <button type="button" class="btn-add" onclick="openAssignPatrolModal()" style="white-space: nowrap;">
                        <i class="fas fa-plus"></i> Assign Patrol
                    </button>
                </div>
                <div class="table-container">
                    <table id="patrolsTable">
                        <thead>
                            <tr>
                                <th>BPSO Personnel</th>
<<<<<<< HEAD
                                <th>Shift</th>
                                <th>Patrol Zone</th>
                                <th>Date</th>
                                <th>Patrol Start</th>
                                <th>Patrol End</th>
                                <th>Duration</th>
=======
                                <th>Route</th>
                                <th>Date</th>
                                <th>Time</th>
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="patrolsTableBody">
<<<<<<< HEAD
                            <tr><td colspan="9" style="text-align:center;padding:2rem;color:#666;">Loading patrol schedules...</td></tr>
=======
                            <tr><td colspan="6" style="text-align:center;padding:2rem;color:#666;">Loading patrol schedules...</td></tr>
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Assign Patrol Modal -->
    <div id="assignPatrolModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2>Assign Patrol</h2>
                <span class="close" onclick="closeAssignPatrolModal()">&times;</span>
            </div>
            <form id="assignPatrolForm" onsubmit="savePatrolAssignment(event)">
                <div class="form-group">
                    <label for="patrolOfficer">BPSO Personnel *</label>
                    <select id="patrolOfficer" name="patrol_id" required>
                        <option value="">Select BPSO Personnel</option>
                    </select>
                    <small style="display:block;margin-top:0.35rem;color:var(--text-secondary);font-size:0.85rem;">Only personnel <strong>currently at the barangay hall</strong> (timed in today) are shown.</small>
                </div>
                <div class="form-group">
                    <label for="patrolDate">Date *</label>
                    <input type="date" id="patrolDate" name="date" required>
                </div>
                <div class="form-group">
<<<<<<< HEAD
                    <label for="patrolShift">Shift *</label>
                    <select id="patrolShift" name="shift" required>
                        <option value="">Select shift</option>
                        <option value="Day Shift">Day Shift (8:00 AM – 8:00 PM)</option>
                        <option value="Night Shift">Night Shift (8:00 PM – 8:00 AM)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="patrolZone">Patrol Zone *</label>
                    <input type="text" id="patrolZone" name="patrol_zone" required placeholder="e.g. Zone 1 - North or Heavenly Drive, Barangay San Agustin">
                </div>
                <div class="form-group">
                    <label for="patrolRoute">Route / Streets</label>
                    <input type="text" id="patrolRoute" name="route" placeholder="Optional route details">
=======
                    <label for="patrolTime">Time *</label>
                    <input type="time" id="patrolTime" name="time" required>
                </div>
                <div class="form-group">
                    <label for="patrolRoute">Route *</label>
                    <input type="text" id="patrolRoute" name="route" required placeholder="e.g. San Agustin Street to Quezon Avenue">
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                </div>
                <div class="form-group">
                    <label for="patrolNotes">Notes</label>
                    <textarea id="patrolNotes" name="notes" rows="3" placeholder="Optional instructions for the assigned personnel"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeAssignPatrolModal()">Cancel</button>
                    <button type="submit" class="btn-save">Assign Patrol</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Patrol Modal -->
    <div id="viewPatrolModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Patrol Details</h2>
                <span class="close" onclick="closeViewPatrolModal()">&times;</span>
            </div>
            <div id="viewPatrolContent">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeViewPatrolModal()">Close</button>
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
        function filterPatrols() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('patrolsTableBody');
            const rows = table.getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
<<<<<<< HEAD
                const searchText = row.getAttribute('data-search') || row.textContent || row.innerText;
                row.style.display = searchText.toLowerCase().indexOf(filter) > -1 ? '' : 'none';
            }
        }

        function formatScheduleTime(value) {
            if (!value) return '—';
            const normalized = String(value).length === 5 ? value + ':00' : String(value);
            const date = new Date('1970-01-01T' + normalized.replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        }
=======
                const text = row.textContent || row.innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
        // Patrol schedule data from database
        let patrolData = {};
        let riskAlertData = {};

        function severityBadgeClass(severity) {
            const level = String(severity || '').toUpperCase();
            if (level === 'CRITICAL') return 'severity-critical';
            if (level === 'HIGH') return 'severity-high';
            if (level === 'LOW') return 'severity-low';
            return 'severity-medium';
        }

        function formatAlertTime(value) {
            if (!value) return '—';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleString();
        }

        function buildHotspotNotes(alert) {
            const parts = [
                'Group 5 high-risk alert — recommended extra patrol.',
                alert.rule_name ? `Rule: ${alert.rule_name}` : '',
                alert.severity ? `Severity: ${alert.severity}` : '',
                alert.condition_text ? `Condition: ${alert.condition_text}` : '',
                alert.incident_count ? `Incidents: ${alert.incident_count}` : '',
                alert.time_window ? `Window: ${alert.time_window}` : '',
                alert.alert_id ? `Ref: ${alert.alert_id}` : ''
            ].filter(Boolean);
            return parts.join('\n');
        }

        async function loadRiskAlerts() {
            const listEl = document.getElementById('riskAlertsList');
            try {
                const response = await fetch('api/risk_alerts.php?status=active');
                const result = await response.json();

                if (!result.success) {
                    listEl.innerHTML = `<div class="risk-alerts-empty">${escapeHtml(result.message || 'Unable to load high-risk alerts.')}</div>`;
                    return;
                }

                riskAlertData = {};
                const rows = result.data || [];

                if (rows.length === 0) {
                    listEl.innerHTML = '<div class="risk-alerts-empty">No active high-risk alerts from Group 5 right now.</div>';
                    return;
                }

                listEl.innerHTML = `<div class="risk-alerts-grid">${rows.map(row => {
                    riskAlertData[row.alert_id] = row;
                    const severity = String(row.severity || 'MEDIUM').toUpperCase();
                    return `<div class="risk-alert-card">
                        <div class="risk-alert-card-top">
                            <div>
                                <p class="risk-alert-title">${escapeHtml(row.rule_name)}</p>
                                <div class="risk-alert-type">${escapeHtml(row.rule_type || 'Alert')}</div>
                            </div>
                            <span class="severity-badge ${severityBadgeClass(severity)}">${escapeHtml(severity)}</span>
                        </div>
                        <div class="risk-alert-location"><strong>Area:</strong> ${escapeHtml(row.area_name || row.location)}</div>
                        ${row.condition_text ? `<div class="risk-alert-condition">${escapeHtml(row.condition_text)}</div>` : ''}
                        <div class="risk-alert-meta">
                            ${row.incident_count ? `${escapeHtml(String(row.incident_count))} incident(s)` : ''}
                            ${row.incident_count && row.time_window ? ' · ' : ''}
                            ${row.time_window ? escapeHtml(row.time_window) : ''}
                            ${(row.incident_count || row.time_window) ? '<br>' : ''}
                            Triggered: ${escapeHtml(formatAlertTime(row.triggered_at))}
                        </div>
                        <button type="button" class="btn-assign-hotspot" data-alert-id="${escapeHtml(row.alert_id)}">
                            <i class="fas fa-walking"></i> Assign Patrol Here
                        </button>
                    </div>`;
                }).join('')}</div>`;

                listEl.querySelectorAll('.btn-assign-hotspot').forEach(btn => {
                    btn.addEventListener('click', () => assignPatrolFromHotspot(btn.dataset.alertId));
                });
            } catch (e) {
                console.error('Error loading risk alerts:', e);
                listEl.innerHTML = '<div class="risk-alerts-empty">Error loading high-risk alerts.</div>';
            }
        }

        async function assignPatrolFromHotspot(alertId) {
            const hotspot = riskAlertData[alertId];
            if (!hotspot) {
                window.alert('Hotspot data not found. Please refresh and try again.');
                return;
            }
            await openAssignPatrolModal({
                route: hotspot.route_suggestion || hotspot.area_name || hotspot.location || '',
                notes: buildHotspotNotes(hotspot)
            });
        }

        function statusClass(status) {
            if (status === 'Completed') return 'status-completed';
            if (status === 'In Progress') return 'status-in-progress';
            return 'status-scheduled';
        }

        async function loadPatrolSchedules() {
            const tableBody = document.getElementById('patrolsTableBody');
            try {
                const response = await fetch('api/patrol_schedules.php');
                const result = await response.json();

                if (!result.success) {
<<<<<<< HEAD
                    tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:2rem;color:#666;">Failed to load patrol schedules.</td></tr>';
=======
                    tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:#666;">Failed to load patrol schedules.</td></tr>';
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                    return;
                }

                patrolData = {};
                const rows = result.data || [];

                if (rows.length === 0) {
<<<<<<< HEAD
                    tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:2rem;color:#666;">No patrol assignments yet. Click "Assign Patrol" to create one.</td></tr>';
=======
                    tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:#666;">No patrol assignments yet. Click "Assign Patrol" to create one.</td></tr>';
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                    return;
                }

                tableBody.innerHTML = rows.map(row => {
                    patrolData[row.id] = row;
<<<<<<< HEAD
                    const zone = row.patrol_zone || row.location || row.route || '—';
                    const startDisplay = row.patrol_start_display || formatScheduleTime(row.patrol_start || row.schedule_time) || (row.status === 'Scheduled' ? 'Pending' : '—');
                    const endDisplay = row.patrol_end_display || formatScheduleTime(row.patrol_end) || (row.status === 'Scheduled' ? 'Pending' : (row.status === 'In Progress' ? 'In progress' : '—'));
                    const durationLabel = row.duration_label || (row.status === 'In Progress' ? 'In progress' : '—');
                    const searchText = [row.personnel_name, row.shift, zone, row.schedule_date, row.status, startDisplay, endDisplay, durationLabel].join(' ').toLowerCase();
                    return `<tr data-schedule-id="${row.id}" data-search="${escapeHtml(searchText)}">
                        <td>${escapeHtml(row.personnel_name)}</td>
                        <td>${escapeHtml(row.shift || '—')}</td>
                        <td>${escapeHtml(zone)}</td>
                        <td>${escapeHtml(row.schedule_date)}</td>
                        <td>${escapeHtml(startDisplay)}</td>
                        <td>${escapeHtml(endDisplay)}</td>
                        <td>${escapeHtml(durationLabel)}</td>
=======
                    return `<tr data-schedule-id="${row.id}">
                        <td>${escapeHtml(row.personnel_name)}</td>
                        <td>${escapeHtml(row.route)}</td>
                        <td>${escapeHtml(row.schedule_date)}</td>
                        <td>${escapeHtml(row.schedule_time)}</td>
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                        <td><span class="status-badge ${statusClass(row.status)}">${escapeHtml(row.status)}</span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-view" onclick="viewPatrol('${row.id}')">View</button>
                            </div>
                        </td>
                    </tr>`;
                }).join('');
            } catch (e) {
                console.error('Error loading patrol schedules:', e);
<<<<<<< HEAD
                tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:2rem;color:#666;">Error loading patrol schedules.</td></tr>';
=======
                tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:#666;">Error loading patrol schedules.</td></tr>';
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        async function openAssignPatrolModal(prefill = null) {
            const patrolOfficerSelect = document.getElementById('patrolOfficer');
            patrolOfficerSelect.innerHTML = '<option value="">Select BPSO Personnel</option>';

            const today = new Date().toISOString().split('T')[0];
            document.getElementById('patrolDate').value = today;
            document.getElementById('patrolRoute').value = '';
<<<<<<< HEAD
            document.getElementById('patrolZone').value = '';
            document.getElementById('patrolShift').value = '';
            document.getElementById('patrolNotes').value = '';

            if (prefill) {
                document.getElementById('patrolZone').value = prefill.route || '';
=======
            document.getElementById('patrolNotes').value = '';

            if (prefill) {
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                document.getElementById('patrolRoute').value = prefill.route || '';
                document.getElementById('patrolNotes').value = prefill.notes || '';
            }

            try {
                const [patrolResponse, hallResponse] = await Promise.all([
                    fetch('api/patrols.php'),
                    fetch('api/bpso_attendance.php?view=at_hall')
                ]);
                const patrolResult = await patrolResponse.json();
                const hallResult = await hallResponse.json();

                const atHallIds = new Set(
                    (hallResult.success ? (hallResult.data || []) : [])
                        .map(row => String(row.patrol_id))
                );

                if (patrolResult.success && patrolResult.data) {
                    const atHallPersonnel = patrolResult.data.filter(officer => atHallIds.has(String(officer.id)));

                    if (atHallPersonnel.length === 0) {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'No personnel at barangay hall';
                        option.disabled = true;
                        patrolOfficerSelect.appendChild(option);
                    } else {
                        atHallPersonnel.forEach(officer => {
                            const option = document.createElement('option');
                            option.value = officer.id;
                            option.textContent = `${officer.bpso_personnel_id} - ${officer.personnel_name} (At Hall)`;
                            patrolOfficerSelect.appendChild(option);
                        });
                    }
                }
            } catch (e) {
                console.error('Error loading BPSO personnel:', e);
            }

            document.getElementById('assignPatrolModal').style.display = 'block';
        }

        async function assignPatrolToDispatch(id) {
            await openAssignPatrolModal();
        }

        function closeAssignPatrolModal() {
            document.getElementById('assignPatrolModal').style.display = 'none';
            document.getElementById('assignPatrolForm').reset();
        }

        function openViewPatrolModal(id) {
            const schedule = patrolData[id];
            if (!schedule) return;
            
<<<<<<< HEAD
            const zone = schedule.patrol_zone || schedule.location || schedule.route || '—';
            const content = `
                <div style="line-height: 1.8;">
                    <p><strong>BPSO Personnel:</strong> ${escapeHtml(schedule.personnel_name)}</p>
                    <p><strong>Shift:</strong> ${escapeHtml(schedule.shift || '—')}</p>
                    <p><strong>Patrol Zone:</strong> ${escapeHtml(zone)}</p>
                    <p><strong>Date:</strong> ${escapeHtml(schedule.schedule_date)}</p>
                    <p><strong>Patrol Start:</strong> ${escapeHtml(schedule.patrol_start_display || formatScheduleTime(schedule.patrol_start || schedule.schedule_time) || 'Pending')}</p>
                    <p><strong>Patrol End:</strong> ${escapeHtml(schedule.patrol_end_display || formatScheduleTime(schedule.patrol_end) || '—')}</p>
                    <p><strong>Duration:</strong> ${escapeHtml(schedule.duration_label || '—')}</p>
=======
            const content = `
                <div style="line-height: 1.8;">
                    <p><strong>BPSO Personnel:</strong> ${escapeHtml(schedule.personnel_name)}</p>
                    <p><strong>Route:</strong> ${escapeHtml(schedule.route)}</p>
                    <p><strong>Date:</strong> ${escapeHtml(schedule.schedule_date)}</p>
                    <p><strong>Time:</strong> ${escapeHtml(schedule.schedule_time)}</p>
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                    <p><strong>Status:</strong> <span class="status-badge ${statusClass(schedule.status)}">${escapeHtml(schedule.status)}</span></p>
                    <p><strong>Notes:</strong> ${escapeHtml(schedule.notes || '—')}</p>
                </div>
            `;
            
            document.getElementById('viewPatrolContent').innerHTML = content;
            document.getElementById('viewPatrolModal').style.display = 'block';
        }

        function closeViewPatrolModal() {
            document.getElementById('viewPatrolModal').style.display = 'none';
        }

        function viewPatrol(id) {
            openViewPatrolModal(id);
        }

        async function savePatrolAssignment(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const patrolId = parseInt(formData.get('patrol_id'), 10);

            if (!patrolId) {
                alert('Please select BPSO personnel.');
                return;
            }

            const assignmentData = {
                action: 'create',
                patrol_id: patrolId,
                schedule_date: formData.get('date'),
<<<<<<< HEAD
                shift: formData.get('shift'),
                patrol_zone: formData.get('patrol_zone'),
                route: formData.get('route') || formData.get('patrol_zone'),
                location: formData.get('patrol_zone'),
=======
                schedule_time: formData.get('time'),
                route: formData.get('route'),
                location: '',
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                notes: formData.get('notes') || ''
            };

            try {
                const response = await fetch('api/patrol_schedules.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(assignmentData)
                });
                const data = await response.json();
                if (data.success) {
                    alert('Patrol assignment created successfully. The assigned personnel can view it in the BPSO portal.');
                    closeAssignPatrolModal();
                    await loadPatrolSchedules();
                } else {
                    alert('Error creating assignment: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to create patrol assignment.');
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const assignModal = document.getElementById('assignPatrolModal');
            const viewModal = document.getElementById('viewPatrolModal');
            
            if (event.target === assignModal) {
                closeAssignPatrolModal();
            }
            if (event.target === viewModal) {
                closeViewPatrolModal();
            }
        }

        // Initialize data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadRiskAlerts();
            loadPatrolSchedules();
        });
        
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


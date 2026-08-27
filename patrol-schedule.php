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
    <title>Assign Patrol Schedule - Alertara</title>
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
        .btn-edit { padding: 0.5rem 1rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; margin-right: 0.5rem; }
        .btn-edit:hover { background: #4ca8a6; }
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
        .schedule-toolbar { display: flex; gap: 0.75rem; margin-bottom: 1.5rem; align-items: center; flex-wrap: wrap; }
        .schedule-toolbar .search-box { flex: 1; min-width: 220px; }
        .btn-high-risk {
            padding: 0.75rem 1.15rem;
            background: #fff;
            color: #b91c1c;
            border: 1px solid #fecaca;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .btn-high-risk:hover { background: #fef2f2; }
        .btn-high-risk .count-badge {
            background: #dc2626;
            color: #fff;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            min-width: 1.4rem;
            padding: 0.1rem 0.45rem;
            text-align: center;
            line-height: 1.2;
        }
        .btn-back-schedule {
            width: 2.5rem;
            height: 2.5rem;
            padding: 0;
            background: #fff;
            color: var(--primary-color);
            border: 1.5px solid var(--primary-color);
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 1px 2px rgba(76, 138, 137, 0.12);
            transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
        }
        .btn-back-schedule i { font-size: 0.95rem; }
        .btn-back-schedule:hover {
            background: var(--primary-color);
            color: #fff;
            box-shadow: 0 4px 10px rgba(76, 138, 137, 0.28);
            transform: translateY(-1px);
        }
        .btn-back-schedule:active { transform: translateY(0); }
        .section-heading { margin: 0 0 1rem 0; color: var(--tertiary-color); font-size: 1.25rem; }
        .filter-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; flex-wrap: wrap; align-items: center; }
        .filter-tab {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 999px;
            background: #fff;
            color: var(--text-color);
            font: inherit;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .filter-tab.active { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
        .risk-panel-actions { margin-left: auto; display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .risk-alerts-refresh {
            padding: 0.5rem 0.9rem;
            background: transparent;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-color);
            cursor: pointer;
            font-size: 0.85rem;
        }
        .risk-alerts-refresh:hover { background: #f5f5f5; }
        .risk-alerts-empty {
            padding: 1.25rem;
            text-align: center;
            color: var(--text-secondary);
            background: #f9fafb;
            border-radius: 8px;
            border: 1px dashed var(--border-color);
        }
        .severity-badge { padding: 0.2rem 0.55rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.03em; white-space: nowrap; display: inline-block; }
        .severity-critical { background: #fee2e2; color: #b91c1c; }
        .severity-high { background: #ffedd5; color: #c2410c; }
        .severity-medium { background: #fef3c7; color: #a16207; }
        .severity-low { background: #e5e7eb; color: #4b5563; }
        .btn-assign-hotspot {
            padding: 0.45rem 0.8rem;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-assign-hotspot:hover { background: #4ca8a6; }
        .risk-condition { color: var(--text-secondary); font-size: 0.85rem; font-style: italic; }
        @media (max-width: 768px) { .sidebar { width: 320px; transform: translateX(-100%); transition: transform 0.3s ease; } .sidebar.mobile-open { transform: translateX(0); } .sidebar.collapsed { width: 80px; transform: translateX(0); } .main-wrapper { margin-left: 0; } body.sidebar-collapsed .main-wrapper { margin-left: 80px; } .modal-content { width: 95%; margin: 10% auto; padding: 1.5rem; } .schedule-toolbar { flex-direction: column; align-items: stretch; } .btn-add, .btn-high-risk { width: 100%; justify-content: center; } .risk-panel-actions { margin-left: 0; width: 100%; } }
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
                <h1 class="page-title">Assign Patrol Schedule</h1>
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
                <!-- Default: Patrol Schedule -->
                <div id="scheduleSection">
                    <div class="schedule-toolbar">
                        <div class="search-box">
                            <input type="text" id="searchInput" placeholder="Search by patrol, patrol zone, shift, status, or date..." onkeyup="filterPatrols()">
                        </div>
                        <button type="button" class="btn-high-risk" id="btnOpenHighRisk" onclick="showHighRiskSection()">
                            <i class="fas fa-exclamation-triangle"></i>
                            High-Risk Areas
                            <span class="count-badge" id="highRiskCountBadge" hidden>0</span>
                        </button>
                        <button type="button" class="btn-add" onclick="openAssignPatrolModal()" style="white-space: nowrap;">
                            <i class="fas fa-plus"></i> Assign Patrol
                        </button>
                    </div>
                    <div class="table-container">
                        <table id="patrolsTable">
                            <thead>
                                <tr>
                                    <th>Patrol</th>
                                    <th>Shift</th>
                                    <th>Patrol Zone</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="patrolsTableBody">
                                <tr><td colspan="6" style="text-align:center;padding:2rem;color:#666;">Loading patrol schedules...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-pagination">
                        <div class="page-info" id="schedulesPageInfo">Page 1 of 1</div>
                        <div class="page-buttons">
                            <button type="button" id="schedulesPrevBtn" onclick="changeSchedulesPage(-1)" disabled>Previous</button>
                            <button type="button" id="schedulesNextBtn" onclick="changeSchedulesPage(1)" disabled>Next</button>
                        </div>
                    </div>
                </div>

                <!-- High-Risk Areas table view (My Schedule style) -->
                <div id="highRiskSection" hidden>
                    <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-bottom:0.75rem;">
                        <button type="button" class="btn-back-schedule" onclick="showScheduleSection()" title="Back to Schedule" aria-label="Back to Schedule">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <h2 class="section-heading" style="margin:0;">High-Risk Areas</h2>
                    </div>
                    <div class="filter-tabs" id="riskFilterTabs">
                        <button type="button" class="filter-tab active" data-filter="all" onclick="setRiskFilter('all', this)">All</button>
                        <button type="button" class="filter-tab" data-filter="critical" onclick="setRiskFilter('critical', this)">Critical</button>
                        <button type="button" class="filter-tab" data-filter="high" onclick="setRiskFilter('high', this)">High</button>
                        <button type="button" class="filter-tab" data-filter="medium" onclick="setRiskFilter('medium', this)">Medium</button>
                        <div class="risk-panel-actions">
                            <button type="button" class="risk-alerts-refresh" onclick="loadRiskAlerts()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div id="riskAlertsEmpty" class="risk-alerts-empty" hidden>Loading high-risk alerts...</div>
                    <div class="table-container" id="riskAlertsTableWrap">
                        <table id="riskAlertsTable">
                            <thead>
                                <tr>
                                    <th>Severity</th>
                                    <th>Area</th>
                                    <th>Alert</th>
                                    <th>Type</th>
                                    <th>Incidents</th>
                                    <th>Triggered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="riskAlertsTableBody">
                                <tr><td colspan="7" style="text-align:center;padding:2rem;color:#666;">Loading high-risk alerts...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-pagination" id="riskAlertsPagination">
                        <div class="page-info" id="riskAlertsPageInfo">Page 1 of 1</div>
                        <div class="page-buttons">
                            <button type="button" id="riskAlertsPrevBtn" onclick="changeRiskAlertsPage(-1)" disabled>Previous</button>
                            <button type="button" id="riskAlertsNextBtn" onclick="changeRiskAlertsPage(1)" disabled>Next</button>
                        </div>
                    </div>
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
                <input type="hidden" id="linkedPatrolRequestId" name="linked_request_id" value="">
                <input type="hidden" id="linkedPatrolRequestCode" name="linked_request_code" value="">
                <input type="hidden" id="assignSlotsRemaining" value="">
                <div class="form-group">
                    <label for="patrolOfficer">Patrol *</label>
                    <select id="patrolOfficer" name="patrol_id" required onchange="onPatrolOfficerSelectionChange()">
                        <option value="">Select Patrol</option>
                    </select>
                </div>
                <div class="form-group" id="patrolShiftGroup">
                    <label for="patrolShift">Shift *</label>
                    <select id="patrolShift" name="shift" required>
                        <option value="">Select shift</option>
                        <option value="Day Shift">Day Shift (8:00 AM – 8:00 PM)</option>
                        <option value="Night Shift">Night Shift (8:00 PM – 8:00 AM)</option>
                    </select>
                    <small id="patrolShiftHint" style="display:block;margin-top:0.35rem;color:var(--text-secondary);font-size:0.85rem;">Filled from the selected personnel's fixed duty shift.</small>
                </div>
                <div class="form-group">
                    <label for="patrolDate">Date *</label>
                    <input type="date" id="patrolDate" name="date" required>
                </div>
                <div class="form-group">
                    <label for="patrolZone">Patrol Zone *</label>
                    <select id="patrolZone" name="patrol_zone" required>
                        <option value="">Select patrol zone</option>
                    </select>
                    <small style="display:block;margin-top:0.35rem;color:var(--text-secondary);font-size:0.85rem;">Barangay San Agustin zones — pick a zone, then a street/route.</small>
                </div>
                <div class="form-group">
                    <label for="patrolRoute">Route / Streets *</label>
                    <select id="patrolRoute" name="route" required disabled>
                        <option value="">Select zone first</option>
                    </select>
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


    <script src="js/san-agustin-patrol-zones.js"></script>
    <script src="js/table-pagination.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
            initPatrolZoneDropdowns();
        });

        function getPatrolZoneMap() {
            return window.SAN_AGUSTIN_PATROL_ZONES || {};
        }

        function initPatrolZoneDropdowns() {
            const zoneSelect = document.getElementById('patrolZone');
            const routeSelect = document.getElementById('patrolRoute');
            if (!zoneSelect || !routeSelect) return;

            const zones = getPatrolZoneMap();
            zoneSelect.innerHTML = '<option value="">Select patrol zone</option>';
            Object.keys(zones).forEach(zoneName => {
                const option = document.createElement('option');
                option.value = zoneName;
                option.textContent = zoneName;
                zoneSelect.appendChild(option);
            });

            routeSelect.innerHTML = '<option value="">Select zone first</option>';
            routeSelect.disabled = true;

            zoneSelect.addEventListener('change', function() {
                populatePatrolRouteOptions(this.value);
            });
        }

        function populatePatrolRouteOptions(zoneName, selectedRoute = '') {
            const routeSelect = document.getElementById('patrolRoute');
            if (!routeSelect) return;

            const streets = getPatrolZoneMap()[zoneName] || [];
            routeSelect.innerHTML = '';

            if (!zoneName || streets.length === 0) {
                routeSelect.innerHTML = '<option value="">Select zone first</option>';
                routeSelect.disabled = true;
                return;
            }

            routeSelect.disabled = false;
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Select street / route';
            routeSelect.appendChild(placeholder);

            streets.forEach(street => {
                const option = document.createElement('option');
                option.value = street;
                option.textContent = street;
                routeSelect.appendChild(option);
            });

            if (selectedRoute) {
                ensureSelectOption(routeSelect, selectedRoute);
                routeSelect.value = selectedRoute;
            }
        }

        function ensureSelectOption(selectEl, value) {
            if (!selectEl || !value) return;
            const exists = Array.from(selectEl.options).some(opt => opt.value === value);
            if (!exists) {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                selectEl.appendChild(option);
            }
        }

        function setPatrolZoneAndRoute(zoneValue, routeValue) {
            const zoneSelect = document.getElementById('patrolZone');
            const routeSelect = document.getElementById('patrolRoute');
            if (!zoneSelect || !routeSelect) return;

            const zones = getPatrolZoneMap();
            let zone = (zoneValue || '').trim();
            let route = (routeValue || '').trim();

            // High-risk / request prefills may send a street as "zone".
            if (zone && !zones[zone]) {
                const matchedZone = Object.keys(zones).find(name => (zones[name] || []).includes(zone));
                if (matchedZone) {
                    if (!route) route = zone;
                    zone = matchedZone;
                } else if (route && zones[route]) {
                    zone = route;
                    route = '';
                } else {
                    ensureSelectOption(zoneSelect, zone);
                }
            }

            if (zone && zones[zone]) {
                zoneSelect.value = zone;
                populatePatrolRouteOptions(zone, route);
            } else if (zone) {
                zoneSelect.value = zone;
                populatePatrolRouteOptions('', '');
                routeSelect.disabled = false;
                routeSelect.innerHTML = '<option value="">Select street / route</option>';
                if (route) {
                    ensureSelectOption(routeSelect, route);
                    routeSelect.value = route;
                }
            } else {
                zoneSelect.value = '';
                populatePatrolRouteOptions('', '');
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
        function filterPatrols() {
            schedulesPager.reset();
            renderSchedulesTable();
        }

        function changeSchedulesPage(delta) {
            schedulesPager.change(delta, getFilteredSchedules().length);
            renderSchedulesTable();
        }

        function getFilteredSchedules() {
            const input = document.getElementById('searchInput');
            const filter = (input && input.value ? input.value : '').toLowerCase().trim();
            if (!filter) return allSchedules.slice();
            return allSchedules.filter(function(row) {
                const zone = row.patrol_zone || row.location || row.route || '';
                const shiftLabel = formatShiftWithHours(row.shift);
                const haystack = [row.personnel_name, shiftLabel, row.shift, zone, row.schedule_date, row.status]
                    .join(' ').toLowerCase();
                return haystack.indexOf(filter) > -1;
            });
        }

        function renderSchedulesTable() {
            const tableBody = document.getElementById('patrolsTableBody');
            const filtered = getFilteredSchedules();
            const pageRows = schedulesPager.slice(filtered);

            if (filtered.length === 0) {
                const emptyMsg = allSchedules.length === 0
                    ? 'No patrol assignments yet. Click "Assign Patrol" to create one.'
                    : 'No patrol schedules match your search.';
                tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:#666;">' + emptyMsg + '</td></tr>';
                return;
            }

            tableBody.innerHTML = pageRows.map(function(row) {
                const zone = row.patrol_zone || row.location || row.route || '—';
                const shiftLabel = formatShiftWithHours(row.shift);
                const searchText = [row.personnel_name, shiftLabel, row.shift, zone, row.schedule_date, row.status].join(' ').toLowerCase();
                return `<tr data-schedule-id="${row.id}" data-search="${escapeHtml(searchText)}">
                    <td>${escapeHtml(row.personnel_name)}</td>
                    <td>${escapeHtml(shiftLabel)}</td>
                    <td>${escapeHtml(zone)}</td>
                    <td>${escapeHtml(row.schedule_date)}</td>
                    <td><span class="status-badge ${statusClass(row.status)}">${escapeHtml(row.status)}</span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-view" onclick="viewPatrol('${row.id}')">View</button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
        }

        function formatScheduleTime(value) {
            if (!value) return '—';
            const normalized = String(value).length === 5 ? value + ':00' : String(value);
            const date = new Date('1970-01-01T' + normalized.replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        }
        // Patrol schedule data from database
        let patrolData = {};
        let allSchedules = [];
        let riskAlertData = {};
        let riskAlertRows = [];
        let riskFilter = 'all';
        const schedulesPager = AlertaraTablePager.create({
            pageSize: 10,
            pageInfoId: 'schedulesPageInfo',
            prevBtnId: 'schedulesPrevBtn',
            nextBtnId: 'schedulesNextBtn',
            itemLabel: 'schedules'
        });
        const riskAlertsPager = AlertaraTablePager.create({
            pageSize: 10,
            pageInfoId: 'riskAlertsPageInfo',
            prevBtnId: 'riskAlertsPrevBtn',
            nextBtnId: 'riskAlertsNextBtn',
            itemLabel: 'alerts'
        });

        function showScheduleSection() {
            document.getElementById('scheduleSection').hidden = false;
            document.getElementById('highRiskSection').hidden = true;
        }

        function showHighRiskSection() {
            document.getElementById('scheduleSection').hidden = true;
            document.getElementById('highRiskSection').hidden = false;
            renderRiskAlertsTable();
        }

        function setRiskFilter(filter, btn) {
            riskFilter = filter || 'all';
            document.querySelectorAll('#riskFilterTabs .filter-tab').forEach(tab => {
                tab.classList.toggle('active', tab === btn);
            });
            riskAlertsPager.reset();
            renderRiskAlertsTable();
        }

        function updateHighRiskBadge(count) {
            const badge = document.getElementById('highRiskCountBadge');
            if (!badge) return;
            if (count > 0) {
                badge.textContent = String(count);
                badge.hidden = false;
            } else {
                badge.hidden = true;
            }
        }

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
                'Crime Analytics high-risk alert — recommended extra patrol.',
                alert.rule_name ? `Rule: ${alert.rule_name}` : '',
                alert.severity ? `Severity: ${alert.severity}` : '',
                alert.condition_text ? `Condition: ${alert.condition_text}` : '',
                alert.incident_count ? `Incidents: ${alert.incident_count}` : '',
                alert.time_window ? `Window: ${alert.time_window}` : '',
                alert.alert_id ? `Ref: ${alert.alert_id}` : ''
            ].filter(Boolean);
            return parts.join('\n');
        }

        function setRiskAlertsEmpty(message, showEmpty) {
            const emptyEl = document.getElementById('riskAlertsEmpty');
            const tableWrap = document.getElementById('riskAlertsTableWrap');
            const pagination = document.getElementById('riskAlertsPagination');
            const tbody = document.getElementById('riskAlertsTableBody');
            if (showEmpty) {
                emptyEl.textContent = message;
                emptyEl.hidden = false;
                tableWrap.hidden = true;
                if (pagination) pagination.hidden = true;
                tbody.innerHTML = '';
            } else {
                emptyEl.hidden = true;
                tableWrap.hidden = false;
                if (pagination) pagination.hidden = false;
            }
        }

        function getFilteredRiskAlerts() {
            return riskAlertRows.filter(row => {
                if (riskFilter === 'all') return true;
                return String(row.severity || '').toLowerCase() === riskFilter;
            });
        }

        function changeRiskAlertsPage(delta) {
            riskAlertsPager.change(delta, getFilteredRiskAlerts().length);
            renderRiskAlertsTable();
        }

        function renderRiskAlertsTable() {
            const tbody = document.getElementById('riskAlertsTableBody');
            const filtered = getFilteredRiskAlerts();

            if (riskAlertRows.length === 0) {
                return;
            }

            if (filtered.length === 0) {
                setRiskAlertsEmpty('No alerts match this severity filter.', true);
                return;
            }

            setRiskAlertsEmpty('', false);
            const pageRows = riskAlertsPager.slice(filtered);
            tbody.innerHTML = pageRows.map(row => {
                const severity = String(row.severity || 'MEDIUM').toUpperCase();
                const area = row.area_name || row.location || '—';
                const incidents = row.incident_count
                    ? `${escapeHtml(String(row.incident_count))}${row.time_window ? ' · ' + escapeHtml(row.time_window) : ''}`
                    : (row.time_window ? escapeHtml(row.time_window) : '—');
                return `<tr>
                    <td><span class="severity-badge ${severityBadgeClass(severity)}">${escapeHtml(severity)}</span></td>
                    <td>${escapeHtml(area)}</td>
                    <td>
                        <div>${escapeHtml(row.rule_name || 'Alert')}</div>
                        ${row.condition_text ? `<div class="risk-condition">${escapeHtml(row.condition_text)}</div>` : ''}
                    </td>
                    <td>${escapeHtml(row.rule_type || '—')}</td>
                    <td>${incidents}</td>
                    <td>${escapeHtml(formatAlertTime(row.triggered_at))}</td>
                    <td>
                        <button type="button" class="btn-assign-hotspot" data-alert-id="${escapeHtml(row.alert_id)}">
                            <i class="fas fa-walking"></i> Assign Patrol
                        </button>
                    </td>
                </tr>`;
            }).join('');

            tbody.querySelectorAll('.btn-assign-hotspot').forEach(btn => {
                btn.addEventListener('click', () => assignPatrolFromHotspot(btn.dataset.alertId));
            });
        }

        async function loadRiskAlerts() {
            const emptyEl = document.getElementById('riskAlertsEmpty');
            const tbody = document.getElementById('riskAlertsTableBody');
            try {
                emptyEl.hidden = true;
                document.getElementById('riskAlertsTableWrap').hidden = false;
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:#666;">Loading high-risk alerts...</td></tr>';

                const response = await fetch('api/risk_alerts.php?status=active');
                const result = await response.json();

                if (!result.success) {
                    riskAlertRows = [];
                    riskAlertData = {};
                    updateHighRiskBadge(0);
                    setRiskAlertsEmpty(result.message || 'Unable to load high-risk alerts.', true);
                    return;
                }

                riskAlertData = {};
                riskAlertRows = result.data || [];
                const sync = result.sync || null;

                riskAlertRows.forEach(row => {
                    if (row.alert_id) riskAlertData[row.alert_id] = row;
                });
                updateHighRiskBadge(riskAlertRows.length);

                if (riskAlertRows.length === 0) {
                    let emptyMsg = 'No active high-risk alerts right now.';
                    if (sync && sync.success === false) {
                        emptyMsg = 'Could not sync Crime Analytics alerts: ' + (sync.message || 'Unknown error');
                    }
                    setRiskAlertsEmpty(emptyMsg, true);
                    return;
                }

                riskAlertsPager.reset();
                renderRiskAlertsTable();
            } catch (e) {
                console.error('Error loading risk alerts:', e);
                riskAlertRows = [];
                riskAlertData = {};
                updateHighRiskBadge(0);
                setRiskAlertsEmpty('Error loading high-risk alerts.', true);
            }
        }

        async function assignPatrolFromHotspot(alertId) {
            const hotspot = riskAlertData[alertId];
            if (!hotspot) {
                window.alert('Hotspot data not found. Please refresh and try again.');
                return;
            }
            await openAssignPatrolModal({
                zone: hotspot.area_name || hotspot.location || '',
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
                    tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:#666;">Failed to load patrol schedules.</td></tr>';
                    return;
                }

                patrolData = {};
                allSchedules = result.data || [];
                allSchedules.forEach(function(row) {
                    patrolData[row.id] = row;
                });
                schedulesPager.reset();
                renderSchedulesTable();
            } catch (e) {
                console.error('Error loading patrol schedules:', e);
                tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:#666;">Error loading patrol schedules.</td></tr>';
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        async function openAssignPatrolModal(prefill = null) {
            const patrolOfficerSelect = document.getElementById('patrolOfficer');
            const slotsRemaining = prefill && Number(prefill.slots) > 0 ? Number(prefill.slots) : 0;
            const isRequestAssign = !!(prefill && (prefill.pr_id || prefill.request_id));
            const maxSelectable = isRequestAssign ? Math.max(slotsRemaining, 1) : 1;

            document.getElementById('assignSlotsRemaining').value = String(maxSelectable);
            document.getElementById('linkedPatrolRequestId').value = (prefill && prefill.pr_id) ? String(prefill.pr_id) : '';
            document.getElementById('linkedPatrolRequestCode').value = (prefill && prefill.request_id) ? String(prefill.request_id) : '';

            configurePatrolOfficerSelect(maxSelectable, isRequestAssign);

            const today = new Date().toISOString().split('T')[0];
            document.getElementById('patrolDate').value = (prefill && prefill.date) ? prefill.date : today;
            setPatrolZoneAndRoute(
                (prefill && prefill.zone) ? prefill.zone : '',
                (prefill && prefill.route) ? prefill.route : ''
            );
            document.getElementById('patrolShift').value = '';
            document.getElementById('patrolNotes').value = (prefill && prefill.notes) ? prefill.notes : '';

            const alreadyAssignedIds = new Set(
                String((prefill && prefill.assigned_ids) || '')
                    .split(',')
                    .map(id => id.trim())
                    .filter(Boolean)
            );

            patrolOfficerSelect.innerHTML = maxSelectable > 1
                ? ''
                : '<option value="">Select Patrol</option>';

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
                            const dutyShift = normalizeDutyShift(officer.duty_shift || officer.schedule || '');
                            const status = normalizeAvailabilityStatus(officer.status);
                            const alreadyOnRequest = alreadyAssignedIds.has(String(officer.id));
                            option.dataset.dutyShift = dutyShift;
                            option.dataset.status = status;
                            option.textContent = `${officer.bpso_personnel_id} - ${officer.personnel_name} (${alreadyOnRequest ? 'Already assigned to request' : status})`;
                            // Only Available officers can be selected; others stay visible for status matching
                            if (status !== 'Available' || alreadyOnRequest) {
                                option.disabled = true;
                            }
                            patrolOfficerSelect.appendChild(option);
                        });
                    }
                }
            } catch (e) {
                console.error('Error loading patrol:', e);
            }

            onPatrolOfficerSelectionChange();
            document.getElementById('assignPatrolModal').style.display = 'block';
        }

        function configurePatrolOfficerSelect(maxSelectable, isRequestAssign) {
            const select = document.getElementById('patrolOfficer');
            const shiftSelect = document.getElementById('patrolShift');
            const shiftHint = document.getElementById('patrolShiftHint');

            if (maxSelectable > 1) {
                select.multiple = true;
                select.size = Math.min(Math.max(maxSelectable + 2, 6), 10);
                select.removeAttribute('required');
                select.name = 'patrol_ids';
                shiftSelect.required = false;
                shiftSelect.value = '';
                shiftHint.textContent = 'Each selected personnel uses their own fixed duty shift.';
            } else {
                select.multiple = false;
                select.removeAttribute('size');
                select.required = true;
                select.name = 'patrol_id';
                shiftSelect.required = true;
                shiftHint.textContent = 'Filled from the selected personnel\'s fixed duty shift.';
            }
        }

        function normalizeAvailabilityStatus(status) {
            const raw = String(status || 'Available').trim();
            const map = {
                'Available': 'Available',
                'Assigned': 'Assigned',
                'Assigned to Simulation': 'Assigned to Simulation',
                'On Patrol': 'On Patrol',
                'Unavailable': 'Unavailable',
                'Off Duty': 'Unavailable',
                'Off-Duty': 'Unavailable',
                'Off-duty': 'Unavailable'
            };
            return map[raw] || 'Available';
        }

        function normalizeDutyShift(value) {
            const raw = String(value || '').trim().toLowerCase();
            if (raw.includes('night')) return 'Night Shift';
            if (raw.includes('day')) return 'Day Shift';
            if (value === 'Day Shift' || value === 'Night Shift') return value;
            return '';
        }

        function formatShiftWithHours(value) {
            const shift = normalizeDutyShift(value) || String(value || '').trim();
            if (shift === 'Day Shift') return 'Day Shift (8:00 AM – 8:00 PM)';
            if (shift === 'Night Shift') return 'Night Shift (8:00 PM – 8:00 AM)';
            return shift || '—';
        }

        function getSelectedPatrolOptions() {
            const select = document.getElementById('patrolOfficer');
            return Array.from(select.selectedOptions).filter(opt => opt.value && !opt.disabled);
        }

        function onPatrolOfficerSelectionChange() {
            const select = document.getElementById('patrolOfficer');
            const maxSelectable = parseInt(document.getElementById('assignSlotsRemaining').value, 10) || 1;
            let selected = getSelectedPatrolOptions();

            if (select.multiple && selected.length > maxSelectable) {
                // Keep only the first N selections
                selected.slice(maxSelectable).forEach(opt => { opt.selected = false; });
                selected = getSelectedPatrolOptions();
                alert(`You can select only ${maxSelectable} patrol personnel for this request.`);
            }

            if (!select.multiple) {
                applyPersonnelDutyShift();
            } else {
                document.getElementById('patrolShift').value = '';
            }
        }

        function applyPersonnelDutyShift() {
            const select = document.getElementById('patrolOfficer');
            const option = select.options[select.selectedIndex];
            const dutyShift = option && option.dataset ? (option.dataset.dutyShift || '') : '';
            document.getElementById('patrolShift').value = dutyShift;
        }

        async function assignPatrolToDispatch(id) {
            await openAssignPatrolModal();
        }

        function closeAssignPatrolModal() {
            document.getElementById('assignPatrolModal').style.display = 'none';
            const select = document.getElementById('patrolOfficer');
            select.multiple = false;
            select.removeAttribute('size');
            select.required = true;
            select.name = 'patrol_id';
            document.getElementById('patrolShift').required = true;
            document.getElementById('assignPatrolForm').reset();
            document.getElementById('linkedPatrolRequestId').value = '';
            document.getElementById('linkedPatrolRequestCode').value = '';
            document.getElementById('assignSlotsRemaining').value = '';
        }

        function openViewPatrolModal(id) {
            const schedule = patrolData[id];
            if (!schedule) return;
            
            const zone = schedule.patrol_zone || schedule.location || schedule.route || '—';
            const content = `
                <div style="line-height: 1.8;">
                    <p><strong>Patrol:</strong> ${escapeHtml(schedule.personnel_name)}</p>
                    <p><strong>Shift:</strong> ${escapeHtml(formatShiftWithHours(schedule.shift))}</p>
                    <p><strong>Patrol Zone:</strong> ${escapeHtml(zone)}</p>
                    <p><strong>Date:</strong> ${escapeHtml(schedule.schedule_date)}</p>
                    <p><strong>Status:</strong> <span class="status-badge ${statusClass(schedule.status)}">${escapeHtml(schedule.status)}</span></p>
                    <p><strong>Notes:</strong> ${escapeHtml(schedule.notes || '—')}</p>
                    <p style="margin-top:0.75rem;color:var(--text-secondary);font-size:0.9rem;"><em>Attendance is based on Clock On / Clock Out in the BPSO portal.</em></p>
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

        async function createPatrolScheduleForOfficer(patrolId, shift, sharedFields) {
            const response = await fetch('api/patrol_schedules.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create',
                    patrol_id: patrolId,
                    schedule_date: sharedFields.schedule_date,
                    shift: shift,
                    patrol_zone: sharedFields.patrol_zone,
                    route: sharedFields.route,
                    location: sharedFields.location,
                    notes: sharedFields.notes
                })
            });
            return response.json();
        }

        async function savePatrolAssignment(event) {
            event.preventDefault();

            const formData = new FormData(event.target);
            const linkedRequestId = parseInt(document.getElementById('linkedPatrolRequestId').value, 10) || 0;
            const linkedRequestCode = document.getElementById('linkedPatrolRequestCode').value || '';
            const maxSelectable = parseInt(document.getElementById('assignSlotsRemaining').value, 10) || 1;
            const selectedOptions = getSelectedPatrolOptions();

            if (!selectedOptions.length) {
                alert('Please select Available patrol.');
                return;
            }

            if (selectedOptions.length > maxSelectable) {
                alert(`You can assign only ${maxSelectable} patrol personnel for this request.`);
                return;
            }

            const unavailable = selectedOptions.filter(opt => (opt.dataset.status || '') !== 'Available');
            if (unavailable.length) {
                alert('Only personnel with Available status can be assigned. Status must match Patrol List.');
                return;
            }

            const sharedFields = {
                schedule_date: formData.get('date'),
                patrol_zone: formData.get('patrol_zone'),
                route: formData.get('route') || formData.get('patrol_zone'),
                location: formData.get('patrol_zone'),
                notes: formData.get('notes') || ''
            };

            const officers = selectedOptions.map(opt => ({
                id: parseInt(opt.value, 10),
                shift: normalizeDutyShift(opt.dataset.dutyShift || formData.get('shift') || '')
            }));

            for (const officer of officers) {
                if (!officer.shift) {
                    alert('Each selected personnel must have a fixed duty shift (Day Shift or Night Shift).');
                    return;
                }
            }

            try {
                const assignedIds = [];
                for (const officer of officers) {
                    const data = await createPatrolScheduleForOfficer(officer.id, officer.shift, sharedFields);
                    if (!data.success) {
                        throw new Error(data.message || 'Failed to create assignment for one or more personnel.');
                    }
                    assignedIds.push(officer.id);
                }

                if (linkedRequestId > 0) {
                    await markPatrolRequestScheduled(linkedRequestId, assignedIds, linkedRequestCode);
                }

                alert(
                    assignedIds.length > 1
                        ? `${assignedIds.length} patrol assignments created successfully.`
                        : 'Patrol assignment created successfully. The assigned personnel can view it in the BPSO portal.'
                );
                closeAssignPatrolModal();
                if (window.history && window.history.replaceState) {
                    window.history.replaceState({}, document.title, 'patrol-schedule.php');
                }
                await loadPatrolSchedules();
            } catch (error) {
                console.error('Error:', error);
                alert(error.message || 'Failed to create patrol assignment.');
            }
        }

        async function markPatrolRequestScheduled(requestDbId, patrolIds, requestCode) {
            try {
                const idsToAdd = (Array.isArray(patrolIds) ? patrolIds : [patrolIds])
                    .map(Number)
                    .filter(id => id > 0);

                const listRes = await fetch('api/patrol_requests.php');
                const listResult = await listRes.json();
                if (!listResult.success) return;

                const request = (listResult.data || []).find(item => Number(item.id) === Number(requestDbId));
                if (!request) return;

                const assigned = Array.isArray(request.assigned_patrol_ids)
                    ? request.assigned_patrol_ids.map(Number)
                    : [];
                idsToAdd.forEach(id => {
                    if (!assigned.includes(id)) assigned.push(id);
                });

                const needed = Number(request.patrols_needed || 0);
                if (needed > 0 && assigned.length > needed) {
                    throw new Error(`Cannot assign more than ${needed} personnel for this request.`);
                }

                const status = (needed > 0 && assigned.length >= needed) ? 'Scheduled' : 'Approved';

                const manageRes = await fetch('api/patrol_requests.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'manage',
                        id: requestDbId,
                        status: status,
                        assigned_patrol_ids: assigned,
                        review_notes: request.review_notes || ('Assigned via patrol schedule' + (requestCode ? ' for ' + requestCode : '') + '.'),
                        scheduling_notes: 'Linked schedule assignment for ' + (requestCode || ('#' + requestDbId))
                    })
                });
                const manageResult = await manageRes.json();
                if (!manageResult.success) {
                    throw new Error(manageResult.message || 'Failed to update patrol request.');
                }
            } catch (err) {
                console.error('Failed to update linked patrol request:', err);
                throw err;
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

        async function openAssignFromQueryParams() {
            const params = new URLSearchParams(window.location.search);
            if (params.get('assign') !== '1') return;

            const slots = parseInt(params.get('slots') || params.get('patrols_needed') || '1', 10) || 1;

            await openAssignPatrolModal({
                zone: params.get('zone') || '',
                route: params.get('route') || '',
                date: params.get('date') || '',
                notes: params.get('notes') || '',
                request_id: params.get('request_id') || '',
                pr_id: params.get('pr_id') || '',
                slots: String(slots),
                patrols_needed: params.get('patrols_needed') || '',
                already_assigned: params.get('already_assigned') || '',
                assigned_ids: params.get('assigned_ids') || ''
            });
        }

        // Initialize data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadRiskAlerts();
            loadPatrolSchedules();
            openAssignFromQueryParams();
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


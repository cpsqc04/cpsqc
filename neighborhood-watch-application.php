<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

if (!defined('NW_PAGE_MODE')) {
    define('NW_PAGE_MODE', 'applications');
}

$nwIsMemberList = NW_PAGE_MODE === 'members';
$nwPageTitle = $nwIsMemberList ? 'Neighborhood Watch Member List' : 'Neighborhood Watch Application';
$nwSearchPlaceholder = $nwIsMemberList
    ? 'Search approved neighborhood watch members by name or contact...'
    : 'Search pending or rejected applications by name or contact...';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($nwPageTitle); ?> - Alertara</title>
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
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap; }
        .search-box { flex: 1; min-width: 250px; position: relative; }
        .search-box input { width: 100%; padding: 0.75rem 1rem 0.75rem 2.5rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.95rem; transition: all 0.2s ease; }
        .search-box input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1); }
        .search-box::before { content: "🔍"; position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 1rem; }
        .btn-add { padding: 0.75rem 1.5rem; background: var(--primary-color); color: #fff; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; gap: 0.5rem; }
        .btn-add:hover { background: #4ca8a6; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(76, 138, 137, 0.2); }
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
        .status-rejected { background: #f8d7da; color: #842029; }
        .status-inactive { background: #e2e3e5; color: #41464b; }
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-review, .btn-edit, .btn-delete { padding: 0.5rem 1rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; }
        .btn-review:hover { background: #4ca8a6; }
        .btn-edit { background: #ff9800; }
        .btn-edit:hover { background: #f57c00; }
        .btn-delete { background: #dc3545; }
        .btn-delete:hover { background: #c82333; }
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: var(--card-bg); border-radius: 12px; padding: 2rem; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color); }
        .modal-header h2 { margin: 0; color: var(--tertiary-color); font-size: 1.5rem; }
        .close-modal { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary); transition: color 0.2s ease; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px; }
        .close-modal:hover { color: var(--text-color); background: rgba(0, 0, 0, 0.05); }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-color); font-weight: 500; font-size: 0.9rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.95rem; font-family: var(--font-family); transition: all 0.2s ease; box-sizing: border-box; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1); }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); }
        .file-upload { position: relative; display: inline-block; width: 100%; }
        .file-upload input[type="file"] { position: absolute; opacity: 0; width: 100%; height: 100%; cursor: pointer; z-index: 1; }
        .file-upload-label { display: flex; align-items: center; justify-content: center; padding: 1rem; border: 2px dashed var(--border-color); border-radius: 8px; cursor: pointer; transition: all 0.2s ease; background: #f9f9f9; }
        .file-upload-label:hover { border-color: var(--primary-color); background: rgba(76, 138, 137, 0.05); }
        .file-preview { margin-top: 0.5rem; display: none; position: relative; z-index: 2; }
        .file-preview img { max-width: 100%; max-height: 200px; border-radius: 8px; border: 1px solid var(--border-color); }
        .id-photo-preview { width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid var(--border-color); cursor: pointer; }
        .btn-cancel, .btn-submit { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; }
        .btn-cancel { background: #e5e5e5; color: var(--text-color); }
        .btn-cancel:hover { background: #d5d5d5; }
        .btn-submit { background: var(--primary-color); color: #fff; }
        .btn-submit:hover { background: #4ca8a6; }
        .btn-approve { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; background: #059669; color: #fff; transition: all 0.2s ease; }
        .btn-approve:hover { background: #047857; }
        .btn-reject { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; background: #dc3545; color: #fff; transition: all 0.2s ease; }
        .btn-reject:hover { background: #c82333; }
        .btn-reject-cancel { padding: 0.75rem 1.5rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; background: #fff; color: var(--text-color); }
        .btn-reject-cancel:hover { background: #f3f4f6; }
        .reject-form-panel {
            display: none;
            width: 100%;
            margin-top: 0.25rem;
            padding: 1rem;
            border: 1px solid #fecaca;
            border-radius: 10px;
            background: #fff5f5;
        }
        .reject-form-panel.active { display: block; }
        .reject-form-panel label {
            display: block;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.4rem;
            color: var(--text-color);
        }
        .reject-form-panel select,
        .reject-form-panel textarea {
            width: 100%;
            padding: 0.65rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font: inherit;
            margin-bottom: 0.9rem;
            background: #fff;
        }
        .reject-form-panel textarea { min-height: 90px; resize: vertical; }
        .reject-form-actions { display: flex; gap: 0.75rem; justify-content: flex-end; flex-wrap: wrap; }
        .eligibility-review-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 0.65rem;
        }
        .eligibility-review-item {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: flex-start;
            padding: 0.65rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: #f8fafc;
        }
        .eligibility-review-item span:first-child {
            color: var(--text-color);
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .eligibility-answer {
            flex-shrink: 0;
            font-weight: 700;
            font-size: 0.85rem;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
        }
        .eligibility-answer.yes { background: #d1e7dd; color: #0f5132; }
        .eligibility-answer.no { background: #f8d7da; color: #842029; }
        .review-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); }
        .review-photo-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; }
        .review-photo-card { display: flex; flex-direction: column; gap: 0.5rem; }
        .review-photo-card img { width: 100%; max-width: 180px; height: 140px; object-fit: cover; border-radius: 8px; border: 2px solid var(--border-color); cursor: pointer; }
        /* View Modal Styles */
        .complaint-details { display: flex; flex-direction: column; gap: 1.25rem; }
        .detail-row { display: flex; flex-direction: column; gap: 0.5rem; }
        .detail-label { font-weight: 600; color: var(--text-color); font-size: 0.9rem; }
        .detail-value { color: var(--text-secondary); font-size: 0.95rem; line-height: 1.6; }
        .detail-row.inline { flex-direction: row; align-items: center; gap: 1rem; }
        .detail-row.inline .detail-label { min-width: 120px; }
        @media (max-width: 768px) { .sidebar { width: 320px; transform: translateX(-100%); transition: transform 0.3s ease; } .sidebar.mobile-open { transform: translateX(0); } .sidebar.collapsed { width: 80px; transform: translateX(0); } .main-wrapper { margin-left: 0; } body.sidebar-collapsed .main-wrapper { margin-left: 80px; } .toolbar { flex-direction: column; } .search-box { width: 100%; } .form-row { grid-template-columns: 1fr; } }
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
            
            <div class="nav-module active">
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
                <h1 class="page-title"><?php echo htmlspecialchars($nwPageTitle); ?></h1>
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
                <div class="toolbar">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="<?php echo htmlspecialchars($nwSearchPlaceholder); ?>" onkeyup="filterMembers()">
                    </div>
                </div>
                <div class="table-container">
                    <table id="membersTable">
                        <thead>
                            <tr>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Gender</th>
                                <th>Marital Status</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="membersTableBody">
                            <!-- Members will be loaded from database via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add Member Modal -->
    <div id="addMemberModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Neighborhood Watch Member</h2>
                <button class="close-modal" onclick="closeAddMemberModal()">&times;</button>
            </div>
            <form id="addMemberForm" onsubmit="saveMember(event)" autocomplete="off">
                <input type="hidden" id="addMemberId" value="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="memberName">Full Name *</label>
                        <input type="text" id="memberName" name="memberName" required>
                    </div>
                    <div class="form-group">
                        <label for="memberContact">Contact Number *</label>
                        <input type="tel" id="memberContact" name="memberContact" class="contact-number-input" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="memberEmail">Email Address *</label>
                        <input type="email" id="memberEmail" name="memberEmail" required>
                    </div>
                    <div class="form-group">
                        <label for="memberAddress">Home Address *</label>
                        <input type="text" id="memberAddress" name="memberAddress" required placeholder="e.g., 123 Bonifacio Street, Barangay San Agustin, Quezon City">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="memberStatus">Status *</label>
                        <select id="memberStatus" name="memberStatus" required>
                            <option value="">Select Status</option>
                            <option value="Active">Active</option>
                            <option value="Pending">Pending</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="memberEmergencyName">Emergency Contact Full Name *</label>
                        <input type="text" id="memberEmergencyName" name="memberEmergencyName" required>
                    </div>
                    <div class="form-group">
                        <label for="memberEmergencyContact">Emergency Contact Number *</label>
                        <input type="tel" id="memberEmergencyContact" name="memberEmergencyContact" class="contact-number-input" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="memberPhoto">Neighborhood Watch Member Photo *</label>
                    <div class="file-upload">
                        <input type="file" id="memberPhoto" name="memberPhoto" accept="image/*" required onchange="previewImage(this, 'memberPhotoPreview')">
                        <label for="memberPhoto" class="file-upload-label">
                            <span>📷 Click to upload Neighborhood Watch Member Photo</span>
                        </label>
                        <div class="file-preview" id="memberPhotoPreview"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="memberPhotoId">Photo of Valid ID *</label>
                    <div class="file-upload">
                        <input type="file" id="memberPhotoId" name="memberPhotoId" accept="image/*" required onchange="previewImage(this, 'memberPhotoIdPreview')">
                        <label for="memberPhotoId" class="file-upload-label">
                            <span>🆔 Click to upload Valid ID</span>
                        </label>
                        <div class="file-preview" id="memberPhotoIdPreview"></div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeAddMemberModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Save Member</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Member Modal -->
    <div id="editMemberModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Neighborhood Watch Member</h2>
                <button class="close-modal" onclick="closeEditMemberModal()">&times;</button>
            </div>
            <form id="editMemberForm" onsubmit="updateMember(event)" autocomplete="off">
                <input type="hidden" id="editMemberId" name="memberId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editMemberName">Full Name *</label>
                        <input type="text" id="editMemberName" name="memberName" required>
                    </div>
                    <div class="form-group">
                        <label for="editMemberContact">Contact Number *</label>
                        <input type="tel" id="editMemberContact" name="memberContact" class="contact-number-input" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editMemberEmail">Email Address *</label>
                        <input type="email" id="editMemberEmail" name="memberEmail" required>
                    </div>
                    <div class="form-group">
                        <label for="editMemberAddress">Home Address *</label>
                        <input type="text" id="editMemberAddress" name="memberAddress" required placeholder="e.g., 123 Bonifacio Street, Barangay San Agustin, Quezon City">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editMemberEmergencyName">Emergency Contact Full Name *</label>
                        <input type="text" id="editMemberEmergencyName" name="memberEmergencyName" required>
                    </div>
                    <div class="form-group">
                        <label for="editMemberEmergencyContact">Emergency Contact Number *</label>
                        <input type="tel" id="editMemberEmergencyContact" name="memberEmergencyContact" class="contact-number-input" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="editMemberStatus">Status *</label>
                        <select id="editMemberStatus" name="memberStatus" required>
                            <option value="">Select Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Active">Active</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="editMemberPhoto">Neighborhood Watch Member Photo</label>
                    <div class="file-upload">
                        <input type="file" id="editMemberPhoto" name="memberPhoto" accept="image/*" onchange="previewImage(this, 'editMemberPhotoPreview')">
                        <label for="editMemberPhoto" class="file-upload-label">
                            <span>📷 Click to upload member photo (optional — leave blank to keep existing)</span>
                        </label>
                        <div class="file-preview" id="editMemberPhotoPreview"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="editMemberPhotoId">Photo of Valid ID</label>
                    <div class="file-upload">
                        <input type="file" id="editMemberPhotoId" name="memberPhotoId" accept="image/*" onchange="previewImage(this, 'editMemberPhotoIdPreview')">
                        <label for="editMemberPhotoId" class="file-upload-label">
                            <span>🆔 Click to upload valid ID (optional — leave blank to keep existing)</span>
                        </label>
                        <div class="file-preview" id="editMemberPhotoIdPreview"></div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeEditMemberModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Update Member</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Review Member Modal -->
    <div id="reviewMemberModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Review Application</h2>
                <button class="close-modal" onclick="closeReviewMemberModal()">&times;</button>
            </div>
            <div id="memberDetails" class="complaint-details">
                <!-- Details will be populated by JavaScript -->
            </div>
            <div id="reviewMemberActions" class="review-actions" style="display: none; flex-direction: column; align-items: stretch;">
                <div id="rejectFormPanel" class="reject-form-panel">
                    <label for="rejectionReason">Reason of Rejection *</label>
                    <select id="rejectionReason">
                        <option value="">Select a reason</option>
                        <option value="Maximum of slots reached">Maximum of slots reached</option>
                        <option value="Incomplete document requirements">Incomplete document requirements</option>
                        <option value="Discrepancy in residency requirements">Discrepancy in residency requirements</option>
                    </select>
                    <label for="rejectionNotes">Notes</label>
                    <textarea id="rejectionNotes" placeholder="Add optional notes for this rejection"></textarea>
                    <div class="reject-form-actions">
                        <button type="button" class="btn-reject-cancel" onclick="cancelRejectForm()">Cancel</button>
                        <button type="button" class="btn-reject" onclick="confirmRejectApplication()">
                            <i class="fas fa-times"></i> Confirm Reject
                        </button>
                    </div>
                </div>
                <div id="reviewDecisionButtons" style="display: flex; gap: 1rem; justify-content: flex-end; width: 100%; flex-wrap: wrap;">
                    <button type="button" class="btn-reject" onclick="showRejectForm()">
                        <i class="fas fa-times"></i> Reject
                    </button>
                    <button type="button" class="btn-approve" onclick="reviewDecision('approve')">
                        <i class="fas fa-check"></i> Approve
                    </button>
                </div>
                <div id="rejectedEmailActions" style="display: none; gap: 1rem; justify-content: flex-end; width: 100%;">
                    <button type="button" class="btn-approve" style="background:#4c8a89;" onclick="resendRejectionEmail()">
                        <i class="fas fa-envelope"></i> Resend Rejection Email
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/form-contact-validation.js"></script>
    <script>
        const NW_PAGE_MODE = <?php echo json_encode(NW_PAGE_MODE); ?>;

        function memberMatchesPageMode(member) {
            const status = member.status || 'Pending';
            if (NW_PAGE_MODE === 'members') {
                return status === 'Active';
            }
            return status === 'Pending' || status === 'Rejected';
        }

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
        function filterMembers() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('membersTableBody');
            const rows = table.getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent || row.innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }
        // Member data storage (loaded from database)
        let memberData = {};
        let currentReviewMemberId = null;

        function getStatusBadgeClass(status) {
            if (status === 'Pending') return 'status-pending';
            if (status === 'Rejected') return 'status-rejected';
            if (status === 'Inactive') return 'status-inactive';
            return 'status-resolved';
        }

        function compressImageFile(file, maxWidth = 1280, quality = 0.82) {
            return new Promise((resolve, reject) => {
                if (!file || !file.type.startsWith('image/')) {
                    reject(new Error('Invalid image file'));
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = new Image();
                    img.onload = function() {
                        let width = img.width;
                        let height = img.height;

                        if (width > maxWidth) {
                            height = Math.round(height * (maxWidth / width));
                            width = maxWidth;
                        }

                        const canvas = document.createElement('canvas');
                        canvas.width = width;
                        canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);
                        resolve(canvas.toDataURL('image/jpeg', quality));
                    };
                    img.onerror = function() {
                        reject(new Error('Failed to load image'));
                    };
                    img.src = e.target.result;
                };
                reader.onerror = function() {
                    reject(new Error('Failed to read image'));
                };
                reader.readAsDataURL(file);
            });
        }
        
        // Load Members from database
        async function loadMembers() {
            try {
                const response = await fetch('api/neighborhood-watcher-members.php');
                const result = await response.json();
                
                if (!result.success) {
                    console.error(result.message || 'Failed to load members');
                    return;
                }
                
                const members = result.data || [];
                const tbody = document.getElementById('membersTableBody');
                tbody.innerHTML = '';
                
                // Store Members by id for easy lookup
                memberData = {};
                members.forEach(v => {
                    if (memberMatchesPageMode(v)) {
                        memberData[v.id] = v;
                    }
                });
                
                // Populate table
                Object.keys(memberData).forEach(id => {
                    addTableRow(id);
                });
            } catch (e) {
                console.error('Error loading Members:', e);
            }
        }
        
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (!preview) return;

            preview.innerHTML = '';
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.className = 'id-photo-preview';
                    img.alt = file.name;

                    const reader = new FileReader();
                    reader.onload = function (e) {
                        img.src = e.target.result;
                        img.onclick = function () { viewPhoto(img.src); };
                    };
                    reader.readAsDataURL(file);
                    preview.appendChild(img);
                } else {
                    const label = document.createElement('div');
                    label.textContent = file.name;
                    preview.appendChild(label);
                }
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }
        
        function viewPhoto(src) {
            // Create a simple modal to view the photo
            const modal = document.createElement('div');
            modal.style.cssText = 'position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); display: flex; align-items: center; justify-content: center;';
            modal.onclick = function() { document.body.removeChild(modal); };
            
            const img = document.createElement('img');
            img.src = src;
            img.style.cssText = 'max-width: 90%; max-height: 90%; border-radius: 8px;';
            img.onclick = function(e) { e.stopPropagation(); };
            
            modal.appendChild(img);
            document.body.appendChild(modal);
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadMembers();
        });
        
        function openAddMemberModal() {
            document.getElementById('addMemberModal').classList.add('active');
        }
        
        function closeAddMemberModal() {
            document.getElementById('addMemberModal').classList.remove('active');
            document.getElementById('addMemberForm').reset();
            document.getElementById('memberPhotoIdPreview').style.display = 'none';
            document.getElementById('memberPhotoIdPreview').innerHTML = '';
            document.getElementById('memberPhotoPreview').style.display = 'none';
            document.getElementById('memberPhotoPreview').innerHTML = '';
        }
        
        function saveMember(event) {
            event.preventDefault();
            
            const name = document.getElementById('memberName').value.trim();
            const contact = document.getElementById('memberContact').value.trim();
            const email = document.getElementById('memberEmail').value.trim();
            const address = document.getElementById('memberAddress').value.trim();
            const status = document.getElementById('memberStatus').value;
            const photoFile = document.getElementById('memberPhoto').files[0];
            const photoIdFile = document.getElementById('memberPhotoId').files[0];
            const emergencyName = document.getElementById('memberEmergencyName').value.trim();
            const emergencyContact = document.getElementById('memberEmergencyContact').value.trim();
            
            const contactError = AlertaraFormEnhancements.validateContactInput(document.getElementById('memberContact'), 'Contact number');
            if (contactError) {
                alert(contactError);
                return;
            }
            const emergencyContactError = AlertaraFormEnhancements.validateContactInput(document.getElementById('memberEmergencyContact'), 'Emergency contact number');
            if (emergencyContactError) {
                alert(emergencyContactError);
                return;
            }
            
            if (!photoFile) {
                alert('Member photo is required!');
                return;
            }
            
            if (!photoIdFile) {
                alert('Photo ID is required!');
                return;
            }
            
            // Handle file uploads
            let photoSrc = null;
            let photoIdSrc = null;
            
            const processFiles = () => {
                const promises = [];
                
                if (photoFile) {
                    promises.push(new Promise(resolve => {
                        const reader = new FileReader();
                        reader.onload = e => {
                            photoSrc = e.target.result;
                            resolve();
                        };
                        reader.readAsDataURL(photoFile);
                    }));
                } else {
                    promises.push(Promise.resolve());
                }
                
                promises.push(new Promise(resolve => {
                    const reader = new FileReader();
                    reader.onload = e => {
                        photoIdSrc = e.target.result;
                        resolve();
                    };
                    reader.readAsDataURL(photoIdFile);
                }));
                
                Promise.all(promises).then(() => {
                    completeSave();
                });
            };
            
            processFiles();
            
            function completeSave() {
                const formData = {
                    action: 'create',
                    name: name,
                    contact: contact,
                    email: email,
                    address: address,
                    status: status,
                    photo: photoSrc,
                    photo_id: photoIdSrc,
                    emergency_contact_name: emergencyName,
                    emergency_contact_number: emergencyContact
                };
                
                fetch('api/neighborhood-watcher-members.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                })
                .then(res => {
                    if (!res.ok) {
                        return res.text().then(text => {
                            try {
                                const json = JSON.parse(text);
                                throw new Error(json.message || 'Server error');
                            } catch (e) {
                                if (e instanceof Error && e.message !== 'Server error') {
                                    throw e;
                                }
                                throw new Error('Server error: ' + res.status + ' ' + res.statusText);
                            }
                        });
                    }
                    return res.json();
                })
                .then(result => {
                    if (!result.success) {
                        alert(result.message || 'Failed to save member.');
                        return;
                    }
                    
                    // Reload Members to refresh the table
                    loadMembers();
                    alert('Member added successfully!');
                    closeAddMemberModal();
                })
                .catch(err => {
                    console.error('Error saving member:', err);
                    alert('Error saving member: ' + (err.message || 'Please try again.'));
                });
            }
        }
        
        function addTableRow(id) {
            const member = memberData[id];
            if (!member) return;
            
            const tbody = document.getElementById('membersTableBody');
            
            const row = document.createElement('tr');
            row.setAttribute('data-member-id', id);
            
            const statusClass = getStatusBadgeClass(member.status);
            const primaryActionLabel = NW_PAGE_MODE === 'members' ? 'View' : 'Review';
            
            row.innerHTML = `
                <td>${member.first_name || ''}</td>
                <td>${member.last_name || ''}</td>
                <td>${member.gender || '—'}</td>
                <td>${member.marital_status || '—'}</td>
                <td>${member.contact || ''}</td>
                <td><span class="status-badge ${statusClass}">${member.status || 'Pending'}</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-review" onclick="reviewMember('${id}')">${primaryActionLabel}</button>
                        <button class="btn-edit" onclick="editMember('${id}')">Edit</button>
                    </div>
                </td>
            `;
            
            tbody.appendChild(row);
        }
        
        function formatApplicantBirthday(value) {
            if (!value) return '';
            const raw = String(value).trim();
            const isoMatch = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
            if (isoMatch) {
                return `${isoMatch[2]}/${isoMatch[3]}/${isoMatch[1]}`;
            }
            return raw;
        }

        const ELIGIBILITY_CRITERIA_LABELS = {
            eligibility_1: 'I am a Filipino Citizen.',
            eligibility_2: 'I am Barangay San Agustin Resident.',
            eligibility_3: 'I have been a resident of Barangay San Agustin for at least six (6) months.',
            eligibility_4: 'I am a registered voter in Barangay San Agustin.',
            eligibility_5: 'I am between 18 and 60 years old.',
            eligibility_6: 'I can read and write in Tagalog o English.'
        };

        function parseEligibilityAnswers(raw) {
            if (!raw) return null;
            if (typeof raw === 'object' && !Array.isArray(raw)) return raw;
            if (typeof raw === 'string') {
                try {
                    const parsed = JSON.parse(raw);
                    return parsed && typeof parsed === 'object' ? parsed : null;
                } catch (e) {
                    return null;
                }
            }
            return null;
        }

        function buildEligibilityReviewHtml(member) {
            const answers = parseEligibilityAnswers(member.eligibility_answers);
            if (!answers) {
                return `
                <div class="detail-row">
                    <span class="detail-label">Eligibility Criteria:</span>
                    <span class="detail-value" style="color: var(--text-secondary); font-style: italic;">No eligibility answers recorded</span>
                </div>
                `;
            }

            const items = Object.keys(ELIGIBILITY_CRITERIA_LABELS).map((key) => {
                const answer = String(answers[key] || '').toLowerCase();
                const answerLabel = answer === 'yes' ? 'Yes' : (answer === 'no' ? 'No' : '—');
                const answerClass = answer === 'yes' ? 'yes' : (answer === 'no' ? 'no' : '');
                return `
                    <li class="eligibility-review-item">
                        <span>${ELIGIBILITY_CRITERIA_LABELS[key]}</span>
                        <span class="eligibility-answer ${answerClass}">${answerLabel}</span>
                    </li>
                `;
            }).join('');

            return `
                <div class="detail-row">
                    <span class="detail-label">Eligibility Criteria Answers:</span>
                    <div class="detail-value">
                        <ul class="eligibility-review-list">${items}</ul>
                    </div>
                </div>
            `;
        }

        function resetRejectForm() {
            const panel = document.getElementById('rejectFormPanel');
            const buttons = document.getElementById('reviewDecisionButtons');
            const rejectedActions = document.getElementById('rejectedEmailActions');
            const reason = document.getElementById('rejectionReason');
            const notes = document.getElementById('rejectionNotes');
            if (panel) panel.classList.remove('active');
            if (buttons) buttons.style.display = 'flex';
            if (rejectedActions) rejectedActions.style.display = 'none';
            if (reason) reason.value = '';
            if (notes) notes.value = '';
        }

        function showRejectForm() {
            const panel = document.getElementById('rejectFormPanel');
            const buttons = document.getElementById('reviewDecisionButtons');
            const rejectedActions = document.getElementById('rejectedEmailActions');
            if (panel) panel.classList.add('active');
            if (buttons) buttons.style.display = 'none';
            if (rejectedActions) rejectedActions.style.display = 'none';
        }

        function cancelRejectForm() {
            const member = currentReviewMemberId ? memberData[currentReviewMemberId] : null;
            resetRejectForm();
            if (member && member.status === 'Rejected') {
                const rejectedActions = document.getElementById('rejectedEmailActions');
                const buttons = document.getElementById('reviewDecisionButtons');
                if (buttons) buttons.style.display = 'none';
                if (rejectedActions) rejectedActions.style.display = 'flex';
            }
        }

        function reviewMember(id) {
            const member = memberData[id];
            if (!member) {
                alert('Member not found!');
                return;
            }

            currentReviewMemberId = id;
            const modal = document.getElementById('reviewMemberModal');
            const detailsContainer = document.getElementById('memberDetails');
            const reviewActions = document.getElementById('reviewMemberActions');
            const statusClass = getStatusBadgeClass(member.status);
            const modalTitle = NW_PAGE_MODE === 'members' ? 'Member Details' : 'Review Application';
            document.querySelector('#reviewMemberModal .modal-header h2').textContent = modalTitle;
            const birthdayDisplay = formatApplicantBirthday(member.birthday);
            resetRejectForm();

            detailsContainer.innerHTML = `
                <div class="detail-row inline">
                    <span class="detail-label">First Name:</span>
                    <span class="detail-value"><strong>${member.first_name || ''}</strong></span>
                </div>
                <div class="detail-row inline">
                    <span class="detail-label">Last Name:</span>
                    <span class="detail-value"><strong>${member.last_name || ''}</strong></span>
                </div>
                ${member.middle_name ? `
                <div class="detail-row inline">
                    <span class="detail-label">Middle Name:</span>
                    <span class="detail-value">${member.middle_name}</span>
                </div>
                ` : ''}

                <div class="detail-row inline">
                    <span class="detail-label">Status:</span>
                    <span class="status-badge ${statusClass}">${member.status || ''}</span>
                </div>

                ${member.gender ? `
                <div class="detail-row">
                    <span class="detail-label">Gender:</span>
                    <span class="detail-value">${member.gender}</span>
                </div>
                ` : ''}

                ${member.marital_status ? `
                <div class="detail-row">
                    <span class="detail-label">Marital Status:</span>
                    <span class="detail-value">${member.marital_status}</span>
                </div>
                ` : ''}

                ${birthdayDisplay ? `
                <div class="detail-row">
                    <span class="detail-label">Birthday:</span>
                    <span class="detail-value">${birthdayDisplay}</span>
                </div>
                ` : ''}

                ${member.id_number ? `
                <div class="detail-row">
                    <span class="detail-label">ID Number:</span>
                    <span class="detail-value">${member.id_number}</span>
                </div>
                ` : ''}

                ${member.contact ? `
                <div class="detail-row">
                    <span class="detail-label">Contact Number:</span>
                    <span class="detail-value">${member.contact}</span>
                </div>
                ` : ''}

                ${member.email ? `
                <div class="detail-row">
                    <span class="detail-label">Email Address:</span>
                    <span class="detail-value">${member.email}</span>
                </div>
                ` : ''}

                ${member.address || member.address_unit_street ? `
                <div class="detail-row">
                    <span class="detail-label">Home Address:</span>
                    <span class="detail-value">
                        ${member.address_unit_street || member.address_subdivision || member.address_barangay ? `
                            ${member.address_unit_street ? `<div><strong>Unit/House & Street:</strong> ${member.address_unit_street}</div>` : ''}
                            ${member.address_subdivision ? `<div><strong>Subdivision:</strong> ${member.address_subdivision}</div>` : ''}
                            ${member.address_barangay ? `<div><strong>Barangay:</strong> ${member.address_barangay}</div>` : ''}
                            ${member.address_city ? `<div><strong>City/Municipality:</strong> ${member.address_city}</div>` : ''}
                            ${member.address_postal_code ? `<div><strong>Postal Code:</strong> ${member.address_postal_code}</div>` : ''}
                            ${member.address_country ? `<div><strong>Country:</strong> ${member.address_country}</div>` : ''}
                        ` : (member.address || '')}
                    </span>
                </div>
                ` : ''}

                ${member.emergency_contact_name || member.emergency_contact_number ? `
                <div class="detail-row">
                    <span class="detail-label">Emergency Contact:</span>
                    <span class="detail-value">
                        ${member.emergency_contact_name || ''}${member.emergency_contact_name && member.emergency_contact_number ? ' - ' : ''}${member.emergency_contact_number || ''}
                    </span>
                </div>
                ` : ''}

                ${buildEligibilityReviewHtml(member)}

                ${member.status === 'Rejected' && (member.rejection_reason || member.notes) ? `
                <div class="detail-row">
                    <span class="detail-label">Rejection Details:</span>
                    <span class="detail-value">
                        ${member.rejection_reason ? `<div><strong>Reason:</strong> ${member.rejection_reason}</div>` : ''}
                        ${member.notes ? `<div style="margin-top:0.35rem;"><strong>Notes:</strong> ${member.notes}</div>` : ''}
                    </span>
                </div>
                ` : ''}

                <div class="detail-row">
                    <span class="detail-label">Uploaded Photos / Documents:</span>
                    <div class="detail-value review-photo-grid">
                        ${member.photo_data ? `
                        <div class="review-photo-card">
                            <span style="font-size: 0.85rem; color: var(--text-secondary);">Member Photo</span>
                            <img src="${member.photo_data}" alt="Member Photo" onclick="viewPhoto(this.src)">
                        </div>
                        ` : ''}
                        ${member.photo_id_data ? `
                        <div class="review-photo-card">
                            <span style="font-size: 0.85rem; color: var(--text-secondary);">Valid ID</span>
                            <img src="${member.photo_id_data}" alt="Photo ID" onclick="viewPhoto(this.src)">
                        </div>
                        ` : `
                        <span style="color: var(--text-secondary); font-style: italic;">No photo ID uploaded</span>
                        `}
                        ${member.barangay_clearance_data ? (
                            /\.pdf($|\?)/i.test(String(member.barangay_clearance_data))
                                ? `
                        <div class="review-photo-card">
                            <span style="font-size: 0.85rem; color: var(--text-secondary);">Barangay Clearance</span>
                            <a href="${member.barangay_clearance_data}" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:0.4rem;color:var(--primary-color);font-weight:600;">
                                <i class="fas fa-file-pdf"></i> View PDF
                            </a>
                        </div>
                                `
                                : `
                        <div class="review-photo-card">
                            <span style="font-size: 0.85rem; color: var(--text-secondary);">Barangay Clearance</span>
                            <img src="${member.barangay_clearance_data}" alt="Barangay Clearance" onclick="viewPhoto(this.src)">
                        </div>
                                `
                        ) : `
                        <span style="color: var(--text-secondary); font-style: italic;">No barangay clearance uploaded</span>
                        `}
                    </div>
                </div>
            `;

            reviewActions.style.display = (member.status === 'Pending' || member.status === 'Rejected') ? 'flex' : 'none';
            const decisionButtons = document.getElementById('reviewDecisionButtons');
            const rejectedActions = document.getElementById('rejectedEmailActions');
            if (member.status === 'Pending') {
                if (decisionButtons) decisionButtons.style.display = 'flex';
                if (rejectedActions) rejectedActions.style.display = 'none';
            } else if (member.status === 'Rejected') {
                if (decisionButtons) decisionButtons.style.display = 'none';
                if (rejectedActions) rejectedActions.style.display = 'flex';
                const reason = document.getElementById('rejectionReason');
                const notes = document.getElementById('rejectionNotes');
                if (reason && member.rejection_reason) reason.value = member.rejection_reason;
                if (notes) notes.value = member.notes || '';
            }
            modal.classList.add('active');
        }

        function closeReviewMemberModal() {
            document.getElementById('reviewMemberModal').classList.remove('active');
            currentReviewMemberId = null;
            resetRejectForm();
        }

        function getStatusUpdateMessage(decision, result) {
            const baseMessage = decision === 'approve'
                ? 'Application approved successfully.'
                : 'Application rejected.';

            if (result.email_sent === true) {
                const to = result.email_to ? ` (${result.email_to})` : '';
                return baseMessage + ` A notification email was sent to the applicant${to}.`;
            }

            if (result.email_sent === false) {
                const detail = result.email_error ? ` Reason: ${result.email_error}` : '';
                return baseMessage + ' However, the notification email could not be sent.' + detail + ' Please verify the applicant email address and mail settings.';
            }

            return baseMessage;
        }

        function confirmRejectApplication() {
            const reason = (document.getElementById('rejectionReason') || {}).value || '';
            const notes = (document.getElementById('rejectionNotes') || {}).value || '';
            if (!reason) {
                alert('Please select a reason of rejection.');
                return;
            }
            reviewDecision('reject', reason, notes);
        }

        function resendRejectionEmail() {
            const id = currentReviewMemberId;
            const member = id ? memberData[id] : null;
            if (!member) {
                alert('Member not found!');
                return;
            }

            let reason = member.rejection_reason || '';
            const reasonInput = document.getElementById('rejectionReason');
            if (reasonInput && reasonInput.value) {
                reason = reasonInput.value;
            }
            if (!reason) {
                alert('No rejection reason found. Please select a reason and confirm reject again.');
                showRejectForm();
                return;
            }

            const notesInput = document.getElementById('rejectionNotes');
            const notes = notesInput ? notesInput.value : (member.notes || '');

            if (!confirm('Resend the rejection email to ' + (member.email || 'the applicant') + '?')) {
                return;
            }

            reviewDecision('reject', reason, notes, true);
        }

        function reviewDecision(decision, rejectionReason, rejectionNotes, resendRejectionEmailFlag) {
            const id = currentReviewMemberId;
            const member = id ? memberData[id] : null;
            if (!member) {
                alert('Member not found!');
                return;
            }

            const status = decision === 'approve' ? 'Active' : 'Rejected';
            if (decision === 'approve') {
                if (!confirm('Approve this neighborhood watch application?')) {
                    return;
                }
            } else if (!rejectionReason) {
                alert('Please select a reason of rejection.');
                showRejectForm();
                return;
            } else if (!resendRejectionEmailFlag && !confirm('Reject this neighborhood watch application and email the applicant?')) {
                return;
            }

            const payload = {
                action: 'update',
                id: parseInt(id, 10),
                name: member.name,
                first_name: member.first_name || '',
                middle_name: member.middle_name || '',
                last_name: member.last_name || '',
                gender: member.gender || '',
                marital_status: member.marital_status || '',
                contact: member.contact,
                email: member.email || '',
                address: member.address || '',
                birthday: member.birthday || '',
                id_number: member.id_number || '',
                status: status,
                photo_id: null,
                emergency_contact_name: member.emergency_contact_name || '',
                emergency_contact_number: member.emergency_contact_number || '',
                notes: decision === 'reject' ? (rejectionNotes || '') : (member.notes || '')
            };

            if (decision === 'reject') {
                payload.rejection_reason = rejectionReason;
                if (resendRejectionEmailFlag || member.status === 'Rejected') {
                    payload.resend_rejection_email = true;
                }
            }

            fetch('api/neighborhood-watcher-members.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(result => {
                if (!result.success) {
                    alert(result.message || 'Failed to update application status.');
                    return;
                }

                loadMembers();
                closeReviewMemberModal();
                if (resendRejectionEmailFlag) {
                    if (result.email_sent === true) {
                        alert('Rejection email resent to ' + (result.email_to || member.email || 'the applicant') + '.');
                    } else {
                        alert('Failed to resend rejection email.' + (result.email_error ? (' Reason: ' + result.email_error) : ''));
                    }
                    return;
                }
                alert(getStatusUpdateMessage(decision, result));
            })
            .catch(err => {
                console.error('Error updating application status:', err);
                alert('Error updating application status. Please try again.');
            });
        }
        
        function editMember(id) {
            const member = memberData[id];
            if (!member) {
                alert('Member not found!');
                return;
            }
            
            // Populate form fields
            document.getElementById('editMemberId').value = member.id;
            document.getElementById('editMemberName').value = member.name || '';
            document.getElementById('editMemberContact').value = member.contact || '';
            document.getElementById('editMemberEmail').value = member.email || '';
            document.getElementById('editMemberAddress').value = member.address || '';
            document.getElementById('editMemberStatus').value = member.status || 'Pending';
            document.getElementById('editMemberEmergencyName').value = member.emergency_contact_name || '';
            document.getElementById('editMemberEmergencyContact').value = member.emergency_contact_number || '';

            const memberPhotoPreview = document.getElementById('editMemberPhotoPreview');
            if (member.photo_data) {
                memberPhotoPreview.innerHTML = '<img src="' + member.photo_data + '" alt="Member Photo Preview" class="id-photo-preview" onclick="viewPhoto(this.src)">';
                memberPhotoPreview.style.display = 'block';
            } else {
                memberPhotoPreview.style.display = 'none';
                memberPhotoPreview.innerHTML = '';
            }

            const photoPreview = document.getElementById('editMemberPhotoIdPreview');
            if (member.photo_id_data) {
                photoPreview.innerHTML = '<img src="' + member.photo_id_data + '" alt="Photo ID Preview" class="id-photo-preview" onclick="viewPhoto(this.src)">';
                photoPreview.style.display = 'block';
            } else {
                photoPreview.style.display = 'none';
                photoPreview.innerHTML = '';
            }
            
            // Open modal
            document.getElementById('editMemberModal').classList.add('active');
        }
        
        function closeEditMemberModal() {
            document.getElementById('editMemberModal').classList.remove('active');
            document.getElementById('editMemberForm').reset();
            document.getElementById('editMemberPhotoPreview').style.display = 'none';
            document.getElementById('editMemberPhotoPreview').innerHTML = '';
            document.getElementById('editMemberPhotoIdPreview').style.display = 'none';
            document.getElementById('editMemberPhotoIdPreview').innerHTML = '';
        }
        
        function updateMember(event) {
            event.preventDefault();
            
            const id = parseInt(document.getElementById('editMemberId').value);
            if (!id) {
                alert('Invalid member ID!');
                return;
            }
            
            const member = memberData[id];
            if (!member) {
                alert('Member not found!');
                return;
            }
            
            const name = document.getElementById('editMemberName').value.trim();
            const contact = document.getElementById('editMemberContact').value.trim();
            const email = document.getElementById('editMemberEmail').value.trim();
            const address = document.getElementById('editMemberAddress').value.trim();
            const status = document.getElementById('editMemberStatus').value;
            const emergencyName = document.getElementById('editMemberEmergencyName').value.trim();
            const emergencyContact = document.getElementById('editMemberEmergencyContact').value.trim();
            const memberPhotoFile = document.getElementById('editMemberPhoto').files[0];
            const photoIdFile = document.getElementById('editMemberPhotoId').files[0];

            const contactError = AlertaraFormEnhancements.validateContactInput(document.getElementById('editMemberContact'), 'Contact number');
            if (contactError) {
                alert(contactError);
                return;
            }
            const emergencyContactError = AlertaraFormEnhancements.validateContactInput(document.getElementById('editMemberEmergencyContact'), 'Emergency contact number');
            if (emergencyContactError) {
                alert(emergencyContactError);
                return;
            }

            const uploadPromises = [];
            let photoSrc = null;
            let photoIdSrc = null;

            if (memberPhotoFile) {
                uploadPromises.push(
                    compressImageFile(memberPhotoFile).then(result => { photoSrc = result; })
                );
            }

            if (photoIdFile) {
                uploadPromises.push(
                    compressImageFile(photoIdFile).then(result => { photoIdSrc = result; })
                );
            }

            Promise.all(uploadPromises)
                .then(() => completeUpdate())
                .catch(err => {
                    console.error('Error processing images:', err);
                    alert('Unable to process photos. Please use smaller JPG or PNG images and try again.');
                });

            function completeUpdate() {
                const formData = {
                    action: 'update',
                    id: id,
                    name: name,
                    contact: contact,
                    email: email,
                    address: address,
                    status: status,
                    notes: member.notes || '',
                    photo: photoSrc,
                    photo_id: photoIdSrc,
                    emergency_contact_name: emergencyName,
                    emergency_contact_number: emergencyContact
                };

                fetch('api/neighborhood-watcher-members.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                })
                .then(res => res.json())
                .then(result => {
                    if (!result.success) {
                        alert(result.message || 'Failed to update member.');
                        return;
                    }

                    loadMembers();
                    let successMessage = 'Member updated successfully!';
                    if (result.email_sent === true) {
                        successMessage += ' A notification email was sent to the applicant.';
                    } else if (result.email_sent === false) {
                        successMessage += ' However, the notification email could not be sent.';
                    }
                    alert(successMessage);
                    closeEditMemberModal();
                })
                .catch(err => {
                    console.error('Error updating member:', err);
                    alert('Error updating member. Please try again.');
                });
            }
        }
        
        function deleteMember(id) {
            if (confirm('Are you sure you want to delete this member? This action cannot be undone.')) {
                fetch('api/neighborhood-watcher-members.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        id: parseInt(id)
                    })
                })
                .then(res => res.json())
                .then(result => {
                    if (!result.success) {
                        alert(result.message || 'Failed to delete member.');
                        return;
                    }
                    
                    // Reload Members to refresh the table
                    loadMembers();
                    alert('Member deleted successfully!');
                })
                .catch(err => {
                    console.error('Error deleting member:', err);
                    alert('Error deleting member. Please try again.');
                });
            }
        }
        
        function updateMemberRow(id) {
            const member = memberData[id];
            const row = document.querySelector(`tr[data-member-id="${id}"]`);
            
            if (!row) return;
            
            const cells = row.querySelectorAll('td');
            
            // Update name
            cells[0].textContent = member.name;
            
            // Update contact
            cells[1].textContent = member.contact;
            
            // Update status badge
            const statusBadge = cells[2].querySelector('.status-badge');
            statusBadge.textContent = member.status;
            statusBadge.className = `status-badge ${getStatusBadgeClass(member.status)}`;
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addMemberModal');
            const editModal = document.getElementById('editMemberModal');
            const reviewModal = document.getElementById('reviewMemberModal');
            
            if (event.target == addModal) {
                closeAddMemberModal();
            }
            if (event.target == editModal) {
                closeEditMemberModal();
            }
            if (event.target == reviewModal) {
                closeReviewMemberModal();
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



<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Request - Alertara</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/theme.css">
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
        .action-buttons { display: flex; gap: 0.5rem; }
        .btn-view, .btn-edit, .btn-delete { padding: 0.5rem 1rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; }
        .btn-view:hover { background: #4ca8a6; }
        .btn-edit { background: #ff9800; }
        .btn-edit:hover { background: #f57c00; }
        .btn-delete { background: #dc3545; }
        .btn-delete:hover { background: #c82333; }
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: var(--card-bg); border-radius: 12px; padding: 2rem; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); }
        #assignVolunteersModal .modal-content { max-width: 1200px; width: 95%; }
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
        .btn-cancel, .btn-submit { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; }
        .btn-cancel { background: #e5e5e5; color: var(--text-color); }
        .btn-cancel:hover { background: #d5d5d5; }
        .btn-submit { background: var(--primary-color); color: #fff; }
        .btn-submit:hover { background: #4ca8a6; }
        .complaint-details { display: flex; flex-direction: column; gap: 1.25rem; }
        .detail-row { display: flex; flex-direction: column; gap: 0.5rem; }
        .detail-label { font-weight: 600; color: var(--text-color); font-size: 0.9rem; }
        .detail-value { color: var(--text-secondary); font-size: 0.95rem; line-height: 1.6; }
        .detail-row.inline { flex-direction: row; align-items: center; gap: 1rem; }
        .detail-row.inline .detail-label { min-width: 120px; }
        @media (max-width: 768px) { .sidebar { width: 320px; transform: translateX(-100%); transition: transform 0.3s ease; } .sidebar.mobile-open { transform: translateX(0); } .sidebar.collapsed { width: 80px; transform: translateX(0); } .main-wrapper { margin-left: 0; } body.sidebar-collapsed .main-wrapper { margin-left: 80px; } .toolbar { flex-direction: column; } .search-box { width: 100%; } .form-row { grid-template-columns: 1fr; } }
    </style>
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
            <!-- Dashboard Link -->
            <a href="index.php" class="nav-module-header" data-tooltip="Dashboard" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.5rem; color: rgba(255, 255, 255, 0.9); cursor: pointer; transition: background-color 0.2s ease; font-weight: 500; user-select: none; gap: 0.75rem; <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'background: rgba(76, 138, 137, 0.25); border-left: 3px solid #4c8a89;' : ''; ?>">
                <span class="nav-module-icon"><i class="fas fa-home"></i></span>
                <span class="nav-module-header-text">Dashboard</span>
            </a>
            
            <!-- User Management Module (Admin Only) -->
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin'): ?>
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
                    <a href="login-history.php" class="nav-submodule <?php echo basename($_SERVER['PHP_SELF']) == 'login-history.php' ? 'active' : ''; ?>" data-tooltip="Login History">
                        <span class="nav-submodule-icon"><i class="fas fa-history"></i></span>
                        <span class="nav-submodule-text">Login History</span>
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
                    <a href="member-list.php" class="nav-submodule" data-tooltip="Member List">
                        <span class="nav-submodule-icon"><i class="fas fa-clipboard-list"></i></span>
                        <span class="nav-submodule-text">Member List</span>
                    </a>
                    <a href="activity-logs.php" class="nav-submodule" data-tooltip="Activity Logs">
                        <span class="nav-submodule-icon"><i class="fas fa-chart-bar"></i></span>
                        <span class="nav-submodule-text">Activity Logs</span>
                    </a>
                    <a href="incident-feed.php" class="nav-submodule" data-tooltip="Incident Feed">
                        <span class="nav-submodule-icon"><i class="fas fa-exclamation-triangle"></i></span>
                        <span class="nav-submodule-text">Incident Feed</span>
                    </a>
                </div>
            </div>
            <div class="nav-module">
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="CCTV Surveillance System Management">
                    <span class="nav-module-icon"><i class="fas fa-video"></i></span>
                    <span class="nav-module-header-text">CCTV Surveillance System Management</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="open-surveillance-app.php" class="nav-submodule" data-tooltip="Open Surveillance App">
                        <span class="nav-submodule-icon"><i class="fas fa-desktop"></i></span>
                        <span class="nav-submodule-text">Open Surveillance App</span>
                    </a>
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
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="Volunteer Registry and Scheduling">
                    <span class="nav-module-icon"><i class="fas fa-handshake"></i></span>
                    <span class="nav-module-header-text">Volunteer Registry and Scheduling</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="volunteer-list.php" class="nav-submodule" data-tooltip="Volunteer List">
                        <span class="nav-submodule-icon"><i class="fas fa-user"></i></span>
                        <span class="nav-submodule-text">Volunteer List</span>
                    </a>
                    <a href="schedule-management.php" class="nav-submodule active" data-tooltip="Schedule Management">
                        <span class="nav-submodule-icon"><i class="fas fa-calendar"></i></span>
                        <span class="nav-submodule-text">Schedule Management</span>
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
                    <a href="patrol-list.php" class="nav-submodule" data-tooltip="Patrol List">
                        <span class="nav-submodule-icon"><i class="fas fa-list"></i></span>
                        <span class="nav-submodule-text">Patrol List</span>
                    </a>
                    <a href="patrol-schedule.php" class="nav-submodule" data-tooltip="Patrol Schedule">
                        <span class="nav-submodule-icon"><i class="fas fa-calendar-alt"></i></span>
                        <span class="nav-submodule-text">Patrol Schedule</span>
                    </a>
                    <a href="patrol-logs.php" class="nav-submodule" data-tooltip="Patrol Logs">
                        <span class="nav-submodule-icon"><i class="fas fa-file"></i></span>
                        <span class="nav-submodule-text">Patrol Logs</span>
                    </a>
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
                <h1 class="page-title">Schedule Management</h1>
            </div>
            <div class="user-info">
                <div class="datetime-display">
                    <span class="date-part" id="currentDate"></span>
                    <span class="time-part" id="currentTime"></span>
                </div>
                <div class="notification-container">
                    <button class="notification-bell" onclick="toggleNotifications()" aria-label="Notifications">
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
                        <input type="text" id="searchInput" placeholder="Search requests by event, audience, or venue..." onkeyup="filterRequests()">
                    </div>
                </div>
                <div class="table-container">
                    <table id="requestsTable">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Event Type</th>
                                <th>Event Title</th>
                                <th>Audience Type</th>
                                <th>Event Date</th>
                                <th>Call Time</th>
                                <th>End Time</th>
                                <th>Venue</th>
                                <th>Volunteers Needed</th>
                                <th>Role</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="requestsTableBody">
                            <!-- Sample requests (can be replaced by API data) -->
                            <tr data-request-id="REQ-001">
                                <td>REQ-001</td>
                                <td>Awareness Campaign</td>
                                <td>Fire Safety Seminar</td>
                                <td>Homeowners</td>
                                <td>2025-02-10</td>
                                <td>08:00</td>
                                <td>12:00</td>
                                <td>Barangay San Agustin Hall</td>
                                <td>5</td>
                                <td>Usher / Registration</td>
                                <td>Assist with registration and crowd management for fire safety seminar.</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewRequest('REQ-001')">View</button>
                                        <button class="btn-edit" onclick="openAssignVolunteersModal('REQ-001')">Assign Volunteers</button>
                                    </div>
                                </td>
                            </tr>
                            <tr data-request-id="REQ-002">
                                <td>REQ-002</td>
                                <td>Health Mission</td>
                                <td>Community First Aid Training</td>
                                <td>General Public</td>
                                <td>2025-02-15</td>
                                <td>09:00</td>
                                <td>15:00</td>
                                <td>Covered Court, Barangay San Agustin</td>
                                <td>8</td>
                                <td>First Aid Volunteer</td>
                                <td>Support trainers and assist participants during hands-on first aid drills.</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewRequest('REQ-002')">View</button>
                                        <button class="btn-edit" onclick="openAssignVolunteersModal('REQ-002')">Assign Volunteers</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Assign Volunteers Modal -->
    <div id="assignVolunteersModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Assign Volunteers</h2>
                <button class="close-modal" onclick="closeAssignVolunteersModal()">&times;</button>
            </div>
            <form id="assignVolunteersForm" onsubmit="saveVolunteerAssignments(event)">
                <input type="hidden" id="currentRequestId" name="requestId">
                <div class="form-group">
                    <label>Request Details</label>
                    <div id="currentRequestSummary" class="detail-value" style="font-size:0.9rem;"></div>
                </div>
                <div class="form-group">
                    <label>Select Volunteers to Assign</label>
                    <div class="table-container" style="max-height:400px; overflow-y:auto;">
                    <table>
                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Category</th>
                                    <th>Skills</th>
                                    <th>Availability</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="volunteerChoicesBody">
                                <!-- Filled from database -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="form-group">
                    <label for="assignmentRole">Role for Assigned Volunteers</label>
                    <input type="text" id="assignmentRole" name="assignmentRole" placeholder="Defaults to request role if left blank">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeAssignVolunteersModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Assign Selected Volunteers</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Request Modal -->
    <div id="viewRequestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Request Details</h2>
                <button class="close-modal" onclick="closeViewRequestModal()">&times;</button>
            </div>
            <div id="requestDetails" class="complaint-details">
                <!-- Filled dynamically -->
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
        function filterRequests() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('requestsTableBody');
            const rows = table.getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent || row.innerText;
                row.style.display = text.toLowerCase().includes(filter) ? '' : 'none';
            }
        }

        // --- Volunteer Request + Assignment State ---

        // In-memory request data (could be loaded from backend)
        const requestData = {};

        // Load initial requests from table or external system
        function initializeRequestData() {
            const rows = document.querySelectorAll('#requestsTableBody tr[data-request-id]');
            rows.forEach((row) => {
                const id = row.getAttribute('data-request-id');
                const cells = row.querySelectorAll('td');
                requestData[id] = {
                    requestId: id,
                    eventType: cells[1].textContent.trim(),
                    eventTitle: cells[2].textContent.trim(),
                    audienceType: cells[3].textContent.trim(),
                    eventDate: cells[4].textContent.trim(),
                    callTime: cells[5].textContent.trim(),
                    endTime: cells[6].textContent.trim(),
                    venue: cells[7].textContent.trim(),
                    volunteersNeeded: cells[8].textContent.trim(),
                    role: cells[9].textContent.trim(),
                    description: cells[10].textContent.trim()
                };
            });

            // Optional: try to load from external system (stub)
            tryLoadRequestsFromExternalSystem();
        }

        function tryLoadRequestsFromExternalSystem() {
            // Stub: if you expose an API from Campaign Planning / Event system,
            // you can load live requests here.
            fetch('api/get_volunteer_requests.php')
                .then(r => r.ok ? r.json() : null)
                .then(data => {
                    if (!data || !data.success || !Array.isArray(data.requests)) return;
                    const tbody = document.getElementById('requestsTableBody');
                    tbody.innerHTML = '';
                    data.requests.forEach(req => {
                        requestData[req.requestId] = req;
                        const row = document.createElement('tr');
                        row.setAttribute('data-request-id', req.requestId);
                        row.innerHTML = `
                            <td>${req.requestId}</td>
                            <td>${req.eventType}</td>
                            <td>${req.eventTitle}</td>
                            <td>${req.audienceType}</td>
                            <td>${req.eventDate}</td>
                            <td>${req.callTime}</td>
                            <td>${req.endTime}</td>
                            <td>${req.venue}</td>
                            <td>${req.volunteersNeeded}</td>
                            <td>${req.role}</td>
                            <td>${req.description}</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-view" onclick="viewRequest('${req.requestId}')">View</button>
                                    <button class="btn-edit" onclick="openAssignVolunteersModal('${req.requestId}')">Assign Volunteers</button>
                                </div>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                })
                .catch(() => {
                    // Fail silently if API not available; sample data remains
                });
        }

        function viewRequest(id) {
            const req = requestData[id];
            if (!req) {
                alert('Request not found');
                return;
            }
            const container = document.getElementById('requestDetails');
            container.innerHTML = `
                <div class="detail-row inline">
                    <span class="detail-label">Request ID:</span>
                    <span class="detail-value"><strong>${req.requestId}</strong></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Event Type:</span>
                    <span class="detail-value">${req.eventType}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Event Title:</span>
                    <span class="detail-value">${req.eventTitle}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Audience Type:</span>
                    <span class="detail-value">${req.audienceType}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Event Date:</span>
                    <span class="detail-value">${req.eventDate}</span>
                </div>
                <div class="detail-row inline">
                    <span class="detail-label">Call Time:</span>
                    <span class="detail-value">${req.callTime}</span>
                </div>
                <div class="detail-row inline">
                    <span class="detail-label">End Time:</span>
                    <span class="detail-value">${req.endTime}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Venue:</span>
                    <span class="detail-value">${req.venue}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Volunteers Needed:</span>
                    <span class="detail-value">${req.volunteersNeeded}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Role:</span>
                    <span class="detail-value">${req.role}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Description:</span>
                    <span class="detail-value">${req.description}</span>
                </div>
            `;
            document.getElementById('viewRequestModal').classList.add('active');
        }

        function closeViewRequestModal() {
            document.getElementById('viewRequestModal').classList.remove('active');
        }

        // --- Volunteer selection + activity recording ---

        function loadVolunteerDataFromStorage() {
            try {
                const raw = localStorage.getItem('volunteerData');
                if (!raw) return {};
                const parsed = JSON.parse(raw);
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch (e) {
                console.error('Failed to load volunteer data from storage', e);
                return {};
            }
        }

        async function openAssignVolunteersModal(requestId) {
            const req = requestData[requestId];
            if (!req) {
                alert('Request not found');
                return;
            }
            document.getElementById('currentRequestId').value = req.requestId;
            document.getElementById('currentRequestSummary').textContent =
                `${req.eventTitle} (${req.eventType}) • ${req.eventDate} ${req.callTime}-${req.endTime} @ ${req.venue}`;

            const tbody = document.getElementById('volunteerChoicesBody');
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 2rem; color: var(--text-secondary);">Loading volunteers...</td></tr>';

            try {
                // Load volunteers from database
                const response = await fetch('api/volunteers.php');
                const result = await response.json();
                
                tbody.innerHTML = '';
                
                if (!result.success || !result.data || result.data.length === 0) {
                    const row = document.createElement('tr');
                    row.innerHTML = `<td colspan="7" style="text-align:center; padding: 2rem; color: var(--text-secondary);">No volunteers available. Please add volunteers first.</td>`;
                    tbody.appendChild(row);
                } else {
                    const volunteers = result.data;
                    
                    volunteers.forEach(v => {
                        // Determine status badge class
                        let statusClass = 'status-resolved';
                        if (v.status === 'Pending') {
                            statusClass = 'status-pending';
                        } else if (v.status === 'Inactive') {
                            statusClass = 'status-pending';
                        }
                        
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td><input type="checkbox" name="selectedVolunteers" value="${v.id}"></td>
                            <td>${v.name || ''}</td>
                            <td>${v.contact || ''}</td>
                            <td>${v.category || 'Not specified'}</td>
                            <td>${v.skills || ''}</td>
                            <td>${v.availability || ''}</td>
                            <td><span class="status-badge ${statusClass}">${v.status || 'Pending'}</span></td>
                        `;
                        tbody.appendChild(row);
                    });
                }
            } catch (e) {
                console.error('Error loading volunteers:', e);
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 2rem; color: #dc3545;">Error loading volunteers. Please try again.</td></tr>';
            }

            document.getElementById('assignVolunteersModal').classList.add('active');
        }

        function closeAssignVolunteersModal() {
            document.getElementById('assignVolunteersModal').classList.remove('active');
            document.getElementById('assignVolunteersForm').reset();
            document.getElementById('volunteerChoicesBody').innerHTML = '';
        }

        // Save volunteer activities to localStorage so Volunteer List can show them
        function loadVolunteerActivities() {
            try {
                const raw = localStorage.getItem('volunteerActivities');
                return raw ? JSON.parse(raw) : [];
            } catch (e) {
                console.error('Failed to load volunteer activities', e);
                return [];
            }
        }

        function saveVolunteerActivities(activities) {
            localStorage.setItem('volunteerActivities', JSON.stringify(activities));
        }

        async function saveVolunteerAssignments(event) {
            event.preventDefault();
            const requestId = document.getElementById('currentRequestId').value;
            const req = requestData[requestId];
            if (!req) {
                alert('Request not found');
                return;
            }
            const assignmentRoleInput = document.getElementById('assignmentRole').value.trim();
            const finalRole = assignmentRoleInput || req.role;

            const checkboxes = document.querySelectorAll('input[name="selectedVolunteers"]:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one volunteer to assign.');
                return;
            }

            // Load volunteers from database to get full details
            let volunteers = {};
            try {
                const response = await fetch('api/volunteers.php');
                const result = await response.json();
                if (result.success && result.data) {
                    result.data.forEach(v => {
                        volunteers[v.id] = v;
                    });
                }
            } catch (e) {
                console.error('Error loading volunteers:', e);
            }

            const activities = loadVolunteerActivities();
            const assignedVolunteersPayload = [];

            checkboxes.forEach(cb => {
                const id = cb.value;
                const v = volunteers[id];
                if (!v) {
                    console.warn('Volunteer not found:', id);
                    return;
                }

                const activity = {
                    volunteerId: id,
                    fullName: v.name,
                    contactNumber: v.contact,
                    role: finalRole,
                    checkInStatus: 'Pending',
                    emergencyContactName: v.emergency_contact_name || '',
                    emergencyContactNumber: v.emergency_contact_number || '',
                    requestId: req.requestId,
                    eventType: req.eventType,
                    eventTitle: req.eventTitle,
                    eventDate: req.eventDate,
                    callTime: req.callTime,
                    endTime: req.endTime,
                    venue: req.venue
                };

                activities.push(activity);
                assignedVolunteersPayload.push({
                    VolunteerID: id,
                    FullName: v.name,
                    ContactNumber: v.contact,
                    Role: finalRole,
                    CheckInStatus: 'Pending',
                    EmergencyContactName: v.emergencyContactName || '',
                    EmergencyContactNumber: v.emergencyContactNumber || ''
                });
            });

            saveVolunteerActivities(activities);
            sendAssignmentsToExternalSystem(req, assignedVolunteersPayload);

            alert('Volunteers assigned and activities recorded successfully.');
            closeAssignVolunteersModal();
        }

        // Stub to send assigned volunteers back to Campaign Planning & Event systems
        function sendAssignmentsToExternalSystem(request, volunteers) {
            const payload = {
                requestId: request.requestId,
                eventType: request.eventType,
                eventTitle: request.eventTitle,
                audienceType: request.audienceType,
                eventDate: request.eventDate,
                callTime: request.callTime,
                endTime: request.endTime,
                venue: request.venue,
                role: request.role,
                volunteers: volunteers
            };

            // Adjust endpoint/contract to match the other system
            fetch('api/send_volunteer_assignment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).catch(err => {
                console.error('Failed to send assignments to external system', err);
            });
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const assignModal = document.getElementById('assignVolunteersModal');
            const viewReqModal = document.getElementById('viewRequestModal');

            if (event.target === assignModal) {
                closeAssignVolunteersModal();
            }
            if (event.target === viewReqModal) {
                closeViewRequestModal();
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeRequestData();
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
        
        // Notification System
        let notificationDropdown = null;
        let notificationBadge = null;
        let notificationList = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            notificationDropdown = document.getElementById('notificationDropdown');
            notificationBadge = document.getElementById('notificationBadge');
            notificationList = document.getElementById('notificationList');
            
            if (notificationDropdown && notificationBadge && notificationList) {
                loadNotifications();
                // Refresh notifications every 30 seconds
                setInterval(loadNotifications, 30000);
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (notificationDropdown && !event.target.closest('.notification-container')) {
                        notificationDropdown.classList.remove('show');
                    }
                });
            }
        });
        
        function toggleNotifications() {
            if (notificationDropdown) {
                notificationDropdown.classList.toggle('show');
                if (notificationDropdown.classList.contains('show')) {
                    loadNotifications();
                }
            }
        }
        
        async function loadNotifications() {
            try {
                // Sync activities first
                await fetch('api/notifications.php?action=sync');
                
                // Then load notifications
                const response = await fetch('api/notifications.php?action=list');
                const data = await response.json();
                
                if (data.success) {
                    updateNotificationBadge(data.unread_count);
                    renderNotifications(data.notifications);
                }
            } catch (error) {
                console.error('Error loading notifications:', error);
            }
        }
        
        function updateNotificationBadge(count) {
            if (notificationBadge) {
                if (count > 0) {
                    notificationBadge.textContent = count > 99 ? '99+' : count;
                    notificationBadge.classList.add('show');
                } else {
                    notificationBadge.classList.remove('show');
                }
            }
        }
        
        function renderNotifications(notifications) {
            if (!notificationList) return;
            
            if (notifications.length === 0) {
                notificationList.innerHTML = `
                    <div class="notification-empty">
                        <i class="fas fa-bell-slash"></i>
                        <p>No notifications</p>
                    </div>
                `;
                return;
            }
            
            notificationList.innerHTML = notifications.map(notif => {
                let iconClass, icon;
                if (notif.type === 'complaint' || notif.type === 'incident') {
                    iconClass = 'complaint';
                    icon = 'fa-file-alt';
                } else if (notif.type === 'tip') {
                    iconClass = 'tip';
                    icon = 'fa-comments';
                } else if (notif.type === 'volunteer' || notif.type === 'volunteer_request') {
                    iconClass = 'volunteer';
                    icon = 'fa-handshake';
                } else if (notif.type === 'login') {
                    iconClass = 'login';
                    icon = 'fa-sign-in-alt';
                } else if (notif.type === 'logout') {
                    iconClass = 'logout';
                    icon = 'fa-sign-out-alt';
                } else if (notif.type === 'event' || notif.type === 'event_report' || notif.type === 'patrol') {
                    iconClass = 'event';
                    icon = 'fa-bullhorn';
                } else {
                    iconClass = 'event';
                    icon = 'fa-bullhorn';
                }
                
                const safeLink = (notif.link || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                
                return `
                    <div class="notification-item ${notif.is_read ? '' : 'unread'}" 
                         onclick="handleNotificationClick(${notif.id}, '${safeLink}')">
                        <div class="notification-icon ${iconClass}">
                            <i class="fas ${icon}"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">${escapeHtml(notif.title)}</div>
                            <div class="notification-message">${escapeHtml(notif.message)}</div>
                            <div class="notification-time">${notif.time_ago}</div>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        function handleNotificationClick(id, link) {
            // Mark as read
            fetch('api/notifications.php?action=mark_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + id
            });
            
            // Remove unread class
            const item = event.currentTarget;
            item.classList.remove('unread');
            
            // Navigate if link exists
            if (link && link !== '') {
                window.location.href = link;
            }
            
            // Reload notifications to update badge
            loadNotifications();
        }
        
        async function markAllAsRead() {
            try {
                await fetch('api/notifications.php?action=mark_read', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                });
                loadNotifications();
            } catch (error) {
                console.error('Error marking all as read:', error);
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>


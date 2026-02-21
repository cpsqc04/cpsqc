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
    <title>Patrol List - Alertara</title>
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
        .status-available { background: #d1e7dd; color: #0f5132; }
        .status-assigned { background: #fff3cd; color: #856404; }
        .status-off-duty { background: #f8d7da; color: #842029; }
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
        @media (max-width: 768px) { .sidebar { width: 320px; transform: translateX(-100%); transition: transform 0.3s ease; } .sidebar.mobile-open { transform: translateX(0); } .sidebar.collapsed { width: 80px; transform: translateX(0); } .main-wrapper { margin-left: 0; } body.sidebar-collapsed .main-wrapper { margin-left: 80px; } .modal-content { width: 95%; margin: 10% auto; padding: 1.5rem; } .search-container { flex-direction: column; } .btn-add { width: 100%; justify-content: center; } }
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <a href="index.php" style="display: block; cursor: pointer;">
                    <img src="images/tara.png" alt="Alertara Logo" style="display: block;">
                </a>
                <div class="user-name-display">
                    <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
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
            <div class="nav-module">
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
                    <a href="schedule-management.php" class="nav-submodule" data-tooltip="Schedule Management">
                        <span class="nav-submodule-icon"><i class="fas fa-calendar"></i></span>
                        <span class="nav-submodule-text">Schedule Management</span>
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
                    <a href="patrol-list.php" class="nav-submodule active" data-tooltip="Patrol List">
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
                <h1 class="page-title">Patrol List</h1>
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
                <div class="search-container">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search patrol officers by name, badge number, or schedule..." onkeyup="filterPatrolOfficers()">
                    </div>
                    <button class="btn-add" onclick="openAddOfficerModal()">
                        <i class="fas fa-plus"></i> Add Officer
                    </button>
                </div>
                <div class="table-container">
                    <table id="patrolOfficersTable">
                        <thead>
                            <tr>
                                <th>Badge Number</th>
                                <th>Officer Name</th>
                                <th>Contact Number</th>
                                <th>Schedule</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="patrolOfficersTableBody">
                            <!-- Patrol officers will be loaded from database via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Officer Modal -->
    <div id="addOfficerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Patrol Officer</h2>
                <span class="close" onclick="closeAddOfficerModal()">&times;</span>
            </div>
            <form id="addOfficerForm" onsubmit="saveOfficer(event)">
                <div class="form-group">
                    <label for="officerBadgeNumber">Badge Number *</label>
                    <input type="text" id="officerBadgeNumber" name="badgeNumber" required>
                </div>
                <div class="form-group">
                    <label for="officerName">Officer Name *</label>
                    <input type="text" id="officerName" name="name" required>
                </div>
                <div class="form-group">
                    <label for="officerContact">Contact Number *</label>
                    <input type="text" id="officerContact" name="contact" required>
                </div>
                <div class="form-group">
                    <label for="officerSchedule">Schedule *</label>
                    <input type="text" id="officerSchedule" name="schedule" placeholder="e.g., Mon-Fri, 08:00-16:00" required>
                </div>
                <div class="form-group">
                    <label for="officerStatus">Status *</label>
                    <select id="officerStatus" name="status" required>
                        <option value="Available">Available</option>
                        <option value="Assigned">Assigned</option>
                        <option value="Off-Duty">Off-Duty</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeAddOfficerModal()">Cancel</button>
                    <button type="submit" class="btn-save">Add Officer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Officer Modal -->
    <div id="viewOfficerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Officer Details</h2>
                <span class="close" onclick="closeViewOfficerModal()">&times;</span>
            </div>
            <div id="viewOfficerContent">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeViewOfficerModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Officer Modal -->
    <div id="editOfficerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Patrol Officer</h2>
                <span class="close" onclick="closeEditOfficerModal()">&times;</span>
            </div>
            <form id="editOfficerForm" onsubmit="updateOfficer(event)">
                <input type="hidden" id="editOfficerId" name="id">
                <div class="form-group">
                    <label for="editOfficerBadgeNumber">Badge Number *</label>
                    <input type="text" id="editOfficerBadgeNumber" name="badgeNumber" required>
                </div>
                <div class="form-group">
                    <label for="editOfficerName">Officer Name *</label>
                    <input type="text" id="editOfficerName" name="name" required>
                </div>
                <div class="form-group">
                    <label for="editOfficerContact">Contact Number *</label>
                    <input type="text" id="editOfficerContact" name="contact" required>
                </div>
                <div class="form-group">
                    <label for="editOfficerSchedule">Schedule *</label>
                    <input type="text" id="editOfficerSchedule" name="schedule" placeholder="e.g., Mon-Fri, 08:00-16:00" required>
                </div>
                <div class="form-group">
                    <label for="editOfficerStatus">Status *</label>
                    <select id="editOfficerStatus" name="status" required>
                        <option value="Available">Available</option>
                        <option value="Assigned">Assigned</option>
                        <option value="Off-Duty">Off-Duty</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeEditOfficerModal()">Cancel</button>
                    <button type="submit" class="btn-save">Update Officer</button>
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
            loadPatrols();
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
        function filterPatrolOfficers() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('patrolOfficersTableBody');
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
        // Patrol officer data storage (loaded from database)
        let officerData = {};
        
        // Load patrols from database
        async function loadPatrols() {
            try {
                const response = await fetch('api/patrols.php');
                const result = await response.json();
                
                if (!result.success) {
                    console.error(result.message || 'Failed to load patrols');
                    return;
                }
                
                const patrols = result.data || [];
                const tbody = document.getElementById('patrolOfficersTableBody');
                tbody.innerHTML = '';
                
                // Store patrols by id for easy lookup
                officerData = {};
                patrols.forEach(p => {
                    officerData[p.id] = p;
                });
                
                // Populate table
                patrols.forEach(p => {
                    addTableRow(p.id);
                });
            } catch (e) {
                console.error('Error loading patrols:', e);
            }
        }
        
        function addTableRow(id) {
            const officer = officerData[id];
            if (!officer) return;
            
            const tbody = document.getElementById('patrolOfficersTableBody');
            const row = document.createElement('tr');
            row.setAttribute('data-officer-id', id);
            
            const statusClass = officer.status === 'Available' ? 'status-available' : 
                                officer.status === 'Assigned' ? 'status-assigned' : 
                                'status-off-duty';
            
            row.innerHTML = `
                <td>${officer.badge_number || ''}</td>
                <td>${officer.officer_name || ''}</td>
                <td>${officer.contact_number || ''}</td>
                <td>${officer.schedule || ''}</td>
                <td><span class="status-badge ${statusClass}">${officer.status || 'Available'}</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-view" onclick="viewOfficer('${id}')">View</button>
                        <button class="btn-edit" onclick="editOfficer('${id}')">Edit</button>
                    </div>
                </td>
            `;
            
            tbody.appendChild(row);
        }

        function openAddOfficerModal() {
            document.getElementById('addOfficerModal').style.display = 'block';
            document.getElementById('addOfficerForm').reset();
        }

        function closeAddOfficerModal() {
            document.getElementById('addOfficerModal').style.display = 'none';
        }

        function openViewOfficerModal(id) {
            const officer = officerData[id];
            if (!officer) return;
            
            const content = `
                <div style="line-height: 1.8;">
                    <p><strong>Badge Number:</strong> ${officer.badge_number || ''}</p>
                    <p><strong>Officer Name:</strong> ${officer.officer_name || ''}</p>
                    <p><strong>Contact Number:</strong> ${officer.contact_number || ''}</p>
                    <p><strong>Schedule:</strong> ${officer.schedule || ''}</p>
                    <p><strong>Status:</strong> ${officer.status || 'Available'}</p>
                </div>
            `;
            
            document.getElementById('viewOfficerContent').innerHTML = content;
            document.getElementById('viewOfficerModal').style.display = 'block';
        }

        function closeViewOfficerModal() {
            document.getElementById('viewOfficerModal').style.display = 'none';
        }

        function openEditOfficerModal(id) {
            const officer = officerData[id];
            if (!officer) return;
            
            document.getElementById('editOfficerId').value = officer.id;
            document.getElementById('editOfficerBadgeNumber').value = officer.badge_number || '';
            document.getElementById('editOfficerName').value = officer.officer_name || '';
            document.getElementById('editOfficerContact').value = officer.contact_number || '';
            document.getElementById('editOfficerSchedule').value = officer.schedule || '';
            document.getElementById('editOfficerStatus').value = officer.status || 'Available';
            
            document.getElementById('editOfficerModal').style.display = 'block';
        }

        function closeEditOfficerModal() {
            document.getElementById('editOfficerModal').style.display = 'none';
        }

        function viewOfficer(id) {
            openViewOfficerModal(id);
        }

        function editOfficer(id) {
            openEditOfficerModal(id);
        }

        function deleteOfficer(id) {
            if (!confirm('Are you sure you want to delete this patrol officer?')) {
                return;
            }
            
            fetch('api/patrols.php', {
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
                    alert(result.message || 'Failed to delete patrol officer.');
                    return;
                }
                
                // Reload patrols to refresh the table
                loadPatrols();
                alert('Patrol officer deleted successfully!');
            })
            .catch(err => {
                console.error('Error deleting patrol officer:', err);
                alert('Error deleting patrol officer. Please try again.');
            });
        }

        function saveOfficer(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const apiData = {
                action: 'create',
                badge_number: formData.get('badgeNumber').trim(),
                officer_name: formData.get('name').trim(),
                contact_number: formData.get('contact').trim(),
                schedule: formData.get('schedule').trim(),
                status: formData.get('status')
            };
            
            if (!apiData.badge_number || !apiData.officer_name || !apiData.contact_number || !apiData.schedule) {
                alert('Please fill in all required fields.');
                return;
            }
            
            fetch('api/patrols.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(apiData)
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
                    alert(result.message || 'Failed to save patrol officer.');
                    return;
                }
                
                // Reload patrols to refresh the table
                loadPatrols();
                alert('Patrol officer added successfully!');
                closeAddOfficerModal();
            })
            .catch(err => {
                console.error('Error saving patrol officer:', err);
                alert('Error saving patrol officer: ' + (err.message || 'Please try again.'));
            });
        }

        function updateOfficer(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const id = parseInt(formData.get('id'));
            
            const apiData = {
                action: 'update',
                id: id,
                badge_number: formData.get('badgeNumber').trim(),
                officer_name: formData.get('name').trim(),
                contact_number: formData.get('contact').trim(),
                schedule: formData.get('schedule').trim(),
                status: formData.get('status')
            };
            
            if (!apiData.badge_number || !apiData.officer_name || !apiData.contact_number || !apiData.schedule || !apiData.status) {
                alert('Please fill in all required fields.');
                return;
            }
            
            fetch('api/patrols.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(apiData)
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
                    alert(result.message || 'Failed to update patrol officer.');
                    return;
                }
                
                // Reload patrols to refresh the table
                loadPatrols();
                alert('Patrol officer updated successfully!');
                closeEditOfficerModal();
            })
            .catch(err => {
                console.error('Error updating patrol officer:', err);
                alert('Error updating patrol officer: ' + (err.message || 'Please try again.'));
            });
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addOfficerModal');
            const viewModal = document.getElementById('viewOfficerModal');
            const editModal = document.getElementById('editOfficerModal');
            
            if (event.target === addModal) {
                closeAddOfficerModal();
            }
            if (event.target === viewModal) {
                closeViewOfficerModal();
            }
            if (event.target === editModal) {
                closeEditOfficerModal();
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





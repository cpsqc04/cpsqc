<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/db.php';

$cctvNavActive = 'cctv-request';

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCTV Request - Alertara</title>
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
        .status-fulfilled { background: #d1e7dd; color: #0f5132; }
        .status-rejected { background: #f8d7da; color: #842029; }
        .status-cancelled { background: #e9ecef; color: #6c757d; }
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-view, .btn-manage, .btn-link { padding: 0.5rem 1rem; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; color: #fff; background: var(--primary-color); text-decoration: none; display: inline-flex; align-items: center; }
        .btn-manage { background: #ff9800; }
        .btn-link { background: #6366f1; }
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
        .evidence-panel {
            margin: 1.25rem 0 0;
            padding: 1rem 1.1rem;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: #f8fafb;
        }
        .evidence-panel h3 {
            margin: 0 0 0.35rem;
            font-size: 0.95rem;
            color: var(--tertiary-color);
        }
        .evidence-panel p {
            margin: 0 0 0.85rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }
        .evidence-actions {
            display: flex;
            gap: 0.65rem;
            flex-wrap: wrap;
        }
        .group1-status {
            margin: 0.85rem 0 0;
            padding: 0.7rem 0.85rem;
            border-radius: 8px;
            background: #ecfdf5;
            color: #0f5132;
            font-size: 0.9rem;
            display: none;
        }
        .form-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            align-items: center;
            margin-top: 1.25rem;
            padding-top: 1.1rem;
            border-top: 1px solid var(--border-color);
        }
        .btn-save {
            background: var(--primary-color);
            color: #fff;
            border: none;
            padding: 0.7rem 1.35rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-group1 {
            background: #fff;
            color: #0f5132;
            border: 1px solid #0f5132;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-group1:hover:not(:disabled) { background: #0f5132; color: #fff; }
        .btn-group1:disabled { opacity: 0.55; cursor: not-allowed; }
        .btn-link {
            background: #fff;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            padding: 0.6rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-link:hover { background: var(--primary-color); color: #fff; }
        .btn-cancel {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            padding: 0.7rem 1.1rem;
            border-radius: 8px;
            cursor: pointer;
        }
        .btn-cancel:hover { background: #f1f3f5; }
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
                <h1 class="page-title">CCTV Request</h1>
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
                            <input type="text" id="searchInput" placeholder="Search by request ID, agency, contact, or location..." oninput="filterRequests()">
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
                                    <th>Agency</th>
                                    <th>Contact</th>
                                    <th>Location / Camera</th>
                                    <th>Footage Window</th>
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
                <h2>CCTV Request Details</h2>
                <button class="close-modal" onclick="closeViewModal()">&times;</button>
            </div>
            <div id="viewDetails"></div>
        </div>
    </div>

    <div id="manageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Manage CCTV Request</h2>
                <button class="close-modal" onclick="closeManageModal()">&times;</button>
            </div>
            <p id="manageRequestRef" style="margin:0 0 1rem;font-weight:600;color:var(--tertiary-color);"></p>
            <form id="manageForm" onsubmit="saveManage(event)">
                <input type="hidden" id="manageId">
                <div class="form-group">
                    <label for="manageStatus">Status *</label>
                    <select id="manageStatus" required>
                        <option value="Pending">Pending</option>
                        <option value="Under Review">Under Review</option>
                        <option value="Approved">Approved</option>
                        <option value="Fulfilled">Fulfilled</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="manageApprovedCamera">Approved Camera</label>
                    <select id="manageApprovedCamera"><option value="">Select camera</option></select>
                </div>
                <div class="form-group">
                    <label for="manageActualStart">Actual Footage Start</label>
                    <input type="time" id="manageActualStart">
                </div>
                <div class="form-group">
                    <label for="manageActualEnd">Actual Footage End</label>
                    <input type="time" id="manageActualEnd">
                </div>
                <div class="form-group">
                    <label for="manageReviewNotes">Review Notes (internal)</label>
                    <textarea id="manageReviewNotes" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="manageRejectionReason">Rejection Reason (shown to requester)</label>
                    <textarea id="manageRejectionReason" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label for="manageFulfillmentNotes">Fulfillment Notes</label>
                    <textarea id="manageFulfillmentNotes" rows="2" placeholder="Delivery details, file reference, etc."></textarea>
                </div>
                <div class="evidence-panel">
                    <h3>Evidence delivery</h3>
                    <p>Review matching footage, then send the package to Group 1 for case evidence.</p>
                    <div class="evidence-actions">
                        <a id="openPlaybackLink" href="playback.php" class="btn-link" target="_blank">Open Playback</a>
                        <button type="button" id="sendToGroup1Btn" class="btn-group1" onclick="sendFootageToGroup1()">Send to Group 1</button>
                    </div>
                    <div id="group1EvidenceStatus" class="group1-status"></div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeManageModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
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
            loadCameras();
        });

        let requestData = {};
        let allRequests = [];
        let cameras = [];

        function statusClass(status) {
            return String(status || '').toLowerCase().replace(/\s+/g, '-');
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

        function formatDateTime(value) {
            if (!value) return '—';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        }

        async function loadCameras() {
            try {
                const res = await fetch('api/cameras.php');
                const result = await res.json();
                cameras = result.success ? (result.cameras || []) : [];
                const select = document.getElementById('manageApprovedCamera');
                select.innerHTML = '<option value="">Select camera</option>';
                cameras.forEach(camera => {
                    const option = document.createElement('option');
                    option.value = camera.cameraId;
                    option.textContent = `${camera.cameraId} - ${camera.name}`;
                    select.appendChild(option);
                });
            } catch (e) {
                console.error('Failed to load cameras', e);
            }
        }

        async function loadRequests() {
            try {
                const res = await fetch('api/cctv_requests.php');
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
                document.getElementById('requestsTableBody').innerHTML = '<tr><td colspan="8" style="text-align:center;color:#b91c1c;">Failed to load CCTV requests.</td></tr>';
            }
        }

        function filterRequests() {
            const query = document.getElementById('searchInput').value.trim().toLowerCase();
            const dateFilter = document.getElementById('dateFilter').value;
            const tbody = document.getElementById('requestsTableBody');
            tbody.innerHTML = '';

            const filtered = allRequests.filter(item => {
                const haystack = [
                    item.request_id, item.requesting_agency, item.contact_person,
                    item.contact_number, item.incident_location, item.camera_id, item.status
                ].join(' ').toLowerCase();
                const matchesQuery = query === '' || haystack.includes(query);
                const matchesDate = dateFilter === '' || String(item.incident_date || '').startsWith(dateFilter) || String(item.submitted_at || '').startsWith(dateFilter);
                return matchesQuery && matchesDate;
            });

            if (!filtered.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-secondary);">No CCTV requests found.</td></tr>';
                return;
            }

            filtered.forEach(item => {
                const row = document.createElement('tr');
                const locationLabel = item.camera_id ? item.camera_id : (item.location_description || item.incident_location);
                row.innerHTML = `
                    <td>${item.request_id}</td>
                    <td>${item.requesting_agency}</td>
                    <td>${item.contact_person}<br><small>${item.contact_number}</small></td>
                    <td>${locationLabel}</td>
                    <td>${formatDate(item.incident_date)}<br><small>${formatTime(item.footage_start_time)} - ${formatTime(item.footage_end_time)}</small></td>
                    <td><span class="status-badge status-${statusClass(item.status)}">${item.status}</span></td>
                    <td>${formatDate(item.submitted_at)}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-view" onclick="viewRequest('${item.request_id}')">View</button>
                            <button class="btn-manage" onclick="manageRequest('${item.request_id}')">Manage</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function viewRequest(requestId) {
            const item = requestData[requestId];
            if (!item) return;
            const docButton = item.has_supporting_document
                ? `<button class="btn-view" onclick="viewDocument(${item.id})">View Document</button>`
                : 'None';
            document.getElementById('viewDetails').innerHTML = `
                <div class="detail-row"><span class="detail-label">Request ID</span><span>${item.request_id}</span></div>
                <div class="detail-row"><span class="detail-label">Agency</span><span>${item.requesting_agency}</span></div>
                <div class="detail-row"><span class="detail-label">Contact</span><span>${item.contact_person} (${item.contact_number})</span></div>
                <div class="detail-row"><span class="detail-label">Email</span><span>${item.contact_email || '—'}</span></div>
                <div class="detail-row"><span class="detail-label">Case Reference</span><span>${item.case_reference || '—'}</span></div>
                <div class="detail-row"><span class="detail-label">Purpose</span><span>${item.purpose_details}</span></div>
                <div class="detail-row"><span class="detail-label">Legal Basis</span><span>${item.legal_basis}</span></div>
                <div class="detail-row"><span class="detail-label">Incident Location</span><span>${item.incident_location}</span></div>
                <div class="detail-row"><span class="detail-label">Camera</span><span>${item.camera_id || '—'}</span></div>
                <div class="detail-row"><span class="detail-label">Footage Window</span><span>${formatDate(item.incident_date)} ${formatTime(item.footage_start_time)} - ${formatTime(item.footage_end_time)}</span></div>
                <div class="detail-row"><span class="detail-label">Incident Description</span><span>${item.incident_description}</span></div>
                <div class="detail-row"><span class="detail-label">Delivery Method</span><span>${item.delivery_method}</span></div>
                <div class="detail-row"><span class="detail-label">Supporting Document</span><span>${docButton}</span></div>
                <div class="detail-row"><span class="detail-label">Status</span><span>${item.status}</span></div>
                <div class="detail-row"><span class="detail-label">Review Notes</span><span>${item.review_notes || '—'}</span></div>
                <div class="detail-row"><span class="detail-label">Rejection Reason</span><span>${item.rejection_reason || '—'}</span></div>
                <div class="detail-row"><span class="detail-label">Fulfillment Notes</span><span>${item.fulfillment_notes || '—'}</span></div>
                <div class="detail-row"><span class="detail-label">Group 1 Evidence</span><span>${item.forwarded_to_group1_at ? `Sent ${formatDateTime(item.forwarded_to_group1_at)}${item.group1_evidence_reference_id ? ' — Ref: ' + item.group1_evidence_reference_id : ''}` : '—'}</span></div>
            `;
            document.getElementById('viewModal').classList.add('active');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
        }

        async function viewDocument(id) {
            try {
                const res = await fetch('api/cctv_requests.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_document', id })
                });
                const result = await res.json();
                if (!result.success) throw new Error(result.message || 'Failed to load document');
                window.open(result.data.supporting_document, '_blank');
            } catch (e) {
                alert(e.message || 'Unable to open supporting document.');
            }
        }

        function manageRequest(requestId) {
            const item = requestData[requestId];
            if (!item) return;
            document.getElementById('manageId').value = item.id;
            document.getElementById('manageRequestRef').textContent = 'Request ID: ' + item.request_id;
            document.getElementById('manageStatus').value = item.status;
            document.getElementById('manageApprovedCamera').value = item.approved_camera_id || item.camera_id || '';
            document.getElementById('manageActualStart').value = item.actual_footage_start ? String(item.actual_footage_start).slice(0, 5) : (item.footage_start_time ? String(item.footage_start_time).slice(0, 5) : '');
            document.getElementById('manageActualEnd').value = item.actual_footage_end ? String(item.actual_footage_end).slice(0, 5) : (item.footage_end_time ? String(item.footage_end_time).slice(0, 5) : '');
            document.getElementById('manageReviewNotes').value = item.review_notes || '';
            document.getElementById('manageRejectionReason').value = item.rejection_reason || '';
            document.getElementById('manageFulfillmentNotes').value = item.fulfillment_notes || '';
            const group1Btn = document.getElementById('sendToGroup1Btn');
            const group1Status = document.getElementById('group1EvidenceStatus');
            const blocked = ['Rejected', 'Cancelled'].includes(item.status);
            group1Btn.disabled = Boolean(item.forwarded_to_group1_at) || blocked;
            group1Btn.textContent = item.forwarded_to_group1_at ? 'Already sent' : 'Send to Group 1';
            if (item.forwarded_to_group1_at) {
                group1Status.style.display = 'block';
                group1Status.innerHTML = `<strong>Group 1:</strong> Sent ${formatDateTime(item.forwarded_to_group1_at)}${item.group1_evidence_reference_id ? ' — Ref: ' + item.group1_evidence_reference_id : ''}`;
            } else {
                group1Status.style.display = 'none';
                group1Status.textContent = '';
            }
            const cam = item.approved_camera_id || item.camera_id || '';
            const date = item.incident_date || '';
            const start = item.actual_footage_start ? String(item.actual_footage_start).slice(0, 5) : (item.footage_start_time ? String(item.footage_start_time).slice(0, 5) : '');
            const end = item.actual_footage_end ? String(item.actual_footage_end).slice(0, 5) : (item.footage_end_time ? String(item.footage_end_time).slice(0, 5) : '');
            const playbackParams = new URLSearchParams();
            if (cam) playbackParams.set('camera', cam);
            if (date) playbackParams.set('date', date);
            if (start) playbackParams.set('start', start);
            if (end) playbackParams.set('end', end);
            playbackParams.set('request_id', item.request_id);
            document.getElementById('openPlaybackLink').href = 'playback.php?' + playbackParams.toString();
            document.getElementById('manageModal').classList.add('active');
        }

        function closeManageModal() {
            document.getElementById('manageModal').classList.remove('active');
        }

        function sendFootageToGroup1() {
            const id = parseInt(document.getElementById('manageId').value, 10);
            const item = allRequests.find(row => row.id === id);
            if (!item) return;
            if (item.forwarded_to_group1_at) {
                alert('This CCTV request was already sent to Group 1.');
                return;
            }
            if (!confirm('Send matching CCTV recordings to Group 1 for evidence?\n\nThis will mark the request as Fulfilled.')) {
                return;
            }
            const btn = document.getElementById('sendToGroup1Btn');
            btn.disabled = true;
            btn.textContent = 'Sending...';
            fetch('api/send_cctv_to_group1.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            })
            .then(res => res.json().then(data => ({ ok: res.ok, data })))
            .then(({ ok, data }) => {
                if (!ok || !data.success) {
                    throw new Error(data.message || 'Failed to send footage to Group 1.');
                }
                item.forwarded_to_group1_at = data.data?.forwarded_to_group1_at || new Date().toISOString();
                item.group1_evidence_reference_id = data.data?.group1_evidence_reference_id || '';
                item.forwarded_recording_files = JSON.stringify(data.data?.forwarded_recording_files || []);
                item.status = data.data?.status || 'Fulfilled';
                item.fulfillment_notes = (item.fulfillment_notes ? item.fulfillment_notes + '\n' : '')
                    + 'Sent to Group 1 for evidence'
                    + (item.group1_evidence_reference_id ? ' — Ref: ' + item.group1_evidence_reference_id : '')
                    + ' (' + (data.data?.segment_count || 0) + ' recording segment' + ((data.data?.segment_count || 0) === 1 ? '' : 's') + ').';
                requestData[item.request_id] = item;
                document.getElementById('manageStatus').value = item.status;
                document.getElementById('manageFulfillmentNotes').value = item.fulfillment_notes;
                manageRequest(item.request_id);
                loadRequests();
                alert(data.message || 'CCTV footage sent to Group 1.');
            })
            .catch(err => {
                btn.disabled = false;
                btn.textContent = 'Send to Group 1';
                alert(err.message || 'Failed to send footage to Group 1.');
            });
        }

        function saveManage(event) {
            event.preventDefault();
            const id = parseInt(document.getElementById('manageId').value, 10);
            const status = document.getElementById('manageStatus').value;
            const rejectionReason = document.getElementById('manageRejectionReason').value.trim();
            if (status === 'Rejected' && rejectionReason === '') {
                alert('Please provide a rejection reason.');
                return;
            }
            fetch('api/cctv_requests.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'manage',
                    id,
                    status,
                    approved_camera_id: document.getElementById('manageApprovedCamera').value,
                    actual_footage_start: document.getElementById('manageActualStart').value,
                    actual_footage_end: document.getElementById('manageActualEnd').value,
                    review_notes: document.getElementById('manageReviewNotes').value.trim(),
                    rejection_reason: rejectionReason,
                    fulfillment_notes: document.getElementById('manageFulfillmentNotes').value.trim()
                })
            })
            .then(res => res.json())
            .then(result => {
                if (!result.success) throw new Error(result.message || 'Update failed');
                closeManageModal();
                loadRequests();
                alert('CCTV request updated successfully.');
            })
            .catch(err => alert(err.message || 'Failed to update request.'));
        }

        window.onclick = function(event) {
            if (event.target === document.getElementById('viewModal')) closeViewModal();
            if (event.target === document.getElementById('manageModal')) closeManageModal();
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
</body>
</html>

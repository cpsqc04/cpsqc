<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/db.php';

$cctvNavActive = 'camera-management';

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Camera Management - Alertara</title>
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
        .status-at-hall { background: #d1e7dd; color: #0f5132; }
        .status-timed-out { background: #e9ecef; color: #6c757d; }
        .status-online { background: #d1e7dd; color: #0f5132; }
        .status-offline { background: #f8d7da; color: #842029; }
        .status-maintenance { background: #fff3cd; color: #856404; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .btn-primary { background: var(--primary-color); color: #fff; border: none; padding: 0.75rem 1.2rem; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-primary:hover { background: #4ca8a6; }
        .btn-secondary { background: #fff; color: var(--primary-color); border: 1px solid var(--primary-color); padding: 0.75rem 1.2rem; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-secondary:hover { background: rgba(76, 138, 137, 0.08); }
        .btn-secondary:disabled { opacity: 0.6; cursor: not-allowed; }
        .toolbar-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; }
        .scan-panel { margin-top: 1rem; padding: 1rem; border: 1px solid var(--border-color); border-radius: 10px; background: #f8fafc; display: none; }
        .scan-panel.show { display: block; }
        .scan-status { color: var(--text-secondary); font-size: 0.9rem; margin: 0 0 0.75rem; }
        .scan-list { display: flex; flex-direction: column; gap: 0.5rem; }
        .scan-item { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; padding: 0.75rem 0.9rem; background: #fff; border: 1px solid var(--border-color); border-radius: 8px; flex-wrap: wrap; }
        .scan-item-meta { font-size: 0.85rem; color: var(--text-secondary); }
        .scan-item strong { color: var(--text-color); font-size: 1rem; }
        .btn-use-ip { background: var(--primary-color); color: #fff; border: none; padding: 0.45rem 0.85rem; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem; }
        .btn-use-ip:hover { background: #4ca8a6; }
        .toast-popup {
            position: fixed;
            top: 1.25rem;
            right: 1.25rem;
            z-index: 4000;
            background: #059669;
            color: #fff;
            padding: 0.85rem 1.15rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: 0 10px 30px rgba(5, 150, 105, 0.35);
            opacity: 0;
            transform: translateY(-8px);
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        .toast-popup.show {
            opacity: 1;
            transform: translateY(0);
        }
        .confidence-pill { display: inline-block; font-size: 0.75rem; font-weight: 600; padding: 0.15rem 0.5rem; border-radius: 999px; margin-left: 0.35rem; }
        .confidence-high { background: #d1fae5; color: #065f46; }
        .confidence-medium { background: #fef3c7; color: #92400e; }
        .confidence-low { background: #e5e7eb; color: #374151; }
        .ip-scan-row { display: flex; gap: 0.5rem; align-items: stretch; }
        .ip-scan-row input { flex: 1; }
        .btn-edit, .btn-delete { padding: 0.45rem 0.85rem; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; color: #fff; }
        .btn-edit { background: var(--primary-color); margin-right: 0.35rem; }
        .btn-edit:hover { background: #4ca8a6; }
        .btn-delete { background: #dc3545; }
        .stream-badge { display: inline-block; padding: 0.15rem 0.55rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: #e0f2fe; color: #0369a1; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: var(--card-bg); border-radius: 12px; padding: 2rem; width: 92%; max-width: 720px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color); }
        .modal-header h2 { margin: 0; color: var(--tertiary-color); font-size: 1.25rem; }
        .close-modal { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { display: block; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.92rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; box-sizing: border-box; font: inherit; }
        .form-hint { font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.35rem; }
        .form-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.25rem; }
        .btn-cancel { background: #e9ecef; color: var(--text-color); border: none; padding: 0.75rem 1.1rem; border-radius: 8px; cursor: pointer; }
        .btn-save { background: var(--primary-color); color: #fff; border: none; padding: 0.75rem 1.25rem; border-radius: 8px; cursor: pointer; font-weight: 600; }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
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
                <h1 class="page-title">Camera Management</h1>
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
                    <div class="toolbar">
                        <h2 class="section-title" style="margin:0;"><i class="fas fa-video"></i> Registered Cameras</h2>
                        <div class="toolbar-actions">
                            <button type="button" class="btn-secondary" id="scanLanBtn" onclick="startCameraScan()"><i class="fas fa-network-wired"></i> Scan LAN for Cameras</button>
                            <button type="button" class="btn-primary" onclick="openCameraModal()"><i class="fas fa-plus"></i> Add Camera</button>
                        </div>
                    </div>
                    <p style="margin:0 0 1rem;color:var(--text-secondary);font-size:0.9rem;">
                        Edit camera IP, credentials, and stream here. Changes save to <strong>cameras.json</strong> on the server right away; the on-site PC syncs them automatically (do not edit the JSON file by hand).
                    </p>
                    <div class="scan-panel" id="scanPanel">
                        <p class="scan-status" id="scanStatus">Ready to scan.</p>
                        <div class="scan-list" id="scanList"></div>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Camera ID</th>
                                    <th>Name</th>
                                    <th>Location</th>
                                    <th>Network</th>
                                    <th>Stream</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="camerasTableBody">
                                <tr><td colspan="7" style="text-align:center;color:var(--text-secondary);">Loading cameras…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="cameraModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="cameraModalTitle">Add Camera</h2>
                <button class="close-modal" onclick="closeCameraModal()">&times;</button>
            </div>
            <form id="cameraForm" onsubmit="saveCamera(event)">
                <input type="hidden" id="cameraDbId">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="cameraName">Camera Name *</label>
                        <input type="text" id="cameraName" required placeholder="Main Entrance Camera">
                    </div>
                    <div class="form-group">
                        <label for="cameraStatus">Status</label>
                        <select id="cameraStatus">
                            <option value="Online">Online</option>
                            <option value="Offline">Offline</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label for="cameraLocation">Location *</label>
                        <input type="text" id="cameraLocation" required placeholder="Susano Road, Barangay San Agustin, Quezon City">
                    </div>
                    <div class="form-group">
                        <label for="cameraIp">IP Address *</label>
                        <div class="ip-scan-row">
                            <input type="text" id="cameraIp" required placeholder="192.168.1.6">
                            <button type="button" class="btn-secondary" style="padding:0.65rem 0.9rem;white-space:nowrap;" onclick="startCameraScan(true)" title="Scan LAN"><i class="fas fa-search"></i></button>
                        </div>
                        <div class="form-hint">Camera stays on the router; this PC uses the same Wi‑Fi/LAN (no direct camera cable needed). Use <strong>Scan LAN</strong> to find the IP.</div>
                    </div>
                    <div class="form-group">
                        <label for="cameraPort">RTSP Port</label>
                        <input type="text" id="cameraPort" value="554" placeholder="554">
                    </div>
                    <div class="form-group">
                        <label for="cameraUsername">Camera Username *</label>
                        <input type="text" id="cameraUsername" required placeholder="admin" autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label for="cameraPassword">Camera Password *</label>
                        <input type="password" id="cameraPassword" placeholder="Camera login password" autocomplete="current-password">
                        <div class="form-hint" id="passwordHint" style="display:none;">Leave blank to keep the current password.</div>
                    </div>
                    <div class="form-group">
                        <label for="cameraStreamType">Stream Type</label>
                        <select id="cameraStreamType">
                            <option value="main">Main stream (clear quality — recommended on LAN)</option>
                            <option value="sub">Sub stream (lower bandwidth)</option>
                        </select>
                        <div class="form-hint">Camera and this PC should be on the same router. Use Main for original clarity.</div>
                    </div>
                    <div class="form-group full" id="rtspUrlGroup" style="display:none;">
                        <label for="cameraRtspUrl">RTSP URL</label>
                        <input type="text" id="cameraRtspUrl" readonly placeholder="Auto-filled from IP, port, username, password, and stream">
                        <div class="form-hint">Auto-generated for the on-site PC. Updates as you change IP / credentials / stream.</div>
                    </div>
                    <div class="form-group full">
                        <label for="cameraDescription">Description</label>
                        <textarea id="cameraDescription" rows="2" placeholder="Optional notes about this camera"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeCameraModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Camera</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width:480px;">
            <div class="modal-header">
                <h2>Delete Camera</h2>
                <button class="close-modal" onclick="closeDeleteModal()">&times;</button>
            </div>
            <p style="margin:0 0 1.25rem;color:var(--text-secondary);">Remove <strong id="deleteCameraLabel"></strong>? This cannot be undone.</p>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn-delete" style="padding:0.75rem 1.1rem;border-radius:8px;" onclick="confirmDeleteCamera()">Delete</button>
            </div>
        </div>
    </div>

    <div id="toastPopup" class="toast-popup" role="status" aria-live="polite">Camera updated</div>

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
            loadCameras();
        });

        let cameras = [];
        let deleteTargetId = null;
        let scanPollTimer = null;
        let lastScanCameras = [];
        let toastTimer = null;
        let editingCamera = null;

        function showToast(message) {
            const el = document.getElementById('toastPopup');
            if (!el) return;
            el.textContent = message || 'Camera updated';
            el.classList.add('show');
            if (toastTimer) clearTimeout(toastTimer);
            toastTimer = setTimeout(function() {
                el.classList.remove('show');
            }, 2200);
        }

        function buildRtspUrl(ip, port, username, password, streamType) {
            const safeIp = String(ip || '').trim();
            const safePort = String(port || '554').trim() || '554';
            const user = encodeURIComponent(String(username || '').trim());
            const pass = encodeURIComponent(String(password || ''));
            const path = String(streamType || 'main') === 'sub' ? 'h264Preview_01_sub' : 'h264Preview_01_main';
            if (!safeIp) return '';
            if (username) {
                return 'rtsp://' + user + ':' + pass + '@' + safeIp + ':' + safePort + '/' + path;
            }
            return 'rtsp://' + safeIp + ':' + safePort + '/' + path;
        }

        function refreshRtspPreview() {
            const rtspInput = document.getElementById('cameraRtspUrl');
            const group = document.getElementById('rtspUrlGroup');
            if (!rtspInput || !group || group.style.display === 'none') return;

            const ip = document.getElementById('cameraIp').value.trim();
            const port = document.getElementById('cameraPort').value.trim() || '554';
            const username = document.getElementById('cameraUsername').value.trim();
            const typedPassword = document.getElementById('cameraPassword').value;
            const streamType = document.getElementById('cameraStreamType').value;
            const password = typedPassword !== ''
                ? typedPassword
                : (editingCamera ? (editingCamera.password || '') : '');

            if (editingCamera && typedPassword === '' && editingCamera.rtspUrl && ip === (editingCamera.ipAddress || '')) {
                // Keep saved URL until IP/credentials fields change enough to rebuild.
                const sameUser = username === (editingCamera.username || '');
                const samePort = port === String(editingCamera.port || '554');
                const sameStream = streamType === (editingCamera.streamType || 'main');
                if (sameUser && samePort && sameStream) {
                    rtspInput.value = editingCamera.rtspUrl;
                    return;
                }
            }

            rtspInput.value = buildRtspUrl(ip, port, username, password, streamType);
        }

        function bindRtspPreviewInputs() {
            ['cameraIp', 'cameraPort', 'cameraUsername', 'cameraPassword', 'cameraStreamType'].forEach(function(id) {
                const el = document.getElementById(id);
                if (!el || el.dataset.rtspBound === '1') return;
                el.dataset.rtspBound = '1';
                el.addEventListener('input', refreshRtspPreview);
                el.addEventListener('change', refreshRtspPreview);
            });
        }

        function statusClass(status) {
            return String(status || 'offline').toLowerCase();
        }

        function showScanPanel() {
            document.getElementById('scanPanel').classList.add('show');
        }

        function setScanStatus(message) {
            document.getElementById('scanStatus').textContent = message;
        }

        function renderScanResults(list) {
            const wrap = document.getElementById('scanList');
            lastScanCameras = Array.isArray(list) ? list : [];
            if (!lastScanCameras.length) {
                wrap.innerHTML = '<div class="scan-item-meta">No camera candidates found on the scanned subnet(s).</div>';
                return;
            }
            wrap.innerHTML = lastScanCameras.map(function(cam, index) {
                const conf = String(cam.confidence || 'low').toLowerCase();
                const ports = (cam.open_ports || []).join(', ');
                const hint = cam.hint ? (' · ' + cam.hint) : '';
                return `
                    <div class="scan-item">
                        <div>
                            <strong>${escapeHtml(cam.ip)}</strong>
                            <span class="confidence-pill confidence-${escapeHtml(conf)}">${escapeHtml(conf)}</span>
                            <div class="scan-item-meta">ports ${escapeHtml(ports)}${escapeHtml(hint)}</div>
                        </div>
                        <button type="button" class="btn-use-ip" onclick="useDiscoveredCamera(${index})">Use IP</button>
                    </div>
                `;
            }).join('');
        }

        function useDiscoveredCamera(index) {
            const cam = lastScanCameras[index];
            if (!cam) return;
            openCameraModal();
            document.getElementById('cameraIp').value = cam.ip || '';
            document.getElementById('cameraPort').value = String(cam.rtsp_port || 554);
            if (!document.getElementById('cameraUsername').value) {
                document.getElementById('cameraUsername').value = 'admin';
            }
            if (!document.getElementById('cameraName').value) {
                document.getElementById('cameraName').value = 'Camera ' + (cam.ip || '');
            }
            if (!document.getElementById('cameraLocation').value) {
                document.getElementById('cameraLocation').value = 'Barangay San Agustin, Quezon City';
            }
            document.getElementById('cameraStatus').value = 'Online';
        }

        function stopScanPolling() {
            if (scanPollTimer) {
                clearInterval(scanPollTimer);
                scanPollTimer = null;
            }
            const btn = document.getElementById('scanLanBtn');
            if (btn) btn.disabled = false;
        }

        function applyScanJob(job) {
            if (!job) return false;
            const status = job.status || '';
            if (status === 'pending' || status === 'running') {
                setScanStatus(job.message || 'Scanning…');
                return false;
            }
            if (status === 'done') {
                setScanStatus(job.message || ('Found ' + (job.count || 0) + ' camera(s).'));
                renderScanResults(job.cameras || []);
                stopScanPolling();
                return true;
            }
            if (status === 'error') {
                setScanStatus(job.error || job.message || 'Scan failed.');
                document.getElementById('scanList').innerHTML = '';
                stopScanPolling();
                return true;
            }
            return false;
        }

        async function pollCameraScan() {
            try {
                const res = await fetch('api/cctv_camera_scan.php', { cache: 'no-store' });
                const result = await res.json();
                if (result.job) {
                    applyScanJob(result.job);
                }
            } catch (e) {
                /* keep polling */
            }
        }

        async function startCameraScan(fromModal) {
            showScanPanel();
            setScanStatus('Starting LAN scan…');
            document.getElementById('scanList').innerHTML = '';
            if (scanPollTimer) {
                clearInterval(scanPollTimer);
                scanPollTimer = null;
            }
            const btn = document.getElementById('scanLanBtn');
            if (btn) btn.disabled = true;

            try {
                const res = await fetch('api/cctv_camera_scan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'start' })
                });
                const result = await res.json();
                if (!result.success && !result.job) {
                    setScanStatus(result.message || 'Could not start scan.');
                    if (btn) btn.disabled = false;
                    return;
                }
                const job = result.job;
                if (job && (job.status === 'done' || job.status === 'error')) {
                    applyScanJob(job);
                    return;
                }
                setScanStatus(
                    (job && job.message)
                        || (result.message)
                        || 'Waiting for on-site PC to scan the network…'
                );
                scanPollTimer = setInterval(pollCameraScan, 2500);
            } catch (e) {
                setScanStatus('Network error while starting scan.');
                if (btn) btn.disabled = false;
            }
        }

        async function loadCameras() {
            const tbody = document.getElementById('camerasTableBody');
            try {
                const res = await fetch('api/cameras.php');
                const result = await res.json();
                if (!result.success) throw new Error(result.error || 'Failed to load cameras');
                cameras = result.cameras || [];
                renderCameras();
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#b91c1c;">Failed to load cameras.</td></tr>';
            }
        }

        function renderCameras() {
            const tbody = document.getElementById('camerasTableBody');
            if (!cameras.length) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-secondary);">No cameras registered yet.</td></tr>';
                return;
            }
            tbody.innerHTML = cameras.map(function(cam) {
                const streamLabel = cam.streamType === 'main' ? 'Main' : 'Sub';
                return `
                    <tr>
                        <td>${escapeHtml(cam.cameraId)}</td>
                        <td>${escapeHtml(cam.name)}</td>
                        <td>${escapeHtml(cam.location)}</td>
                        <td>${escapeHtml(cam.ipAddress)}:${escapeHtml(cam.port || '554')}</td>
                        <td><span class="stream-badge">${streamLabel}</span></td>
                        <td><span class="status-badge status-${statusClass(cam.status)}">${escapeHtml(cam.status || 'Offline')}</span></td>
                        <td>
                            <button type="button" class="btn-edit" onclick="editCamera('${cam.id}')">Edit</button>
                            <button type="button" class="btn-delete" onclick="deleteCamera('${cam.id}')">Delete</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function openCameraModal(camera) {
            editingCamera = camera || null;
            bindRtspPreviewInputs();
            document.getElementById('cameraModalTitle').textContent = camera ? 'Edit Camera' : 'Add Camera';
            document.getElementById('cameraDbId').value = camera ? camera.id : '';
            document.getElementById('cameraName').value = camera ? camera.name : '';
            document.getElementById('cameraLocation').value = camera ? camera.location : '';
            document.getElementById('cameraIp').value = camera ? camera.ipAddress : '';
            document.getElementById('cameraPort').value = camera ? (camera.port || '554') : '554';
            document.getElementById('cameraUsername').value = camera ? (camera.username || 'admin') : 'admin';
            document.getElementById('cameraPassword').value = '';
            document.getElementById('cameraPassword').required = !camera;
            document.getElementById('cameraPassword').placeholder = camera
                ? 'Leave blank to keep current password'
                : 'Camera login password';
            document.getElementById('passwordHint').style.display = camera ? 'block' : 'none';
            document.getElementById('cameraStreamType').value = camera ? (camera.streamType || 'main') : 'main';
            document.getElementById('cameraStatus').value = camera ? (camera.status || 'Online') : 'Online';
            document.getElementById('cameraDescription').value = camera ? (camera.description || '') : '';

            const rtspGroup = document.getElementById('rtspUrlGroup');
            if (camera) {
                rtspGroup.style.display = 'block';
                document.getElementById('cameraRtspUrl').value = camera.rtspUrl || '';
                refreshRtspPreview();
            } else {
                rtspGroup.style.display = 'none';
                document.getElementById('cameraRtspUrl').value = '';
            }

            document.getElementById('cameraModal').classList.add('active');
        }

        function closeCameraModal() {
            editingCamera = null;
            document.getElementById('cameraModal').classList.remove('active');
        }

        function editCamera(id) {
            const camera = cameras.find(function(item) { return String(item.id) === String(id); });
            if (camera) openCameraModal(camera);
        }

        function deleteCamera(id) {
            const camera = cameras.find(function(item) { return String(item.id) === String(id); });
            if (!camera) return;
            deleteTargetId = id;
            document.getElementById('deleteCameraLabel').textContent = camera.cameraId + ' — ' + camera.name;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            deleteTargetId = null;
            document.getElementById('deleteModal').classList.remove('active');
        }

        async function confirmDeleteCamera() {
            if (!deleteTargetId) return;
            try {
                const res = await fetch('api/cameras.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: String(deleteTargetId) })
                });
                const result = await res.json();
                if (!result.success) throw new Error(result.error || 'Delete failed');
                closeDeleteModal();
                loadCameras();
            } catch (e) {
                alert(e.message || 'Failed to delete camera.');
            }
        }

        async function saveCamera(event) {
            event.preventDefault();
            const id = document.getElementById('cameraDbId').value;
            const payload = {
                action: id ? 'update' : 'create',
                name: document.getElementById('cameraName').value.trim(),
                location: document.getElementById('cameraLocation').value.trim(),
                ipAddress: document.getElementById('cameraIp').value.trim(),
                port: document.getElementById('cameraPort').value.trim() || '554',
                username: document.getElementById('cameraUsername').value.trim(),
                password: document.getElementById('cameraPassword').value,
                streamType: document.getElementById('cameraStreamType').value,
                status: document.getElementById('cameraStatus').value,
                description: document.getElementById('cameraDescription').value.trim()
            };
            if (id) payload.id = String(id);
            if (!payload.name || !payload.location || !payload.ipAddress || !payload.username) {
                alert('Please fill in all required fields.');
                return;
            }
            if (!id && !payload.password) {
                alert('Password is required for a new camera.');
                return;
            }
            try {
                const res = await fetch('api/cameras.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await res.json();
                if (!result.success) throw new Error(result.error || 'Save failed');
                closeCameraModal();
                await loadCameras();
                try {
                    localStorage.setItem('cameraConfigUpdated', String(Date.now()));
                    window.dispatchEvent(new Event('camera-config-updated'));
                } catch (e) {
                    /* ignore */
                }
                showToast(id ? 'Camera updated' : 'Camera added');
            } catch (e) {
                alert(e.message || 'Failed to save camera.');
            }
        }

        window.onclick = function(event) {
            if (event.target === document.getElementById('cameraModal')) closeCameraModal();
            if (event.target === document.getElementById('deleteModal')) closeDeleteModal();
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

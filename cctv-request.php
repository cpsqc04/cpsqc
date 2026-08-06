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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Footage Request - Alertara</title>
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
        .request-list { display: flex; flex-direction: column; gap: 0.75rem; }
        .request-list-item {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.75rem 1.25rem;
            align-items: center;
            width: 100%;
            text-align: left;
            padding: 1rem 1.25rem;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: #fff;
            cursor: pointer;
            font: inherit;
            color: inherit;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .request-list-item:hover {
            border-color: var(--primary-color);
            box-shadow: 0 2px 10px rgba(76, 138, 137, 0.12);
            background: #f8fbfb;
        }
        .request-list-item.is-active {
            border-color: var(--primary-color);
            box-shadow: inset 3px 0 0 var(--primary-color);
            background: #f0f9f8;
        }
        .request-list-main { min-width: 0; }
        .request-list-id { font-weight: 700; color: var(--tertiary-color); font-size: 1rem; margin: 0 0 0.35rem; }
        .request-list-meta { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.45; }
        .request-list-meta strong { color: var(--text-color); font-weight: 600; }
        .request-list-side { display: flex; flex-direction: column; align-items: flex-end; gap: 0.45rem; }
        .request-list-date { font-size: 0.82rem; color: var(--text-secondary); white-space: nowrap; }
        .request-list-empty {
            padding: 2.5rem 1rem;
            text-align: center;
            color: var(--text-secondary);
            border: 1px dashed var(--border-color);
            border-radius: 10px;
            background: #fafafa;
        }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.85rem; font-weight: 500; display: inline-block; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-under-review { background: #cff4fc; color: #055160; }
        .status-approved { background: #d1e7dd; color: #0f5132; }
        .status-fulfilled { background: #d1e7dd; color: #0f5132; }
        .status-rejected { background: #f8d7da; color: #842029; }
        .status-cancelled { background: #e9ecef; color: #6c757d; }
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-view, .btn-manage, .btn-link, .btn-approve, .btn-reject, .btn-search-footage { padding: 0.5rem 1rem; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; color: #fff; background: var(--primary-color); text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 600; }
        .btn-manage { background: var(--primary-color); }
        .btn-manage:hover { background: #4ca8a6; }
        .btn-link { background: var(--primary-color); }
        .btn-link:hover { background: #4ca8a6; }
        .btn-approve { background: var(--primary-color); }
        .btn-approve:hover { background: #4ca8a6; }
        .btn-reject { background: #dc2626; }
        .btn-search-footage { background: var(--tertiary-color); }
        .detail-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--border-color);
        }
        .reject-panel, .select-footage-panel {
            margin-top: 1.25rem;
            padding: 1.1rem 1.15rem;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: #f8fafb;
            display: none;
        }
        .reject-panel.show, .select-footage-panel.show { display: block; }
        .select-footage-panel h3, .reject-panel h3 {
            margin: 0 0 0.85rem;
            font-size: 1rem;
            color: var(--tertiary-color);
        }
        .footage-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: flex-end;
            margin-bottom: 1rem;
        }
        .footage-filters .form-group { margin: 0; min-width: 160px; flex: 1; }
        .footage-results { display: flex; flex-direction: column; gap: 0.65rem; max-height: 320px; overflow-y: auto; }
        .footage-item {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.75rem;
            align-items: center;
            padding: 0.85rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
            text-align: left;
            font: inherit;
            color: inherit;
        }
        .footage-item:hover { border-color: var(--primary-color); }
        .footage-item.selected {
            border-color: var(--primary-color);
            box-shadow: inset 3px 0 0 var(--primary-color);
            background: #f0f9f8;
        }
        .footage-item-title { font-weight: 600; color: var(--tertiary-color); margin: 0 0 0.25rem; }
        .footage-item-meta { font-size: 0.85rem; color: var(--text-secondary); line-height: 1.4; }
        .camera-match-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 0 0 0.85rem;
        }
        .camera-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.7rem;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
        }
        .camera-chip.selected {
            background: #d1fae5;
            color: #065f46;
            border-color: #059669;
        }
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
        .incident-reporting-status {
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
        .btn-incident-reporting {
            background: #fff;
            color: #0f5132;
            border: 1px solid #0f5132;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-incident-reporting:hover:not(:disabled) { background: #0f5132; color: #fff; }
        .btn-incident-reporting:disabled { opacity: 0.55; cursor: not-allowed; }
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
                <h1 class="page-title">Footage Request</h1>
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
                    <div id="requestsList" class="request-list">
                        <div class="request-list-empty">Loading requests...</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Footage Request Details</h2>
                <button class="close-modal" onclick="closeViewModal()">&times;</button>
            </div>
            <div id="viewDetails"></div>
            <div class="detail-actions" id="detailActions"></div>
            <div id="rejectPanel" class="reject-panel">
                <h3>Reject Request</h3>
                <div class="form-group">
                    <label for="rejectReason">Rejection Reason *</label>
                    <textarea id="rejectReason" rows="3" placeholder="Explain why this request is being rejected..."></textarea>
                </div>
                <div class="form-actions" style="border-top:none;padding-top:0;margin-top:0;">
                    <button type="button" class="btn-cancel" onclick="hideRejectPanel()">Cancel</button>
                    <button type="button" class="btn-reject" onclick="confirmReject()">Confirm Reject</button>
                </div>
            </div>
            <div id="selectFootagePanel" class="select-footage-panel">
                <h3>Select Footage</h3>
                <p style="margin:0 0 0.85rem;font-size:0.9rem;color:var(--text-secondary);">Search recordings by date and location to match this request.</p>
                <div class="footage-filters">
                    <div class="form-group">
                        <label for="footageDate">Date</label>
                        <input type="date" id="footageDate">
                    </div>
                    <div class="form-group">
                        <label for="footageLocation">Location</label>
                        <input type="text" id="footageLocation" placeholder="Search camera location...">
                    </div>
                    <button type="button" class="btn-search-footage" onclick="searchFootage()"><i class="fas fa-search"></i> Search</button>
                </div>
                <div id="cameraMatches" class="camera-match-list"></div>
                <div id="footageResults" class="footage-results">
                    <div class="request-list-empty">Enter a date and search for recordings.</div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="hideSelectFootagePanel()">Cancel</button>
                    <button type="button" class="btn-approve" id="confirmFootageBtn" onclick="confirmSelectedFootage()" disabled>Confirm Selected Footage</button>
                    <button type="button" class="btn-incident-reporting" id="sendToIncidentReportingBtn" onclick="sendFootageToIncidentReporting()">Send to Incident Reporting</button>
                </div>
                <div id="incidentReportingEvidenceStatus" class="incident-reporting-status"></div>
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
            updateDateTime();
            setInterval(updateDateTime, 1000);
            loadRequests();
            loadCameras();
        });

        let requestData = {};
        let allRequests = [];
        let cameras = [];
        let activeRequestId = null;
        let selectedFootageFilename = null;
        let selectedCameraId = null;
        let footageSegments = {};

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

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

        function purposeLabel(item) {
            const purpose = String(item.purpose || '').trim();
            const details = String(item.purpose_details || '').trim();
            if (purpose && details && purpose !== details) return purpose + ' — ' + details;
            return details || purpose || '—';
        }

        function cameraLabel(cameraId) {
            if (!cameraId) return '—';
            const camera = cameras.find(c => String(c.cameraId) === String(cameraId));
            if (!camera) return cameraId;
            return camera.cameraId + (camera.name ? ' — ' + camera.name : '');
        }

        async function loadCameras() {
            try {
                const res = await fetch('api/cameras.php');
                const result = await res.json();
                cameras = result.success ? (result.cameras || []) : [];
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
                document.getElementById('requestsList').innerHTML = '<div class="request-list-empty" style="color:#b91c1c;">Failed to load footage requests.</div>';
            }
        }

        function filterRequests() {
            const query = document.getElementById('searchInput').value.trim().toLowerCase();
            const dateFilter = document.getElementById('dateFilter').value;
            const list = document.getElementById('requestsList');
            list.innerHTML = '';

            const filtered = allRequests.filter(item => {
                const haystack = [
                    item.request_id, item.requesting_agency, item.contact_person,
                    item.contact_number, item.contact_email, item.incident_location,
                    item.camera_id, item.status, item.case_reference
                ].join(' ').toLowerCase();
                const matchesQuery = query === '' || haystack.includes(query);
                const matchesDate = dateFilter === '' || String(item.incident_date || '').startsWith(dateFilter) || String(item.submitted_at || '').startsWith(dateFilter);
                return matchesQuery && matchesDate;
            });

            if (!filtered.length) {
                list.innerHTML = '<div class="request-list-empty">No footage requests found.</div>';
                return;
            }

            filtered.forEach(item => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'request-list-item' + (activeRequestId === item.request_id ? ' is-active' : '');
                button.onclick = () => viewRequest(item.request_id);
                const locationLabel = item.camera_id || item.location_description || item.incident_location || '—';
                button.innerHTML = `
                    <div class="request-list-main">
                        <div class="request-list-id">${escapeHtml(item.request_id)}</div>
                        <div class="request-list-meta">
                            <strong>${escapeHtml(item.requesting_agency || '—')}</strong><br>
                            ${escapeHtml(locationLabel)} · ${escapeHtml(formatDate(item.incident_date))} ${escapeHtml(formatTime(item.footage_start_time))}–${escapeHtml(formatTime(item.footage_end_time))}
                        </div>
                    </div>
                    <div class="request-list-side">
                        <span class="status-badge status-${statusClass(item.status)}">${escapeHtml(item.status || 'Pending')}</span>
                        <span class="request-list-date">${escapeHtml(formatDate(item.submitted_at))}</span>
                    </div>
                `;
                list.appendChild(button);
            });
        }

        function detailRow(label, valueHtml) {
            return `<div class="detail-row"><span class="detail-label">${escapeHtml(label)}</span><span>${valueHtml}</span></div>`;
        }

        function viewRequest(requestId) {
            const item = requestData[requestId];
            if (!item) return;
            activeRequestId = requestId;
            selectedFootageFilename = null;
            selectedCameraId = item.approved_camera_id || item.camera_id || null;
            hideRejectPanel();
            hideSelectFootagePanel();
            filterRequests();

            const docButton = item.has_supporting_document
                ? `<button class="btn-view" type="button" onclick="viewDocument(${item.id})">View Document</button>`
                : 'None';

            document.getElementById('viewDetails').innerHTML = [
                detailRow('Request ID', escapeHtml(item.request_id)),
                detailRow('Agency', escapeHtml(item.requesting_agency || '—')),
                detailRow('Contact Number', escapeHtml(item.contact_number || '—')),
                detailRow('Email', escapeHtml(item.contact_email || '—')),
                detailRow('Case Reference', escapeHtml(item.case_reference || '—')),
                detailRow('Purpose', escapeHtml(purposeLabel(item))),
                detailRow('Legal Basis', escapeHtml(item.legal_basis || '—')),
                detailRow('Incident Location', escapeHtml(item.incident_location || '—')),
                detailRow('Camera', escapeHtml(cameraLabel(item.camera_id))),
                detailRow('Footage Window', escapeHtml(`${formatDate(item.incident_date)} ${formatTime(item.footage_start_time)} - ${formatTime(item.footage_end_time)}`)),
                detailRow('Incident Description', escapeHtml(item.incident_description || '—')),
                detailRow('Delivery Method', escapeHtml(item.delivery_method || '—')),
                detailRow('Supporting Document', docButton),
                detailRow('Review Notes', escapeHtml(item.review_notes || '—')),
                detailRow('Date Submitted', escapeHtml(formatDateTime(item.submitted_at))),
                detailRow('Status', `<span class="status-badge status-${statusClass(item.status)}">${escapeHtml(item.status || 'Pending')}</span>`)
            ].join('');

            const blocked = ['Rejected', 'Cancelled', 'Fulfilled'].includes(item.status);
            const actions = document.getElementById('detailActions');
            const rejectDisabled = item.status === 'Rejected' || item.status === 'Cancelled' || item.status === 'Fulfilled';
            actions.innerHTML = `
                <button type="button" class="btn-approve" ${blocked ? 'disabled style="opacity:0.55;cursor:not-allowed;"' : ''} onclick="startApprove()"><i class="fas fa-check"></i> Approve</button>
                <button type="button" class="btn-reject" ${rejectDisabled ? 'disabled style="opacity:0.55;cursor:not-allowed;"' : ''} onclick="startReject()"><i class="fas fa-times"></i> Reject</button>
            `;

            document.getElementById('footageDate').value = item.incident_date || '';
            document.getElementById('footageLocation').value = item.incident_location || item.location_description || '';
            updateIncidentReportingButtons(item);
            document.getElementById('viewModal').classList.add('active');
        }

        function updateIncidentReportingButtons(item) {
            const incidentReportingBtn = document.getElementById('sendToIncidentReportingBtn');
            const incidentReportingStatus = document.getElementById('incidentReportingEvidenceStatus');
            const blocked = ['Rejected', 'Cancelled'].includes(item.status);
            incidentReportingBtn.disabled = Boolean(item.forwarded_to_incident_reporting_at) || blocked;
            incidentReportingBtn.textContent = item.forwarded_to_incident_reporting_at ? 'Already sent' : 'Send to Incident Reporting';
            if (item.forwarded_to_incident_reporting_at) {
                incidentReportingStatus.style.display = 'block';
                incidentReportingStatus.innerHTML = `<strong>Incident Reporting:</strong> Sent ${escapeHtml(formatDateTime(item.forwarded_to_incident_reporting_at))}${item.incident_reporting_evidence_reference_id ? ' — Ref: ' + escapeHtml(item.incident_reporting_evidence_reference_id) : ''}`;
            } else {
                incidentReportingStatus.style.display = 'none';
                incidentReportingStatus.textContent = '';
            }
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
            activeRequestId = null;
            hideRejectPanel();
            hideSelectFootagePanel();
            filterRequests();
        }

        function startReject() {
            hideSelectFootagePanel();
            document.getElementById('rejectPanel').classList.add('show');
            document.getElementById('rejectReason').value = '';
            document.getElementById('rejectReason').focus();
        }

        function hideRejectPanel() {
            document.getElementById('rejectPanel').classList.remove('show');
        }

        async function confirmReject() {
            const item = requestData[activeRequestId];
            if (!item) return;
            const reason = document.getElementById('rejectReason').value.trim();
            if (!reason) {
                alert('Please provide a rejection reason.');
                return;
            }
            try {
                const result = await updateRequestStatus(item.id, {
                    status: 'Rejected',
                    rejection_reason: reason,
                    review_notes: item.review_notes || ''
                });
                if (!result.success) throw new Error(result.message || 'Reject failed');
                alert('Footage request rejected.');
                closeViewModal();
                await loadRequests();
            } catch (err) {
                alert(err.message || 'Failed to reject request.');
            }
        }

        async function startApprove() {
            const item = requestData[activeRequestId];
            if (!item) return;
            hideRejectPanel();
            document.getElementById('selectFootagePanel').classList.add('show');
            document.getElementById('footageDate').value = item.incident_date || '';
            document.getElementById('footageLocation').value = item.incident_location || item.location_description || '';
            selectedFootageFilename = null;
            document.getElementById('confirmFootageBtn').disabled = true;
            updateIncidentReportingButtons(item);
            await searchFootage();
            try {
                if (!['Approved', 'Fulfilled'].includes(item.status)) {
                    const result = await updateRequestStatus(item.id, {
                        status: 'Approved',
                        approved_camera_id: item.approved_camera_id || item.camera_id || '',
                        actual_footage_start: item.actual_footage_start || item.footage_start_time || '',
                        actual_footage_end: item.actual_footage_end || item.footage_end_time || '',
                        review_notes: item.review_notes || '',
                        rejection_reason: '',
                        fulfillment_notes: item.fulfillment_notes || ''
                    });
                    if (result.success) {
                        item.status = 'Approved';
                        requestData[item.request_id] = item;
                        filterRequests();
                        const statusRow = document.querySelector('#viewDetails .detail-row:last-child span:last-child');
                        if (statusRow) {
                            statusRow.innerHTML = `<span class="status-badge status-approved">Approved</span>`;
                        }
                    }
                }
            } catch (e) {
                console.error(e);
            }
        }

        function hideSelectFootagePanel() {
            document.getElementById('selectFootagePanel').classList.remove('show');
            selectedFootageFilename = null;
            document.getElementById('confirmFootageBtn').disabled = true;
        }

        function matchingCameras(locationQuery) {
            const q = String(locationQuery || '').trim().toLowerCase();
            if (!q) return cameras.slice();
            return cameras.filter(camera => {
                const haystack = [camera.cameraId, camera.name, camera.location, camera.description].join(' ').toLowerCase();
                return haystack.includes(q);
            });
        }

        function renderCameraMatches(matches) {
            const wrap = document.getElementById('cameraMatches');
            if (!matches.length) {
                wrap.innerHTML = '<span style="font-size:0.85rem;color:var(--text-secondary);">No cameras matched the location filter. Showing all recordings for the date.</span>';
                return;
            }
            wrap.innerHTML = matches.map(camera => {
                const selected = String(selectedCameraId || '') === String(camera.cameraId);
                return `<button type="button" class="camera-chip${selected ? ' selected' : ''}" onclick="selectCamera('${escapeHtml(camera.cameraId)}')">
                    <i class="fas fa-video"></i> ${escapeHtml(camera.cameraId)} — ${escapeHtml(camera.location || camera.name || '')}
                </button>`;
            }).join('');
        }

        function selectCamera(cameraId) {
            selectedCameraId = cameraId;
            const locationInput = document.getElementById('footageLocation');
            const camera = cameras.find(c => String(c.cameraId) === String(cameraId));
            if (camera && camera.location && !locationInput.value.trim()) {
                locationInput.value = camera.location;
            }
            renderCameraMatches(matchingCameras(locationInput.value));
        }

        async function searchFootage() {
            const date = document.getElementById('footageDate').value;
            const location = document.getElementById('footageLocation').value.trim();
            const results = document.getElementById('footageResults');
            const cameraMatches = matchingCameras(location);
            renderCameraMatches(cameraMatches);

            if (!date) {
                results.innerHTML = '<div class="request-list-empty">Select a date to search recordings.</div>';
                return;
            }

            results.innerHTML = '<div class="request-list-empty">Searching recordings...</div>';
            try {
                const item = requestData[activeRequestId];
                let url = 'api/recordings.php?action=find&date=' + encodeURIComponent(date);
                const start = item && item.footage_start_time ? String(item.footage_start_time).slice(0, 5) : '';
                const end = item && item.footage_end_time ? String(item.footage_end_time).slice(0, 5) : '';
                if (start) url += '&start=' + encodeURIComponent(start);
                if (end) url += '&end=' + encodeURIComponent(end);

                let res = await fetch(url);
                let result = await res.json();
                if (!result.success) throw new Error(result.message || 'Failed to search recordings');

                let segments = result.data || [];
                if (!segments.length) {
                    res = await fetch('api/recordings.php?action=list&date=' + encodeURIComponent(date));
                    result = await res.json();
                    if (!result.success) throw new Error(result.message || 'Failed to load recordings');
                    segments = result.data || [];
                }

                footageSegments = {};
                if (!segments.length) {
                    results.innerHTML = '<div class="request-list-empty">No recordings found for that date.</div>';
                    return;
                }

                results.innerHTML = segments.map(segment => {
                    footageSegments[segment.filename] = segment;
                    const selected = selectedFootageFilename === segment.filename;
                    return `<button type="button" class="footage-item${selected ? ' selected' : ''}" data-filename="${escapeHtml(segment.filename)}" onclick="selectFootage(this.dataset.filename)">
                        <div>
                            <div class="footage-item-title">${escapeHtml(segment.filename)}</div>
                            <div class="footage-item-meta">${escapeHtml(segment.label || (segment.start_at + ' – ' + segment.end_at))} · ${escapeHtml(segment.size_label || '')}</div>
                        </div>
                        <span class="status-badge status-${segment.playable ? 'approved' : 'under-review'}">${escapeHtml(segment.status || 'ready')}</span>
                    </button>`;
                }).join('');
            } catch (err) {
                results.innerHTML = `<div class="request-list-empty" style="color:#b91c1c;">${escapeHtml(err.message || 'Failed to search recordings.')}</div>`;
            }
        }

        function selectFootage(filename) {
            selectedFootageFilename = filename;
            document.getElementById('confirmFootageBtn').disabled = !filename;
            document.querySelectorAll('.footage-item').forEach(el => {
                el.classList.toggle('selected', el.dataset.filename === filename);
            });
        }

        async function updateRequestStatus(id, payload) {
            const res = await fetch('api/cctv_requests.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.assign({ action: 'manage', id }, payload))
            });
            return res.json();
        }

        async function confirmSelectedFootage() {
            const item = requestData[activeRequestId];
            if (!item || !selectedFootageFilename) return;
            const segment = footageSegments[selectedFootageFilename];
            if (!segment) {
                alert('Selected recording was not found. Search again.');
                return;
            }

            const cameraId = selectedCameraId || item.approved_camera_id || item.camera_id || '';
            const noteLine = 'Selected footage: ' + selectedFootageFilename
                + (cameraId ? ' (camera ' + cameraId + ')' : '')
                + ' · ' + (segment.start_at || '') + ' – ' + (segment.end_at || '');
            const fulfillmentNotes = [item.fulfillment_notes || '', noteLine].filter(Boolean).join('\n');

            try {
                const result = await updateRequestStatus(item.id, {
                    status: 'Approved',
                    approved_camera_id: cameraId,
                    actual_footage_start: (segment.start_time || '').slice(0, 8),
                    actual_footage_end: (segment.end_time || '').slice(0, 8),
                    review_notes: item.review_notes || '',
                    rejection_reason: '',
                    fulfillment_notes: fulfillmentNotes
                });
                if (!result.success) throw new Error(result.message || 'Failed to save selected footage');
                alert('Footage selected and request approved.');
                closeViewModal();
                await loadRequests();
            } catch (err) {
                alert(err.message || 'Failed to confirm footage.');
            }
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

        function sendFootageToIncidentReporting() {
            const item = requestData[activeRequestId];
            if (!item) return;
            if (item.forwarded_to_incident_reporting_at) {
                alert('This footage request was already sent to Incident Reporting.');
                return;
            }
            if (!confirm('Send matching CCTV recordings to Incident Reporting for evidence?\n\nThis will mark the request as Fulfilled.')) {
                return;
            }
            const btn = document.getElementById('sendToIncidentReportingBtn');
            btn.disabled = true;
            btn.textContent = 'Sending...';
            fetch('api/send_cctv_to_incident_reporting.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: item.id })
            })
            .then(res => res.json().then(data => ({ ok: res.ok, data })))
            .then(({ ok, data }) => {
                if (!ok || !data.success) {
                    throw new Error(data.message || 'Failed to send footage to Incident Reporting.');
                }
                item.forwarded_to_incident_reporting_at = data.data?.forwarded_to_incident_reporting_at || new Date().toISOString();
                item.incident_reporting_evidence_reference_id = data.data?.incident_reporting_evidence_reference_id || '';
                item.status = data.data?.status || 'Fulfilled';
                requestData[item.request_id] = item;
                updateIncidentReportingButtons(item);
                loadRequests();
                alert(data.message || 'Footage sent to Incident Reporting.');
            })
            .catch(err => {
                btn.disabled = false;
                btn.textContent = 'Send to Incident Reporting';
                alert(err.message || 'Failed to send footage to Incident Reporting.');
            });
        }

        window.onclick = function(event) {
            if (event.target === document.getElementById('viewModal')) closeViewModal();
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

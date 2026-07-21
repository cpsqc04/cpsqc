<?php
require_once __DIR__ . '/includes/bpso_auth.php';

requireBpsoLogin();

$personnelName = htmlspecialchars(getBpsoPersonnelName());
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>BPSO Portal - Alertara</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/admin-sidebar.css">
    <style>
        body { margin: 0; padding: 0; font-family: var(--font-family); background-color: var(--bg-color); display: flex; min-height: 100vh; }
        .sidebar { width: 320px; background: var(--tertiary-color); color: #fff; position: fixed; left: 0; top: 0; height: 100vh; overflow: hidden; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); z-index: 1000; transition: width 0.3s ease; display: flex; flex-direction: column; }
        .sidebar.collapsed { width: 80px; overflow: visible; }
        .sidebar.collapsed .sidebar-header { min-height: 120px; padding: 1rem 0.5rem; }
        .sidebar.collapsed .sidebar-nav { overflow-x: hidden; }
        .sidebar.collapsed .nav-module { display: block !important; margin-bottom: 0.25rem; }
        .sidebar.collapsed .nav-module-header { padding: 0.75rem; justify-content: center; min-height: 48px; margin: 0.25rem 0.5rem; border-radius: 8px; position: relative; }
        .sidebar.collapsed .nav-module-header:hover { background: rgba(255, 255, 255, 0.1); }
        .sidebar.collapsed .nav-module-header-text { opacity: 0; width: 0; overflow: hidden; display: none; }
        .sidebar.collapsed .nav-module-header .arrow { opacity: 0; width: 0; overflow: hidden; margin: 0; display: none; }
        .sidebar.collapsed .nav-submodules { display: none !important; max-height: 0 !important; }
        .sidebar.collapsed .nav-module.active .nav-submodules { display: none !important; }
<<<<<<< HEAD
=======
        .nav-submodule { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
        .sidebar.collapsed .nav-submodule { padding: 0.75rem; justify-content: center; min-height: 44px; margin: 0.25rem 0.5rem; border-radius: 8px; }
        .sidebar.collapsed .nav-submodule-dashboard { padding: 0.75rem; justify-content: center; }
        .sidebar.collapsed .nav-submodule:hover { padding-left: 0.75rem; }
        .sidebar.collapsed .nav-submodule-text { opacity: 0; width: 0; overflow: hidden; display: none; }
        .sidebar.collapsed .nav-badge { display: none !important; }
<<<<<<< HEAD
        .sidebar.collapsed .nav-submodule.active { border-left: none; border-top: 3px solid var(--primary-color); box-shadow: none; }
=======
        .sidebar.collapsed .nav-submodule.active { border-left: none; border-top: 3px solid var(--primary-color); }
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
        .sidebar.collapsed .nav-module-header::after { content: attr(data-tooltip); position: absolute; left: 100%; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.9); color: #fff; padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.85rem; white-space: nowrap; opacity: 0; pointer-events: none; margin-left: 0.75rem; z-index: 2000; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
        .sidebar.collapsed .nav-module-header:hover::after { opacity: 1; }
        .sidebar.collapsed .sidebar-logout-btn { justify-content: center; padding: 0.875rem; }
        .sidebar.collapsed .sidebar-logout-btn span { opacity: 0; width: 0; overflow: hidden; display: none; }
        .sidebar.collapsed .sidebar-logout-btn::after { content: attr(data-tooltip); position: absolute; left: 100%; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.9); color: #fff; padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.85rem; white-space: nowrap; opacity: 0; pointer-events: none; margin-left: 0.75rem; z-index: 2000; }
        .sidebar.collapsed .sidebar-logout-btn { position: relative; }
        .sidebar.collapsed .sidebar-logout-btn:hover::after { opacity: 1; }
        .sidebar-header { padding: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 128px; flex-shrink: 0; }
        .logo-container { display: flex; flex-direction: column; align-items: center; gap: 0.35rem; }
        .logo-container img { height: 88px; width: 88px; object-fit: contain; transition: all 0.3s ease; }
        .sidebar.collapsed .logo-container img { height: 56px; width: 56px; }
        .user-name-display { color: rgba(255, 255, 255, 0.9); font-size: 0.88rem; font-weight: 500; text-align: center; padding: 0.25rem 0.75rem 0; word-break: break-word; max-width: 100%; line-height: 1.3; }
        .sidebar.collapsed .user-name-display { opacity: 0; height: 0; padding: 0; overflow: hidden; font-size: 0; }
        .sidebar.collapsed .personnel-status-chip { opacity: 0; height: 0; padding: 0; margin: 0; overflow: hidden; font-size: 0; }
        .sidebar-nav { padding: 0.35rem 0; overflow-y: auto; overflow-x: hidden; flex: 1; display: flex; flex-direction: column; min-height: 0; scrollbar-width: thin; scrollbar-color: rgba(255, 255, 255, 0.22) transparent; }
        .sidebar-nav::-webkit-scrollbar { width: 5px; }
        .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.22); border-radius: 999px; }
        .sidebar-nav::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.35); }
        .nav-module { margin-bottom: 0.125rem; }
<<<<<<< HEAD
        .nav-module-header,
        .nav-submodule,
        .nav-submodule-dashboard {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-height: 44px;
            box-sizing: border-box;
        }
        .nav-module-header {
            justify-content: space-between;
            padding: 0.75rem 1.25rem;
            color: rgba(255, 255, 255, 0.9);
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            line-height: 1.25;
        }
        .nav-module-header:hover { background: rgba(255, 255, 255, 0.08); color: #fff; }
        .nav-module.active .nav-module-header { background: rgba(255, 255, 255, 0.1); color: #fff; }
        .nav-module-icon,
        .nav-submodule-icon {
            width: 24px;
            min-width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            line-height: 1;
        }
        .nav-module-icon i,
        .nav-submodule-icon i {
            font-size: 1rem;
            line-height: 1;
            width: 1em;
            text-align: center;
        }
        .nav-module-header-text,
        .nav-submodule-text {
            flex: 1;
            min-width: 0;
            line-height: 1.25;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .nav-module-header-text { flex: 1; }
        .sidebar.collapsed .nav-module-header-text { opacity: 0; width: 0; overflow: hidden; }
        .nav-module-header .arrow {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.6);
            transition: transform 0.3s ease;
            flex-shrink: 0;
            width: 12px;
            text-align: center;
        }
        .nav-module.active .nav-module-header .arrow { transform: rotate(90deg); }
        .nav-submodules { max-height: 0; overflow: hidden; transition: max-height 0.3s ease; background: rgba(0, 0, 0, 0.15); }
        .nav-module.active .nav-submodules { max-height: 320px; }
        .nav-submodule {
            padding: 0.65rem 1.25rem 0.65rem 2.75rem;
            color: rgba(255, 255, 255, 0.75);
            text-decoration: none;
            font-size: 0.85rem;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-family: inherit;
            position: relative;
        }
        .nav-submodule:hover { background: rgba(255, 255, 255, 0.08); color: #fff; }
        .nav-submodule.active {
            background: rgba(76, 138, 137, 0.35);
            color: #fff;
            box-shadow: inset 3px 0 0 var(--primary-color);
            font-weight: 600;
        }
        .nav-submodule.active .nav-submodule-icon i { color: #fff; }
        .nav-badge { display: none; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background: #ef4444; color: #fff; font-size: 0.7rem; font-weight: 700; align-items: center; justify-content: center; line-height: 20px; text-align: center; flex-shrink: 0; margin-left: auto; }
        .nav-submodule.active .nav-badge { background: #fff; color: #ef4444; }
        .nav-submodule-dashboard {
            padding: 0.75rem 1.25rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-family: inherit;
            position: relative;
        }
        .nav-submodule-dashboard:hover { background: rgba(255, 255, 255, 0.08); color: #fff; }
        .nav-submodule-dashboard.active {
            background: rgba(76, 138, 137, 0.35);
            color: #fff;
            box-shadow: inset 3px 0 0 var(--primary-color);
            font-weight: 600;
        }
        .nav-submodule-dashboard.active .nav-submodule-icon i { color: #fff; }
=======
        .nav-module-header { display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.5rem; color: rgba(255, 255, 255, 0.9); cursor: pointer; font-weight: 500; font-size: 0.9rem; gap: 0.75rem; line-height: 1.4; }
        .nav-module-header:hover { background: rgba(255, 255, 255, 0.08); color: #fff; }
        .nav-module.active .nav-module-header { background: rgba(255, 255, 255, 0.1); color: #fff; }
        .nav-module-icon { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .nav-module-header-text { flex: 1; }
        .sidebar.collapsed .nav-module-header-text { opacity: 0; width: 0; overflow: hidden; }
        .nav-module-header .arrow { font-size: 0.7rem; color: rgba(255, 255, 255, 0.6); transition: transform 0.3s ease; }
        .nav-module.active .nav-module-header .arrow { transform: rotate(90deg); }
        .nav-submodules { max-height: 0; overflow: hidden; transition: max-height 0.3s ease; background: rgba(0, 0, 0, 0.15); }
        .nav-module.active .nav-submodules { max-height: 280px; }
        .nav-submodule { padding: 0.65rem 1rem 0.65rem 3rem; color: rgba(255, 255, 255, 0.75); text-decoration: none; display: flex; align-items: center; gap: 0.65rem; transition: all 0.2s ease; font-size: 0.82rem; cursor: pointer; border: none; background: none; width: 100%; text-align: left; font-family: inherit; position: relative; box-sizing: border-box; }
        .nav-submodule:hover { background: rgba(255, 255, 255, 0.08); color: #fff; padding-left: 3.35rem; }
        .nav-submodule.active { background: rgba(76, 138, 137, 0.35); color: #fff; border-left: 3px solid var(--primary-color); font-weight: 600; }
        .nav-submodule.active .nav-submodule-icon i { color: #fff; }
        .nav-submodule-text { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; }
        .nav-badge { display: none; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background: #ef4444; color: #fff; font-size: 0.7rem; font-weight: 700; align-items: center; justify-content: center; line-height: 20px; text-align: center; flex-shrink: 0; }
        .nav-submodule.active .nav-badge { background: #fff; color: #ef4444; }
        .nav-submodule-dashboard { padding-left: 1.25rem; font-weight: 500; }
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
        .sidebar.collapsed .nav-submodule::after { content: attr(data-tooltip); position: absolute; left: 100%; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.9); color: #fff; padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.85rem; white-space: nowrap; opacity: 0; pointer-events: none; margin-left: 0.75rem; z-index: 2000; }
        .sidebar.collapsed .nav-submodule:hover::after { opacity: 1; }
        .personnel-status-chip { display: inline-flex; align-items: center; gap: 0.35rem; margin-top: 0.25rem; padding: 0.28rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: rgba(255,255,255,0.12); color: rgba(255,255,255,0.9); }
        .personnel-status-chip.available { background: rgba(16,185,129,0.2); color: #a7f3d0; }
        .personnel-status-chip.assigned { background: rgba(59,130,246,0.2); color: #bfdbfe; }
        .personnel-status-chip.off-duty { background: rgba(239,68,68,0.2); color: #fecaca; }
        .personnel-status-chip.at-hall { background: rgba(16,185,129,0.25); color: #a7f3d0; }
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
        .notification-item:hover { background: #f9fafb; }
        .notification-item.unread { background: rgba(76, 138, 137, 0.05); }
        .notification-item.unread::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--primary-color); }
        .notification-icon { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.95rem; }
        .notification-icon.patrol { background: #dbeafe; color: #1d4ed8; }
        .notification-icon.complaint { background: #fee2e2; color: #991b1b; }
        .notification-content { flex: 1; min-width: 0; }
        .notification-title { font-weight: 600; color: var(--text-color); font-size: 0.9rem; margin-bottom: 0.25rem; }
        .notification-message { color: var(--text-secondary); font-size: 0.85rem; line-height: 1.4; }
        .notification-time { color: var(--text-secondary); font-size: 0.75rem; margin-top: 0.35rem; }
        .notification-empty { padding: 2rem 1.25rem; text-align: center; color: var(--text-secondary); }
        .notification-empty i { font-size: 2rem; margin-bottom: 0.75rem; opacity: 0.35; display: block; }
        .attendance-card { border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem 1.5rem; background: linear-gradient(145deg, rgba(16,185,129,0.08), #fff); }
        .attendance-card h3 { margin: 0 0 0.5rem; font-size: 1rem; color: var(--tertiary-color); display: flex; align-items: center; gap: 0.5rem; }
        .attendance-card p { margin: 0 0 1rem; color: var(--text-secondary); font-size: 0.9rem; }
        .attendance-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; }
        .btn-time-in, .btn-time-out { padding: 0.75rem 1.25rem; border: none; border-radius: 8px; font: inherit; font-size: 0.95rem; font-weight: 600; cursor: pointer; }
        .btn-time-in { background: #059669; color: #fff; }
        .btn-time-in:hover { background: #047857; }
        .btn-time-out { background: #64748b; color: #fff; }
        .btn-time-out:hover { background: #475569; }
        .btn-time-in:disabled, .btn-time-out:disabled { opacity: 0.55; cursor: not-allowed; }
        .attendance-meta { font-size: 0.9rem; color: var(--text-secondary); }
        .filter-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
        .filter-tab { padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 999px; background: #fff; color: var(--text-color); font: inherit; font-size: 0.85rem; cursor: pointer; }
        .filter-tab.active { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
        .priority-badge { padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.78rem; font-weight: 600; display: inline-block; }
        .priority-urgent { background: #fee2e2; color: #991b1b; }
        .priority-high { background: #ffedd5; color: #c2410c; }
        .priority-medium { background: #fef3c7; color: #b45309; }
        .priority-low { background: #e5e7eb; color: #374151; }
        @media (max-width: 900px) {
            .form-grid-split { grid-template-columns: 1fr; }
        }
<<<<<<< HEAD
=======
        .nav-submodule-icon { width: 22px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
        .nav-submodule-icon i { color: rgba(255, 255, 255, 0.75); transition: color 0.2s ease; }
        .sidebar-footer { margin-top: auto; padding: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.1); position: relative; flex-shrink: 0; }
        .sidebar-logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.5rem; background: rgba(239, 68, 68, 0.1); color: rgba(255, 255, 255, 0.9); text-decoration: none; border-radius: 8px; font-size: 1rem; font-weight: 500; border: 1px solid rgba(239, 68, 68, 0.2); width: 100%; box-sizing: border-box; }
        .sidebar-logout-btn:hover { background: rgba(239, 68, 68, 0.2); color: #fff; }
        .main-wrapper { margin-left: 320px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; transition: margin-left 0.3s ease; }
        body.sidebar-collapsed .main-wrapper { margin-left: 80px; }
        .top-header { background: var(--header-bg); padding: 1.5rem 2rem 1rem; display: flex; justify-content: space-between; align-items: flex-end; position: sticky; top: 0; z-index: 100; border-bottom: 1px solid var(--border-color); }
        .top-header-content { flex: 1; display: flex; align-items: center; gap: 1rem; }
        .content-burger-btn { background: transparent; border: none; color: var(--tertiary-color); width: 40px; height: 40px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
        .content-burger-btn span { display: block; width: 22px; height: 1.5px; background: var(--tertiary-color); position: relative; }
        .content-burger-btn span::before, .content-burger-btn span::after { content: ''; position: absolute; width: 22px; height: 1.5px; background: var(--tertiary-color); }
        .content-burger-btn span::before { top: -7px; }
        .content-burger-btn span::after { bottom: -7px; }
        .page-title { font-size: 2rem; font-weight: 700; color: var(--tertiary-color); margin: 0; }
        .datetime-display { display: flex; align-items: center; gap: 0.75rem; color: var(--text-color); font-size: 0.9rem; font-weight: 500; }
        .datetime-display .date-part { color: var(--text-secondary); }
        .datetime-display .time-part { color: var(--text-color); font-weight: 600; }
        .content-area { padding: 2rem; flex: 1; background: #f5f5f5; }
        .page-content { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; box-shadow: 0 2px 8px var(--shadow); margin-top: 1.5rem; }
        .portal-panel { display: none; }
        .portal-panel.active { display: block; }
        .section-heading { margin: 0 0 1.5rem; color: var(--tertiary-color); font-size: 1.25rem; font-weight: 600; }
        .table-container { overflow-x: auto; border-radius: 8px; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; background: var(--card-bg); }
        thead { background: var(--tertiary-color); color: #fff; }
        th { padding: 1rem; text-align: left; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-color); }
        tbody tr:hover { background: #f9f9f9; }
        tbody tr:last-child td { border-bottom: none; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.85rem; font-weight: 500; display: inline-block; }
        .status-scheduled { background: #dbeafe; color: #1d4ed8; }
        .status-in-progress { background: #fef3c7; color: #b45309; }
        .status-completed { background: #d1fae5; color: #047857; }
        .status-pending { background: #fef3c7; color: #b45309; }
        .status-processing { background: #dbeafe; color: #1d4ed8; }
        .status-resolved { background: #d1fae5; color: #047857; }
        .btn-view { padding: 0.5rem 1rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; }
        .btn-view:hover { background: #4ca8a6; }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow: auto; }
        .modal.active { display: block; }
        .modal-content { background: var(--card-bg); margin: 5% auto; padding: 2rem; border-radius: 12px; width: 90%; max-width: 700px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid var(--border-color); }
        .modal-header h2 { margin: 0; color: var(--tertiary-color); }
        .close-modal { background: none; border: none; font-size: 1.75rem; cursor: pointer; color: #aaa; }
        .complaint-detail { margin-bottom: 1rem; line-height: 1.6; }
        .complaint-detail strong { color: var(--tertiary-color); }
        .incident-photo {
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
        .incident-photo:hover { opacity: 0.92; }
        .empty-state { text-align: center; padding: 2.5rem 1rem; color: var(--text-secondary); }
        .empty-state i { font-size: 2rem; margin-bottom: 0.75rem; opacity: 0.4; display: block; }
        .form-grid { display: grid; gap: 1.25rem; width: 100%; }
        .form-grid-split { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem; }
        .form-grid-actions { display: flex; justify-content: flex-end; padding-top: 0.25rem; }
        .form-group { margin-bottom: 0; min-width: 0; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-color); font-weight: 500; font-size: 0.95rem; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            max-width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: var(--font-family);
            box-sizing: border-box;
            display: block;
            min-width: 0;
        }
        .form-group input[type="date"],
        .form-group input[type="time"] {
            width: 100%;
            min-height: 44px;
        }
        .form-group input[readonly] { background: #f8fafc; color: var(--text-secondary); cursor: not-allowed; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1); }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .btn-submit { padding: 0.75rem 1.5rem; background: var(--primary-color); color: #fff; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { background: #4ca8a6; }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-start { padding: 0.5rem 1rem; background: #ffc107; color: #000; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; margin-left: 0.35rem; }
        .btn-start:hover { background: #e0a800; }
        .btn-report { padding: 0.5rem 1rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; }
        .btn-report:hover { background: #4ca8a6; }
        .alert { padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.92rem; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        @media (max-width: 768px) {
            .main-wrapper { margin-left: 0; }
            body.sidebar-collapsed .main-wrapper { margin-left: 0; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
        }
    </style>
    <link rel="stylesheet" href="css/mobile-responsive.css">
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <a href="bpso-dashboard.php">
                    <img src="images/tara.png" alt="Alertara Logo">
                </a>
                <div class="user-name-display"><?php echo $personnelName; ?></div>
                <div id="personnelStatusChip" class="personnel-status-chip">Loading status...</div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <button type="button" class="nav-submodule nav-submodule-dashboard active" data-tab="dashboard" data-title="Barangay Hall Attendance" data-tooltip="Attendance" onclick="switchSection(this, 'dashboard', 'Barangay Hall Attendance')">
                <span class="nav-submodule-icon"><i class="fas fa-building"></i></span>
                <span class="nav-submodule-text">Attendance</span>
            </button>
            <div class="nav-module">
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="Patrol Duties">
                    <span class="nav-module-icon"><i class="fas fa-walking"></i></span>
                    <span class="nav-module-header-text">Patrol Duties</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <button type="button" class="nav-submodule" data-tab="schedule" data-title="My Schedule" data-tooltip="My Schedule" onclick="switchSection(this, 'schedule', 'My Schedule')">
                        <span class="nav-submodule-icon"><i class="fas fa-calendar-alt"></i></span>
                        <span class="nav-submodule-text">My Schedule</span>
                        <span class="nav-badge" id="badge-schedule">0</span>
                    </button>
                    <button type="button" class="nav-submodule" data-tab="report" data-title="Submit Report" data-tooltip="Submit Report" onclick="switchSection(this, 'report', 'Submit Report')">
                        <span class="nav-submodule-icon"><i class="fas fa-file-alt"></i></span>
                        <span class="nav-submodule-text">Submit Report</span>
                        <span class="nav-badge" id="badge-report">0</span>
                    </button>
                    <button type="button" class="nav-submodule" data-tab="reports" data-title="My Reports" data-tooltip="My Reports" onclick="switchSection(this, 'reports', 'My Reports')">
                        <span class="nav-submodule-icon"><i class="fas fa-clipboard-list"></i></span>
                        <span class="nav-submodule-text">My Reports</span>
                    </button>
                </div>
            </div>
            <div class="nav-module" id="nav-module-complaints">
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="Community Complaints">
                    <span class="nav-module-icon"><i class="fas fa-comments"></i></span>
                    <span class="nav-module-header-text">Community Complaints</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <button type="button" class="nav-submodule" data-tab="complaints" data-title="Assigned Complaints" data-tooltip="Assigned Complaints" onclick="switchSection(this, 'complaints', 'Assigned Complaints')">
                        <span class="nav-submodule-icon"><i class="fas fa-exclamation-circle"></i></span>
                        <span class="nav-submodule-text">Assigned Complaints</span>
                        <span class="nav-badge" id="badge-complaints">0</span>
                    </button>
                    <button type="button" class="nav-submodule" data-tab="nw-incidents" data-title="Assigned Neighborhood Watch Incidents" data-tooltip="Assigned Neighborhood Watch Incidents" onclick="switchSection(this, 'nw-incidents', 'Assigned Neighborhood Watch Incidents')">
                        <span class="nav-submodule-icon"><i class="fas fa-shield-alt"></i></span>
                        <span class="nav-submodule-text">Neighborhood Watch Incidents</span>
                        <span class="nav-badge" id="badge-nw-incidents">0</span>
                    </button>
                </div>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="bpso-logout.php" class="sidebar-logout-btn" data-tooltip="Logout">
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
                <div>
                    <h1 class="page-title" id="pageTitle">Barangay Hall Attendance</h1>
                </div>
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
                            <button type="button" onclick="markAllNotificationsRead()">Mark all read</button>
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
                <section id="panel-dashboard" class="portal-panel active">
                    <div class="attendance-card" id="attendanceCard">
                        <h3><i class="fas fa-building"></i> Barangay Hall Attendance</h3>
                        <p id="attendanceStatusText">Checking attendance status...</p>
                        <div class="attendance-actions">
                            <button type="button" class="btn-time-in" id="btnTimeIn" onclick="attendanceTimeIn()"><i class="fas fa-sign-in-alt"></i> Time In</button>
                            <button type="button" class="btn-time-out" id="btnTimeOut" onclick="attendanceTimeOut()" disabled><i class="fas fa-sign-out-alt"></i> Time Out</button>
                            <span class="attendance-meta" id="attendanceMeta"></span>
                        </div>
                    </div>
                </section>

                <section id="panel-schedule" class="portal-panel">
                    <h2 class="section-heading">Assigned Patrol Schedule</h2>
                    <div id="scheduleAlert"></div>
                    <div class="filter-tabs">
                        <button type="button" class="filter-tab active" data-filter="all" onclick="setScheduleFilter('all', this)">All</button>
                        <button type="button" class="filter-tab" data-filter="today" onclick="setScheduleFilter('today', this)">Today</button>
                        <button type="button" class="filter-tab" data-filter="upcoming" onclick="setScheduleFilter('upcoming', this)">Upcoming</button>
                        <button type="button" class="filter-tab" data-filter="completed" onclick="setScheduleFilter('completed', this)">Completed</button>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
<<<<<<< HEAD
                                    <th>Shift</th>
                                    <th>Patrol Zone</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Duration</th>
=======
                                    <th>Time</th>
                                    <th>Route</th>
                                    <th>Location</th>
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="scheduleTableBody">
<<<<<<< HEAD
                                <tr><td colspan="8" class="empty-state">Loading schedule...</td></tr>
=======
                                <tr><td colspan="6" class="empty-state">Loading schedule...</td></tr>
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="panel-report" class="portal-panel">
                    <h2 class="section-heading">Submit Patrol Report</h2>
                    <div id="reportAlert"></div>
                    <form id="reportForm" class="form-grid">
                        <div class="form-group">
                            <label for="reportSchedule">Patrol Assignment *</label>
                            <select id="reportSchedule" name="schedule_id" required>
                                <option value="">Select an assignment</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="reportRoute">Route *</label>
                            <input type="text" id="reportRoute" name="route" required readonly>
                        </div>
                        <div class="form-grid-split">
                            <div class="form-group">
                                <label for="reportDate">Date *</label>
                                <input type="date" id="reportDate" name="date" required readonly>
                            </div>
                            <div class="form-group">
                                <label for="reportTime">Time Completed *</label>
                                <input type="time" id="reportTime" name="time" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="reportLocation">Location</label>
                            <input type="text" id="reportLocation" name="location">
                        </div>
                        <div class="form-group">
                            <label for="reportIncidents">Incidents</label>
                            <input type="text" id="reportIncidents" name="incidents" placeholder="None" value="None">
                        </div>
                        <div class="form-group">
                            <label for="reportDetails">Patrol Details / Summary *</label>
                            <textarea id="reportDetails" name="details" required placeholder="Describe patrol activities, observations, and actions taken..."></textarea>
                        </div>
                        <div class="form-grid-actions">
                            <button type="submit" class="btn-submit" id="reportSubmitBtn">Submit Report</button>
                        </div>
                    </form>
                </section>

                <section id="panel-reports" class="portal-panel">
                    <h2 class="section-heading">Submitted Reports</h2>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Route</th>
                                    <th>Incidents</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="reportsTableBody">
                                <tr><td colspan="5" class="empty-state">Loading reports...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="panel-complaints" class="portal-panel">
                    <h2 class="section-heading">Assigned Complaints</h2>
                    <div id="complaintsAlert"></div>
                    <div class="filter-tabs">
                        <button type="button" class="filter-tab active" data-filter="all" onclick="setComplaintFilter('all', this)">All</button>
                        <button type="button" class="filter-tab" data-filter="processing" onclick="setComplaintFilter('processing', this)">Processing</button>
                        <button type="button" class="filter-tab" data-filter="resolved" onclick="setComplaintFilter('resolved', this)">Resolved</button>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Complaint ID</th>
                                    <th>Complainant</th>
                                    <th>Type</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="complaintsTableBody">
                                <tr><td colspan="6" class="empty-state">Loading complaints...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="panel-nw-incidents" class="portal-panel">
                    <h2 class="section-heading">Assigned Neighborhood Watch Incidents</h2>
                    <div id="nwIncidentsAlert"></div>
                    <div class="filter-tabs">
                        <button type="button" class="filter-tab active" data-filter="all" onclick="setNwIncidentFilter('all', this)">All</button>
                        <button type="button" class="filter-tab" data-filter="in-progress" onclick="setNwIncidentFilter('in-progress', this)">In Progress</button>
                        <button type="button" class="filter-tab" data-filter="resolved" onclick="setNwIncidentFilter('resolved', this)">Resolved</button>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Report ID</th>
                                    <th>Member</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="nwIncidentsTableBody">
                                <tr><td colspan="5" class="empty-state">Loading neighborhood watch incidents...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <div id="complaintResolutionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Complaint Resolution Report</h2>
                <button type="button" class="close-modal" onclick="closeComplaintModal()">&times;</button>
            </div>
            <div id="complaintDetailContent"></div>
            <form id="complaintResolutionForm" class="form-grid" style="margin-top:1.5rem;">
                <input type="hidden" id="resolutionComplaintId">
                <div class="form-group">
                    <label for="resolutionReport">What actions did you take? *</label>
                    <textarea id="resolutionReport" required placeholder="Describe how you handled the complaint, actions taken, and outcome..."></textarea>
                </div>
                <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                    <button type="button" id="saveProgressBtn" class="btn-submit" style="background:#2563eb;" onclick="submitComplaintResolution('Processing')">Save Progress</button>
                    <button type="submit" id="resolveComplaintBtn" class="btn-submit">Mark as Resolved</button>
                </div>
            </form>
        </div>
    </div>

    <div id="nwIncidentResolutionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Neighborhood Watch Incident Resolution Report</h2>
                <button type="button" class="close-modal" onclick="closeNwIncidentModal()">&times;</button>
            </div>
            <div id="nwIncidentDetailContent"></div>
            <form id="nwIncidentResolutionForm" class="form-grid" style="margin-top:1.5rem;">
                <input type="hidden" id="resolutionNwIncidentId">
                <div class="form-group">
                    <label for="nwResolutionReport">What actions did you take? *</label>
                    <textarea id="nwResolutionReport" required placeholder="Describe how you responded to the incident, actions taken, and outcome..."></textarea>
                </div>
                <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                    <button type="button" id="saveNwProgressBtn" class="btn-submit" style="background:#2563eb;" onclick="submitNwIncidentResolution('In Progress')">Save Progress</button>
                    <button type="submit" id="resolveNwIncidentBtn" class="btn-submit">Mark as Resolved</button>
                </div>
            </form>
        </div>
    </div>

    <div id="reportDetailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Patrol Report Details</h2>
                <button type="button" class="close-modal" onclick="closeReportModal()">&times;</button>
            </div>
            <div id="reportDetailContent"></div>
        </div>
    </div>

    <script>
        let scheduleData = {};
        let complaintData = {};
        let nwIncidentData = {};
        let portalSchedules = [];
        let portalComplaints = [];
        let portalNwIncidents = [];
        let portalReports = [];
        let complaintFilter = 'all';
        let nwIncidentFilter = 'all';
        let scheduleFilter = 'all';
        let refreshTimer = null;
        let initialSectionSet = false;

        function switchSection(button, tab, title) {
            document.querySelectorAll('.nav-submodule').forEach(item => item.classList.remove('active'));
            button.classList.add('active');
            document.querySelectorAll('.portal-panel').forEach(panel => panel.classList.remove('active'));
            document.getElementById('panel-' + tab).classList.add('active');
            document.getElementById('pageTitle').textContent = title;

            const parentModule = button.closest('.nav-module');
            if (parentModule) {
                document.querySelectorAll('.nav-module').forEach(function(m) { m.classList.remove('active'); });
                parentModule.classList.add('active');
            }
        }

        function goToTab(tab) {
            const btn = document.querySelector(`.nav-submodule[data-tab="${tab}"]`);
            if (btn) {
                switchSection(btn, tab, btn.dataset.title);
            }
        }

        function setNavBadge(id, count) {
            const el = document.getElementById(id);
            if (!el) return;
            if (count > 0) {
                el.textContent = count > 99 ? '99+' : String(count);
                el.style.display = 'inline-flex';
            } else {
                el.style.display = 'none';
            }
        }

        function updateNavBadges() {
            const openSchedules = portalSchedules.filter(s => s.status === 'Scheduled' || s.status === 'In Progress');
            const openComplaints = portalComplaints.filter(c => c.status === 'Processing');
            const openNwIncidents = portalNwIncidents.filter(r => r.status === 'In Progress');
            setNavBadge('badge-schedule', openSchedules.length);
            setNavBadge('badge-report', openSchedules.length);
            setNavBadge('badge-complaints', openComplaints.length);
            setNavBadge('badge-nw-incidents', openNwIncidents.length);
        }

        function getTodayDateString() {
            return new Date().toISOString().slice(0, 10);
        }

        function setInitialSectionIfNeeded() {
            if (initialSectionSet) return;
            initialSectionSet = true;
            const openComplaints = portalComplaints.filter(c => c.status === 'Processing');
            const openNwIncidents = portalNwIncidents.filter(r => r.status === 'In Progress');
            if (openComplaints.length > 0) {
                goToTab('complaints');
            } else if (openNwIncidents.length > 0) {
                goToTab('nw-incidents');
            }
        }

        function setScheduleFilter(filter, button) {
            scheduleFilter = filter;
            document.querySelectorAll('#panel-schedule .filter-tab').forEach(tab => tab.classList.remove('active'));
            button.classList.add('active');
            renderScheduleTable();
        }

<<<<<<< HEAD
        function getTodayDateString() {
            return new Date().toISOString().split('T')[0];
        }

        function formatScheduleTime(value) {
            if (!value) return '—';
            const normalized = String(value).length === 5 ? value + ':00' : String(value);
            const date = new Date('1970-01-01T' + normalized.replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        }

=======
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
        function getFilteredSchedules() {
            const today = getTodayDateString();
            if (scheduleFilter === 'today') {
                return portalSchedules.filter(s => s.schedule_date === today);
            }
            if (scheduleFilter === 'upcoming') {
                return portalSchedules.filter(s => s.schedule_date > today && s.status !== 'Completed');
            }
            if (scheduleFilter === 'completed') {
                return portalSchedules.filter(s => s.status === 'Completed');
            }
            return portalSchedules;
        }

        function renderScheduleTable() {
            const tbody = document.getElementById('scheduleTableBody');
            const select = document.getElementById('reportSchedule');
            const rows = getFilteredSchedules();

            if (rows.length === 0) {
<<<<<<< HEAD
                tbody.innerHTML = '<tr><td colspan="8" class="empty-state"><i class="fas fa-calendar-times"></i>No patrol assignments in this view.</td></tr>';
            } else {
                tbody.innerHTML = rows.map(row => {
                    scheduleData[row.id] = row;
                    const zone = row.patrol_zone || row.location || row.route || '—';
                    const startDisplay = row.patrol_start_display || (row.patrol_start || row.schedule_time ? formatScheduleTime(row.patrol_start || row.schedule_time) : (row.status === 'Scheduled' ? 'Pending' : '—'));
                    const endDisplay = row.patrol_end_display || (row.patrol_end ? formatScheduleTime(row.patrol_end) : (row.status === 'In Progress' ? 'In progress' : (row.status === 'Scheduled' ? 'Pending' : '—')));
                    const durationLabel = row.duration_label || (row.status === 'In Progress' ? 'In progress' : '—');
=======
                tbody.innerHTML = '<tr><td colspan="6" class="empty-state"><i class="fas fa-calendar-times"></i>No patrol assignments in this view.</td></tr>';
            } else {
                tbody.innerHTML = rows.map(row => {
                    scheduleData[row.id] = row;
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                    const canReport = row.status === 'Scheduled' || row.status === 'In Progress';
                    const actions = canReport
                        ? `<button type="button" class="btn-report" onclick="openReportForSchedule(${row.id})">Submit Report</button>`
                        : '—';
                    const startBtn = row.status === 'Scheduled'
                        ? `<button type="button" class="btn-start" onclick="markInProgress(${row.id})">Start</button>`
                        : '';
                    return `<tr>
                        <td>${escapeHtml(row.schedule_date)}</td>
<<<<<<< HEAD
                        <td>${escapeHtml(row.shift || '—')}</td>
                        <td>${escapeHtml(zone)}</td>
                        <td>${escapeHtml(startDisplay)}</td>
                        <td>${escapeHtml(endDisplay)}</td>
                        <td>${escapeHtml(durationLabel)}</td>
                        <td><span class="status-badge ${statusClass(row.status)}">${escapeHtml(row.status)}</span></td>
                        <td>${startBtn}${actions}</td>
=======
                        <td>${escapeHtml(row.schedule_time)}</td>
                        <td>${escapeHtml(row.route)}</td>
                        <td>${escapeHtml(row.location || '—')}</td>
                        <td><span class="status-badge ${statusClass(row.status)}">${escapeHtml(row.status)}</span></td>
                        <td>${actions}${startBtn}</td>
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                    </tr>`;
                }).join('');
            }

            const openAssignments = portalSchedules.filter(r => r.status === 'Scheduled' || r.status === 'In Progress');
            select.innerHTML = '<option value="">Select an assignment</option>' +
                openAssignments.map(r =>
                    `<option value="${r.id}">${escapeHtml(r.schedule_date)} · ${escapeHtml(r.route)} (${escapeHtml(r.status)})</option>`
                ).join('');
        }

        async function loadProfile() {
            try {
                const [profileRes, attendanceRes] = await Promise.all([
                    fetch('api/bpso_profile.php'),
                    fetch('api/bpso_attendance.php?view=my_status')
                ]);
                const result = await profileRes.json();
                const attendanceResult = await attendanceRes.json();
                const chip = document.getElementById('personnelStatusChip');
                if (!result.success || !result.data) {
                    chip.textContent = 'Status unavailable';
                    return;
                }

                const isAtHall = attendanceResult.success && attendanceResult.data && attendanceResult.data.is_at_hall;
                if (isAtHall) {
                    chip.textContent = 'At Hall';
                    chip.className = 'personnel-status-chip at-hall';
                } else {
                    const status = (result.data.status || 'Available').toLowerCase().replace(/\s+/g, '-');
                    chip.textContent = result.data.status || 'Available';
                    chip.className = 'personnel-status-chip ' + (status === 'off-duty' ? 'off-duty' : status === 'assigned' ? 'assigned' : 'available');
                }

                updateAttendanceUi(attendanceResult.data || {});
            } catch (e) {
                document.getElementById('personnelStatusChip').textContent = 'Status unavailable';
            }
        }

        function formatDateTime(value) {
            if (!value) return '—';
            return new Date(value).toLocaleString('en-US', {
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', hour12: true
            });
        }

        function updateAttendanceUi(data) {
            const isAtHall = Boolean(data.is_at_hall);
            const session = data.open_session || null;
            const statusText = document.getElementById('attendanceStatusText');
            const meta = document.getElementById('attendanceMeta');
            const btnIn = document.getElementById('btnTimeIn');
            const btnOut = document.getElementById('btnTimeOut');

            if (isAtHall && session) {
                statusText.textContent = 'You are currently timed in at the barangay hall.';
                meta.textContent = 'Time in: ' + formatDateTime(session.time_in);
                btnIn.disabled = true;
                btnOut.disabled = false;
            } else {
                statusText.textContent = 'Time in when you arrive at the barangay hall so admins know you are on-site.';
                meta.textContent = '';
                btnIn.disabled = false;
                btnOut.disabled = true;
            }
        }

        async function attendanceTimeIn() {
            const btnIn = document.getElementById('btnTimeIn');
            btnIn.disabled = true;
            try {
                const res = await fetch('api/bpso_attendance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'time_in' })
                });
                const result = await res.json();
                if (!result.success) {
                    alert(result.message || 'Failed to time in.');
                    btnIn.disabled = false;
                    return;
                }
                await loadProfile();
            } catch (e) {
                alert('Failed to time in.');
                btnIn.disabled = false;
            }
        }

        async function attendanceTimeOut() {
            const btnOut = document.getElementById('btnTimeOut');
            btnOut.disabled = true;
            try {
                const res = await fetch('api/bpso_attendance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'time_out' })
                });
                const result = await res.json();
                if (!result.success) {
                    alert(result.message || 'Failed to time out.');
                    btnOut.disabled = false;
                    return;
                }
                await loadProfile();
            } catch (e) {
                alert('Failed to time out.');
                btnOut.disabled = false;
            }
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const isCollapsed = sidebar.classList.contains('collapsed');
            if (isCollapsed) {
                sidebar.classList.remove('collapsed');
                document.body.classList.remove('sidebar-collapsed');
                localStorage.setItem('bpsoSidebarCollapsed', 'false');
            } else {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
                localStorage.setItem('bpsoSidebarCollapsed', 'true');
            }
        }

        function toggleModule(element) {
            const sidebar = document.getElementById('sidebar');
            const module = element.closest('.nav-module');
            const isActive = module.classList.contains('active');

            if (sidebar.classList.contains('collapsed')) {
                sidebar.classList.remove('collapsed');
                document.body.classList.remove('sidebar-collapsed');
                localStorage.setItem('bpsoSidebarCollapsed', 'false');
                document.querySelectorAll('.nav-module').forEach(function(m) { m.classList.remove('active'); });
                module.classList.add('active');
                return;
            }

            document.querySelectorAll('.nav-module').forEach(function(m) { m.classList.remove('active'); });
            if (!isActive) {
                module.classList.add('active');
            }
        }

        function updateDateTime() {
            const now = new Date();
            const dateEl = document.getElementById('currentDate');
            const timeEl = document.getElementById('currentTime');
            if (dateEl) {
                dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
            }
            if (timeEl) {
                timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
            }
        }

        function statusClass(status) {
            if (status === 'Completed') return 'status-completed';
            if (status === 'In Progress') return 'status-in-progress';
            return 'status-scheduled';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        async function loadSchedules() {
            const tbody = document.getElementById('scheduleTableBody');
            const select = document.getElementById('reportSchedule');

            try {
                const res = await fetch('api/patrol_schedules.php');
                const result = await res.json();

                if (!result.success) {
<<<<<<< HEAD
                    tbody.innerHTML = '<tr><td colspan="8" class="empty-state">Failed to load schedule.</td></tr>';
=======
                    tbody.innerHTML = '<tr><td colspan="6" class="empty-state">Failed to load schedule.</td></tr>';
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                    portalSchedules = [];
                    updateNavBadges();
                    return;
                }

                scheduleData = {};
                portalSchedules = result.data || [];

                if (portalSchedules.length === 0) {
<<<<<<< HEAD
                    tbody.innerHTML = '<tr><td colspan="8" class="empty-state"><i class="fas fa-calendar-times"></i>No patrol assignments yet.</td></tr>';
=======
                    tbody.innerHTML = '<tr><td colspan="6" class="empty-state"><i class="fas fa-calendar-times"></i>No patrol assignments yet.</td></tr>';
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                    select.innerHTML = '<option value="">No assignments available</option>';
                    updateNavBadges();
                    return;
                }

                renderScheduleTable();
                updateNavBadges();
            } catch (e) {
<<<<<<< HEAD
                tbody.innerHTML = '<tr><td colspan="8" class="empty-state">Error loading schedule.</td></tr>';
=======
                tbody.innerHTML = '<tr><td colspan="6" class="empty-state">Error loading schedule.</td></tr>';
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                portalSchedules = [];
                updateNavBadges();
            }
        }

        function formatDateTime(value) {
            if (!value) return '—';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return escapeHtml(String(value));
            return date.toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        }

        function renderReportsTable() {
            const tbody = document.getElementById('reportsTableBody');
            if (portalReports.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="empty-state"><i class="fas fa-file-alt"></i>No reports submitted yet.</td></tr>';
                return;
            }

            tbody.innerHTML = portalReports.map(row => `<tr>
                <td>${escapeHtml(row.date)} ${escapeHtml(row.time || '')}</td>
                <td>${escapeHtml(row.route)}</td>
                <td>${escapeHtml(row.incidents || 'None')}</td>
                <td><span class="status-badge ${statusClass(row.status)}">${escapeHtml(row.status)}</span></td>
                <td><button type="button" class="btn-view" onclick="openReportModal(${row.id})">View</button></td>
            </tr>`).join('');
        }

        function openReportModal(id) {
            const report = portalReports.find(r => Number(r.id) === Number(id));
            if (!report) return;
            document.getElementById('reportDetailContent').innerHTML = `
                <div class="complaint-detail"><strong>Date:</strong> ${escapeHtml(report.date)} ${escapeHtml(report.time || '')}</div>
                <div class="complaint-detail"><strong>Route:</strong> ${escapeHtml(report.route)}</div>
                <div class="complaint-detail"><strong>Location:</strong> ${escapeHtml(report.location || '—')}</div>
                <div class="complaint-detail"><strong>Incidents:</strong> ${escapeHtml(report.incidents || 'None')}</div>
                <div class="complaint-detail"><strong>Status:</strong> <span class="status-badge ${statusClass(report.status)}">${escapeHtml(report.status)}</span></div>
                <div class="complaint-detail"><strong>Patrol Details:</strong><br>${escapeHtml(report.details || 'No details provided.')}</div>
            `;
            document.getElementById('reportDetailModal').classList.add('active');
        }

        function closeReportModal() {
            document.getElementById('reportDetailModal').classList.remove('active');
        }

        async function loadReports() {
            const tbody = document.getElementById('reportsTableBody');
            try {
                const res = await fetch('api/patrol_logs.php');
                const result = await res.json();

                if (!result.success) {
                    tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Failed to load reports.</td></tr>';
                    portalReports = [];
                    return;
                }

                portalReports = (result.data || []).filter(r => r.status !== 'Scheduled');
                renderReportsTable();
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Error loading reports.</td></tr>';
                portalReports = [];
            }
        }

        function fillReportFromSchedule(scheduleId) {
            const row = scheduleData[scheduleId];
            if (!row) return;
            document.getElementById('reportSchedule').value = String(scheduleId);
            document.getElementById('reportRoute').value = row.route || '';
            document.getElementById('reportDate').value = row.schedule_date || '';
            document.getElementById('reportLocation').value = row.location || '';
        }

        function openReportForSchedule(scheduleId) {
            const reportBtn = document.querySelector('.nav-submodule[data-tab="report"]');
            switchSection(reportBtn, 'report', 'Submit Report');
            fillReportFromSchedule(scheduleId);
            const now = new Date();
            document.getElementById('reportTime').value = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
        }

        async function markInProgress(scheduleId) {
            try {
                const res = await fetch('api/patrol_schedules.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
<<<<<<< HEAD
                    body: JSON.stringify({ action: 'start_patrol', schedule_id: scheduleId, status: 'In Progress' })
=======
                    body: JSON.stringify({ action: 'update_status', schedule_id: scheduleId, status: 'In Progress' })
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                });
                const result = await res.json();
                if (result.success) {
                    await refreshAllData();
                } else {
                    alert(result.message || 'Failed to update status.');
                }
            } catch (e) {
                alert('Failed to update status.');
            }
        }

        document.getElementById('reportSchedule').addEventListener('change', function() {
            if (this.value) fillReportFromSchedule(parseInt(this.value, 10));
        });

        document.getElementById('reportForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const alertEl = document.getElementById('reportAlert');
            const btn = document.getElementById('reportSubmitBtn');
            alertEl.innerHTML = '';
            btn.disabled = true;

            const payload = {
                action: 'submit_report',
                schedule_id: parseInt(document.getElementById('reportSchedule').value, 10) || 0,
                route: document.getElementById('reportRoute').value.trim(),
                date: document.getElementById('reportDate').value,
                time: document.getElementById('reportTime').value,
                location: document.getElementById('reportLocation').value.trim(),
                incidents: document.getElementById('reportIncidents').value.trim() || 'None',
                details: document.getElementById('reportDetails').value.trim(),
                status: 'Completed'
            };

            try {
                const res = await fetch('api/patrol_logs.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await res.json();
                if (result.success) {
                    alertEl.innerHTML = '<div class="alert alert-success">Patrol report submitted successfully.</div>';
                    this.reset();
                    document.getElementById('reportIncidents').value = 'None';
                    await refreshAllData();
                } else {
                    alertEl.innerHTML = '<div class="alert alert-error">' + escapeHtml(result.message || 'Submission failed.') + '</div>';
                }
            } catch (err) {
                alertEl.innerHTML = '<div class="alert alert-error">Network error. Please try again.</div>';
            } finally {
                btn.disabled = false;
            }
        });

        function complaintStatusClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'resolved') return 'status-resolved';
            if (s === 'processing') return 'status-processing';
            return 'status-pending';
        }

        function priorityClass(priority) {
            const p = (priority || '').toLowerCase();
            if (p === 'urgent') return 'priority-urgent';
            if (p === 'high') return 'priority-high';
            if (p === 'medium') return 'priority-medium';
            return 'priority-low';
        }

        function setComplaintFilter(filter, button) {
            complaintFilter = filter;
            document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
            button.classList.add('active');
            renderComplaintsTable();
        }

        function renderComplaintsTable() {
            const tbody = document.getElementById('complaintsTableBody');
            let rows = portalComplaints;
            if (complaintFilter === 'processing') {
                rows = rows.filter(r => r.status === 'Processing');
            } else if (complaintFilter === 'resolved') {
                rows = rows.filter(r => r.status === 'Resolved');
            }

            if (rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="empty-state"><i class="fas fa-inbox"></i>No complaints in this view.</td></tr>';
                return;
            }

            tbody.innerHTML = rows.map(row => {
                complaintData[row.id] = row;
                const canResolve = row.status !== 'Resolved';
                return `<tr>
                    <td>${escapeHtml(row.complaint_id)}</td>
                    <td>${escapeHtml(row.complainant_name)}</td>
                    <td>${escapeHtml(row.complaint_type)}</td>
                    <td><span class="priority-badge ${priorityClass(row.priority)}">${escapeHtml(row.priority)}</span></td>
                    <td><span class="status-badge ${complaintStatusClass(row.status)}">${escapeHtml(row.status)}</span></td>
                    <td>${canResolve ? `<button type="button" class="btn-view" onclick="openComplaintModal(${row.id})">Report / Resolve</button>` : `<button type="button" class="btn-view" onclick="openComplaintModal(${row.id})">View</button>`}</td>
                </tr>`;
            }).join('');
        }

        async function loadComplaints() {
            const tbody = document.getElementById('complaintsTableBody');
            try {
                const res = await fetch('api/bpso_complaints.php');
                const result = await res.json();
                if (!result.success) {
                    tbody.innerHTML = '<tr><td colspan="6" class="empty-state">Failed to load complaints.</td></tr>';
                    portalComplaints = [];
                    updateNavBadges();
                    return;
                }

                complaintData = {};
                portalComplaints = result.data || [];
                if (portalComplaints.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="empty-state"><i class="fas fa-inbox"></i>No complaints assigned to you yet.</td></tr>';
                    updateNavBadges();
                    setInitialSectionIfNeeded();
                    return;
                }

                renderComplaintsTable();
                updateNavBadges();
                setInitialSectionIfNeeded();
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="6" class="empty-state">Error loading complaints.</td></tr>';
                portalComplaints = [];
                updateNavBadges();
            }
        }

        function buildComplaintTimeline(complaint) {
            const steps = [
                { label: 'Complaint submitted', value: complaint.created_at || complaint.submitted_at || complaint.incident_date },
                { label: 'Assigned to you', value: complaint.assigned_at },
                { label: 'Resolved', value: complaint.resolved_at }
            ].filter(step => step.value);

            if (steps.length === 0) {
                return '<div class="complaint-detail"><strong>Timeline:</strong> No timeline entries yet.</div>';
            }

            return `
                <div class="complaint-detail"><strong>Timeline</strong></div>
                <div style="border-left:2px solid var(--border-color);margin:0.5rem 0 1rem 0.75rem;padding-left:1rem;">
                    ${steps.map(step => `
                        <div style="margin-bottom:0.85rem;">
                            <div style="font-weight:600;color:var(--tertiary-color);">${escapeHtml(step.label)}</div>
                            <div style="font-size:0.85rem;color:var(--text-secondary);">${formatDateTime(step.value)}</div>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        function openComplaintModal(id) {
            const complaint = complaintData[id];
            if (!complaint) return;

            document.getElementById('resolutionComplaintId').value = id;
            document.getElementById('resolutionReport').value = complaint.resolution_report || '';
            document.getElementById('complaintDetailContent').innerHTML = `
                <div class="complaint-detail"><strong>Complaint ID:</strong> ${escapeHtml(complaint.complaint_id)}</div>
                <div class="complaint-detail"><strong>Complainant:</strong> ${escapeHtml(complaint.complainant_name)} · ${escapeHtml(complaint.contact_number)}</div>
                <div class="complaint-detail"><strong>Address:</strong> ${escapeHtml(complaint.address)}</div>
                <div class="complaint-detail"><strong>Defendant:</strong> ${escapeHtml(complaint.defendant_name || 'N/A')}</div>
                <div class="complaint-detail"><strong>Type:</strong> ${escapeHtml(complaint.complaint_type)} · <strong>Priority:</strong> <span class="priority-badge ${priorityClass(complaint.priority)}">${escapeHtml(complaint.priority)}</span></div>
                <div class="complaint-detail"><strong>Description:</strong><br>${escapeHtml(complaint.description)}</div>
                <div class="complaint-detail"><strong>Status:</strong> <span class="status-badge ${complaintStatusClass(complaint.status)}">${escapeHtml(complaint.status)}</span></div>
                ${buildComplaintTimeline(complaint)}
            `;
            const isResolved = complaint.status === 'Resolved';
            document.getElementById('resolutionReport').readOnly = isResolved;
            document.getElementById('saveProgressBtn').style.display = isResolved ? 'none' : '';
            document.getElementById('resolveComplaintBtn').style.display = isResolved ? 'none' : '';
            document.getElementById('complaintResolutionModal').classList.add('active');
        }

        function closeComplaintModal() {
            document.getElementById('complaintResolutionModal').classList.remove('active');
            document.getElementById('resolutionReport').readOnly = false;
            document.getElementById('saveProgressBtn').style.display = '';
            document.getElementById('resolveComplaintBtn').style.display = '';
        }

        async function submitComplaintResolution(status) {
            const id = parseInt(document.getElementById('resolutionComplaintId').value, 10);
            const resolutionReport = document.getElementById('resolutionReport').value.trim();
            const alertEl = document.getElementById('complaintsAlert');

            if (!id || !resolutionReport) {
                alert('Please enter your resolution report.');
                return;
            }

            try {
                const res = await fetch('api/bpso_complaints.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'submit_resolution', id, resolution_report: resolutionReport, status })
                });
                const result = await res.json();
                if (result.success) {
                    alertEl.innerHTML = `<div class="alert alert-success">${escapeHtml(result.message || 'Report saved.')}</div>`;
                    closeComplaintModal();
                    await loadComplaints();
                    await loadProfile();
                } else {
                    alert(result.message || 'Failed to submit report.');
                }
            } catch (e) {
                alert('Network error. Please try again.');
            }
        }

        document.getElementById('complaintResolutionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitComplaintResolution('Resolved');
        });

        function nwIncidentStatusClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'resolved' || s === 'closed') return 'status-resolved';
            if (s === 'in progress') return 'status-processing';
            return 'status-pending';
        }

        function setNwIncidentFilter(filter, button) {
            nwIncidentFilter = filter;
            document.querySelectorAll('#panel-nw-incidents .filter-tab').forEach(tab => tab.classList.remove('active'));
            button.classList.add('active');
            renderNwIncidentsTable();
        }

        function renderNwIncidentsTable() {
            const tbody = document.getElementById('nwIncidentsTableBody');
            let rows = portalNwIncidents;
            if (nwIncidentFilter === 'in-progress') {
                rows = rows.filter(r => r.status === 'In Progress');
            } else if (nwIncidentFilter === 'resolved') {
                rows = rows.filter(r => r.status === 'Resolved' || r.status === 'Closed');
            }

            if (rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="empty-state"><i class="fas fa-inbox"></i>No neighborhood watch incidents in this view.</td></tr>';
                return;
            }

            tbody.innerHTML = rows.map(row => {
                nwIncidentData[row.id] = row;
                const canResolve = row.status !== 'Resolved' && row.status !== 'Closed';
                return `<tr>
                    <td>${escapeHtml(row.report_id)}</td>
                    <td>${escapeHtml(row.member_name)}</td>
                    <td>${escapeHtml(row.location)}</td>
                    <td><span class="status-badge ${nwIncidentStatusClass(row.status)}">${escapeHtml(row.status)}</span></td>
                    <td>${canResolve ? `<button type="button" class="btn-view" onclick="openNwIncidentModal(${row.id})">Report / Resolve</button>` : `<button type="button" class="btn-view" onclick="openNwIncidentModal(${row.id})">View</button>`}</td>
                </tr>`;
            }).join('');
        }

        async function loadNwIncidents() {
            const tbody = document.getElementById('nwIncidentsTableBody');
            try {
                const res = await fetch('api/bpso-neighborhood-watcher-incidents.php');
                const result = await res.json();
                if (!result.success) {
                    tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Failed to load neighborhood watch incidents.</td></tr>';
                    portalNwIncidents = [];
                    updateNavBadges();
                    return;
                }

                nwIncidentData = {};
                portalNwIncidents = result.data || [];
                if (portalNwIncidents.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="empty-state"><i class="fas fa-inbox"></i>No neighborhood watch incidents assigned to you yet.</td></tr>';
                    updateNavBadges();
                    setInitialSectionIfNeeded();
                    return;
                }

                renderNwIncidentsTable();
                updateNavBadges();
                setInitialSectionIfNeeded();
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Error loading neighborhood watch incidents.</td></tr>';
                portalNwIncidents = [];
                updateNavBadges();
            }
        }

        function buildNwIncidentTimeline(report) {
            const steps = [
                { label: 'Report submitted', value: report.created_at },
                { label: 'Assigned to you', value: report.assigned_at },
                { label: 'Resolved', value: report.resolved_at }
            ].filter(step => step.value);

            if (steps.length === 0) {
                return '<div class="complaint-detail"><strong>Timeline:</strong> No timeline entries yet.</div>';
            }

            return `
                <div class="complaint-detail"><strong>Timeline</strong></div>
                <div style="border-left:2px solid var(--border-color);margin:0.5rem 0 1rem 0.75rem;padding-left:1rem;">
                    ${steps.map(step => `
                        <div style="margin-bottom:0.85rem;">
                            <div style="font-weight:600;color:var(--tertiary-color);">${escapeHtml(step.label)}</div>
                            <div style="font-size:0.85rem;color:var(--text-secondary);">${formatDateTime(step.value)}</div>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        function openNwIncidentModal(id) {
            const report = nwIncidentData[id];
            if (!report) return;

            document.getElementById('resolutionNwIncidentId').value = id;
            document.getElementById('nwResolutionReport').value = report.resolution_report || '';

            let photoHtml = '';
            if (report.photo_data) {
                photoHtml = `<div class="complaint-detail"><strong>Photo:</strong><br><img src="${report.photo_data}" alt="Incident photo" class="incident-photo" onclick="window.open(this.src, '_blank')" title="Click to view full size"></div>`;
            }

            document.getElementById('nwIncidentDetailContent').innerHTML = `
                <div class="complaint-detail"><strong>Report ID:</strong> ${escapeHtml(report.report_id)}</div>
                <div class="complaint-detail"><strong>Member:</strong> ${escapeHtml(report.member_name)} · ${escapeHtml(report.member_contact)}</div>
                <div class="complaint-detail"><strong>Location:</strong> ${escapeHtml(report.location)}</div>
                <div class="complaint-detail"><strong>Description:</strong><br>${escapeHtml(report.description)}</div>
                ${photoHtml}
                <div class="complaint-detail"><strong>Status:</strong> <span class="status-badge ${nwIncidentStatusClass(report.status)}">${escapeHtml(report.status)}</span></div>
                ${buildNwIncidentTimeline(report)}
            `;
            const isResolved = report.status === 'Resolved' || report.status === 'Closed';
            document.getElementById('nwResolutionReport').readOnly = isResolved;
            document.getElementById('saveNwProgressBtn').style.display = isResolved ? 'none' : '';
            document.getElementById('resolveNwIncidentBtn').style.display = isResolved ? 'none' : '';
            document.getElementById('nwIncidentResolutionModal').classList.add('active');
        }

        function closeNwIncidentModal() {
            document.getElementById('nwIncidentResolutionModal').classList.remove('active');
            document.getElementById('nwResolutionReport').readOnly = false;
            document.getElementById('saveNwProgressBtn').style.display = '';
            document.getElementById('resolveNwIncidentBtn').style.display = '';
        }

        async function submitNwIncidentResolution(status) {
            const id = parseInt(document.getElementById('resolutionNwIncidentId').value, 10);
            const resolutionReport = document.getElementById('nwResolutionReport').value.trim();
            const alertEl = document.getElementById('nwIncidentsAlert');

            if (!id || !resolutionReport) {
                alert('Please enter your resolution report.');
                return;
            }

            try {
                const res = await fetch('api/bpso-neighborhood-watcher-incidents.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'submit_resolution', id, resolution_report: resolutionReport, status })
                });
                const result = await res.json();
                if (result.success) {
                    alertEl.innerHTML = `<div class="alert alert-success">${escapeHtml(result.message || 'Report saved.')}</div>`;
                    closeNwIncidentModal();
                    await loadNwIncidents();
                    await loadProfile();
                } else {
                    alert(result.message || 'Failed to submit report.');
                }
            } catch (e) {
                alert('Network error. Please try again.');
            }
        }

        document.getElementById('nwIncidentResolutionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitNwIncidentResolution('Resolved');
        });

        window.onclick = function(event) {
            const complaintModal = document.getElementById('complaintResolutionModal');
            const nwIncidentModal = document.getElementById('nwIncidentResolutionModal');
            const reportModal = document.getElementById('reportDetailModal');
            if (event.target === complaintModal) closeComplaintModal();
            if (event.target === nwIncidentModal) closeNwIncidentModal();
            if (event.target === reportModal) closeReportModal();
        };

        async function refreshAllData() {
            await Promise.all([loadProfile(), loadSchedules(), loadReports(), loadComplaints(), loadNwIncidents()]);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            if (localStorage.getItem('bpsoSidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }

            updateDateTime();
            setInterval(updateDateTime, 1000);
            refreshAllData();
            refreshTimer = setInterval(refreshAllData, 60000);
            const now = new Date();
            document.getElementById('reportTime').value = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
        });
    </script>
    <?php require __DIR__ . '/includes/bpso_notifications_script.php'; ?>
    <script src="js/mobile-shell.js"></script>
</body>
</html>

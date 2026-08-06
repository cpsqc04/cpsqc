<?php
require_once __DIR__ . '/includes/bpso_auth.php';

requireBpsoLogin();

$personnelName = htmlspecialchars(getBpsoPersonnelName());
$personnelCode = htmlspecialchars(getBpsoPersonnelCode());
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Patrol Attendance - Alertara</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/admin-sidebar.css">
    <link rel="stylesheet" href="css/digital-bulletin.css">
    <link rel="stylesheet" href="css/notifications.css">
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
        .sidebar.collapsed .nav-submodule { padding: 0.75rem; justify-content: center; min-height: 44px; margin: 0.25rem 0.5rem; border-radius: 8px; }
        .sidebar.collapsed .nav-submodule-dashboard { padding: 0.75rem; justify-content: center; }
        .sidebar.collapsed .nav-submodule:hover { padding-left: 0.75rem; }
        .sidebar.collapsed .nav-submodule-text { opacity: 0; width: 0; overflow: hidden; display: none; }
        .sidebar.collapsed .nav-badge { display: none !important; }
        .sidebar.collapsed .nav-submodule.active { border-left: none; border-top: 3px solid var(--primary-color); box-shadow: none; }
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
        .user-id-display { color: rgba(255, 255, 255, 0.7); font-size: 0.78rem; font-weight: 500; text-align: center; padding: 0.15rem 0.75rem 0; word-break: break-word; max-width: 100%; line-height: 1.3; }
        .sidebar.collapsed .user-name-display,
        .sidebar.collapsed .user-id-display { opacity: 0; height: 0; padding: 0; overflow: hidden; font-size: 0; }
        .sidebar-nav { padding: 0.35rem 0; overflow-y: auto; overflow-x: hidden; flex: 1; display: flex; flex-direction: column; min-height: 0; scrollbar-width: thin; scrollbar-color: rgba(255, 255, 255, 0.22) transparent; }
        .sidebar-nav::-webkit-scrollbar { width: 5px; }
        .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.22); border-radius: 999px; }
        .sidebar-nav::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.35); }
        .nav-module { margin-bottom: 0.125rem; }
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
            font-size: 0.84rem !important;
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
            font-size: inherit;
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
            font-size: 0.8rem !important;
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
            padding: 0.75rem 1.25rem !important;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            font-size: 0.84rem !important;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-family: inherit;
            position: relative;
        }
        .nav-submodule-dashboard:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            padding-left: 1.25rem !important;
        }
        .nav-submodule-dashboard.active {
            background: rgba(76, 138, 137, 0.35);
            color: #fff;
            box-shadow: inset 3px 0 0 var(--primary-color);
            font-weight: 600;
        }
        .nav-submodule-dashboard.active .nav-submodule-icon i { color: #fff; }
        .sidebar.collapsed .nav-submodule-dashboard {
            padding: 0.75rem !important;
            justify-content: center;
        }
        .sidebar.collapsed .nav-submodule-dashboard:hover {
            padding-left: 0.75rem !important;
        }
        .sidebar.collapsed .nav-submodule::after { content: attr(data-tooltip); position: absolute; left: 100%; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.9); color: #fff; padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.85rem; white-space: nowrap; opacity: 0; pointer-events: none; margin-left: 0.75rem; z-index: 2000; }
        .sidebar.collapsed .nav-submodule:hover::after { opacity: 1; }
        .personnel-status-chip { display: inline-flex; align-items: center; gap: 0.35rem; margin-top: 0.25rem; padding: 0.28rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: rgba(255,255,255,0.12); color: rgba(255,255,255,0.9); }
        .personnel-status-chip.available { background: rgba(16,185,129,0.2); color: #a7f3d0; }
        .personnel-status-chip.assigned { background: rgba(59,130,246,0.2); color: #bfdbfe; }
        .personnel-status-chip.off-duty { background: rgba(239,68,68,0.2); color: #fecaca; }
        .personnel-status-chip.at-hall { background: rgba(16,185,129,0.25); color: #a7f3d0; }
        .user-info { display: flex; align-items: center; gap: 1rem; margin-left: 2rem; overflow: visible; position: relative; z-index: 1200; }
        .notification-container { position: relative; display: flex; align-items: center; z-index: 1200; }
        .notification-bell { position: relative; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: transparent; border: none; color: var(--text-color); font-size: 1.25rem; cursor: pointer; border-radius: 8px; transition: all 0.2s ease; }
        .notification-bell:hover { background: rgba(28, 37, 65, 0.05); color: var(--primary-color); }
        .notification-badge { position: absolute; top: 4px; right: 4px; background: #ef4444; color: white; font-size: 0.7rem; font-weight: 600; padding: 2px 6px; border-radius: 10px; min-width: 18px; text-align: center; display: none; line-height: 1.2; }
        .notification-badge.show { display: block !important; }
        .notification-dropdown { position: absolute; top: calc(100% + 10px); right: 0; width: min(380px, calc(100vw - 1.5rem)); max-height: 500px; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15); display: none; flex-direction: column; z-index: 1300; overflow: hidden; border: 1px solid var(--border-color); }
        .notification-dropdown.show { display: flex !important; }
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
        .attendance-hero {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.75rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        .attendance-identity { flex: 1; min-width: 220px; }
        .attendance-full-name {
            margin: 0 0 0.15rem;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--tertiary-color);
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .attendance-live-clock {
            margin: 0;
            font-size: 2.35rem;
            font-weight: 700;
            color: var(--text-color);
            letter-spacing: 0.02em;
            font-variant-numeric: tabular-nums;
            line-height: 1.15;
        }
        .attendance-live-date {
            margin: 0.4rem 0 0;
            color: var(--text-secondary);
            font-size: 0.92rem;
        }
        .attendance-shift-running {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin: 0.65rem 0 0;
            font-size: 0.88rem;
            color: #047857;
            font-weight: 600;
        }
        .attendance-shift-running::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
            flex-shrink: 0;
        }
        .attendance-shift-running.stopped {
            color: var(--text-secondary);
            font-weight: 500;
        }
        .attendance-shift-running.stopped::before {
            background: #94a3b8;
            box-shadow: none;
        }
        .attendance-actions {
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }
        .btn-clock-toggle {
            padding: 0.9rem 1.6rem;
            border: none;
            border-radius: 10px;
            font: inherit;
            font-size: 0.98rem;
            font-weight: 700;
            cursor: pointer;
            min-width: 160px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }
        .btn-clock-toggle:hover { transform: translateY(-1px); }
        .btn-clock-toggle.clock-on {
            background: #059669;
            color: #fff;
            box-shadow: 0 6px 16px rgba(5, 150, 105, 0.25);
        }
        .btn-clock-toggle.clock-on:hover { background: #047857; }
        .btn-clock-toggle.clock-out {
            background: #dc2626;
            color: #fff;
            box-shadow: 0 6px 16px rgba(220, 38, 38, 0.22);
        }
        .btn-clock-toggle.clock-out:hover { background: #b91c1c; }
        .btn-clock-toggle:disabled { opacity: 0.55; cursor: not-allowed; transform: none; box-shadow: none; }
        .activity-section-title {
            margin: 0 0 1rem;
            color: var(--tertiary-color);
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .timesheet-filters { margin-bottom: 1rem; display: flex; align-items: flex-end; gap: 0.75rem; flex-wrap: wrap; }
        .timesheet-filters .filter-field { display: flex; flex-direction: column; gap: 0.35rem; }
        .timesheet-filters label { font-weight: 500; color: var(--text-secondary); font-size: 0.85rem; }
        .timesheet-filters input[type="date"] { padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; }
        .timesheet-filters .filter-hint { color: var(--text-secondary); font-size: 0.8rem; width: 100%; margin: 0; }
        .btn-apply-filter { padding: 0.55rem 1rem; border: none; border-radius: 8px; background: var(--primary-color); color: #fff; font: inherit; font-size: 0.9rem; font-weight: 600; cursor: pointer; }
        .btn-apply-filter:hover { background: #4ca8a6; }
        .activity-badge { padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; display: inline-block; }
        .activity-clock-on { background: #d1fae5; color: #047857; }
        .activity-clock-out { background: #fee2e2; color: #991b1b; }
        @media (max-width: 640px) {
            .attendance-hero { align-items: stretch; }
            .attendance-actions { width: 100%; }
            .btn-clock-toggle { width: 100%; }
            .attendance-live-clock { font-size: 1.85rem; }
        }
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
        .nav-submodule-icon i { color: rgba(255, 255, 255, 0.75); transition: color 0.2s ease; }
        .sidebar-footer { margin-top: auto; padding: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.1); position: relative; flex-shrink: 0; }
        .sidebar-logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.5rem; background: rgba(239, 68, 68, 0.1); color: rgba(255, 255, 255, 0.9); text-decoration: none; border-radius: 8px; font-size: 1rem; font-weight: 500; border: 1px solid rgba(239, 68, 68, 0.2); width: 100%; box-sizing: border-box; }
        .sidebar-logout-btn:hover { background: rgba(239, 68, 68, 0.2); color: #fff; }
        .main-wrapper { margin-left: 320px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; transition: margin-left 0.3s ease; }
        body.sidebar-collapsed .main-wrapper { margin-left: 80px; }
        .top-header { background: var(--header-bg); padding: 1.5rem 2rem 1rem; display: flex; justify-content: space-between; align-items: flex-end; position: sticky; top: 0; z-index: 1100; border-bottom: 1px solid var(--border-color); overflow: visible; }
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
        .btn-edit { padding: 0.5rem 1rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; margin-left: 0.35rem; }
        .btn-edit:hover { background: #4ca8a6; }
        .btn-edit:disabled { background: #94a3b8; cursor: not-allowed; opacity: 0.7; }
        .report-actions { display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center; }
        .doc-cell { display: flex; flex-direction: column; align-items: flex-start; gap: 0.4rem; min-width: 120px; }
        .doc-thumb {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            cursor: pointer;
            background: #f8fafc;
        }
        .doc-empty { color: var(--text-secondary); font-size: 0.85rem; }
        .doc-modal-photo {
            max-width: 100%;
            max-height: 360px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            margin-top: 0.5rem;
            cursor: pointer;
        }
        .assignment-info {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.85rem 1rem;
            margin-bottom: 1rem;
            background: #f8fafc;
            color: var(--text-color);
            font-size: 0.92rem;
        }
        .assignment-info strong { color: var(--tertiary-color); }
        .toast-popup {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 10000;
            background: #047857;
            color: #fff;
            padding: 0.9rem 1.2rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.18);
            font-weight: 600;
            opacity: 0;
            transform: translateY(-8px);
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        .toast-popup.show { opacity: 1; transform: translateY(0); }
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
        .btn-report { padding: 0.5rem 1rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; }
        .btn-report:hover { background: #4ca8a6; }
        .actions-submitted { color: #047857; font-weight: 600; font-size: 0.9rem; }
        .report-hint { margin: 0 0 1rem; color: var(--text-secondary); font-size: 0.9rem; }
        .btn-cancel-add { padding: 0.5rem 1rem; background: #e5e7eb; color: #374151; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; margin-right: 0.5rem; }
        .btn-cancel-add:hover { background: #d1d5db; }
        .reports-toolbar { display: flex; align-items: flex-end; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .reports-toolbar .filter-field { display: flex; flex-direction: column; gap: 0.35rem; flex: 1; min-width: 220px; }
        .reports-toolbar label { font-weight: 500; color: var(--text-secondary); font-size: 0.85rem; }
        .reports-toolbar input[type="text"] { padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; }
        .reports-toolbar .btn-search { padding: 0.55rem 1rem; background: var(--primary-color); color: #fff; border: none; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; }
        .reports-toolbar .btn-search:hover { background: #4ca8a6; }
        .reports-pagination { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem; }
        .reports-pagination .page-info { color: var(--text-secondary); font-size: 0.9rem; }
        .reports-pagination .page-buttons { display: flex; gap: 0.5rem; }
        .reports-pagination button { padding: 0.5rem 0.9rem; border: 1px solid var(--border-color); background: #fff; border-radius: 8px; cursor: pointer; font-size: 0.88rem; }
        .reports-pagination button:hover:not(:disabled) { background: #f8fafc; }
        .reports-pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
        .alert { padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.92rem; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        #accountEmailAlert,
        #accountPasswordAlert { display: none; }
        .account-section { margin-bottom: 2.5rem; padding-bottom: 2.5rem; border-bottom: 1px solid var(--border-color); }
        .account-section:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
        .account-section .section-heading { display: flex; align-items: center; gap: 0.5rem; }
        .field-hint { margin: 0.4rem 0 0; color: var(--text-secondary); font-size: 0.82rem; line-height: 1.4; }
        .btn-submit.inline-icon { display: inline-flex; align-items: center; gap: 0.5rem; margin-top: 1.25rem; }
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
                <a href="patrol-attendance.php">
                    <img src="images/tara.png" alt="Alertara Logo">
                </a>
                <div class="user-name-display" id="sidebarPersonnelName"><?php echo $personnelName; ?></div>
                <div class="user-id-display" id="sidebarPersonnelId"><?php echo $personnelCode !== '' ? $personnelCode : ''; ?></div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <button type="button" class="nav-submodule nav-submodule-dashboard active" data-tab="bulletin" data-title="Digital Bulletin" data-tooltip="Digital Bulletin" onclick="switchSection(this, 'bulletin', 'Digital Bulletin')">
                <span class="nav-submodule-icon"><i class="fas fa-bullhorn"></i></span>
                <span class="nav-submodule-text">Digital Bulletin</span>
            </button>
            <button type="button" class="nav-submodule nav-submodule-dashboard" data-tab="dashboard" data-title="Patrol Attendance" data-tooltip="Clock On/Out" onclick="switchSection(this, 'dashboard', 'Patrol Attendance')">
                <span class="nav-submodule-icon"><i class="fas fa-building"></i></span>
                <span class="nav-submodule-text">Clock On/Out</span>
            </button>
            <button type="button" class="nav-submodule nav-submodule-dashboard" data-tab="timesheet" data-title="Timesheet Summary" data-tooltip="Timesheet Summary" onclick="switchSection(this, 'timesheet', 'Timesheet Summary')">
                <span class="nav-submodule-icon"><i class="fas fa-clock"></i></span>
                <span class="nav-submodule-text">Timesheet Summary</span>
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
                    <button type="button" class="nav-submodule" data-tab="tips" data-title="Assigned Tips" data-tooltip="Assigned Tips" onclick="switchSection(this, 'tips', 'Assigned Tips')">
                        <span class="nav-submodule-icon"><i class="fas fa-comment-dots"></i></span>
                        <span class="nav-submodule-text">Assigned Tips</span>
                        <span class="nav-badge" id="badge-tips">0</span>
                    </button>
                </div>
            </div>
            <button type="button" class="nav-submodule nav-submodule-dashboard" data-tab="account" data-title="Account Settings" data-tooltip="Account Settings" onclick="switchSection(this, 'account', 'Account Settings')">
                <span class="nav-submodule-icon"><i class="fas fa-user-cog"></i></span>
                <span class="nav-submodule-text">Account Settings</span>
            </button>
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
                    <h1 class="page-title" id="pageTitle">Digital Bulletin</h1>
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

        <main class="content-area bulletin-fullscreen">
            <div class="page-content bulletin-fullscreen">
                <section id="panel-bulletin" class="portal-panel active">
                    <div id="patrolBulletinRoot">
                        <div data-db-carousel></div>
                        <div data-db-announcements></div>
                    </div>
                </section>

                <section id="panel-dashboard" class="portal-panel">
                    <div class="attendance-hero" id="attendanceCard">
                        <div class="attendance-identity">
                            <h2 class="attendance-full-name" id="attendanceFullName"><?php echo $personnelName; ?></h2>
                            <p class="attendance-live-clock" id="attendanceLiveClock">--:--:--</p>
                            <p class="attendance-live-date" id="attendanceLiveDate">Manila Time</p>
                            <p class="attendance-shift-running stopped" id="attendanceShiftRunning">Shift not started</p>
                        </div>
                        <div class="attendance-actions">
                            <button type="button" class="btn-clock-toggle clock-on" id="btnClockToggle" onclick="toggleClockOnOut()">
                                <i class="fas fa-sign-in-alt"></i> Clock On
                            </button>
                        </div>
                    </div>

                    <h3 class="activity-section-title"><i class="fas fa-list"></i> Today's Activity Log</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Activity</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody id="activityLogBody">
                                <tr><td colspan="4" class="empty-state">Loading activity...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="panel-timesheet" class="portal-panel">
                    <h2 class="section-heading">Timesheet Summary</h2>
                    <div class="timesheet-filters">
                        <div class="filter-field">
                            <label for="timesheetDateFrom">Date From</label>
                            <input type="date" id="timesheetDateFrom">
                        </div>
                        <div class="filter-field">
                            <label for="timesheetDateTo">Date To</label>
                            <input type="date" id="timesheetDateTo">
                        </div>
                        <button type="button" class="btn-apply-filter" onclick="loadTimesheetSummary()">
                            <i class="fas fa-search"></i> Apply
                        </button>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Duty</th>
                                    <th>Clock On</th>
                                    <th>Clock Out</th>
                                    <th>Duration</th>
                                    <th>Overtime</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="timesheetTableBody">
                                <tr><td colspan="7" class="empty-state">Loading timesheet...</td></tr>
                            </tbody>
                        </table>
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
                                    <th>Shift</th>
                                    <th>Patrol Zone</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="scheduleTableBody">
                                <tr><td colspan="4" class="empty-state">Loading schedule...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="panel-report" class="portal-panel">
                    <h2 class="section-heading">Submit Patrol Report</h2>
                    <div id="reportAlert"></div>
                    <p class="report-hint">Submit at least one report per shift. You can submit additional reports for the same assignment while still clocked on.</p>
                    <div id="reportAssignmentInfo" class="assignment-info">No active assignment selected. Open Submit Report from My Schedule.</div>
                    <form id="reportForm" class="form-grid">
                        <input type="hidden" id="reportSchedule" name="schedule_id" value="">
                        <input type="hidden" id="editingReportId" value="">
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
                        <div class="form-group">
                            <label for="reportDocumentation">Documentation <span style="font-weight:400;color:var(--text-secondary);">(optional)</span></label>
                            <input type="file" id="reportDocumentation" name="documentation" accept="image/jpeg,image/png,image/gif,image/webp">
                            <input type="hidden" id="reportDocumentationData" value="">
                        </div>
                        <div class="form-grid-actions">
                            <button type="button" class="btn-cancel-add" id="cancelAddReportBtn" hidden onclick="cancelAddReportForm()">Cancel</button>
                            <button type="submit" class="btn-submit" id="reportSubmitBtn">Submit</button>
                        </div>
                    </form>
                </section>

                <section id="panel-reports" class="portal-panel">
                    <h2 class="section-heading">My Reports</h2>
                    <div class="reports-toolbar">
                        <div class="filter-field">
                            <input type="text" id="reportsSearch" placeholder="" aria-label="Search reports">
                        </div>
                        <button type="button" class="btn-search" onclick="searchReports()">Search</button>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Route</th>
                                    <th>Incidents</th>
                                    <th>Documentation</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="reportsTableBody">
                                <tr><td colspan="5" class="empty-state">Loading reports...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="reports-pagination">
                        <div class="page-info" id="reportsPageInfo">Page 1 of 1</div>
                        <div class="page-buttons">
                            <button type="button" id="reportsPrevBtn" onclick="changeReportsPage(-1)" disabled>Previous</button>
                            <button type="button" id="reportsNextBtn" onclick="changeReportsPage(1)" disabled>Next</button>
                        </div>
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
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="complaintsTableBody">
                                <tr><td colspan="5" class="empty-state">Loading complaints...</td></tr>
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

                <section id="panel-tips" class="portal-panel">
                    <h2 class="section-heading">Assigned Tips</h2>
                    <div id="tipsAlert"></div>
                    <div class="filter-tabs">
                        <button type="button" class="filter-tab active" data-filter="all" onclick="setTipFilter('all', this)">All</button>
                        <button type="button" class="filter-tab" data-filter="assigned" onclick="setTipFilter('assigned', this)">Assigned</button>
                        <button type="button" class="filter-tab" data-filter="resolved" onclick="setTipFilter('resolved', this)">Resolved</button>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tip ID</th>
                                    <th>Location</th>
                                    <th>Outcome</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tipsTableBody">
                                <tr><td colspan="5" class="empty-state">Loading tips...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="panel-account" class="portal-panel">
                    <div class="account-section">
                        <h2 class="section-heading"><i class="fas fa-envelope"></i> Registered Email</h2>
                        <div id="accountEmailAlert" class="alert"></div>
                        <form id="accountEmailForm" autocomplete="off">
                            <div class="form-grid form-grid-split">
                                <div class="form-group">
                                    <label for="accountPersonnelName">Personnel Name</label>
                                    <input id="accountPersonnelName" type="text" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="accountPersonnelCode">Personnel ID</label>
                                    <input id="accountPersonnelCode" type="text" readonly>
                                </div>
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label for="accountEmail">Email Address *</label>
                                    <input id="accountEmail" type="email" required>
                                    <p class="field-hint">This is the email used for your patrol account login and notifications.</p>
                                </div>
                            </div>
                            <button type="submit" class="btn-submit inline-icon"><i class="fas fa-save"></i> Update Email</button>
                        </form>
                    </div>

                    <div class="account-section">
                        <h2 class="section-heading"><i class="fas fa-key"></i> Change Password</h2>
                        <div id="accountPasswordAlert" class="alert"></div>
                        <form id="accountPasswordForm" autocomplete="off">
                            <div class="form-grid form-grid-split">
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label for="accountCurrentPassword">Current Password *</label>
                                    <input id="accountCurrentPassword" type="password" required>
                                </div>
                                <div class="form-group">
                                    <label for="accountNewPassword">New Password *</label>
                                    <input id="accountNewPassword" type="password" minlength="8" autocomplete="new-password" required>
                                    <p class="field-hint">At least 8 characters with uppercase, lowercase, and a number or special character (e.g. @, #, _).</p>
                                </div>
                                <div class="form-group">
                                    <label for="accountConfirmPassword">Confirm New Password *</label>
                                    <input id="accountConfirmPassword" type="password" minlength="8" autocomplete="new-password" required>
                                </div>
                            </div>
                            <button type="submit" class="btn-submit inline-icon"><i class="fas fa-lock"></i> Update Password</button>
                        </form>
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
                    <button type="submit" id="resolveNwIncidentBtn" class="btn-submit">Mark as Resolved</button>
                </div>
            </form>
        </div>
    </div>

    <div id="tipResolutionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Tip Response Report</h2>
                <button type="button" class="close-modal" onclick="closeTipModal()">&times;</button>
            </div>
            <div id="tipDetailContent"></div>
            <form id="tipResolutionForm" class="form-grid" style="margin-top:1.5rem;">
                <input type="hidden" id="resolutionTipId">
                <div class="form-group">
                    <label for="tipResolutionReport">What actions did you take? *</label>
                    <textarea id="tipResolutionReport" required placeholder="Describe how you responded to the tip, actions taken, and findings..."></textarea>
                </div>
                <div class="form-group">
                    <label for="tipOutcomeSelect">Outcome *</label>
                    <select id="tipOutcomeSelect" required>
                        <option value="Under Investigation">Under Investigation</option>
                        <option value="Investigation Successful">Investigation Successful (No Arrest)</option>
                        <option value="Arrest Made">Arrest Made</option>
                        <option value="Unfounded / No Action">Unfounded / No Action</option>
                    </select>
                    <p class="field-hint">Final outcome is set by your report. Police backup (if requested) is assistance only.</p>
                </div>
                <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                    <button type="button" id="saveTipProgressBtn" class="btn-submit" onclick="submitTipResolution('Assigned')">Save Progress</button>
                    <button type="submit" id="resolveTipBtn" class="btn-submit">Mark as Resolved</button>
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

    <div id="toastPopup" class="toast-popup" role="status" aria-live="polite"></div>

    <script src="js/photo-lightbox.js"></script>
    <script>
        let scheduleData = {};
        let complaintData = {};
        let nwIncidentData = {};
        let tipData = {};
        let portalSchedules = [];
        let portalComplaints = [];
        let portalNwIncidents = [];
        let portalTips = [];
        let portalReports = [];
        let reportsPage = 1;
        let reportsSearchQuery = '';
        const REPORTS_PER_PAGE = 10;
        let complaintFilter = 'all';
        let nwIncidentFilter = 'all';
        let tipFilter = 'all';
        let scheduleFilter = 'all';
        let refreshTimer = null;
        let initialSectionSet = false;
        let seenScheduleBadgeIds = loadSeenBadgeIds('bpso_seen_schedule_badge_ids');
        let seenReportBadgeIds = loadSeenBadgeIds('bpso_seen_report_badge_ids');

        function loadSeenBadgeIds(key) {
            try {
                const raw = localStorage.getItem(key);
                const parsed = raw ? JSON.parse(raw) : [];
                return new Set(Array.isArray(parsed) ? parsed.map(String) : []);
            } catch (e) {
                return new Set();
            }
        }

        function saveSeenBadgeIds(key, idSet) {
            try {
                localStorage.setItem(key, JSON.stringify([...idSet]));
            } catch (e) {
                // ignore storage failures
            }
        }

        function getOpenScheduleAssignments() {
            return portalSchedules.filter(s => s.status === 'Scheduled' || s.status === 'In Progress');
        }

        function getAssignmentsNeedingReport() {
            return portalSchedules.filter(s =>
                (s.status === 'Scheduled' || s.status === 'In Progress') &&
                getReportCountForSchedule(s.id) === 0
            );
        }

        function markNavBadgesSeen(tab) {
            if (tab === 'schedule') {
                getOpenScheduleAssignments().forEach(s => seenScheduleBadgeIds.add(String(s.id)));
                saveSeenBadgeIds('bpso_seen_schedule_badge_ids', seenScheduleBadgeIds);
            } else if (tab === 'report') {
                getAssignmentsNeedingReport().forEach(s => seenReportBadgeIds.add(String(s.id)));
                saveSeenBadgeIds('bpso_seen_report_badge_ids', seenReportBadgeIds);
            }
            updateNavBadges();
        }

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

            const contentArea = document.querySelector('.content-area');
            const pageContent = document.querySelector('.page-content');
            const isBulletin = tab === 'bulletin';
            if (contentArea) contentArea.classList.toggle('bulletin-fullscreen', isBulletin);
            if (pageContent) pageContent.classList.toggle('bulletin-fullscreen', isBulletin);

            if (tab === 'timesheet') {
                loadTimesheetSummary();
            }

            if (tab === 'bulletin' && window.DigitalBulletin) {
                const openPostId = parseInt(button?.dataset?.openPostId || '0', 10) || 0;
                if (button) delete button.dataset.openPostId;
                DigitalBulletin.mount({ root: '#patrolBulletinRoot', audience: 'patrol', openPostId: openPostId });
            }

            if (tab === 'schedule' || tab === 'report') {
                markNavBadgesSeen(tab);
            }

            if (tab === 'report') {
                populateReportScheduleOptions();
                const scheduleId = parseInt(document.getElementById('reportSchedule')?.value || '0', 10) || 0;
                if (scheduleId && !document.getElementById('editingReportId')?.value) {
                    fillReportFromSchedule(scheduleId, true);
                }
                updateReportFormForSelectedSchedule(true);
            }

            if (tab === 'account') {
                loadAccountSettings();
            }
        }

        function goToTab(tab, openPostId) {
            const btn = document.querySelector(`.nav-submodule[data-tab="${tab}"]`);
            if (btn) {
                if (openPostId) {
                    btn.dataset.openPostId = String(openPostId);
                }
                switchSection(btn, tab, btn.dataset.title);
            }
        }
        window.goToTab = goToTab;

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
            const openSchedules = getOpenScheduleAssignments();
            const needingReport = getAssignmentsNeedingReport();
            const openComplaints = portalComplaints.filter(c => c.status === 'Processing');
            const openNwIncidents = portalNwIncidents.filter(r => r.status === 'In Progress');
            const openTips = portalTips.filter(t => t.status === 'Assigned');

            const unseenSchedules = openSchedules.filter(s => !seenScheduleBadgeIds.has(String(s.id)));
            const unseenReports = needingReport.filter(s => !seenReportBadgeIds.has(String(s.id)));

            setNavBadge('badge-schedule', unseenSchedules.length);
            setNavBadge('badge-report', unseenReports.length);
            setNavBadge('badge-complaints', openComplaints.length);
            setNavBadge('badge-nw-incidents', openNwIncidents.length);
            setNavBadge('badge-tips', openTips.length);
        }

        function getTodayDateString() {
            return new Date().toISOString().slice(0, 10);
        }

        function setInitialSectionIfNeeded() {
            if (initialSectionSet) return;
            initialSectionSet = true;
            goToTab('bulletin');
        }

        function setScheduleFilter(filter, button) {
            scheduleFilter = filter;
            document.querySelectorAll('#panel-schedule .filter-tab').forEach(tab => tab.classList.remove('active'));
            button.classList.add('active');
            renderScheduleTable();
        }

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

        function formatShiftWithHours(value) {
            const raw = String(value || '').trim().toLowerCase();
            if (raw.includes('night')) return 'Night Shift (8:00 PM – 8:00 AM)';
            if (raw.includes('day')) return 'Day Shift (8:00 AM – 8:00 PM)';
            return String(value || '').trim() || '—';
        }

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
            const rows = getFilteredSchedules();

            if (rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="empty-state"><i class="fas fa-calendar-times"></i>No patrol assignments in this view.</td></tr>';
            } else {
                tbody.innerHTML = rows.map(row => {
                    scheduleData[row.id] = row;
                    const zone = row.patrol_zone || row.location || row.route || '—';
                    const shiftLabel = formatShiftWithHours(row.shift);
                    const isSubmitted = getReportCountForSchedule(row.id) > 0 || row.status === 'Completed';
                    const canReport = !isSubmitted && (row.status === 'Scheduled' || row.status === 'In Progress');
                    let actions = '—';
                    if (isSubmitted) {
                        actions = '<span class="actions-submitted">Submitted</span>';
                    } else if (canReport) {
                        actions = `<button type="button" class="btn-report" onclick="openReportForSchedule(${row.id})">Submit Report</button>`;
                    }
                    return `<tr>
                        <td>${escapeHtml(row.schedule_date)}</td>
                        <td>${escapeHtml(shiftLabel)}</td>
                        <td>${escapeHtml(zone)}</td>
                        <td>${actions}</td>
                    </tr>`;
                }).join('');
            }

            populateReportScheduleOptions();
            updateReportFormForSelectedSchedule();
        }

        function getReportCountForSchedule(scheduleId) {
            const id = Number(scheduleId);
            if (!id) return 0;
            return portalReports.filter(r => Number(r.schedule_id) === id && r.status !== 'Scheduled').length;
        }

        function populateReportScheduleOptions(selectedId = null) {
            const scheduleInput = document.getElementById('reportSchedule');
            if (!scheduleInput) return;

            let scheduleId = selectedId !== null ? Number(selectedId) : (parseInt(scheduleInput.value, 10) || 0);
            if (!scheduleId) {
                const needing = getAssignmentsNeedingReport();
                if (needing.length > 0) {
                    scheduleId = Number(needing[0].id);
                } else if (portalSchedules.length > 0) {
                    scheduleId = Number(portalSchedules[0].id);
                }
            }

            if (scheduleId) {
                scheduleInput.value = String(scheduleId);
                updateAssignmentInfo(scheduleId);
            } else {
                scheduleInput.value = '';
                updateAssignmentInfo(0);
            }
        }

        function updateAssignmentInfo(scheduleId) {
            const info = document.getElementById('reportAssignmentInfo');
            if (!info) return;
            const row = scheduleData[scheduleId] || portalSchedules.find(s => Number(s.id) === Number(scheduleId));
            if (!row) {
                info.textContent = 'No active assignment selected. Open Submit Report from My Schedule.';
                return;
            }
            const zone = row.patrol_zone || row.route || '—';
            info.innerHTML = `<strong>Assignment:</strong> ${escapeHtml(row.schedule_date || '')} · ${escapeHtml(formatShiftWithHours(row.shift))} · ${escapeHtml(zone)}`;
        }

        function setReportFormVisible(visible, isAdditional = false) {
            const form = document.getElementById('reportForm');
            const cancelBtn = document.getElementById('cancelAddReportBtn');
            const submitBtn = document.getElementById('reportSubmitBtn');
            if (!form) return;

            form.hidden = !visible;
            if (cancelBtn) {
                const editingId = parseInt(document.getElementById('editingReportId')?.value || '0', 10) || 0;
                cancelBtn.hidden = !(visible && (isAdditional || editingId > 0));
            }
            if (submitBtn) {
                const editingId = parseInt(document.getElementById('editingReportId')?.value || '0', 10) || 0;
                submitBtn.textContent = editingId > 0 ? 'Save Changes' : 'Submit';
            }
        }

        function updateReportFormForSelectedSchedule(forceShowForm = true) {
            const scheduleInput = document.getElementById('reportSchedule');
            if (!scheduleInput) return;

            const scheduleId = parseInt(scheduleInput.value, 10) || 0;
            updateAssignmentInfo(scheduleId);

            if (!scheduleId) {
                setReportFormVisible(false, false);
                return;
            }

            const editingId = parseInt(document.getElementById('editingReportId')?.value || '0', 10) || 0;
            setReportFormVisible(forceShowForm || editingId > 0, false);
        }

        function cancelAddReportForm() {
            document.getElementById('reportAlert').innerHTML = '';
            document.getElementById('editingReportId').value = '';
            const scheduleId = parseInt(document.getElementById('reportSchedule')?.value || '0', 10) || 0;
            if (scheduleId) {
                fillReportFromSchedule(scheduleId, true);
            } else {
                clearReportDocumentation();
            }
            updateReportFormForSelectedSchedule(true);
        }

        function showToast(message, isError = false) {
            const toast = document.getElementById('toastPopup');
            if (!toast) return;
            toast.textContent = message;
            toast.style.background = isError ? '#b91c1c' : '#047857';
            toast.classList.add('show');
            window.clearTimeout(showToast._timer);
            showToast._timer = window.setTimeout(() => toast.classList.remove('show'), 2800);
        }

        let isClockedOn = false;
        let openSessionTimeIn = null;
        let activitySessions = [];
        const PERSONNEL_FULL_NAME = <?php echo json_encode(getBpsoPersonnelName(), JSON_UNESCAPED_UNICODE); ?>;

        function manilaParts(date = new Date()) {
            const parts = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Manila',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                weekday: 'long',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            }).formatToParts(date);

            const map = {};
            parts.forEach(part => {
                if (part.type !== 'literal') map[part.type] = part.value;
            });
            return map;
        }

        function manilaDateString(date = new Date()) {
            const p = manilaParts(date);
            return `${p.year}-${p.month}-${p.day}`;
        }

        function manilaNowDisplay() {
            const now = new Date();
            return {
                time: now.toLocaleTimeString('en-US', {
                    timeZone: 'Asia/Manila',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                }),
                dateLong: now.toLocaleDateString('en-US', {
                    timeZone: 'Asia/Manila',
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                }),
                dateShort: now.toLocaleDateString('en-US', {
                    timeZone: 'Asia/Manila',
                    weekday: 'short',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                })
            };
        }

        function formatManilaDateTime(value) {
            if (!value) return '—';
            const match = String(value).match(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
            if (!match) return String(value);
            const iso = `${match[1]}-${match[2]}-${match[3]}T${match[4]}:${match[5]}:${match[6] || '00'}+08:00`;
            const date = new Date(iso);
            if (Number.isNaN(date.getTime())) return String(value);
            return date.toLocaleString('en-US', {
                timeZone: 'Asia/Manila',
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
            });
        }

        function formatManilaDateOnly(value) {
            if (!value) return '—';
            const match = String(value).match(/(\d{4})-(\d{2})-(\d{2})/);
            if (!match) return String(value);
            const iso = `${match[1]}-${match[2]}-${match[3]}T00:00:00+08:00`;
            const date = new Date(iso);
            if (Number.isNaN(date.getTime())) return String(value);
            return date.toLocaleDateString('en-US', {
                timeZone: 'Asia/Manila',
                year: 'numeric', month: '2-digit', day: '2-digit'
            });
        }

        function formatManilaTimeOnly(value) {
            if (!value) return '—';
            const match = String(value).match(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
            if (!match) return String(value);
            const iso = `${match[1]}-${match[2]}-${match[3]}T${match[4]}:${match[5]}:${match[6] || '00'}+08:00`;
            const date = new Date(iso);
            if (Number.isNaN(date.getTime())) return String(value);
            return date.toLocaleTimeString('en-US', {
                timeZone: 'Asia/Manila',
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
            });
        }

        function formatDurationFromMs(ms) {
            const totalMinutes = Math.max(0, Math.floor(ms / 60000));
            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;
            if (hours <= 0) return minutes + ' min';
            if (minutes === 0) return hours === 1 ? '1 Hour' : hours + ' Hours';
            return hours + 'h ' + minutes + 'm';
        }

        function formatOvertimeFromMinutes(totalMinutes) {
            if (totalMinutes == null || totalMinutes <= 0) return '00:00';
            const hours = Math.floor(totalMinutes / 60);
            const mins = totalMinutes % 60;
            return String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0');
        }

        function normalizeDutyShift(duty) {
            const s = String(duty || '').toLowerCase();
            if (s.includes('night')) return 'Night Shift';
            if (s.includes('day')) return 'Day Shift';
            return '';
        }

        function getShiftEndDate(timeInValue, duty) {
            const match = String(timeInValue || '').match(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
            if (!match) return null;
            const shift = normalizeDutyShift(duty);
            if (!shift) return null;

            let y = Number(match[1]);
            let m = Number(match[2]);
            let d = Number(match[3]);
            const hour = Number(match[4]);

            if (shift === 'Night Shift' && hour < 8) {
                const prev = new Date(Date.UTC(y, m - 1, d));
                prev.setUTCDate(prev.getUTCDate() - 1);
                y = prev.getUTCFullYear();
                m = prev.getUTCMonth() + 1;
                d = prev.getUTCDate();
            }

            if (shift === 'Day Shift') {
                return new Date(`${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}T20:00:00+08:00`);
            }

            const end = new Date(Date.UTC(y, m - 1, d));
            end.setUTCDate(end.getUTCDate() + 1);
            const ey = end.getUTCFullYear();
            const em = end.getUTCMonth() + 1;
            const ed = end.getUTCDate();
            return new Date(`${ey}-${String(em).padStart(2, '0')}-${String(ed).padStart(2, '0')}T08:00:00+08:00`);
        }

        function computeLiveOvertimeLabel(row) {
            const start = parseSessionDate(row.time_in);
            if (!start) return '00:00';
            const end = row.time_out ? parseSessionDate(row.time_out) : new Date();
            if (!end) return '00:00';
            const shiftEnd = getShiftEndDate(row.time_in, row.duty || row.duty_shift);
            if (!shiftEnd) return '00:00';
            const overtimeMinutes = Math.max(0, Math.floor((end.getTime() - shiftEnd.getTime()) / 60000));
            if (overtimeMinutes <= 0) return '00:00';
            const label = formatOvertimeFromMinutes(overtimeMinutes);
            return row.time_out ? label : (label + ' (running)');
        }

        function parseSessionDate(value) {
            if (!value) return null;
            const match = String(value).match(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
            if (!match) return null;
            const date = new Date(`${match[1]}-${match[2]}-${match[3]}T${match[4]}:${match[5]}:${match[6] || '00'}+08:00`);
            return Number.isNaN(date.getTime()) ? null : date;
        }

        async function loadProfile() {
            try {
                const [profileRes, attendanceRes] = await Promise.all([
                    fetch('api/bpso_profile.php'),
                    fetch('api/bpso_attendance.php?view=my_status')
                ]);
                const result = await profileRes.json();
                const attendanceResult = await attendanceRes.json();
                if (!result.success || !result.data) {
                    return;
                }

                const nameEl = document.getElementById('attendanceFullName');
                const sidebarName = document.getElementById('sidebarPersonnelName');
                const sidebarId = document.getElementById('sidebarPersonnelId');
                const fullName = result.data.personnel_name || PERSONNEL_FULL_NAME || 'Patrol Personnel';
                const personnelId = result.data.bpso_personnel_id || '';

                if (nameEl) nameEl.textContent = fullName;
                if (sidebarName) sidebarName.textContent = fullName;
                if (sidebarId) sidebarId.textContent = personnelId;

                updateAttendanceUi(attendanceResult.data || {});
            } catch (e) {
                // Keep session-rendered sidebar values if profile refresh fails.
            }
        }

        function formatDateTime(value) {
            return formatManilaDateTime(value);
        }

        function updateAttendanceUi(data) {
            isClockedOn = Boolean(data.is_clocked_on || data.is_at_hall);
            const session = data.open_session || null;
            openSessionTimeIn = session && session.time_in ? session.time_in : null;
            activitySessions = Array.isArray(data.sessions) ? data.sessions : [];

            const btn = document.getElementById('btnClockToggle');
            if (btn) {
                if (isClockedOn) {
                    btn.className = 'btn-clock-toggle clock-out';
                    btn.innerHTML = '<i class="fas fa-sign-out-alt"></i> Clock Out';
                } else {
                    btn.className = 'btn-clock-toggle clock-on';
                    btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Clock On';
                }
                btn.disabled = false;
            }

            renderActivityLog();
            updateShiftRunningLabel();
        }

        function renderActivityLog() {
            const tbody = document.getElementById('activityLogBody');
            if (!tbody) return;

            const activities = [];
            activitySessions.forEach(session => {
                if (session.time_in) {
                    activities.push({
                        at: session.time_in,
                        activity: 'Clock On',
                        duration: session.time_out ? '—' : 'Running',
                        running: !session.time_out,
                        sessionStart: session.time_in
                    });
                }
                if (session.time_out) {
                    activities.push({
                        at: session.time_out,
                        activity: 'Clock Out',
                        duration: session.patrol_duration_label || '—',
                        running: false,
                        sessionStart: null
                    });
                }
            });

            activities.sort((a, b) => {
                const aTime = parseSessionDate(a.at)?.getTime() || 0;
                const bTime = parseSessionDate(b.at)?.getTime() || 0;
                return bTime - aTime;
            });

            if (activities.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="empty-state">No clock activity yet today.</td></tr>';
                return;
            }

            tbody.innerHTML = activities.map(item => {
                const badgeClass = item.activity === 'Clock On' ? 'activity-clock-on' : 'activity-clock-out';
                const durationAttr = item.running ? ` data-running-start="${escapeHtml(item.sessionStart || '')}"` : '';
                return `<tr>
                    <td>${escapeHtml(formatManilaDateOnly(item.at))}</td>
                    <td>${escapeHtml(formatManilaTimeOnly(item.at))}</td>
                    <td><span class="activity-badge ${badgeClass}">${escapeHtml(item.activity)}</span></td>
                    <td class="activity-duration"${durationAttr}>${escapeHtml(item.duration)}</td>
                </tr>`;
            }).join('');
        }

        function updateShiftRunningLabel() {
            const el = document.getElementById('attendanceShiftRunning');
            if (!el) return;

            if (isClockedOn && openSessionTimeIn) {
                const start = parseSessionDate(openSessionTimeIn);
                if (start) {
                    const duration = formatDurationFromMs(Date.now() - start.getTime());
                    el.textContent = 'Shift running: ' + duration;
                    el.classList.remove('stopped');
                } else {
                    el.textContent = 'Shift running';
                    el.classList.remove('stopped');
                }
            } else {
                el.textContent = 'Shift not started';
                el.classList.add('stopped');
            }

            document.querySelectorAll('.activity-duration[data-running-start]').forEach(cell => {
                const startRaw = cell.getAttribute('data-running-start');
                const start = parseSessionDate(startRaw);
                if (start) {
                    cell.textContent = formatDurationFromMs(Date.now() - start.getTime());
                }
            });
        }

        async function toggleClockOnOut() {
            if (isClockedOn) {
                await attendanceTimeOut();
            } else {
                await attendanceTimeIn();
            }
        }

        async function attendanceTimeIn() {
            const btn = document.getElementById('btnClockToggle');
            if (btn) btn.disabled = true;
            try {
                const res = await fetch('api/bpso_attendance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'time_in' })
                });
                const result = await res.json();
                if (!result.success) {
                    alert(result.message || 'Failed to clock on.');
                    if (btn) btn.disabled = false;
                    return;
                }
                await loadProfile();
            } catch (e) {
                alert('Failed to clock on.');
                if (btn) btn.disabled = false;
            }
        }

        async function attendanceTimeOut() {
            const btn = document.getElementById('btnClockToggle');
            if (btn) btn.disabled = true;
            try {
                const res = await fetch('api/bpso_attendance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'time_out' })
                });
                const result = await res.json();
                if (!result.success) {
                    alert(result.message || 'Failed to clock out.');
                    if (btn) btn.disabled = false;
                    return;
                }
                await loadProfile();
            } catch (e) {
                alert('Failed to clock out.');
                if (btn) btn.disabled = false;
            }
        }

        function initTimesheetDateRange() {
            const fromInput = document.getElementById('timesheetDateFrom');
            const toInput = document.getElementById('timesheetDateTo');
            if (!fromInput || !toInput) return;

            const today = manilaDateString();
            const fromDate = new Date();
            fromDate.setDate(fromDate.getDate() - 9);
            const from = manilaDateString(fromDate);

            fromInput.value = from;
            toInput.value = today;
            fromInput.max = today;
            toInput.max = today;
        }

        function getTimesheetRangeOrError() {
            const fromInput = document.getElementById('timesheetDateFrom');
            const toInput = document.getElementById('timesheetDateTo');
            const dateFrom = fromInput ? fromInput.value.trim() : '';
            const dateTo = toInput ? toInput.value.trim() : '';

            if (!dateFrom || !dateTo) {
                return { error: 'Please select both Date From and Date To.' };
            }

            const from = new Date(dateFrom + 'T00:00:00');
            const to = new Date(dateTo + 'T00:00:00');
            if (Number.isNaN(from.getTime()) || Number.isNaN(to.getTime())) {
                return { error: 'Invalid date range.' };
            }
            if (from > to) {
                return { error: 'Date From cannot be later than Date To.' };
            }

            const daySpan = Math.floor((to - from) / 86400000) + 1;
            if (daySpan > 10) {
                return { error: 'Date range is limited to a maximum of 10 days.' };
            }

            return { dateFrom, dateTo };
        }

        async function loadTimesheetSummary() {
            const tbody = document.getElementById('timesheetTableBody');
            if (!tbody) return;

            const range = getTimesheetRangeOrError();
            if (range.error) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state">' + escapeHtml(range.error) + '</td></tr>';
                return;
            }

            const url = 'api/bpso_attendance.php?view=history'
                + '&date_from=' + encodeURIComponent(range.dateFrom)
                + '&date_to=' + encodeURIComponent(range.dateTo);

            tbody.innerHTML = '<tr><td colspan="7" class="empty-state">Loading timesheet...</td></tr>';

            try {
                const res = await fetch(url);
                const result = await res.json();
                if (!result.success) {
                    tbody.innerHTML = '<tr><td colspan="7" class="empty-state">' + escapeHtml(result.message || 'Failed to load timesheet.') + '</td></tr>';
                    return;
                }

                const rows = result.data || [];
                if (rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No timesheet records found for this date range.</td></tr>';
                    return;
                }

                tbody.innerHTML = rows.map(row => {
                    const overtime = computeLiveOvertimeLabel(row);
                    return `
                    <tr>
                        <td>${escapeHtml(row.attendance_date || '—')}</td>
                        <td>${escapeHtml(formatShiftWithHours(row.duty))}</td>
                        <td>${escapeHtml(formatManilaTimeOnly(row.time_in))}</td>
                        <td>${escapeHtml(row.time_out ? formatManilaTimeOnly(row.time_out) : '')}</td>
                        <td>${escapeHtml(row.patrol_duration_label || '—')}</td>
                        <td class="overtime-cell" data-time-in="${escapeHtml(row.time_in || '')}" data-time-out="${escapeHtml(row.time_out || '')}" data-duty="${escapeHtml(row.duty || row.duty_shift || '')}">${escapeHtml(overtime)}</td>
                        <td>${escapeHtml(row.status_label || '—')}</td>
                    </tr>`;
                }).join('');
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state">Failed to load timesheet.</td></tr>';
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
            const display = manilaNowDisplay();
            const dateEl = document.getElementById('currentDate');
            const timeEl = document.getElementById('currentTime');
            const liveClock = document.getElementById('attendanceLiveClock');
            const liveDate = document.getElementById('attendanceLiveDate');

            if (dateEl) {
                dateEl.textContent = display.dateShort;
            }
            if (timeEl) {
                timeEl.textContent = display.time;
            }
            if (liveClock) {
                liveClock.textContent = display.time;
            }
            if (liveDate) {
                liveDate.textContent = display.dateLong + ' · Manila Time';
            }

            updateShiftRunningLabel();

            document.querySelectorAll('#timesheetTableBody .overtime-cell').forEach(cell => {
                const timeIn = cell.getAttribute('data-time-in');
                const timeOut = cell.getAttribute('data-time-out');
                const duty = cell.getAttribute('data-duty');
                cell.textContent = computeLiveOvertimeLabel({
                    time_in: timeIn,
                    time_out: timeOut || null,
                    duty: duty || ''
                });
            });
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

            try {
                const res = await fetch('api/patrol_schedules.php');
                const result = await res.json();

                if (!result.success) {
                    tbody.innerHTML = '<tr><td colspan="7" class="empty-state">Failed to load schedule.</td></tr>';
                    portalSchedules = [];
                    updateNavBadges();
                    return;
                }

                scheduleData = {};
                portalSchedules = result.data || [];

                if (portalSchedules.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="empty-state"><i class="fas fa-calendar-times"></i>No patrol assignments yet.</td></tr>';
                    populateReportScheduleOptions();
                    updateReportFormForSelectedSchedule(false);
                    updateNavBadges();
                    return;
                }

                renderScheduleTable();
                updateNavBadges();
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state">Error loading schedule.</td></tr>';
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

        function getFilteredReports() {
            const q = reportsSearchQuery.trim().toLowerCase();
            if (!q) return portalReports.slice();
            return portalReports.filter(r => {
                const dateText = String(r.date || '').toLowerCase();
                const timeText = String(r.time || '').toLowerCase();
                const routeText = String(r.route || '').toLowerCase();
                return dateText.includes(q) || timeText.includes(q) || routeText.includes(q);
            });
        }

        function searchReports() {
            const input = document.getElementById('reportsSearch');
            reportsSearchQuery = input ? input.value : '';
            reportsPage = 1;
            renderReportsTable();
        }

        function changeReportsPage(delta) {
            const filtered = getFilteredReports();
            const totalPages = Math.max(1, Math.ceil(filtered.length / REPORTS_PER_PAGE));
            reportsPage = Math.min(totalPages, Math.max(1, reportsPage + delta));
            renderReportsTable();
        }

        function renderDocumentationCell(row) {
            const hasPhoto = !!(row.documentation_photo);
            if (hasPhoto) {
                return `<div class="doc-cell">
                    <img class="doc-thumb" src="${escapeHtml(row.documentation_photo)}" alt="Documentation photo" onclick="viewDocumentationPhoto(${row.id})" title="View photo">
                </div>`;
            }
            return `<div class="doc-cell"><span class="doc-empty">No photo</span></div>`;
        }

        function viewDocumentationPhoto(reportId) {
            const report = portalReports.find(r => Number(r.id) === Number(reportId));
            if (!report || !report.documentation_photo) return;
            if (window.AlertaraPhotoLightbox) {
                AlertaraPhotoLightbox.open(report.documentation_photo, 'Documentation photo');
                return;
            }
            window.open(report.documentation_photo, '_blank');
        }

        function setReportDocumentationPreview(photoData) {
            const hidden = document.getElementById('reportDocumentationData');
            if (!hidden) return;
            hidden.value = photoData || '';
        }

        function clearReportDocumentation() {
            const fileInput = document.getElementById('reportDocumentation');
            if (fileInput) {
                fileInput.value = '';
                fileInput.required = false;
            }
            setReportDocumentationPreview('');
        }

        async function readFileAsDataUrl(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(String(reader.result || ''));
                reader.onerror = () => reject(new Error('Failed to read image.'));
                reader.readAsDataURL(file);
            });
        }

        function renderReportsTable() {
            const tbody = document.getElementById('reportsTableBody');
            const pageInfo = document.getElementById('reportsPageInfo');
            const prevBtn = document.getElementById('reportsPrevBtn');
            const nextBtn = document.getElementById('reportsNextBtn');
            const filtered = getFilteredReports();
            const totalPages = Math.max(1, Math.ceil(filtered.length / REPORTS_PER_PAGE));
            reportsPage = Math.min(Math.max(1, reportsPage), totalPages);
            const start = (reportsPage - 1) * REPORTS_PER_PAGE;
            const pageRows = filtered.slice(start, start + REPORTS_PER_PAGE);

            if (pageInfo) {
                pageInfo.textContent = filtered.length === 0
                    ? 'No reports to display'
                    : `Page ${reportsPage} of ${totalPages} · ${filtered.length} report${filtered.length === 1 ? '' : 's'}`;
            }
            if (prevBtn) prevBtn.disabled = reportsPage <= 1 || filtered.length === 0;
            if (nextBtn) nextBtn.disabled = reportsPage >= totalPages || filtered.length === 0;

            if (filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="empty-state"><i class="fas fa-file-alt"></i>' +
                    (portalReports.length === 0 ? 'No reports submitted yet.' : 'No reports match your search.') +
                    '</td></tr>';
                return;
            }

            tbody.innerHTML = pageRows.map(row => {
                const canEdit = !!row.can_edit;
                const editBtn = canEdit
                    ? `<button type="button" class="btn-edit" onclick="openEditReport(${row.id})">Edit</button>`
                    : `<button type="button" class="btn-edit" disabled title="Editing is unavailable after you clock out">Edit</button>`;
                return `<tr>
                    <td>${escapeHtml(row.date)} ${escapeHtml(row.time || '')}</td>
                    <td>${escapeHtml(row.route)}</td>
                    <td>${escapeHtml(row.incidents || 'None')}</td>
                    <td>${renderDocumentationCell(row)}</td>
                    <td><div class="report-actions">
                        <button type="button" class="btn-view" onclick="openReportModal(${row.id})">View</button>
                        ${editBtn}
                    </div></td>
                </tr>`;
            }).join('');
        }

        function openEditReport(id) {
            const report = portalReports.find(r => Number(r.id) === Number(id));
            if (!report) return;
            if (!report.can_edit) {
                showToast('Editing is unavailable after you clock out.', true);
                return;
            }

            const scheduleId = Number(report.schedule_id) || 0;
            const reportBtn = document.querySelector('.nav-submodule[data-tab="report"]');
            switchSection(reportBtn, 'report', 'Submit Report');
            populateReportScheduleOptions(scheduleId || null);
            document.getElementById('editingReportId').value = String(report.id);
            document.getElementById('reportSchedule').value = scheduleId ? String(scheduleId) : '';
            updateAssignmentInfo(scheduleId);
            document.getElementById('reportRoute').value = report.route || '';
            document.getElementById('reportDate').value = report.date || '';
            document.getElementById('reportTime').value = String(report.time || '').slice(0, 5);
            document.getElementById('reportLocation').value = report.location || '';
            document.getElementById('reportIncidents').value = report.incidents || 'None';
            document.getElementById('reportDetails').value = report.details || '';
            const fileInput = document.getElementById('reportDocumentation');
            if (fileInput) {
                fileInput.value = '';
                fileInput.required = false;
            }
            setReportDocumentationPreview(report.documentation_photo || '');
            setReportFormVisible(true, false);
            document.getElementById('reportAlert').innerHTML = '';
            document.getElementById('reportDetails')?.focus();
        }

        function openReportModal(id) {
            const report = portalReports.find(r => Number(r.id) === Number(id));
            if (!report) return;
            const photoHtml = report.documentation_photo
                ? `<div class="complaint-detail"><strong>Documentation:</strong><br><img class="doc-modal-photo" src="${escapeHtml(report.documentation_photo)}" alt="Documentation photo" onclick="AlertaraPhotoLightbox.open(this.src, 'Documentation photo')" title="Click to view full size"></div>`
                : `<div class="complaint-detail"><strong>Documentation:</strong> No photo uploaded</div>`;
            document.getElementById('reportDetailContent').innerHTML = `
                <div class="complaint-detail"><strong>Date:</strong> ${escapeHtml(report.date)} ${escapeHtml(report.time || '')}</div>
                <div class="complaint-detail"><strong>Route:</strong> ${escapeHtml(report.route)}</div>
                <div class="complaint-detail"><strong>Location:</strong> ${escapeHtml(report.location || '—')}</div>
                <div class="complaint-detail"><strong>Incidents:</strong> ${escapeHtml(report.incidents || 'None')}</div>
                ${photoHtml}
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
                reportsPage = 1;
                renderReportsTable();
                populateReportScheduleOptions();
                updateReportFormForSelectedSchedule(true);
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Error loading reports.</td></tr>';
                portalReports = [];
            }
        }

        function fillReportFromSchedule(scheduleId, clearDetails = false) {
            const row = scheduleData[scheduleId] || portalSchedules.find(s => Number(s.id) === Number(scheduleId));
            if (!row) return;
            document.getElementById('reportSchedule').value = String(scheduleId);
            updateAssignmentInfo(scheduleId);
            document.getElementById('reportRoute').value = row.route || row.patrol_zone || '';
            document.getElementById('reportDate').value = row.schedule_date || '';
            document.getElementById('reportLocation').value = clearDetails ? (row.location || row.patrol_zone || '') : (document.getElementById('reportLocation').value || row.location || '');
            if (clearDetails) {
                document.getElementById('editingReportId').value = '';
                document.getElementById('reportIncidents').value = 'None';
                document.getElementById('reportDetails').value = '';
                clearReportDocumentation();
            }
            const now = new Date();
            document.getElementById('reportTime').value = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
        }

        function openReportForSchedule(scheduleId) {
            const reportBtn = document.querySelector('.nav-submodule[data-tab="report"]');
            switchSection(reportBtn, 'report', 'Submit Report');
            document.getElementById('editingReportId').value = '';
            populateReportScheduleOptions(scheduleId);
            fillReportFromSchedule(scheduleId, true);
            updateReportFormForSelectedSchedule(true);
        }

        document.getElementById('reportForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const alertEl = document.getElementById('reportAlert');
            const btn = document.getElementById('reportSubmitBtn');
            alertEl.innerHTML = '';
            btn.disabled = true;

            const scheduleId = parseInt(document.getElementById('reportSchedule').value, 10) || 0;
            const editingId = parseInt(document.getElementById('editingReportId').value, 10) || 0;
            if (!scheduleId && !editingId) {
                showToast('No patrol assignment selected.', true);
                btn.disabled = false;
                return;
            }

            const documentationPhoto = (document.getElementById('reportDocumentationData')?.value || '').trim();

            const payload = {
                action: editingId > 0 ? 'update_report' : 'submit_report',
                id: editingId,
                schedule_id: scheduleId,
                route: document.getElementById('reportRoute').value.trim(),
                date: document.getElementById('reportDate').value,
                time: document.getElementById('reportTime').value,
                location: document.getElementById('reportLocation').value.trim(),
                incidents: document.getElementById('reportIncidents').value.trim() || 'None',
                details: document.getElementById('reportDetails').value.trim(),
                documentation_photo: documentationPhoto,
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
                    if (editingId > 0) {
                        showToast('Report updated successfully');
                    } else {
                        showToast('Submitted a report successfully');
                    }
                    document.getElementById('editingReportId').value = '';
                    this.reset();
                    document.getElementById('reportIncidents').value = 'None';
                    clearReportDocumentation();
                    document.getElementById('reportSchedule').value = scheduleId ? String(scheduleId) : '';
                    await refreshAllData();
                    populateReportScheduleOptions(scheduleId);
                    if (scheduleId) {
                        document.getElementById('reportSchedule').value = String(scheduleId);
                        fillReportFromSchedule(scheduleId, true);
                    }
                    updateReportFormForSelectedSchedule(true);
                } else {
                    showToast(result.message || 'Submission failed.', true);
                    alertEl.innerHTML = '<div class="alert alert-error">' + escapeHtml(result.message || 'Submission failed.') + '</div>';
                }
            } catch (err) {
                showToast('Network error. Please try again.', true);
                alertEl.innerHTML = '<div class="alert alert-error">Network error. Please try again.</div>';
            } finally {
                btn.disabled = false;
            }
        });

        const reportDocumentationInput = document.getElementById('reportDocumentation');
        if (reportDocumentationInput) {
            reportDocumentationInput.addEventListener('change', async function() {
                const file = this.files && this.files[0] ? this.files[0] : null;
                if (!file) {
                    clearReportDocumentation();
                    return;
                }
                if (!file.type.startsWith('image/')) {
                    showToast('Please choose an image file.', true);
                    this.value = '';
                    return;
                }
                if (file.size > 2 * 1024 * 1024) {
                    showToast('Photo must be under 2MB.', true);
                    this.value = '';
                    return;
                }
                try {
                    const photoData = await readFileAsDataUrl(file);
                    setReportDocumentationPreview(photoData);
                } catch (err) {
                    showToast('Failed to read photo. Please try again.', true);
                    clearReportDocumentation();
                }
            });
        }

        const reportsSearchInput = document.getElementById('reportsSearch');
        if (reportsSearchInput) {
            reportsSearchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchReports();
                }
            });
        }

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
                tbody.innerHTML = '<tr><td colspan="5" class="empty-state"><i class="fas fa-inbox"></i>No complaints in this view.</td></tr>';
                return;
            }

            tbody.innerHTML = rows.map(row => {
                complaintData[row.id] = row;
                const canResolve = row.status !== 'Resolved';
                return `<tr>
                    <td>${escapeHtml(row.complaint_id)}</td>
                    <td>${escapeHtml(row.complainant_name)}</td>
                    <td>${escapeHtml(row.complaint_type)}</td>
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
                    tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Failed to load complaints.</td></tr>';
                    portalComplaints = [];
                    updateNavBadges();
                    return;
                }

                complaintData = {};
                portalComplaints = result.data || [];
                if (portalComplaints.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="empty-state"><i class="fas fa-inbox"></i>No complaints assigned to you yet.</td></tr>';
                    updateNavBadges();
                    setInitialSectionIfNeeded();
                    return;
                }

                renderComplaintsTable();
                updateNavBadges();
                setInitialSectionIfNeeded();
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Error loading complaints.</td></tr>';
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
                <div class="complaint-detail"><strong>Type:</strong> ${escapeHtml(complaint.complaint_type)}</div>
                <div class="complaint-detail"><strong>Description:</strong><br>${escapeHtml(complaint.description)}</div>
                <div class="complaint-detail"><strong>Status:</strong> <span class="status-badge ${complaintStatusClass(complaint.status)}">${escapeHtml(complaint.status)}</span></div>
                ${buildComplaintTimeline(complaint)}
            `;
            const isResolved = complaint.status === 'Resolved';
            document.getElementById('resolutionReport').readOnly = isResolved;
            document.getElementById('resolveComplaintBtn').style.display = isResolved ? 'none' : '';
            document.getElementById('complaintResolutionModal').classList.add('active');
        }

        function closeComplaintModal() {
            document.getElementById('complaintResolutionModal').classList.remove('active');
            document.getElementById('resolutionReport').readOnly = false;
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
                photoHtml = `<div class="complaint-detail"><strong>Photo:</strong><br><img src="${report.photo_data}" alt="Incident photo" class="incident-photo" onclick="AlertaraPhotoLightbox.open(this.src, 'Incident photo')" title="Click to view full size"></div>`;
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
            document.getElementById('resolveNwIncidentBtn').style.display = isResolved ? 'none' : '';
            document.getElementById('nwIncidentResolutionModal').classList.add('active');
        }

        function closeNwIncidentModal() {
            document.getElementById('nwIncidentResolutionModal').classList.remove('active');
            document.getElementById('nwResolutionReport').readOnly = false;
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

        function tipStatusClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'resolved') return 'status-resolved';
            if (s === 'assigned') return 'status-processing';
            return 'status-pending';
        }

        function setTipFilter(filter, button) {
            tipFilter = filter;
            document.querySelectorAll('#panel-tips .filter-tab').forEach(tab => tab.classList.remove('active'));
            button.classList.add('active');
            renderTipsTable();
        }

        function renderTipsTable() {
            const tbody = document.getElementById('tipsTableBody');
            tipData = {};
            let rows = portalTips;
            if (tipFilter === 'assigned') rows = rows.filter(r => r.status === 'Assigned');
            if (tipFilter === 'resolved') rows = rows.filter(r => r.status === 'Resolved');

            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="empty-state"><i class="fas fa-inbox"></i>No tips in this filter.</td></tr>';
                return;
            }

            tbody.innerHTML = rows.map(row => {
                tipData[row.id] = row;
                const canResolve = row.status === 'Assigned';
                return `<tr>
                    <td>${escapeHtml(row.tip_id)}</td>
                    <td>${escapeHtml(row.location)}</td>
                    <td>${escapeHtml(row.outcome || 'No Outcome Yet')}</td>
                    <td><span class="status-badge ${tipStatusClass(row.status)}">${escapeHtml(row.status)}</span></td>
                    <td>${canResolve
                        ? `<button type="button" class="btn-view" onclick="openTipModal(${row.id})">Report / Resolve</button>`
                        : `<button type="button" class="btn-view" onclick="openTipModal(${row.id})">View</button>`}</td>
                </tr>`;
            }).join('');
        }

        async function loadTips() {
            const tbody = document.getElementById('tipsTableBody');
            try {
                const res = await fetch('api/bpso_tips.php');
                const result = await res.json();
                if (!result.success) {
                    tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Failed to load tips.</td></tr>';
                    portalTips = [];
                    updateNavBadges();
                    return;
                }
                portalTips = result.data || [];
                if (!portalTips.length) {
                    tbody.innerHTML = '<tr><td colspan="5" class="empty-state"><i class="fas fa-inbox"></i>No tips assigned to you yet.</td></tr>';
                    updateNavBadges();
                    return;
                }
                renderTipsTable();
                updateNavBadges();
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Error loading tips.</td></tr>';
                portalTips = [];
                updateNavBadges();
            }
        }

        function openTipModal(id) {
            const tip = tipData[id];
            if (!tip) return;
            document.getElementById('resolutionTipId').value = id;
            document.getElementById('tipResolutionReport').value = tip.resolution_report || '';
            const outcomeSelect = document.getElementById('tipOutcomeSelect');
            const currentOutcome = tip.outcome && tip.outcome !== 'No Outcome Yet' ? tip.outcome : 'Under Investigation';
            outcomeSelect.value = currentOutcome;
            document.getElementById('tipDetailContent').innerHTML = `
                <div class="complaint-detail"><strong>Tip ID:</strong> ${escapeHtml(tip.tip_id)}</div>
                <div class="complaint-detail"><strong>Location:</strong> ${escapeHtml(tip.location)}</div>
                <div class="complaint-detail"><strong>Description:</strong><br>${escapeHtml(tip.description)}</div>
                <div class="complaint-detail"><strong>Status:</strong> <span class="status-badge ${tipStatusClass(tip.status)}">${escapeHtml(tip.status)}</span></div>
                ${tip.backup_requested_at ? '<div class="complaint-detail"><strong>Police backup:</strong> Requested (assistance only — you still submit the final report)</div>' : ''}
            `;
            const isResolved = tip.status === 'Resolved';
            document.getElementById('tipResolutionReport').readOnly = isResolved;
            outcomeSelect.disabled = isResolved;
            document.getElementById('saveTipProgressBtn').style.display = isResolved ? 'none' : '';
            document.getElementById('resolveTipBtn').style.display = isResolved ? 'none' : '';
            document.getElementById('tipResolutionModal').classList.add('active');
        }

        function closeTipModal() {
            document.getElementById('tipResolutionModal').classList.remove('active');
            document.getElementById('tipResolutionReport').readOnly = false;
            document.getElementById('tipOutcomeSelect').disabled = false;
            document.getElementById('saveTipProgressBtn').style.display = '';
            document.getElementById('resolveTipBtn').style.display = '';
        }

        async function submitTipResolution(status) {
            const id = parseInt(document.getElementById('resolutionTipId').value, 10);
            const resolutionReport = document.getElementById('tipResolutionReport').value.trim();
            const outcome = document.getElementById('tipOutcomeSelect').value;
            const alertEl = document.getElementById('tipsAlert');

            if (!id || !resolutionReport) {
                alert('Please enter your tip response report.');
                return;
            }
            if (status === 'Resolved' && (!outcome || outcome === 'No Outcome Yet' || outcome === 'Under Investigation')) {
                alert('Please select a final outcome before marking the tip as resolved.');
                return;
            }

            try {
                const res = await fetch('api/bpso_tips.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'submit_resolution',
                        id,
                        resolution_report: resolutionReport,
                        outcome,
                        status
                    })
                });
                const result = await res.json();
                if (result.success) {
                    alertEl.innerHTML = `<div class="alert alert-success">${escapeHtml(result.message || 'Report saved.')}</div>`;
                    closeTipModal();
                    await loadTips();
                    await loadProfile();
                } else {
                    alert(result.message || 'Failed to submit tip report.');
                }
            } catch (e) {
                alert('Network error. Please try again.');
            }
        }

        document.getElementById('tipResolutionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitTipResolution('Resolved');
        });

        window.onclick = function(event) {
            const complaintModal = document.getElementById('complaintResolutionModal');
            const nwIncidentModal = document.getElementById('nwIncidentResolutionModal');
            const tipModal = document.getElementById('tipResolutionModal');
            const reportModal = document.getElementById('reportDetailModal');
            if (event.target === complaintModal) closeComplaintModal();
            if (event.target === nwIncidentModal) closeNwIncidentModal();
            if (event.target === tipModal) closeTipModal();
            if (event.target === reportModal) closeReportModal();
        };

        async function refreshAllData() {
            await Promise.all([loadProfile(), loadComplaints(), loadNwIncidents(), loadTips()]);
            await loadReports();
            await loadSchedules();
        }

        function showAccountAlert(elementId, message, isError) {
            const el = document.getElementById(elementId);
            if (!el) return;
            el.className = 'alert ' + (isError ? 'alert-error' : 'alert-success');
            el.textContent = message;
            el.style.display = 'block';
        }

        function fillAccountSettingsForm(data) {
            document.getElementById('accountPersonnelName').value = data.personnel_name || '';
            document.getElementById('accountPersonnelCode').value = data.bpso_personnel_id || '';
            document.getElementById('accountEmail').value = data.email || '';
        }

        async function loadAccountSettings() {
            try {
                const response = await fetch('api/bpso_profile.php');
                const result = await response.json();
                if (result.success && result.data) {
                    fillAccountSettingsForm(result.data);
                } else {
                    showAccountAlert('accountEmailAlert', result.message || 'Failed to load account settings.', true);
                }
            } catch (err) {
                showAccountAlert('accountEmailAlert', 'Network error while loading account settings.', true);
            }
        }

        document.getElementById('accountEmailForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            try {
                const response = await fetch('api/bpso_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_email',
                        email: document.getElementById('accountEmail').value.trim()
                    })
                });
                const result = await response.json();
                if (result.success) {
                    if (result.data) fillAccountSettingsForm(result.data);
                    showAccountAlert('accountEmailAlert', result.message || 'Email updated.', false);
                } else {
                    showAccountAlert('accountEmailAlert', result.message || 'Failed to update email.', true);
                }
            } catch (err) {
                showAccountAlert('accountEmailAlert', 'Network error. Please try again.', true);
            }
        });

        document.getElementById('accountPasswordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            try {
                const response = await fetch('api/bpso_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'change_password',
                        current_password: document.getElementById('accountCurrentPassword').value,
                        new_password: document.getElementById('accountNewPassword').value,
                        confirm_password: document.getElementById('accountConfirmPassword').value
                    })
                });
                const result = await response.json();
                if (result.success) {
                    document.getElementById('accountPasswordForm').reset();
                    showAccountAlert('accountPasswordAlert', result.message || 'Password updated.', false);
                } else {
                    showAccountAlert('accountPasswordAlert', result.message || 'Failed to update password.', true);
                }
            } catch (err) {
                showAccountAlert('accountPasswordAlert', 'Network error. Please try again.', true);
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            if (localStorage.getItem('bpsoSidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }

            initTimesheetDateRange();
            updateDateTime();
            setInterval(updateDateTime, 1000);
            refreshAllData();
            refreshTimer = setInterval(refreshAllData, 60000);
            const nowParts = manilaParts();
            const hour24 = (() => {
                let h = parseInt(nowParts.hour, 10);
                if (nowParts.dayPeriod === 'PM' && h < 12) h += 12;
                if (nowParts.dayPeriod === 'AM' && h === 12) h = 0;
                return String(h).padStart(2, '0');
            })();
            document.getElementById('reportTime').value = hour24 + ':' + nowParts.minute;
        });
    </script>
    <?php require __DIR__ . '/includes/bpso_notifications_script.php'; ?>
    <script src="js/digital-bulletin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.DigitalBulletin && document.getElementById('panel-bulletin')?.classList.contains('active')) {
                DigitalBulletin.mount({ root: '#patrolBulletinRoot', audience: 'patrol' });
            }
        });
    </script>
    <script src="js/mobile-shell.js"></script>
</body>
</html>

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
    <title>Volunteer List - Alertara</title>
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
        .sidebar-nav { padding: 0.5rem 0; overflow: visible; flex: 1; display: flex; flex-direction: column; min-height: 0; }
        .sidebar.collapsed .sidebar-nav { overflow: visible; display: flex !important; flex-direction: column; }
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
        .logout-btn { padding: 0.5rem 1rem; background: var(--primary-color); color: #fff; text-decoration: none; border-radius: 6px; font-size: 0.9rem; transition: background 0.2s ease; }
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
        /* View Modal Styles */
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
                    <a href="volunteer-list.php" class="nav-submodule active" data-tooltip="Volunteer List">
                        <span class="nav-submodule-icon"><i class="fas fa-user"></i></span>
                        <span class="nav-submodule-text">Volunteer List</span>
                    </a>
                    <a href="schedule-management.php" class="nav-submodule" data-tooltip="Volunteer Request">
                        <span class="nav-submodule-icon"><i class="fas fa-calendar"></i></span>
                        <span class="nav-submodule-text">Volunteer Request</span>
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
    </aside>
    <div class="main-wrapper">
        <header class="top-header">
            <div class="top-header-content">
                <button class="content-burger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
                    <span></span>
                </button>
                <h1 class="page-title">Volunteer List</h1>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>
        <main class="content-area">
            <div class="page-content">
                <div class="toolbar">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search volunteers by name, contact, or skills..." onkeyup="filterVolunteers()">
                    </div>
                    <button class="btn-add" onclick="openAddVolunteerModal()">
                        <span>+</span>
                        <span>Add Volunteer</span>
                    </button>
                </div>
                <div class="table-container">
                    <table id="volunteersTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Category</th>
                                <th>Skills</th>
                                <th>Availability</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="volunteersTableBody">
                            <tr data-volunteer-id="1">
                                <td>Maria Rizal</td>
                                <td>0912-345-6789</td>
                                <td>First Aid & Medical</td>
                                <td>First Aid, CPR</td>
                                <td>Weekends</td>
                                <td><span class="status-badge status-resolved">Active</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewVolunteer('1')">View</button>
                                        <button class="btn-edit" onclick="editVolunteer('1')">Edit</button>
                                        <button class="btn-delete" onclick="deleteVolunteer('1')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr data-volunteer-id="2">
                                <td>Juan Aquino</td>
                                <td>0917-890-1234</td>
                                <td>Event Management</td>
                                <td>Event Management</td>
                                <td>Evenings</td>
                                <td><span class="status-badge status-resolved">Active</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewVolunteer('2')">View</button>
                                        <button class="btn-edit" onclick="editVolunteer('2')">Edit</button>
                                        <button class="btn-delete" onclick="deleteVolunteer('2')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr data-volunteer-id="3">
                                <td>Roberto Magsaysay</td>
                                <td>0918-567-8901</td>
                                <td>Community Outreach</td>
                                <td>Community Outreach, Communication</td>
                                <td>Flexible</td>
                                <td><span class="status-badge status-pending">Pending</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewVolunteer('3')">View</button>
                                        <button class="btn-edit" onclick="editVolunteer('3')">Edit</button>
                                        <button class="btn-delete" onclick="deleteVolunteer('3')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add Volunteer Modal -->
    <div id="addVolunteerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Volunteer</h2>
                <button class="close-modal" onclick="closeAddVolunteerModal()">&times;</button>
            </div>
            <form id="addVolunteerForm" onsubmit="saveVolunteer(event)">
                <input type="hidden" id="addVolunteerId" value="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="volunteerName">Full Name *</label>
                        <input type="text" id="volunteerName" name="volunteerName" required>
                    </div>
                    <div class="form-group">
                        <label for="volunteerContact">Contact Number *</label>
                        <input type="tel" id="volunteerContact" name="volunteerContact" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="volunteerEmail">Email Address *</label>
                        <input type="email" id="volunteerEmail" name="volunteerEmail" required>
                    </div>
                    <div class="form-group">
                        <label for="volunteerAddress">Home Address *</label>
                        <input type="text" id="volunteerAddress" name="volunteerAddress" required placeholder="e.g., 123 Bonifacio Street, Barangay San Agustin, Quezon City">
                    </div>
                </div>

                <div class="form-group">
                    <label for="volunteerCategory">Volunteer Category *</label>
                    <select id="volunteerCategory" name="volunteerCategory" required>
                        <option value="">Select Category</option>
                        <option value="First Aid & Medical">First Aid & Medical</option>
                        <option value="Event Management">Event Management</option>
                        <option value="Community Outreach">Community Outreach</option>
                        <option value="Education & Training">Education & Training</option>
                        <option value="Administrative Support">Administrative Support</option>
                        <option value="Food & Nutrition">Food & Nutrition</option>
                        <option value="Environmental">Environmental</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="volunteerSkills">Skills *</label>
                    <textarea id="volunteerSkills" name="volunteerSkills" required placeholder="e.g., First Aid, CPR, Event Management, Communication"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="volunteerAvailability">Availability *</label>
                        <select id="volunteerAvailability" name="volunteerAvailability" required>
                            <option value="">Select Availability</option>
                            <option value="Weekdays">Weekdays</option>
                            <option value="Weekends">Weekends</option>
                            <option value="Evenings">Evenings</option>
                            <option value="Flexible">Flexible</option>
                            <option value="On Call">On Call</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="volunteerStatus">Status *</label>
                        <select id="volunteerStatus" name="volunteerStatus" required>
                            <option value="">Select Status</option>
                            <option value="Active">Active</option>
                            <option value="Pending">Pending</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="volunteerEmergencyName">Emergency Contact Full Name *</label>
                        <input type="text" id="volunteerEmergencyName" name="volunteerEmergencyName" required>
                    </div>
                    <div class="form-group">
                        <label for="volunteerEmergencyContact">Emergency Contact Number *</label>
                        <input type="tel" id="volunteerEmergencyContact" name="volunteerEmergencyContact" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="volunteerPhoto">Volunteer Photo *</label>
                    <div class="file-upload">
                        <input type="file" id="volunteerPhoto" name="volunteerPhoto" accept="image/*" required onchange="previewImage(this, 'volunteerPhotoPreview')">
                        <label for="volunteerPhoto" class="file-upload-label">
                            <span>📷 Click to upload Volunteer Photo</span>
                        </label>
                        <div class="file-preview" id="volunteerPhotoPreview"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="volunteerPhotoId">Photo of Valid ID *</label>
                    <div class="file-upload">
                        <input type="file" id="volunteerPhotoId" name="volunteerPhotoId" accept="image/*" required onchange="previewImage(this, 'volunteerPhotoIdPreview')">
                        <label for="volunteerPhotoId" class="file-upload-label">
                            <span>🆔 Click to upload Valid ID</span>
                        </label>
                        <div class="file-preview" id="volunteerPhotoIdPreview"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="volunteerCertifications">Certifications (optional, multiple files)</label>
                    <div class="file-upload">
                        <input type="file" id="volunteerCertifications" name="volunteerCertifications" accept="image/jpeg,image/jpg,image/png,application/pdf" multiple onchange="previewImage(this, 'volunteerCertificationsPreview')">
                        <label for="volunteerCertifications" class="file-upload-label">
                            <span>📄 Click to upload certification files (JPEG, PNG, PDF)</span>
                        </label>
                        <div class="file-preview" id="volunteerCertificationsPreview"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="volunteerCertificationsDescription">Certifications Description</label>
                    <textarea id="volunteerCertificationsDescription" name="volunteerCertificationsDescription" placeholder="Describe certifications (e.g., First Aid certificate, CPR training, etc.)"></textarea>
                </div>

                <div class="form-group">
                    <label for="volunteerNotes">Additional Notes</label>
                    <textarea id="volunteerNotes" name="volunteerNotes" placeholder="Any additional information about the volunteer..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeAddVolunteerModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Save Volunteer</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Volunteer Modal -->
    <div id="editVolunteerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Volunteer</h2>
                <button class="close-modal" onclick="closeEditVolunteerModal()">&times;</button>
            </div>
            <form id="editVolunteerForm" onsubmit="updateVolunteer(event)">
                <input type="hidden" id="editVolunteerId" name="volunteerId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editVolunteerName">Full Name *</label>
                        <input type="text" id="editVolunteerName" name="volunteerName" required>
                    </div>
                    <div class="form-group">
                        <label for="editVolunteerContact">Contact Number *</label>
                        <input type="tel" id="editVolunteerContact" name="volunteerContact" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editVolunteerEmail">Email Address</label>
                        <input type="email" id="editVolunteerEmail" name="volunteerEmail">
                    </div>
                    <div class="form-group">
                        <label for="editVolunteerAddress">Address *</label>
                        <input type="text" id="editVolunteerAddress" name="volunteerAddress" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="editVolunteerCategory">Volunteer Category *</label>
                    <select id="editVolunteerCategory" name="volunteerCategory" required>
                        <option value="">Select Category</option>
                        <option value="First Aid & Medical">First Aid & Medical</option>
                        <option value="Event Management">Event Management</option>
                        <option value="Community Outreach">Community Outreach</option>
                        <option value="Education & Training">Education & Training</option>
                        <option value="Administrative Support">Administrative Support</option>
                        <option value="Food & Nutrition">Food & Nutrition</option>
                        <option value="Environmental">Environmental</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="editVolunteerSkills">Skills *</label>
                    <textarea id="editVolunteerSkills" name="volunteerSkills" required placeholder="e.g., First Aid, CPR, Event Management, Communication"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editVolunteerAvailability">Availability *</label>
                        <select id="editVolunteerAvailability" name="volunteerAvailability" required>
                            <option value="">Select Availability</option>
                            <option value="Weekdays">Weekdays</option>
                            <option value="Weekends">Weekends</option>
                            <option value="Evenings">Evenings</option>
                            <option value="Flexible">Flexible</option>
                            <option value="On Call">On Call</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editVolunteerStatus">Status *</label>
                        <select id="editVolunteerStatus" name="volunteerStatus" required>
                            <option value="">Select Status</option>
                            <option value="Active">Active</option>
                            <option value="Pending">Pending</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="editVolunteerNotes">Additional Notes</label>
                    <textarea id="editVolunteerNotes" name="volunteerNotes" placeholder="Any additional information about the volunteer..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="editVolunteerPhotoId">Photo ID</label>
                    <div class="file-upload">
                        <input type="file" id="editVolunteerPhotoId" name="volunteerPhotoId" accept="image/*" onchange="previewImage(this, 'editVolunteerPhotoIdPreview')">
                        <label for="editVolunteerPhotoId" class="file-upload-label">
                            <span>🆔 Click to upload Photo ID (Optional - leave blank to keep existing)</span>
                        </label>
                        <div class="file-preview" id="editVolunteerPhotoIdPreview"></div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeEditVolunteerModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Update Volunteer</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Volunteer Modal -->
    <div id="viewVolunteerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Volunteer Details</h2>
                <button class="close-modal" onclick="closeViewVolunteerModal()">&times;</button>
            </div>
            <div id="volunteerDetails" class="complaint-details">
                <!-- Details will be populated by JavaScript -->
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
        function filterVolunteers() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('volunteersTableBody');
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
        // Volunteer data storage
        let volunteerData = {};
        let nextVolunteerId = 4; // Starting from 4 since we have 3 sample volunteers
        // Track selected certification files so they can be removed before saving
        let selectedCertFiles = [];

        function saveVolunteerDataToStorage() {
            try {
                localStorage.setItem('volunteerData', JSON.stringify(volunteerData));
            } catch (e) {
                console.error('Failed to save volunteer data', e);
            }
        }

        function loadVolunteerDataFromStorage() {
            try {
                const raw = localStorage.getItem('volunteerData');
                return raw ? JSON.parse(raw) : null;
            } catch (e) {
                console.error('Failed to load volunteer data', e);
                return null;
            }
        }

        // Initialize volunteer data from existing table rows or storage
        function initializeVolunteerData() {
            const stored = loadVolunteerDataFromStorage();
            if (stored && Object.keys(stored).length > 0) {
                volunteerData = stored;
                const tbody = document.getElementById('volunteersTableBody');
                tbody.innerHTML = '';
                Object.keys(volunteerData).forEach(id => addTableRow(id));
                nextVolunteerId = Math.max(...Object.keys(volunteerData).map(Number)) + 1;
                return;
            }

            const rows = document.querySelectorAll('#volunteersTableBody tr[data-volunteer-id]');
            rows.forEach((row) => {
                const id = row.getAttribute('data-volunteer-id');
                const cells = row.querySelectorAll('td');
                
                volunteerData[id] = {
                    id: id,
                    name: cells[0].textContent.trim(),
                    contact: cells[1].textContent.trim(),
                    category: cells[2].textContent.trim(),
                    skills: cells[3].textContent.trim(),
                    availability: cells[4].textContent.trim(),
                    status: cells[5].querySelector('.status-badge').textContent.trim(),
                    email: id === '1'
                        ? 'maria.rizal@example.com'
                        : id === '2'
                            ? 'juan.aquino@example.com'
                            : 'roberto.magsaysay@example.com',
                    address: id === '1'
                        ? '123 Bonifacio Street, Barangay San Agustin, Quezon City'
                        : id === '2'
                            ? '456 Rizal Avenue, Barangay San Agustin, Quezon City'
                            : '789 Luna Street, Barangay San Agustin, Quezon City',
                    notes: id === '1'
                        ? 'Certified First Aid and CPR instructor. Available for weekend training sessions.'
                        : id === '2'
                            ? 'Experienced in organizing community events and outreach programs.'
                            : 'Experienced in community outreach and communication programs.',
                    photoId: null,
                    emergencyContactName: '',
                    emergencyContactNumber: ''
                };
            });

            saveVolunteerDataToStorage();
        }
        
        function renderCertificationsPreview(input, preview) {
            preview.innerHTML = '';

            if (selectedCertFiles.length > 0) {
                selectedCertFiles.forEach((file, index) => {
                    const wrapper = document.createElement('div');
                    wrapper.style.display = 'flex';
                    wrapper.style.alignItems = 'center';
                    wrapper.style.gap = '0.5rem';
                    wrapper.style.marginBottom = '0.5rem';

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

                        wrapper.appendChild(img);
                    }

                    const label = document.createElement('div');
                    label.textContent = file.name;
                    label.style.fontSize = '0.85rem';
                    wrapper.appendChild(label);

                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.textContent = 'Remove';
                    removeBtn.style.padding = '0.25rem 0.5rem';
                    removeBtn.style.fontSize = '0.75rem';
                    removeBtn.style.borderRadius = '4px';
                    removeBtn.style.border = '1px solid #ccc';
                    removeBtn.style.cursor = 'pointer';
                    removeBtn.onclick = function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        // Remove only this specific file from the tracked list
                        selectedCertFiles = selectedCertFiles.filter(f => f !== file);
                        const dtInner = new DataTransfer();
                        selectedCertFiles.forEach(f => dtInner.items.add(f));
                        input.files = dtInner.files;
                        renderCertificationsPreview(input, preview);
                    };
                    wrapper.appendChild(removeBtn);

                    preview.appendChild(wrapper);
                });
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }

        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (!preview) return;

            // Special handling for certifications (multiple files with remove option)
            if (input.id === 'volunteerCertifications') {
                // Merge newly selected files with already tracked files
                if (input.files && input.files.length > 0) {
                    const existing = new Set(selectedCertFiles.map(f => `${f.name}|${f.size}`));
                    Array.from(input.files).forEach(file => {
                        const key = `${file.name}|${file.size}`;
                        if (!existing.has(key)) {
                            selectedCertFiles.push(file);
                            existing.add(key);
                        }
                    });
                }

                // Rebuild the FileList on the input so form submission sees the same files
                const dt = new DataTransfer();
                selectedCertFiles.forEach(f => dt.items.add(f));
                input.files = dt.files;

                renderCertificationsPreview(input, preview);
                return;
            }

            // Default behaviour for single-file fields (photo, valid ID)
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
            initializeVolunteerData();
        });
        
        function openAddVolunteerModal() {
            document.getElementById('addVolunteerModal').classList.add('active');
        }
        
        function closeAddVolunteerModal() {
            document.getElementById('addVolunteerModal').classList.remove('active');
            document.getElementById('addVolunteerForm').reset();
            document.getElementById('volunteerPhotoIdPreview').style.display = 'none';
            document.getElementById('volunteerPhotoIdPreview').innerHTML = '';
        }
        
        function saveVolunteer(event) {
            event.preventDefault();
            
            const name = document.getElementById('volunteerName').value.trim();
            const contact = document.getElementById('volunteerContact').value.trim();
            const email = document.getElementById('volunteerEmail').value.trim();
            const address = document.getElementById('volunteerAddress').value.trim();
            const category = document.getElementById('volunteerCategory').value;
            const skills = document.getElementById('volunteerSkills').value.trim();
            const availability = document.getElementById('volunteerAvailability').value;
            const status = document.getElementById('volunteerStatus').value;
            const notes = document.getElementById('volunteerNotes').value.trim();
            const photoIdFile = document.getElementById('volunteerPhotoId').files[0];
            const certFiles = selectedCertFiles;
            const certDescription = document.getElementById('volunteerCertificationsDescription').value.trim();
            const emergencyName = document.getElementById('volunteerEmergencyName').value.trim();
            const emergencyContact = document.getElementById('volunteerEmergencyContact').value.trim();
            const volunteerId = nextVolunteerId.toString();
            
            // Handle photo ID upload
            let photoIdSrc = null;
            if (photoIdFile) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    photoIdSrc = e.target.result;
                    completeSave();
                };
                reader.readAsDataURL(photoIdFile);
            } else {
                alert('Photo ID is required!');
                return;
            }
            
            function completeSave() {
                // Store volunteer data
                volunteerData[volunteerId] = {
                    id: volunteerId,
                    name: name,
                    contact: contact,
                    email: email,
                    address: address,
                    category: category,
                    skills: skills,
                    availability: availability,
                    status: status,
                    notes: notes,
                    photoId: photoIdSrc,
                    certifications: certFiles.map(f => f.name),
                    certificationsDescription: certDescription,
                    emergencyContactName: emergencyName,
                    emergencyContactNumber: emergencyContact
                };
                
                // Add new row to table
                addTableRow(volunteerId);
                nextVolunteerId++;
                saveVolunteerDataToStorage();
                alert('Volunteer added successfully!');
                closeAddVolunteerModal();
            }
        }
        
        function addTableRow(id) {
            const volunteer = volunteerData[id];
            const tbody = document.getElementById('volunteersTableBody');
            
            const row = document.createElement('tr');
            row.setAttribute('data-volunteer-id', id);
            
            // Determine status badge class
            let statusClass = 'status-resolved';
            if (volunteer.status === 'Pending') {
                statusClass = 'status-pending';
            } else if (volunteer.status === 'Inactive') {
                statusClass = 'status-pending';
            }
            
            row.innerHTML = `
                <td>${volunteer.name}</td>
                <td>${volunteer.contact}</td>
                <td>${volunteer.category || 'Not specified'}</td>
                <td>${volunteer.skills}</td>
                <td>${volunteer.availability}</td>
                <td><span class="status-badge ${statusClass}">${volunteer.status}</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-view" onclick="viewVolunteer('${id}')">View</button>
                        <button class="btn-edit" onclick="editVolunteer('${id}')">Edit</button>
                        <button class="btn-delete" onclick="deleteVolunteer('${id}')">Delete</button>
                    </div>
                </td>
            `;
            
            tbody.appendChild(row);
        }
        
        function loadVolunteerActivities() {
            try {
                const raw = localStorage.getItem('volunteerActivities');
                return raw ? JSON.parse(raw) : [];
            } catch (e) {
                console.error('Failed to load volunteer activities', e);
                return [];
            }
        }

        function viewVolunteer(id) {
            const volunteer = volunteerData[id];
            if (!volunteer) {
                alert('Volunteer not found!');
                return;
            }
            
            const modal = document.getElementById('viewVolunteerModal');
            const detailsContainer = document.getElementById('volunteerDetails');
            
            // Determine status badge class
            let statusClass = 'status-resolved';
            if (volunteer.status === 'Pending') {
                statusClass = 'status-pending';
            } else if (volunteer.status === 'Inactive') {
                statusClass = 'status-pending';
            }
            
            const activities = loadVolunteerActivities().filter(a => a.volunteerId === id);

            let detailsHTML = `
                <div class="detail-row inline">
                    <span class="detail-label">Name:</span>
                    <span class="detail-value"><strong>${volunteer.name}</strong></span>
                </div>
                
                <div class="detail-row inline">
                    <span class="detail-label">Status:</span>
                    <span class="status-badge ${statusClass}">${volunteer.status}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Contact Number:</span>
                    <span class="detail-value">${volunteer.contact}</span>
                </div>
                
                ${volunteer.email ? `
                <div class="detail-row">
                    <span class="detail-label">Email Address:</span>
                    <span class="detail-value">${volunteer.email}</span>
                </div>
                ` : ''}
                
                ${volunteer.address ? `
                <div class="detail-row">
                    <span class="detail-label">Address:</span>
                    <span class="detail-value">${volunteer.address}</span>
                </div>
                ` : ''}
                
                <div class="detail-row">
                    <span class="detail-label">Category:</span>
                    <span class="detail-value">${volunteer.category || 'Not specified'}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Skills:</span>
                    <span class="detail-value">${volunteer.skills}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Availability:</span>
                    <span class="detail-value">${volunteer.availability}</span>
                </div>

                ${volunteer.emergencyContactName || volunteer.emergencyContactNumber ? `
                <div class="detail-row">
                    <span class="detail-label">Emergency Contact:</span>
                    <span class="detail-value">
                        ${volunteer.emergencyContactName || ''}${volunteer.emergencyContactName && volunteer.emergencyContactNumber ? ' - ' : ''}${volunteer.emergencyContactNumber || ''}
                    </span>
                </div>
                ` : ''}
                
                ${volunteer.notes ? `
                <div class="detail-row">
                    <span class="detail-label">Notes:</span>
                    <span class="detail-value">${volunteer.notes}</span>
                </div>
                ` : ''}
                
                ${volunteer.photoId ? `
                <div class="detail-row">
                    <span class="detail-label">Photo ID:</span>
                    <div class="detail-value">
                        <img src="${volunteer.photoId}" alt="Photo ID" class="id-photo-preview" onclick="viewPhoto(this.src)">
                    </div>
                </div>
                ` : `
                <div class="detail-row">
                    <span class="detail-label">Photo ID:</span>
                    <span class="detail-value" style="color: var(--text-secondary); font-style: italic;">No photo ID uploaded</span>
                </div>
                `}
                
                ${volunteer.certifications && volunteer.certifications.length ? `
                <div class="detail-row">
                    <span class="detail-label">Certifications:</span>
                    <div class="detail-value">
                        <ul style="padding-left:1.1rem; margin:0;">
                            ${volunteer.certifications.map(name => `<li>${name}</li>`).join('')}
                        </ul>
                        ${volunteer.certificationsDescription ? `<div style="margin-top:0.5rem;">${volunteer.certificationsDescription}</div>` : ''}
                    </div>
                </div>
                ` : ''}
            `;

            if (activities.length > 0) {
                detailsHTML += `
                    <div class="detail-row">
                        <span class="detail-label">Assigned Tasks:</span>
                        <div class="detail-value">
                            <ul style="padding-left:1.1rem; margin:0;">
                                ${activities.map(a => `
                                    <li>
                                        <strong>${a.eventTitle || ''}</strong> (${a.eventType || ''})
                                        – ${a.eventDate || ''} ${a.callTime || ''}-${a.endTime || ''} @ ${a.venue || ''}
                                        <br>Role: ${a.role || ''} • Check-in Status: ${a.checkInStatus || 'Pending'}
                                    </li>
                                `).join('')}
                            </ul>
                        </div>
                    </div>
                `;

                const attended = activities.filter(a => (a.checkInStatus || '').toLowerCase() === 'checked-in');
                if (attended.length > 0) {
                    detailsHTML += `
                        <div class="detail-row">
                            <span class="detail-label">Events / Seminars Attended:</span>
                            <div class="detail-value">
                                <ul style="padding-left:1.1rem; margin:0;">
                                    ${attended.map(a => `
                                        <li>
                                            ${a.eventDate || ''} – <strong>${a.eventTitle || ''}</strong> (${a.eventType || ''})
                                            @ ${a.venue || ''}
                                        </li>
                                    `).join('')}
                                </ul>
                            </div>
                        </div>
                    `;
                }
            }
            
            detailsContainer.innerHTML = detailsHTML;
            modal.classList.add('active');
        }
        
        function closeViewVolunteerModal() {
            document.getElementById('viewVolunteerModal').classList.remove('active');
        }
        
        function editVolunteer(id) {
            const volunteer = volunteerData[id];
            if (!volunteer) {
                alert('Volunteer not found!');
                return;
            }
            
            // Populate form fields
            document.getElementById('editVolunteerId').value = volunteer.id;
            document.getElementById('editVolunteerName').value = volunteer.name;
            document.getElementById('editVolunteerContact').value = volunteer.contact;
            document.getElementById('editVolunteerEmail').value = volunteer.email || '';
            document.getElementById('editVolunteerAddress').value = volunteer.address || '';
            document.getElementById('editVolunteerCategory').value = volunteer.category || '';
            document.getElementById('editVolunteerSkills').value = volunteer.skills;
            document.getElementById('editVolunteerAvailability').value = volunteer.availability;
            document.getElementById('editVolunteerStatus').value = volunteer.status;
            document.getElementById('editVolunteerNotes').value = volunteer.notes || '';
            
            // Show existing photo ID if available
            const photoPreview = document.getElementById('editVolunteerPhotoIdPreview');
            if (volunteer.photoId) {
                photoPreview.innerHTML = '<img src="' + volunteer.photoId + '" alt="Photo ID Preview" class="id-photo-preview" onclick="viewPhoto(this.src)">';
                photoPreview.style.display = 'block';
            } else {
                photoPreview.style.display = 'none';
                photoPreview.innerHTML = '';
            }
            
            // Open modal
            document.getElementById('editVolunteerModal').classList.add('active');
        }
        
        function closeEditVolunteerModal() {
            document.getElementById('editVolunteerModal').classList.remove('active');
            document.getElementById('editVolunteerForm').reset();
            document.getElementById('editVolunteerPhotoIdPreview').style.display = 'none';
            document.getElementById('editVolunteerPhotoIdPreview').innerHTML = '';
        }
        
        function updateVolunteer(event) {
            event.preventDefault();
            
            const volunteerId = document.getElementById('editVolunteerId').value;
            const volunteer = volunteerData[volunteerId];
            
            if (!volunteer) {
                alert('Volunteer not found!');
                return;
            }
            
            // Update volunteer data
            volunteer.name = document.getElementById('editVolunteerName').value.trim();
            volunteer.contact = document.getElementById('editVolunteerContact').value.trim();
            volunteer.email = document.getElementById('editVolunteerEmail').value.trim();
            volunteer.address = document.getElementById('editVolunteerAddress').value.trim();
            volunteer.category = document.getElementById('editVolunteerCategory').value;
            volunteer.skills = document.getElementById('editVolunteerSkills').value.trim();
            volunteer.availability = document.getElementById('editVolunteerAvailability').value;
            volunteer.status = document.getElementById('editVolunteerStatus').value;
            volunteer.notes = document.getElementById('editVolunteerNotes').value.trim();
            
            // Handle photo ID upload if new file is selected
            const photoFile = document.getElementById('editVolunteerPhotoId').files[0];
            if (photoFile) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    volunteer.photoId = e.target.result;
                    completeUpdate();
                };
                reader.readAsDataURL(photoFile);
            } else {
                // Keep existing photo ID if no new file uploaded
                completeUpdate();
            }
            
            function completeUpdate() {
                // Update table row
                updateVolunteerRow(volunteerId);
                saveVolunteerDataToStorage();
                alert('Volunteer updated successfully!');
                closeEditVolunteerModal();
            }
        }
        
        function deleteVolunteer(id) {
            if (confirm('Are you sure you want to delete this volunteer? This action cannot be undone.')) {
                // Remove from data
                delete volunteerData[id];
                saveVolunteerDataToStorage();
                // Remove row from table
                const row = document.querySelector(`tr[data-volunteer-id="${id}"]`);
                if (row) {
                    row.remove();
                }
                
                alert('Volunteer deleted successfully!');
            }
        }
        
        function updateVolunteerRow(id) {
            const volunteer = volunteerData[id];
            const row = document.querySelector(`tr[data-volunteer-id="${id}"]`);
            
            if (!row) return;
            
            const cells = row.querySelectorAll('td');
            
            // Update name
            cells[0].textContent = volunteer.name;
            
            // Update contact
            cells[1].textContent = volunteer.contact;
            
            // Update skills
            cells[2].textContent = volunteer.skills;
            
            // Update availability
            cells[3].textContent = volunteer.availability;
            
            // Update status badge
            const statusBadge = cells[4].querySelector('.status-badge');
            statusBadge.textContent = volunteer.status;
            
            // Determine status badge class
            let statusClass = 'status-resolved';
            if (volunteer.status === 'Pending') {
                statusClass = 'status-pending';
            } else if (volunteer.status === 'Inactive') {
                statusClass = 'status-pending';
            }
            statusBadge.className = `status-badge ${statusClass}`;
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addVolunteerModal');
            const editModal = document.getElementById('editVolunteerModal');
            const viewModal = document.getElementById('viewVolunteerModal');
            
            if (event.target == addModal) {
                closeAddVolunteerModal();
            }
            if (event.target == editModal) {
                closeEditVolunteerModal();
            }
            if (event.target == viewModal) {
                closeViewVolunteerModal();
            }
        }
    </script>
</body>
</html>



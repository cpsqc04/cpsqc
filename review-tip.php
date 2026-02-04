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
    <title>Review Tip - Alertara</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/theme.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
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
        .search-box { margin-bottom: 1.5rem; position: relative; }
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
        .status-reviewed { background: #d1e7dd; color: #0f5132; }
        .status-under-review { background: #cfe2ff; color: #084298; }
        .btn-action { padding: 0.5rem 1rem; background: #28a745; color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; }
        .btn-action:hover { background: #218838; }
        .btn-export { padding: 0.5rem 1rem; background: #007bff; color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; }
        .btn-export:hover { background: #0056b3; }
        label:hover { background: rgba(0, 0, 0, 0.02); }
        input[type="checkbox"] { accent-color: var(--primary-color); }
        .btn-view { padding: 0.5rem 1rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; margin-right: 0.5rem; }
        .btn-view:hover { background: #4ca8a6; }
        .btn-edit { padding: 0.5rem 1rem; background: #ffc107; color: #000; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; }
        .btn-edit:hover { background: #e0a800; }
        .action-buttons { display: flex; gap: 0.5rem; align-items: center; }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); overflow: auto; }
        .modal-content { background-color: var(--card-bg); margin: 5% auto; padding: 2rem; border: 1px solid var(--border-color); border-radius: 12px; width: 90%; max-width: 700px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid var(--border-color); }
        .modal-header h2 { margin: 0; color: var(--tertiary-color); font-size: 1.5rem; }
        .close { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; transition: color 0.2s ease; }
        .close:hover { color: var(--tertiary-color); }
        .tip-details { line-height: 1.8; }
        .tip-details p { margin-bottom: 1rem; }
        .tip-details strong { color: var(--tertiary-color); }
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
        @media (max-width: 768px) { .sidebar { width: 320px; transform: translateX(-100%); transition: transform 0.3s ease; } .sidebar.mobile-open { transform: translateX(0); } .sidebar.collapsed { width: 80px; transform: translateX(0); } .main-wrapper { margin-left: 0; } body.sidebar-collapsed .main-wrapper { margin-left: 80px; } .modal-content { width: 95%; margin: 10% auto; padding: 1.5rem; } }
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
                    <a href="playback.php" class="nav-submodule" data-tooltip="Playback">
                        <span class="nav-submodule-icon"><i class="fas fa-play"></i></span>
                        <span class="nav-submodule-text">Playback</span>
                    </a>
                    <a href="camera-management.php" class="nav-submodule" data-tooltip="Camera Management">
                        <span class="nav-submodule-icon"><i class="fas fa-camera"></i></span>
                        <span class="nav-submodule-text">Camera Management</span>
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
            <div class="nav-module active">
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="Anonymous Tip Line System">
                    <span class="nav-module-icon"><i class="fas fa-comments"></i></span>
                    <span class="nav-module-header-text">Anonymous Tip Line System</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="review-tip.php" class="nav-submodule active" data-tooltip="Review Tip">
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
                <h1 class="page-title">Review Tip</h1>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>
        <main class="content-area">
            <div class="page-content">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search tips by ID, timestamp, location, or description..." onkeyup="filterTips()">
                </div>
                <div class="table-container">
                    <table id="tipsTable">
                        <thead>
                            <tr>
                                <th>Tip ID</th>
                                <th>Timestamp</th>
                                <th>Location</th>
                                <th>Tip Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tipsTableBody">
                            <tr data-tip-id="1">
                                <td>TIP-2025-001</td>
                                <td>2025-01-15 14:30:25</td>
                                <td>Susano Road, Barangay San Agustin, Quezon City</td>
                                <td>Suspicious activity observed near residential area. Multiple individuals loitering.</td>
                                <td><span class="status-badge status-under-review">Under Review</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewTip('1')">View</button>
                                    </div>
                                </td>
                            </tr>
                            <tr data-tip-id="2">
                                <td>TIP-2025-002</td>
                                <td>2025-01-14 10:15:42</td>
                                <td>Paraiso St., Barangay San Agustin, Quezon City</td>
                                <td>Safety concern reported regarding broken streetlights in the area.</td>
                                <td><span class="status-badge status-reviewed">Reviewed</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewTip('2')">View</button>
                                    </div>
                                </td>
                            </tr>
                            <tr data-tip-id="3">
                                <td>TIP-2025-003</td>
                                <td>2025-01-13 18:45:10</td>
                                <td>Clemente St., Barangay San Agustin, Quezon City</td>
                                <td>Vandalism reported on public property. Graffiti found on walls.</td>
                                <td><span class="status-badge status-reviewed">Reviewed</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewTip('3')">View</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- View Tip Modal -->
    <div id="viewTipModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Tip Details</h2>
                <span class="close" onclick="closeViewTipModal()">&times;</span>
            </div>
            <div id="viewTipContent" class="tip-details">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="form-group" id="statusGroup" style="margin-top: 1.5rem;">
                <label for="tipStatus">Status *</label>
                <select id="tipStatus" name="status" onchange="updateTipStatus()">
                    <option value="Under Review">Under Review</option>
                    <option value="Reviewed">Reviewed</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeViewTipModal()">Close</button>
                <button type="button" class="btn-action" id="actionButton" onclick="openActionModal()" style="display: none;">
                    <i class="fas fa-cog"></i> Action
                </button>
            </div>
        </div>
    </div>

    <!-- Action Modal -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Tip Actions</h2>
                <span class="close" onclick="closeActionModal()">&times;</span>
            </div>
            <div id="actionTipContent" class="tip-details" style="margin-bottom: 1.5rem; padding: 1rem; background: #f9f9f9; border-radius: 8px; line-height: 1.8;">
                <!-- Tip details will be populated here -->
            </div>
            <div class="form-group">
                <label style="display: block; margin-bottom: 0.75rem; color: var(--text-color); font-weight: 500; font-size: 0.95rem;">Select Actions:</label>
                <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 0.5rem;">
                    <label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; padding: 0.5rem; border-radius: 6px; transition: background 0.2s ease;">
                        <input type="checkbox" id="sendToGroup1" value="group1" style="margin-top: 0.25rem; flex-shrink: 0; width: 18px; height: 18px; cursor: pointer;">
                        <span style="flex: 1; line-height: 1.5;">Send to Suspect and Witness Management System (Group 1)</span>
                    </label>
                    <label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; padding: 0.5rem; border-radius: 6px; transition: background 0.2s ease;">
                        <input type="checkbox" id="sendToGroup5" value="group5" style="margin-top: 0.25rem; flex-shrink: 0; width: 18px; height: 18px; cursor: pointer;">
                        <span style="flex: 1; line-height: 1.5;">Send to Crime Data Analytics System (Group 5)</span>
                    </label>
                    <label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; padding: 0.5rem; border-radius: 6px; transition: background 0.2s ease;">
                        <input type="checkbox" id="exportWord" value="export" style="margin-top: 0.25rem; flex-shrink: 0; width: 18px; height: 18px; cursor: pointer;">
                        <span style="flex: 1; line-height: 1.5;">Export to Word Document</span>
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeActionModal()">Cancel</button>
                <button type="button" class="btn-save" onclick="executeActions()">
                    <i class="fas fa-check"></i> Execute Actions
                </button>
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
            initializeTipData();
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
        function filterTips() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('tipsTableBody');
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
        // Initialize tip data
        let tipData = {};
        let nextTipId = 4; // Starting from 4 since we have 3 sample tips
        
        function loadSubmittedTips() {
            try {
                const stored = localStorage.getItem('submittedTips');
                return stored ? JSON.parse(stored) : [];
            } catch (e) {
                console.error('Failed to load submitted tips', e);
                return [];
            }
        }
        
        function saveTipDataToStorage() {
            try {
                // Convert tipData object to array format for localStorage
                const tipsArray = Object.values(tipData).map(tip => ({
                    tipId: tip.tipId,
                    timestamp: tip.timestamp,
                    location: tip.location,
                    description: tip.description,
                    status: tip.status
                }));
                localStorage.setItem('submittedTips', JSON.stringify(tipsArray));
            } catch (e) {
                console.error('Failed to save tip data', e);
            }
        }
        
        function initializeTipData() {
            // First, load submitted tips from localStorage
            const submittedTips = loadSubmittedTips();
            const tableBody = document.getElementById('tipsTableBody');
            
            // Load existing tips from table rows
            const rows = tableBody.querySelectorAll('tr[data-tip-id]');
            rows.forEach(row => {
                const id = row.getAttribute('data-tip-id');
                const cells = row.querySelectorAll('td');
                
                const statusBadge = cells[4].querySelector('.status-badge');
                const status = statusBadge ? statusBadge.textContent.trim() : 'Under Review';
                
                tipData[id] = {
                    id: id,
                    tipId: cells[0].textContent.trim(),
                    timestamp: cells[1].textContent.trim(),
                    location: cells[2].textContent.trim(),
                    description: cells[3].textContent.trim(),
                    status: status
                };
            });
            
            // Add submitted tips from localStorage that aren't already in the table
            submittedTips.forEach((tip, index) => {
                // Check if tip already exists in tipData
                const existingTip = Object.values(tipData).find(t => t.tipId === tip.tipId);
                if (!existingTip) {
                    const newId = nextTipId.toString();
                    tipData[newId] = {
                        id: newId,
                        tipId: tip.tipId,
                        timestamp: tip.timestamp,
                        location: tip.location,
                        description: tip.description,
                        status: tip.status || 'Under Review'
                    };
                    
                    // Add row to table
                    addTipTableRow(newId);
                    nextTipId++;
                }
            });
        }
        
        function addTipTableRow(id) {
            const tip = tipData[id];
            if (!tip) return;
            
            const tableBody = document.getElementById('tipsTableBody');
            const row = document.createElement('tr');
            row.setAttribute('data-tip-id', id);
            
            const statusClass = tip.status === 'Reviewed' ? 'status-reviewed' : 'status-under-review';
            const statusText = tip.status || 'Under Review';
            
            row.innerHTML = `
                <td>${tip.tipId}</td>
                <td>${tip.timestamp}</td>
                <td>${tip.location}</td>
                <td>${tip.description}</td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-view" onclick="viewTip('${id}')">View</button>
                    </div>
                </td>
            `;
            
            tableBody.appendChild(row);
        }

        let currentTipId = null;

        function viewTip(id) {
            const tip = tipData[id];
            if (!tip) {
                alert('Tip not found');
                return;
            }
            
            currentTipId = id;
            
            const content = `
                <p><strong>Tip ID:</strong> ${tip.tipId}</p>
                <p><strong>Timestamp:</strong> ${tip.timestamp}</p>
                <p><strong>Location:</strong> ${tip.location}</p>
                <p><strong>Tip Description:</strong><br>${tip.description}</p>
            `;
            
            document.getElementById('viewTipContent').innerHTML = content;
            document.getElementById('tipStatus').value = tip.status || 'Under Review';
            
            // Show Action button only if status is Reviewed
            const actionButton = document.getElementById('actionButton');
            if (tip.status === 'Reviewed') {
                actionButton.style.display = 'inline-block';
            } else {
                actionButton.style.display = 'none';
            }
            
            document.getElementById('viewTipModal').style.display = 'block';
        }

        function closeViewTipModal() {
            document.getElementById('viewTipModal').style.display = 'none';
            currentTipId = null;
        }

        function updateTipStatus() {
            if (!currentTipId) return;
            
            const status = document.getElementById('tipStatus').value;
            tipData[currentTipId].status = status;
            saveTipDataToStorage();
            
            // Update status in table
            const row = document.querySelector(`tr[data-tip-id="${currentTipId}"]`);
            if (row) {
                const cells = row.querySelectorAll('td');
                const statusClass = status === 'Reviewed' ? 'status-reviewed' : 'status-under-review';
                cells[4].innerHTML = `<span class="status-badge ${statusClass}">${status}</span>`;
            }
            
            // Show/hide Action button based on status
            const actionButton = document.getElementById('actionButton');
            if (status === 'Reviewed') {
                actionButton.style.display = 'inline-block';
            } else {
                actionButton.style.display = 'none';
            }
        }

        function openActionModal() {
            if (!currentTipId) return;
            
            const tip = tipData[currentTipId];
            if (!tip) return;
            
            // Populate tip details in action modal
            document.getElementById('actionTipContent').innerHTML = `
                <h3 style="margin-top: 0; margin-bottom: 1rem; color: var(--tertiary-color); font-size: 1.1rem;">Tip Details</h3>
                <p style="margin-bottom: 0.75rem;"><strong>Tip ID:</strong> ${tip.tipId}</p>
                <p style="margin-bottom: 0.75rem;"><strong>Timestamp:</strong> ${tip.timestamp}</p>
                <p style="margin-bottom: 0.75rem;"><strong>Location:</strong> ${tip.location}</p>
                <p style="margin-bottom: 0;"><strong>Description:</strong> ${tip.description}</p>
            `;
            
            // Reset checkboxes
            document.getElementById('sendToGroup1').checked = false;
            document.getElementById('sendToGroup5').checked = false;
            document.getElementById('exportWord').checked = false;
            
            document.getElementById('actionModal').style.display = 'block';
        }

        function closeActionModal() {
            document.getElementById('actionModal').style.display = 'none';
        }

        function executeActions() {
            if (!currentTipId) return;
            
            const tip = tipData[currentTipId];
            const sendToGroup1 = document.getElementById('sendToGroup1').checked;
            const sendToGroup5 = document.getElementById('sendToGroup5').checked;
            const exportWord = document.getElementById('exportWord').checked;
            
            if (!sendToGroup1 && !sendToGroup5 && !exportWord) {
                alert('Please select at least one action.');
                return;
            }
            
            const tipDataToSend = {
                tipId: tip.tipId,
                timestamp: tip.timestamp,
                location: tip.location,
                description: tip.description
            };
            
            let actionsCompleted = 0;
            let totalActions = 0;
            
            if (sendToGroup1) {
                totalActions++;
                fetch('api/send_to_group1.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(tipDataToSend)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        actionsCompleted++;
                        checkAllActionsComplete();
                    } else {
                        console.error('Error sending to Group 1:', data.message);
                        checkAllActionsComplete();
                    }
                })
                .catch(error => {
                    console.error('Error sending to Group 1:', error);
                    checkAllActionsComplete();
                });
            }
            
            if (sendToGroup5) {
                totalActions++;
                fetch('api/send_to_group5.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(tipDataToSend)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        actionsCompleted++;
                        checkAllActionsComplete();
                    } else {
                        console.error('Error sending to Group 5:', data.message);
                        checkAllActionsComplete();
                    }
                })
                .catch(error => {
                    console.error('Error sending to Group 5:', error);
                    checkAllActionsComplete();
                });
            }
            
            if (exportWord) {
                totalActions++;
                exportTipToWord(tip).then(() => {
                    actionsCompleted++;
                    checkAllActionsComplete();
                }).catch(error => {
                    console.error('Error exporting to Word:', error);
                    checkAllActionsComplete();
                });
            }
            
            function checkAllActionsComplete() {
                if (actionsCompleted >= totalActions && totalActions > 0) {
                    let message = 'Actions completed:\n';
                    if (sendToGroup1) message += '- Sent to Suspect and Witness Management System (Group 1)\n';
                    if (sendToGroup5) message += '- Sent to Crime Data Analytics System (Group 5)\n';
                    if (exportWord) message += '- Exported to Word document\n';
                    alert(message);
                    closeActionModal();
                }
            }
        }

        async function exportTipToWord(tip) {
            try {
                if (typeof JSZip === 'undefined') {
                    alert('Export library not loaded. Please refresh the page.');
                    return;
                }

                const zip = new JSZip();

                const escapeXml = (text) => {
                    if (!text) return '';
                    return String(text)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&apos;');
                };

                const contentTypes = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">\n' +
'    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>\n' +
'    <Default Extension="xml" ContentType="application/xml"/>\n' +
'    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>\n' +
'    <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>\n' +
'</Types>';

                const documentXml = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">\n' +
'    <w:body>\n' +
'        <w:p>\n' +
'            <w:pPr>\n' +
'                <w:jc w:val="center"/>\n' +
'                <w:spacing w:after="400"/>\n' +
'            </w:pPr>\n' +
'            <w:r>\n' +
'                <w:rPr>\n' +
'                    <w:b/>\n' +
'                    <w:sz w:val="32"/>\n' +
'                </w:rPr>\n' +
'                <w:t>TIP REPORT</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:pPr>\n' +
'                <w:jc w:val="center"/>\n' +
'                <w:spacing w:after="600"/>\n' +
'            </w:pPr>\n' +
'            <w:r>\n' +
'                <w:t>Barangay San Agustin, Quezon City</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:r>\n' +
'                <w:rPr><w:b/></w:rPr>\n' +
'                <w:t>Tip ID:</w:t>\n' +
'            </w:r>\n' +
'            <w:r>\n' +
'                <w:t> ' + escapeXml(tip.tipId) + '</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:r>\n' +
'                <w:rPr><w:b/></w:rPr>\n' +
'                <w:t>Timestamp:</w:t>\n' +
'            </w:r>\n' +
'            <w:r>\n' +
'                <w:t> ' + escapeXml(tip.timestamp) + '</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:r>\n' +
'                <w:rPr><w:b/></w:rPr>\n' +
'                <w:t>Location:</w:t>\n' +
'            </w:r>\n' +
'            <w:r>\n' +
'                <w:t> ' + escapeXml(tip.location) + '</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:pPr>\n' +
'                <w:spacing w:before="400"/>\n' +
'            </w:pPr>\n' +
'            <w:r>\n' +
'                <w:rPr><w:b/></w:rPr>\n' +
'                <w:t>Tip Description:</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:r>\n' +
'                <w:t>' + escapeXml(tip.description) + '</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:pPr>\n' +
'                <w:jc w:val="right"/>\n' +
'                <w:spacing w:before="600"/>\n' +
'            </w:pPr>\n' +
'            <w:r>\n' +
'                <w:t>Generated on: ' + escapeXml(new Date().toLocaleString()) + '</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'    </w:body>\n' +
'</w:document>';

                const stylesXml = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">\n' +
'    <w:style w:type="paragraph" w:styleId="Normal">\n' +
'        <w:name w:val="Normal"/>\n' +
'        <w:qFormat/>\n' +
'    </w:style>\n' +
'</w:styles>';

                const rels = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">\n' +
'    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>\n' +
'</Relationships>';

                const wordRels = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">\n' +
'    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>\n' +
'</Relationships>';

                zip.file("[Content_Types].xml", contentTypes);
                zip.file("word/document.xml", documentXml);
                zip.file("word/styles.xml", stylesXml);
                zip.file("_rels/.rels", rels);
                zip.file("word/_rels/document.xml.rels", wordRels);

                const blob = await zip.generateAsync({ type: "blob", mimeType: "application/vnd.openxmlformats-officedocument.wordprocessingml.document" });
                const fileName = `tip_report_${tip.tipId}_${tip.timestamp.replace(/[:\s]/g, '_')}.docx`;
                
                const link = document.createElement("a");
                link.href = URL.createObjectURL(blob);
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);
            } catch (error) {
                console.error('Error generating DOCX:', error);
                throw error;
            }
        }


        // Close modal when clicking outside
        window.onclick = function(event) {
            const viewModal = document.getElementById('viewTipModal');
            const actionModal = document.getElementById('actionModal');
            
            if (event.target === viewModal) {
                closeViewTipModal();
            }
            if (event.target === actionModal) {
                closeActionModal();
            }
        }

    </script>
</body>
</html>


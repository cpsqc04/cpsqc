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
    <title>Patrol Logs - Alertara</title>
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
        .status-resolved { background: #d1e7dd; color: #0f5132; }
        .btn-view { padding: 0.5rem 1rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; margin-right: 0.5rem; }
        .btn-view:hover { background: #4ca8a6; }
        .btn-export { padding: 0.5rem 1rem; background: #28a745; color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; }
        .btn-export:hover { background: #218838; }
        .action-buttons { display: flex; gap: 0.5rem; align-items: center; }
        .status-in-progress { background: #cfe2ff; color: #084298; }
        .status-completed { background: #d1e7dd; color: #0f5132; }
        .status-scheduled { background: #fff3cd; color: #856404; }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); overflow: auto; }
        .modal-content { background-color: var(--card-bg); margin: 5% auto; padding: 2rem; border: 1px solid var(--border-color); border-radius: 12px; width: 90%; max-width: 700px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid var(--border-color); }
        .modal-header h2 { margin: 0; color: var(--tertiary-color); font-size: 1.5rem; }
        .close { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; transition: color 0.2s ease; }
        .close:hover { color: var(--tertiary-color); }
        .log-details { line-height: 1.8; }
        .log-details p { margin-bottom: 1rem; }
        .log-details strong { color: var(--tertiary-color); }
        .form-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); }
        .btn-cancel { padding: 0.75rem 1.5rem; background: #6c757d; color: #fff; border: none; border-radius: 8px; font-size: 0.95rem; cursor: pointer; transition: all 0.2s ease; }
        .btn-cancel:hover { background: #5a6268; }
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
            <!-- Dashboard Link -->
            <a href="index.php" class="nav-module-header" data-tooltip="Dashboard" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.5rem; color: rgba(255, 255, 255, 0.9); cursor: pointer; transition: background-color 0.2s ease; font-weight: 500; user-select: none; gap: 0.75rem; <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'background: rgba(76, 138, 137, 0.25); border-left: 3px solid #4c8a89;' : ''; ?>">
                <span class="nav-module-icon"><i class="fas fa-home"></i></span>
                <span class="nav-module-header-text">Dashboard</span>
            </a>
            
            <!-- User Management Link -->
            <a href="user-management.php" class="nav-module-header" data-tooltip="User Management" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.5rem; color: rgba(255, 255, 255, 0.9); cursor: pointer; transition: background-color 0.2s ease; font-weight: 500; user-select: none; gap: 0.75rem; <?php echo basename($_SERVER['PHP_SELF']) == 'user-management.php' ? 'background: rgba(76, 138, 137, 0.25); border-left: 3px solid #4c8a89;' : ''; ?>">
                <span class="nav-module-icon"><i class="fas fa-users-cog"></i></span>
                <span class="nav-module-header-text">User Management</span>
            </a>
            
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
                    <a href="patrol-list.php" class="nav-submodule" data-tooltip="Patrol List">
                        <span class="nav-submodule-icon"><i class="fas fa-list"></i></span>
                        <span class="nav-submodule-text">Patrol List</span>
                    </a>
                    <a href="patrol-schedule.php" class="nav-submodule" data-tooltip="Patrol Schedule">
                        <span class="nav-submodule-icon"><i class="fas fa-calendar-alt"></i></span>
                        <span class="nav-submodule-text">Patrol Schedule</span>
                    </a>
                    <a href="patrol-logs.php" class="nav-submodule active" data-tooltip="Patrol Logs">
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
                <h1 class="page-title">Patrol Logs</h1>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>
        <main class="content-area">
            <div class="page-content">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search patrol logs by date, officer, or incident..." onkeyup="filterLogs()">
                </div>
                <div class="table-container">
                    <table id="logsTable">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Officer</th>
                                <th>Route</th>
                                <th>Incidents</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="logsTableBody">
                            <tr data-log-id="1">
                                <td>2025-01-20 08:00</td>
                                <td>Juan Dela Cruz</td>
                                <td>San Agustin Street to Quezon Avenue Extension</td>
                                <td>None</td>
                                <td><span class="status-badge status-completed">Completed</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewLog('1')">View</button>
                                        <button class="btn-export" onclick="exportLog('1')">Export</button>
                                    </div>
                                </td>
                            </tr>
                            <tr data-log-id="2">
                                <td>2025-01-19 16:00</td>
                                <td>Maria Santos</td>
                                <td>Rizal Street to Luna Avenue</td>
                                <td>Minor disturbance reported</td>
                                <td><span class="status-badge status-completed">Completed</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewLog('2')">View</button>
                                        <button class="btn-export" onclick="exportLog('2')">Export</button>
                                    </div>
                                </td>
                            </tr>
                            <tr data-log-id="3">
                                <td>2025-01-18 00:00</td>
                                <td>Roberto Reyes</td>
                                <td>Mabini Street corner Quezon Avenue</td>
                                <td>None</td>
                                <td><span class="status-badge status-in-progress">In Progress</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewLog('3')">View</button>
                                        <button class="btn-export" onclick="exportLog('3')">Export</button>
                                    </div>
                                </td>
                            </tr>
                            <tr data-log-id="4">
                                <td>2025-01-17 08:00</td>
                                <td>Ana Garcia</td>
                                <td>Bonifacio Street Area</td>
                                <td>None</td>
                                <td><span class="status-badge status-completed">Completed</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewLog('4')">View</button>
                                        <button class="btn-export" onclick="exportLog('4')">Export</button>
                                    </div>
                                </td>
                            </tr>
                            <tr data-log-id="5">
                                <td>2025-01-16 16:00</td>
                                <td>Carlos Torres</td>
                                <td>Commonwealth Avenue Extension</td>
                                <td>Assisted elderly resident</td>
                                <td><span class="status-badge status-completed">Completed</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewLog('5')">View</button>
                                        <button class="btn-export" onclick="exportLog('5')">Export</button>
                                    </div>
                                </td>
                            </tr>
                            <tr data-log-id="6">
                                <td>2025-01-15 00:00</td>
                                <td>Liza Fernandez</td>
                                <td>Aguinaldo Street to Commonwealth Avenue</td>
                                <td>None</td>
                                <td><span class="status-badge status-scheduled">Scheduled</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewLog('6')">View</button>
                                        <button class="btn-export" onclick="exportLog('6')">Export</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- View Patrol Log Modal -->
    <div id="viewLogModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Patrol Log Details</h2>
                <span class="close" onclick="closeViewLogModal()">&times;</span>
            </div>
            <div id="viewLogContent" class="log-details">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeViewLogModal()">Close</button>
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
        function filterLogs() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('logsTableBody');
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
        // Initialize patrol log data
        let patrolLogData = {
            '1': {
                id: '1',
                date: '2025-01-20',
                time: '08:00 - 16:00',
                officer: 'Juan Dela Cruz',
                route: 'San Agustin Street to Quezon Avenue Extension',
                status: 'Completed',
                incidents: 'None',
                details: 'Routine morning patrol conducted along San Agustin Street to Quezon Avenue Extension. All areas were secure. Checked streetlights and reported one non-functional light near the Barangay Hall. No suspicious activities observed.',
                location: 'Barangay San Agustin, Quezon City'
            },
            '2': {
                id: '2',
                date: '2025-01-19',
                time: '16:00 - 00:00',
                officer: 'Maria Santos',
                route: 'Rizal Street to Luna Avenue',
                status: 'Completed',
                incidents: 'Minor disturbance reported',
                details: 'Evening patrol along Rizal Street to Luna Avenue. Responded to a minor disturbance call near Luna Avenue. Upon investigation, it was determined to be a false alarm - neighbors were moving furniture. Situation resolved peacefully. All other areas were secure.',
                location: 'Rizal Street to Luna Avenue, Barangay San Agustin, Quezon City'
            },
            '3': {
                id: '3',
                date: '2025-01-18',
                time: '00:00 - 08:00',
                officer: 'Roberto Reyes',
                route: 'Mabini Street corner Quezon Avenue',
                status: 'In Progress',
                incidents: 'None',
                details: 'Night patrol currently in progress along Mabini Street corner Quezon Avenue. Monitoring traffic flow and pedestrian safety. All security measures in place. Regular checkpoints established.',
                location: 'Mabini Street corner Quezon Avenue, Barangay San Agustin, Quezon City'
            },
            '4': {
                id: '4',
                date: '2025-01-17',
                time: '08:00 - 16:00',
                officer: 'Ana Garcia',
                route: 'Bonifacio Street Area',
                status: 'Completed',
                incidents: 'None',
                details: 'Morning patrol conducted in Bonifacio Street Area. Checked all entry points and common areas. Everything was in order. Noted that garbage collection was completed on schedule. All residential areas were secure.',
                location: 'Bonifacio Street Area, Barangay San Agustin, Quezon City'
            },
            '5': {
                id: '5',
                date: '2025-01-16',
                time: '16:00 - 00:00',
                officer: 'Carlos Torres',
                route: 'Commonwealth Avenue Extension',
                status: 'Completed',
                incidents: 'Assisted elderly resident',
                details: 'Afternoon patrol along Commonwealth Avenue Extension. Assisted an elderly resident crossing the street. Checked commercial establishments and residential areas. All security measures in place. No incidents reported.',
                location: 'Commonwealth Avenue Extension, Barangay San Agustin, Quezon City'
            },
            '6': {
                id: '6',
                date: '2025-01-15',
                time: '00:00 - 08:00',
                officer: 'Liza Fernandez',
                route: 'Aguinaldo Street to Commonwealth Avenue',
                status: 'Scheduled',
                incidents: 'None',
                details: 'Night patrol scheduled for Aguinaldo Street to Commonwealth Avenue. Patrol assignment has been confirmed and officer is prepared for duty.',
                location: 'Aguinaldo Street to Commonwealth Avenue, Barangay San Agustin, Quezon City'
            }
        };

        function viewLog(id) {
            const log = patrolLogData[id];
            if (!log) {
                alert('Log not found');
                return;
            }
            
            const statusClass = log.status === 'Completed' ? 'status-completed' : 
                                log.status === 'In Progress' ? 'status-in-progress' : 
                                'status-scheduled';
            
            const content = `
                <p><strong>Date:</strong> ${log.date}</p>
                <p><strong>Time:</strong> ${log.time}</p>
                <p><strong>Officer:</strong> ${log.officer}</p>
                <p><strong>Route:</strong> ${log.route}</p>
                <p><strong>Location:</strong> ${log.location}</p>
                <p><strong>Status:</strong> <span class="status-badge ${statusClass}">${log.status}</span></p>
                <p><strong>Incidents:</strong> ${log.incidents}</p>
                <p><strong>Details:</strong><br>${log.details}</p>
            `;
            
            document.getElementById('viewLogContent').innerHTML = content;
            document.getElementById('viewLogModal').style.display = 'block';
        }

        function closeViewLogModal() {
            document.getElementById('viewLogModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('viewLogModal');
            if (event.target === modal) {
                closeViewLogModal();
            }
        }

        async function exportLog(id) {
            const log = patrolLogData[id];
            if (!log) {
                alert('Log not found');
                return;
            }

            try {
                // Check if JSZip is available
                if (typeof JSZip === 'undefined') {
                    alert('Export library not loaded. Please refresh the page.');
                    return;
                }

                // Create DOCX file structure using JSZip
                const zip = new JSZip();

                // Create [Content_Types].xml
                const contentTypes = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">\n' +
'    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>\n' +
'    <Default Extension="xml" ContentType="application/xml"/>\n' +
'    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>\n' +
'    <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>\n' +
'</Types>';

                // Create word/document.xml with the actual content
                const escapeXml = (text) => {
                    if (!text) return '';
                    return String(text)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&apos;');
                };

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
'                <w:t>PATROL LOG REPORT</w:t>\n' +
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
'                <w:t>Date:</w:t>\n' +
'            </w:r>\n' +
'            <w:r>\n' +
'                <w:t> ' + escapeXml(log.date) + '</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:r>\n' +
'                <w:rPr><w:b/></w:rPr>\n' +
'                <w:t>Time:</w:t>\n' +
'            </w:r>\n' +
'            <w:r>\n' +
'                <w:t> ' + escapeXml(log.time) + '</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:r>\n' +
'                <w:rPr><w:b/></w:rPr>\n' +
'                <w:t>Officer:</w:t>\n' +
'            </w:r>\n' +
'            <w:r>\n' +
'                <w:t> ' + escapeXml(log.officer) + '</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:r>\n' +
'                <w:rPr><w:b/></w:rPr>\n' +
'                <w:t>Route:</w:t>\n' +
'            </w:r>\n' +
'            <w:r>\n' +
'                <w:t> ' + escapeXml(log.route) + '</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:r>\n' +
'                <w:rPr><w:b/></w:rPr>\n' +
'                <w:t>Location:</w:t>\n' +
'            </w:r>\n' +
'            <w:r>\n' +
'                <w:t> ' + escapeXml(log.location) + '</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:r>\n' +
'                <w:rPr><w:b/></w:rPr>\n' +
'                <w:t>Status:</w:t>\n' +
'            </w:r>\n' +
'            <w:r>\n' +
'                <w:t> ' + escapeXml(log.status) + '</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:r>\n' +
'                <w:rPr><w:b/></w:rPr>\n' +
'                <w:t>Incidents:</w:t>\n' +
'            </w:r>\n' +
'            <w:r>\n' +
'                <w:t> ' + escapeXml(log.incidents) + '</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:pPr>\n' +
'                <w:spacing w:before="400"/>\n' +
'            </w:pPr>\n' +
'            <w:r>\n' +
'                <w:rPr><w:b/></w:rPr>\n' +
'                <w:t>Details:</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:r>\n' +
'                <w:t>' + escapeXml(log.details) + '</w:t>\n' +
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

                // Create word/styles.xml
                const stylesXml = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">\n' +
'    <w:style w:type="paragraph" w:styleId="Normal">\n' +
'        <w:name w:val="Normal"/>\n' +
'        <w:qFormat/>\n' +
'    </w:style>\n' +
'</w:styles>';

                // Create _rels/.rels
                const rels = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">\n' +
'    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>\n' +
'</Relationships>';

                // Create word/_rels/document.xml.rels
                const wordRels = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">\n' +
'    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>\n' +
'</Relationships>';

                // Add files to zip
                zip.file("[Content_Types].xml", contentTypes);
                zip.file("word/document.xml", documentXml);
                zip.file("word/styles.xml", stylesXml);
                zip.file("_rels/.rels", rels);
                zip.file("word/_rels/document.xml.rels", wordRels);

                // Generate the DOCX file
                const blob = await zip.generateAsync({ type: "blob", mimeType: "application/vnd.openxmlformats-officedocument.wordprocessingml.document" });
                const fileName = `patrol_log_${log.officer.replace(/\s+/g, '_')}_${log.date}.docx`;
                
                const link = document.createElement("a");
                link.href = URL.createObjectURL(blob);
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);
                
                alert(`Patrol log exported successfully as ${fileName}!`);
            } catch (error) {
                console.error('Error generating DOCX:', error);
                alert('Error generating DOCX file. Please try again.');
            }
        }
    </script>
</body>
</html>


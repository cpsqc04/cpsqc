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
    <title>Camera Management - Alertara</title>
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
        .search-container { display: flex; gap: 1rem; margin-bottom: 1.5rem; align-items: center; }
        .search-box { flex: 1; position: relative; }
        .search-box input { width: 100%; padding: 0.75rem 1rem 0.75rem 2.5rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.95rem; transition: all 0.2s ease; }
        .search-box input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1); }
        .search-box::before { content: "🔍"; position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 1rem; }
        .btn-add { padding: 0.75rem 1.5rem; background: var(--primary-color); color: #fff; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 0.5rem; white-space: nowrap; flex-shrink: 0; }
        .btn-add:hover { background: #4ca8a6; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(76, 138, 137, 0.3); }
        .btn-add i { font-size: 1rem; }
        .table-container { overflow-x: auto; border-radius: 8px; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; background: var(--card-bg); }
        thead { background: var(--tertiary-color); color: #fff; }
        th { padding: 1rem; text-align: left; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-color); }
        tbody tr:hover { background: #f9f9f9; }
        tbody tr:last-child td { border-bottom: none; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.85rem; font-weight: 500; display: inline-block; }
        .status-online { background: #d1e7dd; color: #0f5132; }
        .status-offline { background: #f8d7da; color: #842029; }
        .status-maintenance { background: #fff3cd; color: #856404; }
        .btn-view { padding: 0.5rem 1rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; margin-right: 0.5rem; }
        .btn-view:hover { background: #4ca8a6; }
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
        .camera-details { line-height: 1.8; }
        .camera-details p { margin-bottom: 1rem; }
        .camera-details strong { color: var(--tertiary-color); }
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
                </div>
            </div>
            <div class="nav-module active">
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="CCTV Surveillance System Management">
                    <span class="nav-module-icon"><i class="fas fa-video"></i></span>
                    <span class="nav-module-header-text">CCTV Surveillance System Management</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="live-view.php" class="nav-submodule" data-tooltip="Live View">
                        <span class="nav-submodule-icon"><i class="fas fa-circle" style="color: #ff4444;"></i></span>
                        <span class="nav-submodule-text">Live View</span>
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
                    <a href="submit-tip.php" class="nav-submodule" data-tooltip="Submit Tip">
                        <span class="nav-submodule-icon"><i class="fas fa-envelope"></i></span>
                        <span class="nav-submodule-text">Submit Tip</span>
                    </a>
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
                <h1 class="page-title">Camera Management</h1>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>
        <main class="content-area">
            <div class="page-content">
                <div class="search-container">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search cameras by name, location, or status..." onkeyup="filterCameras()">
                    </div>
                    <button class="btn-add" onclick="openAddCameraModal()">
                        <i class="fas fa-plus"></i> Add Camera
                    </button>
                </div>
                <div class="table-container">
                    <table id="camerasTable">
                        <thead>
                            <tr>
                                <th>Camera ID</th>
                                <th>Camera Name</th>
                                <th>Location</th>
                                <th>IP Address</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="camerasTableBody">
                            <tr data-camera-id="1">
                                <td>CAM-001</td>
                                <td>Main Entrance Camera</td>
                                <td>Susano Road, Barangay San Agustin, Quezon City</td>
                                <td>192.168.1.101</td>
                                <td><span class="status-badge status-online">Online</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewCamera('1')">View</button>
                                        <button class="btn-edit" onclick="editCamera('1')">Edit</button>
                                        <button class="btn-delete" onclick="deleteCamera('1')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr data-camera-id="2">
                                <td>CAM-002</td>
                                <td>Barangay Hall Camera</td>
                                <td>Paraiso St., Barangay San Agustin, Quezon City</td>
                                <td>192.168.1.102</td>
                                <td><span class="status-badge status-online">Online</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewCamera('2')">View</button>
                                        <button class="btn-edit" onclick="editCamera('2')">Edit</button>
                                        <button class="btn-delete" onclick="deleteCamera('2')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr data-camera-id="3">
                                <td>CAM-003</td>
                                <td>Community Center Camera</td>
                                <td>Clemente St., Barangay San Agustin, Quezon City</td>
                                <td>192.168.1.103</td>
                                <td><span class="status-badge status-offline">Offline</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewCamera('3')">View</button>
                                        <button class="btn-edit" onclick="editCamera('3')">Edit</button>
                                        <button class="btn-delete" onclick="deleteCamera('3')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Camera Modal -->
    <div id="addCameraModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Camera</h2>
                <span class="close" onclick="closeAddCameraModal()">&times;</span>
            </div>
            <form id="addCameraForm" onsubmit="saveCamera(event)">
                <div class="form-group">
                    <label for="cameraName">Camera Name *</label>
                    <input type="text" id="cameraName" name="cameraName" required>
                </div>
                <div class="form-group">
                    <label for="cameraLocation">Location *</label>
                    <select id="cameraLocation" name="location" required>
                        <option value="">Select Location</option>
                        <option value="Susano Road, Barangay San Agustin, Quezon City">Susano Road, Barangay San Agustin, Quezon City</option>
                        <option value="Paraiso St., Barangay San Agustin, Quezon City">Paraiso St., Barangay San Agustin, Quezon City</option>
                        <option value="Clemente St., Barangay San Agustin, Quezon City">Clemente St., Barangay San Agustin, Quezon City</option>
                        <option value="Clemente Subd., Barangay San Agustin, Quezon City">Clemente Subd., Barangay San Agustin, Quezon City</option>
                        <option value="Patnubay St., Barangay San Agustin, Quezon City">Patnubay St., Barangay San Agustin, Quezon City</option>
                        <option value="Katarungan St., Barangay San Agustin, Quezon City">Katarungan St., Barangay San Agustin, Quezon City</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cameraIP">IP Address *</label>
                    <input type="text" id="cameraIP" name="ipAddress" placeholder="192.168.1.xxx" pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$" required>
                </div>
                <div class="form-group">
                    <label for="cameraStatus">Status *</label>
                    <select id="cameraStatus" name="status" required>
                        <option value="Online">Online</option>
                        <option value="Offline">Offline</option>
                        <option value="Maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cameraDescription">Description</label>
                    <textarea id="cameraDescription" name="description" placeholder="Camera description or notes..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeAddCameraModal()">Cancel</button>
                    <button type="submit" class="btn-save">Add Camera</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Camera Modal -->
    <div id="viewCameraModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Camera Details</h2>
                <span class="close" onclick="closeViewCameraModal()">&times;</span>
            </div>
            <div id="viewCameraContent" class="camera-details">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeViewCameraModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Camera Modal -->
    <div id="editCameraModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Camera</h2>
                <span class="close" onclick="closeEditCameraModal()">&times;</span>
            </div>
            <form id="editCameraForm" onsubmit="updateCamera(event)">
                <input type="hidden" id="editCameraId" name="id">
                <div class="form-group">
                    <label for="editCameraName">Camera Name *</label>
                    <input type="text" id="editCameraName" name="cameraName" required>
                </div>
                <div class="form-group">
                    <label for="editCameraLocation">Location *</label>
                    <select id="editCameraLocation" name="location" required>
                        <option value="">Select Location</option>
                        <option value="Susano Road, Barangay San Agustin, Quezon City">Susano Road, Barangay San Agustin, Quezon City</option>
                        <option value="Paraiso St., Barangay San Agustin, Quezon City">Paraiso St., Barangay San Agustin, Quezon City</option>
                        <option value="Clemente St., Barangay San Agustin, Quezon City">Clemente St., Barangay San Agustin, Quezon City</option>
                        <option value="Clemente Subd., Barangay San Agustin, Quezon City">Clemente Subd., Barangay San Agustin, Quezon City</option>
                        <option value="Patnubay St., Barangay San Agustin, Quezon City">Patnubay St., Barangay San Agustin, Quezon City</option>
                        <option value="Katarungan St., Barangay San Agustin, Quezon City">Katarungan St., Barangay San Agustin, Quezon City</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editCameraIP">IP Address *</label>
                    <input type="text" id="editCameraIP" name="ipAddress" placeholder="192.168.1.xxx" pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$" required>
                </div>
                <div class="form-group">
                    <label for="editCameraStatus">Status *</label>
                    <select id="editCameraStatus" name="status" required>
                        <option value="Online">Online</option>
                        <option value="Offline">Offline</option>
                        <option value="Maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editCameraDescription">Description</label>
                    <textarea id="editCameraDescription" name="description" placeholder="Camera description or notes..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeEditCameraModal()">Cancel</button>
                    <button type="submit" class="btn-save">Update Camera</button>
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
            initializeCameraData();
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
        function filterCameras() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('camerasTableBody');
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

        // Initialize camera data
        let cameraData = {};
        
        function initializeCameraData() {
            const tableBody = document.getElementById('camerasTableBody');
            const rows = tableBody.querySelectorAll('tr[data-camera-id]');
            
            rows.forEach(row => {
                const id = row.getAttribute('data-camera-id');
                const cells = row.querySelectorAll('td');
                
                cameraData[id] = {
                    id: id,
                    cameraId: cells[0].textContent.trim(),
                    name: cells[1].textContent.trim(),
                    location: cells[2].textContent.trim(),
                    ipAddress: cells[3].textContent.trim(),
                    status: cells[4].querySelector('.status-badge').textContent.trim(),
                    description: ''
                };
            });
        }

        function openAddCameraModal() {
            document.getElementById('addCameraModal').style.display = 'block';
            document.getElementById('addCameraForm').reset();
        }

        function closeAddCameraModal() {
            document.getElementById('addCameraModal').style.display = 'none';
        }

        function viewCamera(id) {
            const camera = cameraData[id];
            if (!camera) {
                alert('Camera not found');
                return;
            }
            
            const content = `
                <p><strong>Camera ID:</strong> ${camera.cameraId}</p>
                <p><strong>Camera Name:</strong> ${camera.name}</p>
                <p><strong>Location:</strong> ${camera.location}</p>
                <p><strong>IP Address:</strong> ${camera.ipAddress}</p>
                <p><strong>Status:</strong> ${camera.status}</p>
                ${camera.description ? `<p><strong>Description:</strong><br>${camera.description}</p>` : '<p><strong>Description:</strong> No description provided.</p>'}
            `;
            
            document.getElementById('viewCameraContent').innerHTML = content;
            document.getElementById('viewCameraModal').style.display = 'block';
        }

        function closeViewCameraModal() {
            document.getElementById('viewCameraModal').style.display = 'none';
        }

        function editCamera(id) {
            const camera = cameraData[id];
            if (!camera) return;
            
            document.getElementById('editCameraId').value = camera.id;
            document.getElementById('editCameraName').value = camera.name;
            document.getElementById('editCameraLocation').value = camera.location;
            document.getElementById('editCameraIP').value = camera.ipAddress;
            document.getElementById('editCameraStatus').value = camera.status;
            document.getElementById('editCameraDescription').value = camera.description || '';
            
            document.getElementById('editCameraModal').style.display = 'block';
        }

        function closeEditCameraModal() {
            document.getElementById('editCameraModal').style.display = 'none';
        }

        function deleteCamera(id) {
            if (!confirm('Are you sure you want to delete this camera?')) {
                return;
            }
            
            const row = document.querySelector(`tr[data-camera-id="${id}"]`);
            if (row) {
                row.remove();
                delete cameraData[id];
            }
        }

        function saveCamera(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const id = Date.now().toString();
            const cameraId = 'CAM-' + String(Math.floor(Math.random() * 900) + 100).padStart(3, '0');
            
            const camera = {
                id: id,
                cameraId: cameraId,
                name: formData.get('cameraName'),
                location: formData.get('location'),
                ipAddress: formData.get('ipAddress'),
                status: formData.get('status'),
                description: formData.get('description') || ''
            };
            
            cameraData[id] = camera;
            
            const tableBody = document.getElementById('camerasTableBody');
            const row = document.createElement('tr');
            row.setAttribute('data-camera-id', id);
            
            const statusClass = camera.status === 'Online' ? 'status-online' : 
                                camera.status === 'Offline' ? 'status-offline' : 
                                'status-maintenance';
            
            row.innerHTML = `
                <td>${camera.cameraId}</td>
                <td>${camera.name}</td>
                <td>${camera.location}</td>
                <td>${camera.ipAddress}</td>
                <td><span class="status-badge ${statusClass}">${camera.status}</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-view" onclick="viewCamera('${id}')">View</button>
                        <button class="btn-edit" onclick="editCamera('${id}')">Edit</button>
                        <button class="btn-delete" onclick="deleteCamera('${id}')">Delete</button>
                    </div>
                </td>
            `;
            
            tableBody.appendChild(row);
            closeAddCameraModal();
        }

        function updateCamera(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const id = formData.get('id');
            
            const camera = {
                id: id,
                cameraId: cameraData[id].cameraId,
                name: formData.get('cameraName'),
                location: formData.get('location'),
                ipAddress: formData.get('ipAddress'),
                status: formData.get('status'),
                description: formData.get('description') || ''
            };
            
            cameraData[id] = camera;
            
            const row = document.querySelector(`tr[data-camera-id="${id}"]`);
            if (row) {
                const cells = row.querySelectorAll('td');
                cells[1].textContent = camera.name;
                cells[2].textContent = camera.location;
                cells[3].textContent = camera.ipAddress;
                
                const statusClass = camera.status === 'Online' ? 'status-online' : 
                                    camera.status === 'Offline' ? 'status-offline' : 
                                    'status-maintenance';
                
                cells[4].innerHTML = `<span class="status-badge ${statusClass}">${camera.status}</span>`;
            }
            
            closeEditCameraModal();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addCameraModal');
            const viewModal = document.getElementById('viewCameraModal');
            const editModal = document.getElementById('editCameraModal');
            
            if (event.target === addModal) {
                closeAddCameraModal();
            }
            if (event.target === viewModal) {
                closeViewCameraModal();
            }
            if (event.target === editModal) {
                closeEditCameraModal();
            }
        }
    </script>
</body>
</html>


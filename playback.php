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
    <title>Playback - CCTV Recordings</title>
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
        .playback-container { display: flex; flex-direction: column; gap: 1.5rem; }
        .video-wrapper { display: none; }
        .video-wrapper.active { display: block; }
        .playback-controls-panel { display: flex; gap: 1.5rem; flex-wrap: wrap; }
        .control-group { flex: 1; min-width: 200px; }
        .control-group label { display: block; margin-bottom: 0.5rem; color: var(--text-color); font-weight: 500; font-size: 0.95rem; }
        .control-group select, .control-group input { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.95rem; font-family: var(--font-family); transition: all 0.2s ease; box-sizing: border-box; }
        .control-group select:focus, .control-group input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1); }
        .btn-search { padding: 0.75rem 1.5rem; background: var(--primary-color); color: #fff; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; align-self: flex-end; margin-top: 1.75rem; }
        .btn-search:hover { background: #4ca8a6; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(76, 138, 137, 0.3); }
        .video-player-container { background: #000; border-radius: 12px; position: relative; overflow: hidden; aspect-ratio: 16/9; display: none; }
        .video-player-container.active { display: block; }
        .video-placeholder { width: 100%; height: 100%; background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; position: relative; }
        .video-placeholder::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(255, 255, 255, 0.03) 2px, rgba(255, 255, 255, 0.03) 4px); }
        .video-info { position: absolute; top: 1rem; left: 1rem; background: rgba(0, 0, 0, 0.7); padding: 0.75rem 1rem; border-radius: 8px; color: #fff; z-index: 10; }
        .video-info p { margin: 0.25rem 0; font-size: 0.9rem; }
        .playback-controls { background: rgba(0, 0, 0, 0.9); padding: 1rem; border-radius: 8px; display: none; align-items: center; gap: 1rem; margin-top: 0; }
        .playback-controls.active { display: flex; }
        .playback-controls button { background: transparent; border: none; color: #fff; font-size: 1.5rem; cursor: pointer; padding: 0.5rem; transition: all 0.2s ease; }
        .playback-controls button:hover { color: var(--primary-color); }
        .playback-controls button:disabled { opacity: 0.5; cursor: not-allowed; }
        .progress-container { flex: 1; position: relative; }
        .progress-bar { width: 100%; height: 6px; background: rgba(255, 255, 255, 0.3); border-radius: 3px; cursor: pointer; position: relative; }
        .progress-fill { height: 100%; background: var(--primary-color); border-radius: 3px; width: 0%; transition: width 0.1s ease; }
        .time-display { color: #fff; font-size: 0.9rem; font-family: 'Courier New', monospace; min-width: 100px; text-align: center; }
        .recordings-list { margin-top: 1.5rem; }
        .recordings-list h3 { margin: 0 0 1rem 0; color: var(--tertiary-color); font-size: 1.25rem; font-weight: 600; }
        .recordings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem; }
        .recording-card { background: #f9f9f9; border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; cursor: pointer; transition: all 0.2s ease; }
        .recording-card:hover { box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); transform: translateY(-2px); }
        .recording-card.active { border-color: var(--primary-color); background: #f0f9f9; }
        .recording-thumbnail { width: 100%; height: 120px; background: linear-gradient(135deg, #e0e0e0 0%, #d0d0d0 100%); border-radius: 6px; margin-bottom: 0.75rem; display: flex; align-items: center; justify-content: center; color: #999; font-size: 2rem; position: relative; overflow: hidden; }
        .recording-thumbnail::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent); animation: scan 3s infinite; }
        @keyframes scan { 0% { left: -100%; } 100% { left: 100%; } }
        .recording-info { font-size: 0.9rem; }
        .recording-info p { margin: 0.25rem 0; }
        .recording-info strong { color: var(--tertiary-color); }
        .recording-duration { color: #666; font-size: 0.85rem; }
        @media (max-width: 768px) { .sidebar { width: 320px; transform: translateX(-100%); transition: transform 0.3s ease; } .sidebar.mobile-open { transform: translateX(0); } .sidebar.collapsed { width: 80px; transform: translateX(0); } .main-wrapper { margin-left: 0; } body.sidebar-collapsed .main-wrapper { margin-left: 80px; } .playback-controls-panel { flex-direction: column; } .recordings-grid { grid-template-columns: 1fr; } }
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
            <div class="nav-module active">
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
                <h1 class="page-title">Playback - CCTV Recordings</h1>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>
        <main class="content-area">
            <div class="page-content">
                <div class="playback-container">
                    <!-- Search Controls -->
                    <div class="playback-controls-panel">
                        <div class="control-group">
                            <label for="cameraSelect">Camera</label>
                            <select id="cameraSelect">
                                <option value="">All Cameras</option>
                                <option value="CAM-001">CAM-001 - Main Entrance</option>
                                <option value="CAM-002">CAM-002 - Barangay Hall</option>
                                <option value="CAM-003">CAM-003 - Community Center</option>
                            </select>
                        </div>
                        <div class="control-group">
                            <label for="dateSelect">Date</label>
                            <input type="date" id="dateSelect" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="control-group">
                            <label for="startTime">Start Time</label>
                            <input type="time" id="startTime" value="00:00">
                        </div>
                        <div class="control-group">
                            <label for="endTime">End Time</label>
                            <input type="time" id="endTime" value="23:59">
                        </div>
                        <button class="btn-search" onclick="searchRecordings()">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>

                    <!-- Video Player -->
                    <div class="video-player-container" id="videoPlayerContainer">
                        <div class="video-placeholder" id="videoPlaceholder">
                            <div class="video-info" id="videoInfo">
                                <p><strong>Camera:</strong> <span id="currentCamera">-</span></p>
                                <p><strong>Date:</strong> <span id="currentDate">-</span></p>
                                <p><strong>Time:</strong> <span id="currentTime">-</span></p>
                            </div>
                        </div>
                    </div>
                    <div class="playback-controls" id="playbackControls">
                        <button onclick="togglePlayPause()" id="playPauseBtn">
                            <i class="fas fa-play" id="playPauseIcon"></i>
                        </button>
                        <button onclick="skipBackward()">
                            <i class="fas fa-backward"></i>
                        </button>
                        <button onclick="skipForward()">
                            <i class="fas fa-forward"></i>
                        </button>
                        <div class="progress-container">
                            <div class="progress-bar" onclick="seekTo(event)" id="progressBar">
                                <div class="progress-fill" id="progressFill"></div>
                            </div>
                        </div>
                        <div class="time-display">
                            <span id="currentTimeDisplay">00:00</span> / <span id="totalTimeDisplay">00:00</span>
                        </div>
                        <button onclick="toggleFullscreen()">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>

                    <!-- Recordings List -->
                    <div class="recordings-list">
                        <h3>Available Recordings</h3>
                        <div class="recordings-grid" id="recordingsGrid">
                            <!-- Recordings will be populated here -->
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
            initializeRecordings();
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

        let recordings = [];
        let currentRecording = null;
        let isPlaying = false;
        let currentTime = 0;
        let totalTime = 0;
        let playbackInterval = null;

        function initializeRecordings() {
            recordings = [
                {
                    id: 1,
                    camera: 'CAM-001',
                    cameraName: 'Main Entrance',
                    date: '2025-01-15',
                    startTime: '08:00:00',
                    endTime: '08:30:00',
                    duration: '30:00',
                    location: 'Susano Road, Barangay San Agustin, Quezon City'
                },
                {
                    id: 2,
                    camera: 'CAM-002',
                    cameraName: 'Barangay Hall',
                    date: '2025-01-15',
                    startTime: '14:30:00',
                    endTime: '15:00:00',
                    duration: '30:00',
                    location: 'Paraiso St., Barangay San Agustin, Quezon City'
                },
                {
                    id: 3,
                    camera: 'CAM-001',
                    cameraName: 'Main Entrance',
                    date: '2025-01-15',
                    startTime: '18:00:00',
                    endTime: '18:45:00',
                    duration: '45:00',
                    location: 'Susano Road, Barangay San Agustin, Quezon City'
                },
                {
                    id: 4,
                    camera: 'CAM-003',
                    cameraName: 'Community Center',
                    date: '2025-01-14',
                    startTime: '10:00:00',
                    endTime: '10:20:00',
                    duration: '20:00',
                    location: 'Clemente St., Barangay San Agustin, Quezon City'
                },
                {
                    id: 5,
                    camera: 'CAM-002',
                    cameraName: 'Barangay Hall',
                    date: '2025-01-14',
                    startTime: '16:00:00',
                    endTime: '17:00:00',
                    duration: '60:00',
                    location: 'Paraiso St., Barangay San Agustin, Quezon City'
                },
                {
                    id: 6,
                    camera: 'CAM-001',
                    cameraName: 'Main Entrance',
                    date: '2025-01-13',
                    startTime: '12:00:00',
                    endTime: '12:30:00',
                    duration: '30:00',
                    location: 'Susano Road, Barangay San Agustin, Quezon City'
                }
            ];
            displayRecordings();
        }

        function displayRecordings() {
            const grid = document.getElementById('recordingsGrid');
            grid.innerHTML = '';
            
            recordings.forEach(recording => {
                const card = document.createElement('div');
                card.className = 'recording-card';
                card.onclick = (e) => selectRecording(recording, e);
                
                card.innerHTML = `
                    <div class="recording-thumbnail">📹</div>
                    <div class="recording-info">
                        <p><strong>${recording.camera}</strong> - ${recording.cameraName}</p>
                        <p>${recording.date} ${recording.startTime} - ${recording.endTime}</p>
                        <p class="recording-duration">Duration: ${recording.duration}</p>
                        <p style="font-size: 0.8rem; color: #666;">${recording.location}</p>
                    </div>
                `;
                
                grid.appendChild(card);
            });
        }

        function selectRecording(recording, event) {
            currentRecording = recording;
            document.querySelectorAll('.recording-card').forEach(card => {
                card.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            document.getElementById('currentCamera').textContent = `${recording.camera} - ${recording.cameraName}`;
            document.getElementById('currentDate').textContent = recording.date;
            document.getElementById('currentTime').textContent = `${recording.startTime} - ${recording.endTime}`;
            
            const [hours, minutes, seconds] = recording.duration.split(':').map(Number);
            totalTime = hours * 3600 + minutes * 60 + seconds;
            currentTime = 0;
            
            document.getElementById('totalTimeDisplay').textContent = formatTime(totalTime);
            document.getElementById('currentTimeDisplay').textContent = '00:00';
            document.getElementById('progressFill').style.width = '0%';
            
            document.getElementById('videoPlayerContainer').classList.add('active');
            document.getElementById('videoInfo').style.display = 'block';
            document.getElementById('playbackControls').classList.add('active');
            
            pausePlayback();
        }

        function togglePlayPause() {
            if (!currentRecording) return;
            
            isPlaying = !isPlaying;
            const icon = document.getElementById('playPauseIcon');
            
            if (isPlaying) {
                icon.classList.remove('fa-play');
                icon.classList.add('fa-pause');
                playbackInterval = setInterval(() => {
                    currentTime += 1;
                    updateProgress();
                    if (currentTime >= totalTime) {
                        pausePlayback();
                    }
                }, 1000);
            } else {
                pausePlayback();
            }
        }

        function pausePlayback() {
            isPlaying = false;
            const icon = document.getElementById('playPauseIcon');
            icon.classList.remove('fa-pause');
            icon.classList.add('fa-play');
            if (playbackInterval) {
                clearInterval(playbackInterval);
                playbackInterval = null;
            }
        }

        function skipBackward() {
            if (!currentRecording) return;
            currentTime = Math.max(0, currentTime - 10);
            updateProgress();
        }

        function skipForward() {
            if (!currentRecording) return;
            currentTime = Math.min(totalTime, currentTime + 10);
            updateProgress();
        }

        function seekTo(event) {
            if (!currentRecording) return;
            const progressBar = document.getElementById('progressBar');
            const rect = progressBar.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const percentage = x / rect.width;
            currentTime = Math.floor(totalTime * percentage);
            updateProgress();
        }

        function updateProgress() {
            const percentage = (currentTime / totalTime) * 100;
            document.getElementById('progressFill').style.width = percentage + '%';
            document.getElementById('currentTimeDisplay').textContent = formatTime(currentTime);
        }

        function formatTime(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            if (hours > 0) {
                return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            }
            return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }

        function toggleFullscreen() {
            const container = document.querySelector('.video-player-container');
            if (!document.fullscreenElement) {
                container.requestFullscreen().catch(err => {
                    alert('Error attempting to enable fullscreen');
                });
            } else {
                document.exitFullscreen();
            }
        }

        function searchRecordings() {
            const camera = document.getElementById('cameraSelect').value;
            const date = document.getElementById('dateSelect').value;
            const startTime = document.getElementById('startTime').value;
            const endTime = document.getElementById('endTime').value;
            
            let filtered = recordings;
            
            if (camera) {
                filtered = filtered.filter(r => r.camera === camera);
            }
            
            if (date) {
                filtered = filtered.filter(r => r.date === date);
            }
            
            if (startTime && endTime) {
                filtered = filtered.filter(r => {
                    const recStart = r.startTime.split(':').slice(0, 2).join(':');
                    const recEnd = r.endTime.split(':').slice(0, 2).join(':');
                    return recStart >= startTime && recEnd <= endTime;
                });
            }
            
            const grid = document.getElementById('recordingsGrid');
            grid.innerHTML = '';
            
            if (filtered.length === 0) {
                grid.innerHTML = '<p style="grid-column: 1 / -1; text-align: center; color: #999; padding: 2rem;">No recordings found matching your criteria.</p>';
                return;
            }
            
            filtered.forEach(recording => {
                const card = document.createElement('div');
                card.className = 'recording-card';
                card.onclick = (e) => selectRecording(recording, e);
                
                card.innerHTML = `
                    <div class="recording-thumbnail">📹</div>
                    <div class="recording-info">
                        <p><strong>${recording.camera}</strong> - ${recording.cameraName}</p>
                        <p>${recording.date} ${recording.startTime} - ${recording.endTime}</p>
                        <p class="recording-duration">Duration: ${recording.duration}</p>
                        <p style="font-size: 0.8rem; color: #666;">${recording.location}</p>
                    </div>
                `;
                
                grid.appendChild(card);
            });
        }
    </script>
</body>
</html>


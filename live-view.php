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
    <title>Live View - CCTV Monitoring</title>
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
        .live-view-container { display: flex; gap: 1.5rem; height: calc(100vh - 300px); min-height: 600px; }
        .detected-objects-panel { width: 320px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; overflow-y: auto; display: flex; flex-direction: column; flex-shrink: 0; }
        .detected-objects-panel h3 { margin: 0 0 1.5rem 0; color: var(--tertiary-color); font-size: 1.25rem; font-weight: 600; }
        .detected-objects-list { display: flex; flex-direction: column; gap: 1rem; }
        .detected-object-card { background: #f9f9f9; border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; transition: all 0.2s ease; }
        .detected-object-card:hover { box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); transform: translateY(-2px); }
        .object-image { width: 100%; height: 150px; object-fit: cover; border-radius: 6px; margin-bottom: 0.75rem; background: linear-gradient(135deg, #e0e0e0 0%, #d0d0d0 100%); display: flex; align-items: center; justify-content: center; color: #666; font-size: 3rem; border: 2px solid var(--border-color); position: relative; overflow: hidden; }
        .object-image::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent); animation: scan 3s infinite; }
        @keyframes scan { 0% { left: -100%; } 100% { left: 100%; } }
        .object-type-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.5rem; }
        .badge-person { background: #cfe2ff; color: #084298; }
        .badge-animal { background: #d1e7dd; color: #0f5132; }
        .badge-vehicle { background: #fff3cd; color: #856404; }
        .object-details { font-size: 0.9rem; line-height: 1.6; }
        .object-details p { margin: 0.25rem 0; }
        .object-details strong { color: var(--tertiary-color); }
        .live-feed-container { flex: 1; background: #000; border-radius: 12px; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; }
        .live-feed-placeholder { width: 100%; height: 100%; background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; position: relative; }
        .live-feed-placeholder::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(255, 255, 255, 0.03) 2px, rgba(255, 255, 255, 0.03) 4px); }
        .live-indicator { position: absolute; top: 1rem; left: 1rem; display: flex; align-items: center; gap: 0.5rem; background: rgba(239, 68, 68, 0.9); padding: 0.5rem 1rem; border-radius: 20px; z-index: 10; }
        .live-dot { width: 10px; height: 10px; background: #fff; border-radius: 50%; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .live-indicator span { color: #fff; font-weight: 600; font-size: 0.9rem; }
        .camera-info { position: absolute; top: 1rem; right: 1rem; background: rgba(0, 0, 0, 0.7); padding: 0.75rem 1rem; border-radius: 8px; color: #fff; z-index: 10; }
        .camera-info p { margin: 0.25rem 0; font-size: 0.9rem; }
        .datetime-display { position: absolute; bottom: 1rem; left: 1rem; background: rgba(0, 0, 0, 0.7); padding: 0.75rem 1rem; border-radius: 8px; color: #fff; z-index: 10; font-family: 'Courier New', monospace; }
        .datetime-display .date { font-size: 1rem; font-weight: 600; }
        .datetime-display .time { font-size: 1.5rem; font-weight: 700; }
        .road-simulation { position: absolute; bottom: 0; left: 0; right: 0; height: 40%; background: #333; border-top: 4px solid #ffd700; }
        .road-simulation::before { content: ''; position: absolute; top: 50%; left: 0; right: 0; height: 2px; background: repeating-linear-gradient(90deg, #ffd700 0, #ffd700 20px, transparent 20px, transparent 40px); }
        .vehicle-animation { position: absolute; bottom: 20%; width: 60px; height: 40px; background: #4a90e2; border-radius: 4px; animation: drive 10s linear infinite; }
        @keyframes drive { 0% { left: -60px; } 100% { left: 100%; } }
        .empty-state { text-align: center; padding: 2rem; color: #999; }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        @media (max-width: 1200px) {
            .live-view-container { flex-direction: column; height: auto; }
            .detected-objects-panel { width: 100%; max-height: 400px; }
        }
        @media (max-width: 768px) { .sidebar { width: 320px; transform: translateX(-100%); transition: transform 0.3s ease; } .sidebar.mobile-open { transform: translateX(0); } .sidebar.collapsed { width: 80px; transform: translateX(0); } .main-wrapper { margin-left: 0; } body.sidebar-collapsed .main-wrapper { margin-left: 80px; } }
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
                    <a href="live-view.php" class="nav-submodule active" data-tooltip="Live View">
                        <span class="nav-submodule-icon"><i class="fas fa-circle" style="color: #ff4444;"></i></span>
                        <span class="nav-submodule-text">Live View</span>
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
                <h1 class="page-title">Live View - CCTV Monitoring</h1>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>
        <main class="content-area">
            <div class="page-content">
                <div class="live-view-container">
                    <!-- Left Side (Small): Detected Objects Collage -->
                    <div class="detected-objects-panel">
                        <h3>Detected Objects</h3>
                        <div class="detected-objects-list" id="detectedObjectsList">
                            <!-- Detected objects will be populated here -->
                        </div>
                    </div>

                    <!-- Right Side (Main): Live CCTV Feed -->
                    <div class="live-feed-container">
                        <div class="live-feed-placeholder">
                            <div class="live-indicator">
                                <div class="live-dot"></div>
                                <span>LIVE</span>
                            </div>
                            <div class="camera-info">
                                <p><strong>Camera:</strong> CAM-001</p>
                                <p><strong>Location:</strong> Susano Road</p>
                                <p><strong>Barangay San Agustin, Quezon City</strong></p>
                            </div>
                            <div class="datetime-display">
                                <div class="date" id="currentDate"></div>
                                <div class="time" id="currentTime"></div>
                            </div>
                            <div class="road-simulation">
                                <div class="vehicle-animation"></div>
                            </div>
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
            updateDateTime();
            setInterval(updateDateTime, 1000);
            initializeDetectedObjects();
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
        function updateDateTime() {
            const now = new Date();
            const dateStr = now.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            const timeStr = now.toLocaleTimeString('en-US', { 
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('currentDate').textContent = dateStr;
            document.getElementById('currentTime').textContent = timeStr;
        }
        function initializeDetectedObjects() {
            const detectedObjects = [
                {
                    type: 'person',
                    image: '👨',
                    details: {
                        expression: 'calm',
                        accessories: 'eyeglasses',
                        age: 'middle-aged',
                        gender: 'male'
                    }
                },
                {
                    type: 'person',
                    image: '👩',
                    details: {
                        expression: 'happy',
                        accessories: 'cap',
                        age: 'young',
                        gender: 'female'
                    }
                },
                {
                    type: 'vehicle',
                    image: '🚗',
                    details: {
                        plateNumber: 'ABC-1234',
                        color: 'White',
                        brand: 'Toyota',
                        model: 'Vios'
                    }
                },
                {
                    type: 'animal',
                    image: '🐕',
                    details: {
                        species: 'dog'
                    }
                },
                {
                    type: 'person',
                    image: '👤',
                    details: {
                        expression: 'mysterious',
                        accessories: 'mask',
                        age: 'young',
                        gender: 'male'
                    }
                },
                {
                    type: 'vehicle',
                    image: '🏍️',
                    details: {
                        plateNumber: 'XYZ-5678',
                        color: 'Red',
                        brand: 'Honda',
                        model: 'Click'
                    }
                },
                {
                    type: 'person',
                    image: '👴',
                    details: {
                        expression: 'sad',
                        accessories: 'hat',
                        age: 'old',
                        gender: 'male'
                    }
                },
                {
                    type: 'person',
                    image: '👵',
                    details: {
                        expression: 'calm',
                        accessories: 'none',
                        age: 'old',
                        gender: 'female'
                    }
                },
                {
                    type: 'animal',
                    image: '🐈',
                    details: {
                        species: 'cat'
                    }
                }
            ];
            const container = document.getElementById('detectedObjectsList');
            detectedObjects.forEach((obj, index) => {
                const card = document.createElement('div');
                card.className = 'detected-object-card';
                
                const badgeClass = obj.type === 'person' ? 'badge-person' : 
                                  obj.type === 'animal' ? 'badge-animal' : 
                                  'badge-vehicle';
                const badgeText = obj.type.charAt(0).toUpperCase() + obj.type.slice(1);
                
                let detailsHTML = '';
                if (obj.type === 'person') {
                    detailsHTML = `
                        <p><strong>Expression:</strong> <span style="text-transform: capitalize;">${obj.details.expression}</span></p>
                        <p><strong>Accessories:</strong> <span style="text-transform: capitalize;">${obj.details.accessories}</span></p>
                        <p><strong>Age:</strong> <span style="text-transform: capitalize;">${obj.details.age}</span></p>
                        <p><strong>Gender:</strong> <span style="text-transform: capitalize;">${obj.details.gender}</span></p>
                    `;
                } else if (obj.type === 'animal') {
                    detailsHTML = `
                        <p><strong>Species:</strong> <span style="text-transform: capitalize;">${obj.details.species}</span></p>
                    `;
                } else if (obj.type === 'vehicle') {
                    detailsHTML = `
                        <p><strong>Plate Number:</strong> ${obj.details.plateNumber}</p>
                        <p><strong>Color:</strong> ${obj.details.color}</p>
                        <p><strong>Brand:</strong> ${obj.details.brand}</p>
                        <p><strong>Model:</strong> ${obj.details.model}</p>
                    `;
                }
                
                card.innerHTML = `
                    <span class="object-type-badge ${badgeClass}">${badgeText}</span>
                    <div class="object-image">${obj.image}</div>
                    <div class="object-details">
                        ${detailsHTML}
                    </div>
                `;
                
                container.appendChild(card);
            });
        }
    </script>
</body>
</html>


<?php
session_start();

// Check if user is logged in, redirect to login if not
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
    <title>Dashboard</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: var(--font-family);
            background-color: var(--bg-color);
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Navigation */
        .sidebar {
            width: 280px;
            background: var(--tertiary-color);
            color: #fff;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 1.5rem 1rem 1rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }
        
        .sidebar-header img {
            height: 90px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
        }
        
        .sidebar-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: #fff;
            text-align: center;
        }
        
        .sidebar-nav {
            padding: 0;
        }
        
        .nav-module {
            margin-bottom: 0.25rem;
        }
        
        .nav-module-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
            user-select: none;
        }
        
        .nav-module-header:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
        }
        
        .nav-module-header .arrow {
            font-size: 0.75rem;
            transition: transform 0.3s ease;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .nav-module.active .nav-module-header .arrow {
            transform: rotate(90deg);
        }
        
        .nav-module.active .nav-module-header {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }
        
        .nav-submodules {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: rgba(0, 0, 0, 0.2);
        }
        
        .nav-module.active .nav-submodules {
            max-height: 500px;
        }
        
        .nav-submodule {
            padding: 0.75rem 1.5rem 0.75rem 3rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            display: block;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }
        
        .nav-submodule:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            padding-left: 3.5rem;
        }
        
        .nav-submodule.active {
            background: rgba(76, 138, 137, 0.2);
            color: var(--primary-color);
            border-left: 3px solid var(--primary-color);
        }
        
        /* Main Content Area */
        .main-wrapper {
            margin-left: 280px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .top-header {
            background: var(--header-bg);
            padding: 1.5rem 2rem 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--border-color);
        }
        
        .top-header-content {
            flex: 1;
            padding-bottom: 0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding-bottom: 0;
            margin-left: 2rem;
        }
        
        .user-info span {
            color: var(--text-color);
            font-weight: 500;
        }
        
        .logout-btn {
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: background 0.2s ease;
        }
        
        .logout-btn:hover {
            background: #4ca8a6;
        }
        
        .content-area {
            padding: 2rem;
            flex: 1;
            background: #ffffff;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--tertiary-color);
            margin: 0;
        }
        
        .page-content {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px var(--shadow);
            margin-top: 1.5rem;
        }
        
        .page-content h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--tertiary-color);
            margin: 0 0 0.75rem 0;
        }
        
        .page-content p {
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-wrapper {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="images/logo.svg" alt="Logo">
        </div>
        <nav class="sidebar-nav">
            <div class="nav-module">
                <div class="nav-module-header" onclick="toggleModule(this)">
                    <span>Neighborhood Watch Coordination</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="#" class="nav-submodule">Member List</a>
                    <a href="#" class="nav-submodule">Activity Logs</a>
                </div>
            </div>
            
            <div class="nav-module">
                <div class="nav-module-header" onclick="toggleModule(this)">
                    <span>CCTV Surveillance System Management</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="#" class="nav-submodule">Live View</a>
                    <a href="#" class="nav-submodule">Playback</a>
                </div>
            </div>
            
            <div class="nav-module">
                <div class="nav-module-header" onclick="toggleModule(this)">
                    <span>Community Complaint Logging and Resolution</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="#" class="nav-submodule">Submit Complaint</a>
                    <a href="#" class="nav-submodule">Track Complaint</a>
                </div>
            </div>
            
            <div class="nav-module">
                <div class="nav-module-header" onclick="toggleModule(this)">
                    <span>Volunteer Registry and Scheduling</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="#" class="nav-submodule">Volunteer List</a>
                    <a href="#" class="nav-submodule">Schedule Management</a>
                </div>
            </div>
            
            <div class="nav-module">
                <div class="nav-module-header" onclick="toggleModule(this)">
                    <span>Patrol Scheduling and Monitoring</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="#" class="nav-submodule">Patrol Schedule</a>
                    <a href="#" class="nav-submodule">Patrol Logs</a>
                </div>
            </div>
            
            <div class="nav-module">
                <div class="nav-module-header" onclick="toggleModule(this)">
                    <span>Awareness and Outreach Event Tracking</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="#" class="nav-submodule">Event List</a>
                    <a href="#" class="nav-submodule">Event Reports</a>
                </div>
            </div>
            
            <div class="nav-module">
                <div class="nav-module-header" onclick="toggleModule(this)">
                    <span>Anonymous Tip Line System</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="#" class="nav-submodule">Submit Tip</a>
                    <a href="#" class="nav-submodule">Review Tip</a>
                </div>
            </div>
        </nav>
    </aside>
    
    <!-- Main Content Area -->
    <div class="main-wrapper">
        <header class="top-header">
            <div class="top-header-content">
                <h1 class="page-title">Dashboard</h1>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>
        
        <main class="content-area">
            <div class="page-content">
                <h2>Welcome to Alertara QC Dashboard</h2>
                <p>Select a module from the sidebar to get started.</p>
            </div>
        </main>
    </div>
    
    <script>
        function toggleModule(element) {
            const module = element.closest('.nav-module');
            const isActive = module.classList.contains('active');
            
            // Close all modules
            document.querySelectorAll('.nav-module').forEach(m => {
                m.classList.remove('active');
            });
            
            // Open clicked module if it wasn't active
            if (!isActive) {
                module.classList.add('active');
            }
        }
    </script>
</body>
</html>


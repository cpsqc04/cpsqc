<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api/bulletin_board_schema.php';

if ($pdo instanceof PDO) {
    try {
        ensureBulletinBoardTable($pdo);
        archiveExpiredBulletinPosts($pdo);
    } catch (PDOException $e) {
        error_log('Bulletin board setup failed: ' . $e->getMessage());
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Bulletin Board - Alertara</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/admin-sidebar.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: var(--font-family);
            background-color: var(--bg-color);
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Navigation - Same as users.php */
        .sidebar {
            width: 320px;
            background: var(--tertiary-color);
            color: #fff;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow: hidden;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: width 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar.collapsed {
            width: 80px;
        }
        
        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            min-height: 160px;
        }
        
        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .logo-container a {
            text-decoration: none;
            display: block;
            transition: all 0.3s ease;
        }
        
        .logo-container a:hover {
            opacity: 0.8;
            transform: scale(1.05);
        }
        
        .logo-container img {
            height: 130px;
            width: 130px;
            object-fit: contain;
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed .logo-container img {
            height: 70px;
            width: 70px;
        }
        
        .user-name-display {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
            font-weight: 500;
            text-align: center;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
            word-break: break-word;
            max-width: 100%;
        }
        
        .sidebar.collapsed .user-name-display {
            opacity: 0;
            height: 0;
            padding: 0;
            overflow: hidden;
            font-size: 0;
        }
        
        .sidebar-nav {
            padding: 0.5rem 0;
            overflow-y: auto;
            overflow-x: hidden;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
        }
        
        .sidebar-nav::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar-nav::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }
        
        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
        
        .sidebar.collapsed .sidebar-nav {
            overflow-y: auto;
            overflow-x: hidden;
            display: flex !important;
            flex-direction: column;
            padding: 0.5rem 0;
            position: relative;
        }
        
        .nav-module-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            cursor: pointer;
            transition: background-color 0.2s ease, padding 0.3s ease;
            font-weight: 500;
            user-select: none;
            white-space: normal;
            overflow: visible;
            font-size: 0.9rem;
            position: relative;
            gap: 0.75rem;
            line-height: 1.4;
        }
        
        .sidebar.collapsed .nav-module-header {
            padding: 0.75rem;
            justify-content: center;
            min-height: 48px;
            max-height: 48px;
            display: flex !important;
            visibility: visible !important;
            cursor: pointer;
            margin: 0.25rem 0.5rem;
            border-radius: 8px;
            position: relative;
        }
        
        .nav-module-header:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }
        
        .nav-module-header.active {
            background: rgba(76, 138, 137, 0.25);
            border-left: 3px solid #4c8a89;
        }
        
        .nav-module-icon {
            font-size: 1.4rem;
            width: 28px;
            height: 28px;
            display: flex !important;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: font-size 0.3s ease;
            opacity: 1 !important;
            visibility: visible !important;
            position: relative;
        }
        
        .nav-module-icon i {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .sidebar.collapsed .nav-module-icon {
            font-size: 1.5rem;
            width: auto;
            height: auto;
            margin: 0;
            padding: 0;
            display: flex !important;
            opacity: 1 !important;
            visibility: visible !important;
            position: relative;
            transform: none;
        }
        
        .sidebar.collapsed .nav-module-icon i {
            font-size: 1.3rem;
        }
        
        .nav-module-header-text {
            flex: 1;
            transition: opacity 0.3s ease;
            opacity: 1;
            word-wrap: break-word;
            overflow-wrap: break-word;
            min-width: 0;
        }
        
        .sidebar.collapsed .nav-module-header-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }
        
        .nav-module {
            margin-bottom: 0.125rem;
        }
        
        .nav-module-header .arrow {
            font-size: 0.7rem;
            transition: transform 0.3s ease, opacity 0.3s ease;
            color: rgba(255, 255, 255, 0.6);
            flex-shrink: 0;
            margin-left: 0.5rem;
        }
        
        .nav-module.active .nav-module-header .arrow {
            transform: rotate(90deg);
        }
        
        .nav-module.active .nav-module-header {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        
        .nav-submodules {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: rgba(0, 0, 0, 0.15);
        }
        
        .nav-module.active .nav-submodules {
            max-height: 500px;
        }
        
        .nav-submodule {
            padding: 0.75rem 1.5rem 0.75rem 3.5rem;
            color: rgba(255, 255, 255, 0.75);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s ease;
            font-size: 0.85rem;
            white-space: nowrap;
            overflow: hidden;
            position: relative;
        }
        
        .nav-submodule:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            padding-left: 4rem;
        }
        
        .nav-submodule.active {
            background: rgba(76, 138, 137, 0.25);
            color: #4c8a89;
            border-left: 3px solid #4c8a89;
            font-weight: 500;
        }
        
        .nav-submodule-icon {
            font-size: 1.1rem;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .nav-submodule-icon i {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.75);
        }
        
        .nav-submodule-text {
            flex: 1;
        }
        
        .main-wrapper {
            margin-left: 320px;
            flex: 1;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease;
        }
        
        body.sidebar-collapsed .main-wrapper {
            margin-left: 80px;
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
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-left: 2rem;
        }
        
        .user-info span {
            color: var(--text-color);
            font-weight: 500;
        }
        
        /* Notification Bell */
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
        .notification-item.unread::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 6px; height: 6px; background: var(--primary-color); border-radius: 50%; }
        .notification-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .notification-icon.complaint { background: #fee2e2; color: #dc2626; }
        .notification-icon.tip { background: #fef3c7; color: #d97706; }
        .notification-icon.volunteer { background: #dbeafe; color: #2563eb; }
        .notification-icon.event { background: #d1fae5; color: #059669; }
        .notification-content { flex: 1; min-width: 0; }
        .notification-title { font-weight: 600; color: var(--text-color); font-size: 0.95rem; margin: 0 0 0.25rem 0; }
        .notification-message { color: var(--text-secondary); font-size: 0.85rem; margin: 0 0 0.5rem 0; line-height: 1.4; }
        .notification-time { color: var(--text-secondary); font-size: 0.75rem; }
        .notification-empty { padding: 3rem 1.5rem; text-align: center; color: var(--text-secondary); }
        .notification-empty i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.3; }
        .datetime-display { display: flex; align-items: center; gap: 0.75rem; color: var(--text-color); font-size: 0.9rem; font-weight: 500; margin-right: 1rem; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .datetime-display .date-part { color: var(--text-secondary); }
        .datetime-display .time-part { color: var(--text-color); font-weight: 600; }
        
        /* Sidebar Logout Button */
        .sidebar-footer {
            margin-top: auto;
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-logout-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.5rem;
            background: rgba(239, 68, 68, 0.1);
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border: 1px solid rgba(239, 68, 68, 0.2);
            width: 100%;
            box-sizing: border-box;
        }
        
        .sidebar-logout-btn:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.4);
            color: #fff;
        }
        
        .sidebar-logout-btn i {
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        
        .sidebar-logout-btn span {
            flex: 1;
            transition: opacity 0.3s ease;
        }
        
        .sidebar.collapsed .sidebar-logout-btn span {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }
        
        .sidebar.collapsed .sidebar-logout-btn {
            justify-content: center;
            padding: 0.875rem;
        }
        
        .content-burger-btn {
            background: transparent;
            border: none;
            color: var(--tertiary-color);
            width: 40px;
            height: 40px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
            padding: 0;
        }
        
        .content-burger-btn:hover {
            background: rgba(28, 37, 65, 0.05);
        }
        
        .content-burger-btn span {
            display: block;
            width: 22px;
            height: 1.5px;
            background: var(--tertiary-color);
            position: relative;
            transition: all 0.3s ease;
        }
        
        .content-burger-btn span::before,
        .content-burger-btn span::after {
            content: '';
            position: absolute;
            width: 22px;
            height: 1.5px;
            background: var(--tertiary-color);
            transition: all 0.3s ease;
        }
        
        .content-burger-btn span::before {
            top: -7px;
        }
        
        .content-burger-btn span::after {
            bottom: -7px;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--tertiary-color);
            margin: 0;
        }
        
        .content-area {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            background: #f5f5f5;
        }
        
        .page-content {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px var(--shadow);
            margin-top: 1.5rem;
        }
        
        .table-container {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: var(--tertiary-color);
            color: #fff;
        }
        
        th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        td {
            padding: 1rem;
            border-top: 1px solid var(--border-color);
            color: var(--text-color);
        }
        
        tbody tr:hover {
            background: rgba(76, 138, 137, 0.05);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-locked {
            background: #fef3c7;
            color: #92400e;
        }

        .bb-layout { display: grid; grid-template-columns: minmax(280px, 380px) 1fr; gap: 1.5rem; align-items: start; }
        .bb-form-card, .bb-list-card { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem; box-shadow: 0 2px 8px var(--shadow); }
        .bb-form-card h2, .bb-list-card h2 { margin: 0 0 1rem; font-size: 1.15rem; color: var(--tertiary-color); }
        .bb-form-group { margin-bottom: 1rem; }
        .bb-form-group label { display: block; margin-bottom: 0.4rem; font-weight: 600; font-size: 0.9rem; color: var(--text-color); }
        .bb-form-group input[type="text"],
        .bb-form-group input[type="datetime-local"],
        .bb-form-group select,
        .bb-form-group textarea { width: 100%; padding: 0.65rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font: inherit; box-sizing: border-box; }
        .bb-form-group textarea { min-height: 100px; resize: vertical; }
        .bb-form-group .hint { font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.3rem; }
        .bb-check { display: flex; align-items: center; gap: 0.5rem; font-weight: 500; }
        .bb-actions { display: flex; gap: 0.6rem; flex-wrap: wrap; margin-top: 0.5rem; }
        .bb-btn { border: none; border-radius: 8px; padding: 0.65rem 1rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; }
        .bb-btn-primary { background: var(--primary-color); color: #fff; }
        .bb-btn-secondary { background: #e2e8f0; color: #334155; }
        .bb-btn-danger { background: #fee2e2; color: #991b1b; }
        .bb-btn-sm { padding: 0.4rem 0.65rem; font-size: 0.8rem; }
        .bb-alert { display: none; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .bb-alert.show { display: block; }
        .bb-alert.success { background: #d1fae5; color: #065f46; }
        .bb-alert.error { background: #fee2e2; color: #991b1b; }
        .bb-post { border: 1px solid var(--border-color); border-radius: 10px; padding: 1rem; margin-bottom: 0.85rem; }
        .bb-post.pinned { border-color: #4c8a89; }
        .bb-post-top { display: flex; justify-content: space-between; gap: 0.75rem; align-items: flex-start; margin-bottom: 0.5rem; }
        .bb-post h3 { margin: 0; font-size: 1rem; color: var(--tertiary-color); }
        .bb-meta { font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.45rem; display: flex; flex-wrap: wrap; gap: 0.5rem 0.85rem; }
        .bb-chip { display: inline-flex; align-items: center; gap: 0.25rem; background: rgba(76,138,137,0.12); color: #2f6b6a; padding: 0.15rem 0.45rem; border-radius: 4px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; }
        .bb-chip.archived { background: #f1f5f9; color: #64748b; }
        .bb-body { margin: 0; white-space: pre-wrap; color: var(--text-color); line-height: 1.5; font-size: 0.92rem; }
        .bb-thumbs { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.65rem; }
        .bb-thumbs img { width: 72px; height: 72px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color); }
        .bb-files { margin-top: 0.5rem; font-size: 0.85rem; }
        .bb-files a { color: #2f6b6a; margin-right: 0.75rem; }
        .bb-empty { text-align: center; padding: 2rem; color: var(--text-secondary); }
        .bb-filters { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .bb-filters select { padding: 0.5rem 0.65rem; border-radius: 8px; border: 1px solid var(--border-color); }
        @media (max-width: 960px) { .bb-layout { grid-template-columns: 1fr; } }
    </style>
    <link rel="stylesheet" href="css/mobile-responsive.css">
</head>
<body>
    <!-- Sidebar -->
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
            
            <!-- User Management Module (Admin Only) -->
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
            
            <div class="nav-module">
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
        
        <!-- Sidebar Footer with Logout -->
        <div class="sidebar-footer">
            <a href="logout.php" class="sidebar-logout-btn" data-tooltip="Logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>
    
    <!-- Main Content -->
    <div class="main-wrapper">
        <header class="top-header">
            <div class="top-header-content">
                <button class="content-burger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
                    <span></span>
                </button>
                <h1 class="page-title">Bulletin Board</h1>
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
                <div id="bbAlert" class="bb-alert"></div>
                <div class="bb-layout">
                    <div class="bb-form-card">
                        <h2 id="bbFormTitle"><i class="fas fa-plus-circle"></i> New Announcement</h2>
                        <form id="bbForm" enctype="multipart/form-data">
                            <input type="hidden" id="bbPostId" value="">
                            <div class="bb-form-group">
                                <label for="bbTitle">Title *</label>
                                <input type="text" id="bbTitle" required maxlength="255" placeholder="Announcement title">
                            </div>
                            <div class="bb-form-group">
                                <label for="bbBody">Message</label>
                                <textarea id="bbBody" placeholder="Write the announcement details..."></textarea>
                            </div>
                            <div class="bb-form-group">
                                <label for="bbAudience">Target Audience *</label>
                                <select id="bbAudience" required>
                                    <option value="all">All</option>
                                    <option value="patrol">Patrol</option>
                                    <option value="watcher">Watcher</option>
                                </select>
                                <div class="hint">Choose All to publish this announcement to Patrol and Watcher portals.</div>
                            </div>
                            <div class="bb-form-group">
                                <label for="bbMedia">Media &amp; Images</label>
                                <input type="file" id="bbMedia" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                                <div class="hint">Images appear in the Digital Bulletin slideshow.</div>
                                <div id="bbExistingMedia" class="bb-thumbs"></div>
                            </div>
                            <div class="bb-form-group">
                                <label for="bbLinkUrl">Image Link URL</label>
                                <input type="url" id="bbLinkUrl" maxlength="500" placeholder="https://example.com">
                                <div class="hint">Optional. If set, clicking the announcement image opens this webpage.</div>
                            </div>
                            <div class="bb-form-group">
                                <label for="bbAttachments">File Attachments</label>
                                <input type="file" id="bbAttachments" multiple>
                                <div class="hint">PDF, Office docs, images, or zip (max 12 MB each).</div>
                                <div id="bbExistingAttachments" class="bb-files"></div>
                            </div>
                            <div class="bb-form-group">
                                <label for="bbPublishAt">Schedule (Publish At)</label>
                                <input type="datetime-local" id="bbPublishAt">
                                <div class="hint">Leave blank to publish immediately.</div>
                            </div>
                            <div class="bb-form-group">
                                <label for="bbExpiresAt">Expiration Date</label>
                                <input type="datetime-local" id="bbExpiresAt">
                                <div class="hint">Expired posts are archived automatically so feeds stay clear.</div>
                            </div>
                            <div class="bb-form-group">
                                <label class="bb-check">
                                    <input type="checkbox" id="bbPinned">
                                    Pin announcement at the top of the feed
                                </label>
                            </div>
                            <div class="bb-form-group">
                                <label for="bbStatus">Status</label>
                                <select id="bbStatus">
                                    <option value="active">Active</option>
                                    <option value="draft">Draft</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
                            <div class="bb-actions">
                                <button type="submit" class="bb-btn bb-btn-primary" id="bbSubmitBtn"><i class="fas fa-save"></i> Publish</button>
                                <button type="button" class="bb-btn bb-btn-secondary" onclick="resetBbForm()"><i class="fas fa-times"></i> Clear</button>
                            </div>
                        </form>
                    </div>
                    <div class="bb-list-card">
                        <h2><i class="fas fa-bullhorn"></i> Posted Announcements</h2>
                        <div class="bb-filters">
                            <select id="bbFilterAudience" onchange="loadBulletinPosts()">
                                <option value="">All audiences</option>
                                <option value="all">Target: All</option>
                                <option value="patrol">Patrol</option>
                                <option value="watcher">Watcher</option>
                            </select>
                            <select id="bbFilterStatus" onchange="loadBulletinPosts()">
                                <option value="all">All statuses</option>
                                <option value="active">Active</option>
                                <option value="draft">Draft</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div id="bbPostsList"><div class="bb-empty">Loading...</div></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        let existingMedia = [];
        let existingAttachments = [];
        let postsById = {};

        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.remove('collapsed');
            document.body.classList.remove('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', 'false');
            loadBulletinPosts();
            document.getElementById('bbForm').addEventListener('submit', saveBulletinPost);
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
                document.querySelectorAll('.nav-module').forEach(m => m.classList.remove('active'));
                module.classList.add('active');
                const firstSubmodule = module.querySelector('.nav-submodule');
                if (firstSubmodule && firstSubmodule.href && firstSubmodule.href !== '#') {
                    window.location.href = firstSubmodule.href;
                }
                return;
            }
            document.querySelectorAll('.nav-module').forEach(m => m.classList.remove('active'));
            if (!isActive) module.classList.add('active');
        }

        function showBbAlert(message, type) {
            const el = document.getElementById('bbAlert');
            el.textContent = message;
            el.className = 'bb-alert show ' + type;
            setTimeout(() => el.classList.remove('show'), 4000);
        }

        function escapeHtml(str) {
            return String(str == null ? '' : str)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function toLocalInputValue(dt) {
            if (!dt) return '';
            const d = new Date(String(dt).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return '';
            const pad = n => String(n).padStart(2, '0');
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        }

        function fileName(path) {
            const parts = String(path).split('/');
            return parts[parts.length - 1] || path;
        }

        function renderExistingMedia() {
            const box = document.getElementById('bbExistingMedia');
            box.innerHTML = existingMedia.map((src, i) =>
                `<div style="position:relative;display:inline-block;">
                    <img src="${escapeHtml(src)}" alt="Media">
                    <button type="button" class="bb-btn bb-btn-danger bb-btn-sm" style="position:absolute;top:2px;right:2px;padding:0.15rem 0.35rem;" onclick="removeExistingMedia(${i})" title="Remove">&times;</button>
                </div>`
            ).join('');
        }

        function renderExistingAttachments() {
            const box = document.getElementById('bbExistingAttachments');
            box.innerHTML = existingAttachments.map((path, i) =>
                `<span><a href="${escapeHtml(path)}" target="_blank" rel="noopener">${escapeHtml(fileName(path))}</a>
                <button type="button" class="bb-btn bb-btn-danger bb-btn-sm" onclick="removeExistingAttachment(${i})">Remove</button></span>`
            ).join(' ');
        }

        function removeExistingMedia(i) {
            existingMedia.splice(i, 1);
            renderExistingMedia();
        }

        function removeExistingAttachment(i) {
            existingAttachments.splice(i, 1);
            renderExistingAttachments();
        }

        function resetBbForm() {
            document.getElementById('bbForm').reset();
            document.getElementById('bbPostId').value = '';
            document.getElementById('bbFormTitle').innerHTML = '<i class="fas fa-plus-circle"></i> New Announcement';
            document.getElementById('bbSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Publish';
            existingMedia = [];
            existingAttachments = [];
            renderExistingMedia();
            renderExistingAttachments();
        }

        async function loadBulletinPosts() {
            const audience = document.getElementById('bbFilterAudience').value;
            const status = document.getElementById('bbFilterStatus').value;
            let url = 'api/bulletin_board.php?status=' + encodeURIComponent(status || 'all');
            if (audience) url += '&audience=' + encodeURIComponent(audience);
            const list = document.getElementById('bbPostsList');
            list.innerHTML = '<div class="bb-empty">Loading...</div>';
            try {
                const res = await fetch(url, { credentials: 'same-origin' });
                const json = await res.json();
                if (!json.success) throw new Error(json.message || 'Failed to load');
                const posts = json.data || [];
                postsById = {};
                posts.forEach(p => { postsById[p.id] = p; });
                if (!posts.length) {
                    list.innerHTML = '<div class="bb-empty">No announcements yet.</div>';
                    return;
                }
                list.innerHTML = posts.map(post => {
                    const thumbs = (post.media || []).map(src => `<img src="${escapeHtml(src)}" alt="">`).join('');
                    const files = (post.attachments || []).map(path =>
                        `<a href="${escapeHtml(path)}" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> ${escapeHtml(fileName(path))}</a>`
                    ).join('');
                    return `<article class="bb-post${post.is_pinned ? ' pinned' : ''}">
                        <div class="bb-post-top">
                            <h3>${escapeHtml(post.title)}</h3>
                            <div class="bb-actions">
                                <button type="button" class="bb-btn bb-btn-secondary bb-btn-sm" onclick="editBulletinPost(${post.id})"><i class="fas fa-edit"></i> Edit</button>
                                <button type="button" class="bb-btn bb-btn-danger bb-btn-sm" onclick="deleteBulletinPost(${post.id})"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <div class="bb-meta">
                            <span class="bb-chip">${escapeHtml(post.target_audience)}</span>
                            <span class="bb-chip${post.status === 'archived' ? ' archived' : ''}">${escapeHtml(post.status)}</span>
                            ${post.is_pinned ? '<span class="bb-chip"><i class="fas fa-thumbtack"></i> Pinned</span>' : ''}
                            <span>Publish: ${escapeHtml(post.publish_at || post.created_at || '—')}</span>
                            <span>Expires: ${escapeHtml(post.expires_at || 'Never')}</span>
                        </div>
                        ${post.body ? `<p class="bb-body">${escapeHtml(post.body)}</p>` : ''}
                        ${thumbs ? `<div class="bb-thumbs">${thumbs}</div>` : ''}
                        ${files ? `<div class="bb-files">${files}</div>` : ''}
                    </article>`;
                }).join('');
            } catch (err) {
                list.innerHTML = `<div class="bb-empty">${escapeHtml(err.message)}</div>`;
            }
        }

        function editBulletinPost(id) {
            const post = postsById[id];
            if (!post) return;
            document.getElementById('bbPostId').value = post.id;
            document.getElementById('bbTitle').value = post.title || '';
            document.getElementById('bbBody').value = post.body || '';
            document.getElementById('bbAudience').value = (post.target_audience === 'resident' ? 'all' : (post.target_audience || 'all'));
            document.getElementById('bbPublishAt').value = toLocalInputValue(post.publish_at);
            document.getElementById('bbExpiresAt').value = toLocalInputValue(post.expires_at);
            document.getElementById('bbPinned').checked = !!post.is_pinned;
            document.getElementById('bbStatus').value = post.status || 'active';
            document.getElementById('bbLinkUrl').value = post.link_url || '';
            document.getElementById('bbMedia').value = '';
            document.getElementById('bbAttachments').value = '';
            existingMedia = Array.isArray(post.media) ? post.media.slice() : [];
            existingAttachments = Array.isArray(post.attachments) ? post.attachments.slice() : [];
            renderExistingMedia();
            renderExistingAttachments();
            document.getElementById('bbFormTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Announcement';
            document.getElementById('bbSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Update';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        async function deleteBulletinPost(id) {
            if (!confirm('Delete this announcement?')) return;
            try {
                const res = await fetch('api/bulletin_board.php?id=' + encodeURIComponent(id), {
                    method: 'DELETE',
                    credentials: 'same-origin'
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.message || 'Delete failed');
                showBbAlert('Announcement deleted.', 'success');
                if (String(document.getElementById('bbPostId').value) === String(id)) resetBbForm();
                loadBulletinPosts();
            } catch (err) {
                showBbAlert(err.message, 'error');
            }
        }

        async function saveBulletinPost(e) {
            e.preventDefault();
            const id = document.getElementById('bbPostId').value;
            const formData = new FormData();
            if (id) formData.append('id', id);
            formData.append('title', document.getElementById('bbTitle').value.trim());
            formData.append('body', document.getElementById('bbBody').value.trim());
            const audienceValue = document.getElementById('bbAudience').value || 'all';
            formData.append('target_audience', audienceValue);
            formData.append('publish_at', document.getElementById('bbPublishAt').value);
            formData.append('expires_at', document.getElementById('bbExpiresAt').value);
            formData.append('is_pinned', document.getElementById('bbPinned').checked ? '1' : '0');
            formData.append('status', document.getElementById('bbStatus').value);
            formData.append('link_url', document.getElementById('bbLinkUrl').value.trim());
            formData.append('existing_media', JSON.stringify(existingMedia));
            formData.append('existing_attachments', JSON.stringify(existingAttachments));

            const mediaFiles = document.getElementById('bbMedia').files;
            for (let i = 0; i < mediaFiles.length; i++) formData.append('media[]', mediaFiles[i]);
            const attachmentFiles = document.getElementById('bbAttachments').files;
            for (let i = 0; i < attachmentFiles.length; i++) formData.append('attachments[]', attachmentFiles[i]);

            try {
                const res = await fetch('api/bulletin_board.php', {
                    method: id ? 'POST' : 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                // PHP may not parse multipart on PUT; use POST with id for updates
                const json = await res.json();
                if (!json.success) throw new Error(json.message || 'Save failed');
                const savedAudience = json.data?.target_audience || audienceValue;
                const audienceLabel = savedAudience === 'all'
                    ? 'All'
                    : savedAudience;
                showBbAlert((json.message || 'Saved.') + ' Audience: ' + audienceLabel, 'success');
                resetBbForm();
                loadBulletinPosts();
            } catch (err) {
                showBbAlert(err.message, 'error');
            }
        }

        function updateDateTime() {
            const now = new Date();
            const dateOptions = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', timeZone: 'Asia/Manila' };
            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true, timeZone: 'Asia/Manila' };
            const dateEl = document.getElementById('currentDate');
            const timeEl = document.getElementById('currentTime');
            if (dateEl) dateEl.textContent = now.toLocaleDateString('en-US', dateOptions);
            if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US', timeOptions);
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);
    </script>
    <?php require __DIR__ . '/includes/admin_notifications_script.php'; ?>
    <script src="js/mobile-shell.js"></script>
</body>
</html>


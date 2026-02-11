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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        .sidebar::-webkit-scrollbar {
            display: none;
        }
        
        .sidebar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        .sidebar.collapsed {
            overflow: visible;
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
        
        .burger-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #fff;
            width: 36px;
            height: 36px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            z-index: 10;
        }
        
        .burger-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }
        
        .burger-btn span {
            display: block;
            width: 18px;
            height: 2px;
            background: #fff;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .burger-btn span::before,
        .burger-btn span::after {
            content: '';
            position: absolute;
            width: 18px;
            height: 2px;
            background: #fff;
            transition: all 0.3s ease;
        }
        
        .burger-btn span::before {
            top: -6px;
        }
        
        .burger-btn span::after {
            bottom: -6px;
        }
        
        .sidebar.collapsed .burger-btn span {
            background: #fff;
        }
        
        .sidebar.collapsed .burger-btn span::before {
            top: -6px;
            transform: rotate(0deg);
        }
        
        .sidebar.collapsed .burger-btn span::after {
            bottom: -6px;
            transform: rotate(0deg);
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
        
        .sidebar-header img {
            display: block;
        }
        
        .sidebar-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: #fff;
            text-align: center;
        }
        
        .sidebar-nav {
            padding: 0.5rem 0;
            overflow: visible;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        
        .sidebar.collapsed .sidebar-nav {
            overflow: visible;
            display: flex !important;
            flex-direction: column;
            padding: 0.5rem 0;
            position: relative;
        }
        
        .nav-module {
            margin-bottom: 0.125rem;
            display: block !important;
            visibility: visible !important;
            position: relative;
        }
        
        .sidebar.collapsed .nav-module {
            display: block !important;
            visibility: visible !important;
            margin-bottom: 0.25rem;
            position: relative;
            height: auto;
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
        
        .sidebar.collapsed .nav-module-header:hover {
            background: rgba(255, 255, 255, 0.1);
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
        
        .nav-module-header:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }
        
        .nav-module-header .arrow {
            font-size: 0.7rem;
            transition: transform 0.3s ease, opacity 0.3s ease;
            color: rgba(255, 255, 255, 0.6);
            flex-shrink: 0;
            margin-left: 0.5rem;
        }
        
        .sidebar.collapsed .nav-module-header .arrow {
            opacity: 0;
            width: 0;
            overflow: hidden;
            margin: 0;
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
        
        .sidebar.collapsed .nav-submodules {
            display: none !important;
        }
        
        .sidebar.collapsed .nav-module.active .nav-submodules {
            display: none !important;
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
        
        
        
        .sidebar.collapsed .nav-submodule {
            padding: 0.75rem;
            justify-content: center;
            min-height: 44px;
        }
        
        .nav-submodule-icon {
            font-size: 1.1rem;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s ease;
            opacity: 1;
        }
        
        .nav-submodule-icon i {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.75);
        }
        
        .sidebar.collapsed .nav-submodule-icon {
            font-size: 1.4rem;
            width: auto;
            height: auto;
            margin: 0;
            display: flex !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        .sidebar.collapsed .nav-submodule-icon i {
            font-size: 1.2rem;
        }
        
        .nav-submodule-text {
            flex: 1;
            transition: opacity 0.3s ease;
            opacity: 1;
        }
        
        .sidebar.collapsed .nav-submodule-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
            display: none;
        }
        
        /* Tooltip for collapsed sidebar */
        .sidebar.collapsed .nav-module-header::after,
        .sidebar.collapsed .nav-submodule::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.9);
            color: #fff;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            margin-left: 0.75rem;
            z-index: 2000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        .sidebar.collapsed .nav-module-header::before,
        .sidebar.collapsed .nav-submodule::before {
            content: '';
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            border: 6px solid transparent;
            border-right-color: rgba(0, 0, 0, 0.9);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            margin-left: 0.5rem;
            z-index: 2001;
        }
        
        .sidebar.collapsed .nav-module-header:hover::after,
        .sidebar.collapsed .nav-submodule:hover::after {
            opacity: 1;
        }
        
        .sidebar.collapsed .nav-module-header:hover::before,
        .sidebar.collapsed .nav-submodule:hover::before {
            opacity: 1;
        }
        
        .nav-submodule:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            padding-left: 4rem;
        }
        
        .sidebar.collapsed .nav-submodule:hover {
            padding-left: 1rem;
        }
        
        .nav-submodule.active {
            background: rgba(76, 138, 137, 0.25);
            color: #4c8a89;
            border-left: 3px solid #4c8a89;
            font-weight: 500;
        }
        
        .sidebar.collapsed .nav-submodule.active {
            border-left: none;
            border-top: 3px solid #4c8a89;
        }
        
        /* Improved collapsed sidebar styling */
        .sidebar.collapsed {
            width: 80px;
        }
        
        .sidebar.collapsed .nav-module {
            margin-bottom: 0.25rem;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            height: auto !important;
            position: relative;
        }
        
        .sidebar.collapsed .nav-module-header {
            border-radius: 8px;
            margin: 0.25rem 0.5rem;
            padding: 0.75rem;
            min-height: 48px;
            max-height: 48px;
            cursor: pointer;
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            justify-content: center;
            align-items: center;
            position: relative;
            box-sizing: border-box;
        }
        
        .sidebar.collapsed .nav-module-header:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        
        .sidebar.collapsed .nav-module.active .nav-module-header {
            background: rgba(76, 138, 137, 0.4);
        }
        
        .sidebar.collapsed .nav-module-icon {
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            font-size: 1.5rem;
            position: relative;
            margin: 0;
            padding: 0;
            transform: none;
        }
        
        .sidebar.collapsed .nav-module-header * {
            visibility: visible !important;
        }
        
        .sidebar.collapsed .nav-module-icon {
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        /* Main Content Area */
        .main-wrapper {
            margin-left: 320px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
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
        
        .top-header-content {
            display: flex;
            align-items: center;
            gap: 1rem;
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
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px var(--shadow);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .stat-card-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #fff;
        }
        
        .stat-card-icon.members { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card-icon.complaints { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card-icon.volunteers { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card-icon.events { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stat-card-icon.tips { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-card-icon.cameras { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); }
        
        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--tertiary-color);
            margin: 0;
        }
        
        .stat-card-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin: 0.5rem 0 0 0;
        }
        
        .stat-card-change {
            font-size: 0.85rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .stat-card-change.positive {
            color: #10b981;
        }
        
        .stat-card-change.negative {
            color: #ef4444;
        }
        
        .dashboard-section {
            margin-bottom: 2rem;
        }
        
        .dashboard-section h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--tertiary-color);
            margin: 0 0 1rem 0;
        }
        
        .tip-analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }
        
        .tip-analytics-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .tip-analytics-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 0.35rem;
        }
        
        .tip-analytics-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--tertiary-color);
        }
        
        .tip-analytics-subtext {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }
        
        .activity-list {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        
        .activity-icon.complaint { background: #fee2e2; color: #dc2626; }
        .activity-icon.tip { background: #fef3c7; color: #d97706; }
        .activity-icon.event { background: #dbeafe; color: #2563eb; }
        .activity-icon.patrol { background: #d1fae5; color: #059669; }
        .activity-icon.member { background: #e9d5ff; color: #7c3aed; }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            color: var(--text-color);
            margin: 0 0 0.25rem 0;
            font-size: 0.95rem;
        }
        
        .activity-details {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin: 0;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }
        
        .quick-links {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 1rem;
        }
        
        @media (max-width: 1200px) {
            .quick-links {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .quick-links {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .quick-link-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            text-decoration: none;
            color: var(--text-color);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .quick-link-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-color: var(--primary-color);
        }
        
        .quick-link-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, #4ca8a6 100%);
            color: #fff;
        }
        
        .quick-link-title {
            font-weight: 600;
            color: var(--tertiary-color);
            margin: 0;
            font-size: 1rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 320px;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .sidebar.collapsed {
                width: 80px;
                transform: translateX(0);
            }
            
            .main-wrapper {
                margin-left: 0;
            }
            
            body.sidebar-collapsed .main-wrapper {
                margin-left: 80px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <a href="index.php" style="display: block; cursor: pointer;">
                    <img src="images/tara.png" alt="Alertara Logo" style="display: block;">
                </a>
            </div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-module active">
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
    
    <!-- Main Content Area -->
    <div class="main-wrapper">
        <header class="top-header">
            <div class="top-header-content">
                <button class="content-burger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
                    <span></span>
                </button>
                <h1 class="page-title">Dashboard</h1>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>
        
        <main class="content-area">
            <div class="page-content">
                <h2>Overview</h2>
                <p style="margin-bottom: 2rem; color: var(--text-secondary);">Welcome back! Here's what's happening in Barangay San Agustin, Quezon City.</p>
                
                <!-- Statistics Cards -->
                <div class="dashboard-grid">
                    <div class="stat-card" onclick="window.location.href='member-list.php'">
                        <div class="stat-card-header">
                            <div class="stat-card-icon members">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <h3 class="stat-card-value" id="totalMembers">156</h3>
                        <p class="stat-card-label">Total Members</p>
                        <div class="stat-card-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+12 this month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card" onclick="window.location.href='track-complaint.php'">
                        <div class="stat-card-header">
                            <div class="stat-card-icon complaints">
                                <i class="fas fa-file-alt"></i>
                            </div>
                        </div>
                        <h3 class="stat-card-value" id="totalComplaints">42</h3>
                        <p class="stat-card-label">Active Complaints</p>
                        <div class="stat-card-change positive">
                            <i class="fas fa-arrow-down"></i>
                            <span>8 resolved this week</span>
                        </div>
                    </div>
                    
                    <div class="stat-card" onclick="window.location.href='volunteer-list.php'">
                        <div class="stat-card-header">
                            <div class="stat-card-icon volunteers">
                                <i class="fas fa-handshake"></i>
                            </div>
                        </div>
                        <h3 class="stat-card-value" id="totalVolunteers">89</h3>
                        <p class="stat-card-label">Active Volunteers</p>
                        <div class="stat-card-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+5 this month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card" onclick="window.location.href='event-list.php'">
                        <div class="stat-card-header">
                            <div class="stat-card-icon events">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                        </div>
                        <h3 class="stat-card-value" id="totalEvents">18</h3>
                        <p class="stat-card-label">Upcoming Events</p>
                        <div class="stat-card-change positive">
                            <i class="fas fa-calendar"></i>
                            <span>3 this week</span>
                        </div>
                    </div>
                    
                    <div class="stat-card" onclick="window.location.href='review-tip.php'">
                        <div class="stat-card-header">
                            <div class="stat-card-icon tips">
                                <i class="fas fa-comments"></i>
                            </div>
                        </div>
                        <h3 class="stat-card-value" id="totalTips">24</h3>
                        <p class="stat-card-label">Pending Tips</p>
                        <div class="stat-card-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+6 this week</span>
                        </div>
                    </div>
                    
                    <div class="stat-card" onclick="window.location.href='camera-management.php'">
                        <div class="stat-card-header">
                            <div class="stat-card-icon cameras">
                                <i class="fas fa-video"></i>
                            </div>
                        </div>
                        <h3 class="stat-card-value" id="totalCameras">3</h3>
                        <p class="stat-card-label">Active Cameras</p>
                        <div class="stat-card-change positive">
                            <i class="fas fa-check-circle"></i>
                            <span>All online</span>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="dashboard-section">
                    <h3>Recent Activity</h3>
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-icon complaint">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="activity-content">
                                <p class="activity-title">New Complaint Submitted</p>
                                <p class="activity-details">Complaint #COMP-2025-042 - Noise disturbance reported at Susano Road</p>
                                <p class="activity-time">2 hours ago</p>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon tip">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div class="activity-content">
                                <p class="activity-title">New Tip Received</p>
                                <p class="activity-details">Tip #TIP-2025-024 - Suspicious activity at Paraiso St.</p>
                                <p class="activity-time">4 hours ago</p>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon event">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="activity-content">
                                <p class="activity-title">Community Meeting Scheduled</p>
                                <p class="activity-details">Barangay Assembly - January 20, 2025 at Community Center</p>
                                <p class="activity-time">1 day ago</p>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon patrol">
                                <i class="fas fa-walking"></i>
                            </div>
                            <div class="activity-content">
                                <p class="activity-title">Patrol Completed</p>
                                <p class="activity-details">Officer Rizal completed patrol route - Susano Road to Clemente St.</p>
                                <p class="activity-time">1 day ago</p>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon member">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="activity-content">
                                <p class="activity-title">New Member Registered</p>
                                <p class="activity-details">Maria Santos joined the Neighborhood Watch program</p>
                                <p class="activity-time">2 days ago</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tip Outcome Analytics -->
                <div class="dashboard-section">
                    <h3>Tip Outcome Analytics</h3>
                    <div class="tip-analytics-grid">
                        <div class="tip-analytics-card">
                            <div class="tip-analytics-label">Total Tips Received</div>
                            <div class="tip-analytics-value" id="tipAnalyticsTotal">0</div>
                            <div class="tip-analytics-subtext">All tips submitted through the anonymous tip line.</div>
                        </div>
                        <div class="tip-analytics-card">
                            <div class="tip-analytics-label">Successful Investigations</div>
                            <div class="tip-analytics-value" id="tipAnalyticsSuccessful">0</div>
                            <div class="tip-analytics-subtext">Tips that led to a successful investigation or case resolution.</div>
                        </div>
                        <div class="tip-analytics-card">
                            <div class="tip-analytics-label">Arrests Made from Tips</div>
                            <div class="tip-analytics-value" id="tipAnalyticsArrests">0</div>
                            <div class="tip-analytics-subtext">Subset of successful tips where an arrest was made.</div>
                        </div>
                        <div class="tip-analytics-card">
                            <div class="tip-analytics-label">Tip Effectiveness Rate</div>
                            <div class="tip-analytics-value" id="tipAnalyticsRate">0%</div>
                            <div class="tip-analytics-subtext">Percentage of all tips that resulted in successful investigations.</div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="dashboard-section">
                    <h3>Quick Actions</h3>
                    <div class="quick-links">
                        <a href="submit-complaint.php" class="quick-link-card">
                            <div class="quick-link-icon">
                                <i class="fas fa-edit"></i>
                            </div>
                            <h4 class="quick-link-title">Submit Complaint</h4>
                        </a>
                        
                        <a href="live-view.php" class="quick-link-card">
                            <div class="quick-link-icon">
                                <i class="fas fa-video"></i>
                            </div>
                            <h4 class="quick-link-title">Live CCTV View</h4>
                        </a>
                        
                        <a href="patrol-schedule.php" class="quick-link-card">
                            <div class="quick-link-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h4 class="quick-link-title">Schedule Patrol</h4>
                        </a>
                        
                        <a href="event-list.php" class="quick-link-card">
                            <div class="quick-link-icon">
                                <i class="fas fa-list"></i>
                            </div>
                            <h4 class="quick-link-title">View Events</h4>
                        </a>
                        
                        <a href="activity-logs.php" class="quick-link-card">
                            <div class="quick-link-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h4 class="quick-link-title">Activity Logs</h4>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Load sidebar state from localStorage and initialize dashboard data
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
            
            updateTipOutcomeAnalytics();
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
            
            // Save state to localStorage
            localStorage.setItem('sidebarCollapsed', !isCollapsed);
        }
        
        function toggleModule(element) {
            const sidebar = document.getElementById('sidebar');
            const module = element.closest('.nav-module');
            const isActive = module.classList.contains('active');
            const isCollapsed = sidebar.classList.contains('collapsed');
            
            // When collapsed, expand sidebar and navigate to first submodule
            if (isCollapsed) {
                // Expand the sidebar
                sidebar.classList.remove('collapsed');
                document.body.classList.remove('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', 'false');
                
                // Activate the clicked module
                document.querySelectorAll('.nav-module').forEach(m => {
                    m.classList.remove('active');
                });
                module.classList.add('active');
                
                // Navigate to first submodule
                const firstSubmodule = module.querySelector('.nav-submodule');
                if (firstSubmodule && firstSubmodule.href && firstSubmodule.href !== '#') {
                    window.location.href = firstSubmodule.href;
                }
                return;
            }
            
            // Normal behavior when expanded
            // Close all modules
            document.querySelectorAll('.nav-module').forEach(m => {
                m.classList.remove('active');
            });
            
            // Open clicked module if it wasn't active
            if (!isActive) {
                module.classList.add('active');
            }
        }
        
        function getTipOutcomeAnalytics() {
            let tips = [];
            try {
                const stored = localStorage.getItem('submittedTips');
                tips = stored ? JSON.parse(stored) : [];
            } catch (e) {
                console.error('Failed to load submitted tips for analytics', e);
                tips = [];
            }
            
            const totalTips = tips.length;
            let successfulInvestigations = 0;
            let arrests = 0;
            
            tips.forEach(tip => {
                const outcome = (tip.outcome || '').trim();
                if (outcome === 'Investigation Successful' || outcome === 'Arrest Made') {
                    successfulInvestigations++;
                }
                if (outcome === 'Arrest Made') {
                    arrests++;
                }
            });
            
            const effectivenessRate = totalTips > 0
                ? Math.round((successfulInvestigations / totalTips) * 100)
                : 0;
            
            return {
                totalTips,
                successfulInvestigations,
                arrests,
                effectivenessRate
            };
        }
        
        function updateTipOutcomeAnalytics() {
            const analytics = getTipOutcomeAnalytics();
            
            const totalEl = document.getElementById('tipAnalyticsTotal');
            const successEl = document.getElementById('tipAnalyticsSuccessful');
            const arrestsEl = document.getElementById('tipAnalyticsArrests');
            const rateEl = document.getElementById('tipAnalyticsRate');
            
            if (!totalEl || !successEl || !arrestsEl || !rateEl) {
                return;
            }
            
            totalEl.textContent = analytics.totalTips;
            successEl.textContent = analytics.successfulInvestigations;
            arrestsEl.textContent = analytics.arrests;
            rateEl.textContent = analytics.effectivenessRate + '%';
        }
    </script>
</body>
</html>


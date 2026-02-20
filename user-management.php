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
    <title>User Management</title>
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
        
        .search-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1rem;
        }
        
        .search-box {
            flex: 1;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.95rem;
            background: var(--card-bg);
            color: var(--text-color);
            transition: all 0.2s ease;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1);
        }
        
        .search-box::before {
            content: "🔍";
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1rem;
            pointer-events: none;
        }
        
        .btn-add {
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .btn-add:hover {
            background: #4ca8a6;
            transform: translateY(-1px);
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
        
        .status-toggle {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }
        
        .status-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .status-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 26px;
        }
        
        .status-toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }
        
        .status-toggle input:checked + .status-toggle-slider {
            background-color: #4c8a89;
        }
        
        .status-toggle input:checked + .status-toggle-slider:before {
            transform: translateX(24px);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .btn-action {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            margin-right: 0.5rem;
        }
        
        .btn-edit {
            background: var(--primary-color);
            color: #fff;
        }
        
        .btn-edit:hover {
            background: #4ca8a6;
        }
        
        .action-cell {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        /* Modal Styles - Fixed to page instead of overlay */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: transparent;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: linear-gradient(145deg, var(--tertiary-color), var(--secondary-color));
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius);
            box-shadow: 0 20px 50px -25px rgba(0, 0, 0, 0.5);
            padding: 2.5rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            pointer-events: auto;
            position: relative;
            margin: 2rem auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.75rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.16);
        }
        
        .modal-header h2 {
            margin: 0;
            color: #f8fafc;
            font-size: 1.75rem;
            font-weight: 600;
        }
        
        .close {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.75rem;
            cursor: pointer;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .close:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .field {
            margin-bottom: 1.5rem;
        }
        
        .field label {
            display: block;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }
        
        .field input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: var(--radius);
            font: inherit;
            font-size: 1rem;
            color: #f8fafc;
            background: rgba(255, 255, 255, 0.08);
            box-sizing: border-box;
        }
        
        .field select {
            width: 100%;
            padding: 0.875rem 1rem;
            padding-right: 2.5rem;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: var(--radius);
            font: inherit;
            font-size: 1rem;
            color: #f8fafc;
            background: rgba(255, 255, 255, 0.08);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23f8fafc' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 12px;
            box-sizing: border-box;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            cursor: pointer;
        }
        
        /* Style for select options - note: limited browser support */
        .field select option {
            background: #1c2541;
            color: #f8fafc;
            padding: 0.75rem 1rem;
        }
        
        /* Ensure select dropdown is visible on all browsers */
        .field select::-ms-expand {
            display: none;
        }
        
        /* For Firefox */
        .field select {
            text-indent: 0.01px;
            text-overflow: '';
        }
        
        .field input:focus,
        .field select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.25);
            background-color: rgba(255, 255, 255, 0.12);
        }
        
        .field select:focus {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%234c8a89' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
        }
        
        .field select:hover {
            background-color: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.24);
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .button-group .btn {
            flex: 1;
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .button-group .btn-primary {
            background: var(--primary-color);
            color: #fff;
        }
        
        .button-group .btn-primary:hover {
            background: #4ca8a6;
        }
        
        .button-group .btn-secondary {
            background: transparent;
            color: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .button-group .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .error-message {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.5rem;
            display: none;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
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
            <a href="user-management.php" class="nav-module-header active" data-tooltip="User Management" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.5rem; color: rgba(255, 255, 255, 0.9); cursor: pointer; transition: background-color 0.2s ease; font-weight: 500; user-select: none; gap: 0.75rem; background: rgba(76, 138, 137, 0.25); border-left: 3px solid #4c8a89;">
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
    
    <!-- Main Content -->
    <div class="main-wrapper">
        <header class="top-header">
            <div class="top-header-content">
                <button class="content-burger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
                    <span></span>
                </button>
                <h1 class="page-title">User Management</h1>
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
                        <input type="text" id="searchInput" placeholder="Search users by name, username, email, or role..." onkeyup="filterUsers()">
                    </div>
                    <button class="btn-add" onclick="openAddUserModal()">
                        <i class="fas fa-plus"></i> Add User
                    </button>
                </div>
                
                <div class="table-container">
                    <table id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <!-- Users will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add User</h2>
                <span class="close" onclick="closeAddUserModal()">&times;</span>
            </div>
            <form id="addUserForm" onsubmit="saveUser(event)">
                <div class="field">
                    <label for="fullName">Full Name *</label>
                    <input type="text" id="fullName" name="full_name" required>
                </div>
                <div class="field">
                    <label for="username">Username *</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="field">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="field">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required>
                    <div class="error-message" id="passwordError">Password must contain at least one capital letter and one number or special character</div>
                </div>
                <div class="field">
                    <label for="role">Role *</label>
                    <select id="role" name="role" required>
                        <option value="">Select role</option>
                        <option value="Admin">Admin</option>
                        <option value="User">User</option>
                    </select>
                </div>
                <div class="error-message" id="formError"></div>
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit User</h2>
                <span class="close" onclick="closeEditUserModal()">&times;</span>
            </div>
            <form id="editUserForm" onsubmit="saveEditUser(event)">
                <input type="hidden" id="editUserId" name="user_id">
                <div class="field">
                    <label for="editFullName">Full Name *</label>
                    <input type="text" id="editFullName" name="full_name" required>
                </div>
                <div class="field">
                    <label for="editUsername">Username *</label>
                    <input type="text" id="editUsername" name="username" required>
                </div>
                <div class="field">
                    <label for="editEmail">Email Address *</label>
                    <input type="email" id="editEmail" name="email" required>
                </div>
                <div class="field">
                    <label for="editPassword">Password (leave blank to keep current password)</label>
                    <input type="password" id="editPassword" name="password">
                    <div class="error-message" id="editPasswordError">Password must contain at least one capital letter and one number or special character</div>
                </div>
                <div class="field">
                    <label for="editRole">Role *</label>
                    <select id="editRole" name="role" required>
                        <option value="">Select role</option>
                        <option value="Admin">Admin</option>
                        <option value="User">User</option>
                    </select>
                </div>
                <div class="error-message" id="editFormError"></div>
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Load users on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadUsers();
            
            // Ensure sidebar is expanded when User Management is clicked
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.remove('collapsed');
            document.body.classList.remove('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', 'false');
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
        
        async function loadUsers() {
            try {
                const response = await fetch('api/users.php');
                const result = await response.json();
                
                if (result.success) {
                    displayUsers(result.users);
                } else {
                    console.error('Failed to load users:', result.error);
                }
            } catch (error) {
                console.error('Error loading users:', error);
            }
        }
        
        function displayUsers(users) {
            const tbody = document.getElementById('usersTableBody');
            tbody.innerHTML = '';
            
            users.forEach(user => {
                const tr = document.createElement('tr');
                const isActive = user.status === 'Active';
                
                tr.innerHTML = `
                    <td>${user.id}</td>
                    <td>${user.full_name || '-'}</td>
                    <td>${user.username}</td>
                    <td>${user.email || '-'}</td>
                    <td>${user.role || 'User'}</td>
                    <td>
                        <span class="status-badge ${isActive ? 'status-active' : 'status-inactive'}" id="status-badge-${user.id}">
                            ${user.status || 'Active'}
                        </span>
                    </td>
                    <td>${user.created_at || '-'}</td>
                    <td>
                        <div class="action-cell">
                            <label class="status-toggle">
                                <input type="checkbox" ${isActive ? 'checked' : ''} onchange="toggleUserStatus(${user.id}, this.checked)">
                                <span class="status-toggle-slider"></span>
                            </label>
                            <button class="btn-action btn-edit" onclick="editUser(${user.id})">Edit</button>
                        </div>
                    </td>
                `;
                
                tbody.appendChild(tr);
            });
        }
        
        async function toggleUserStatus(userId, isActive) {
            const newStatus = isActive ? 'Active' : 'Inactive';
            const statusBadge = document.getElementById(`status-badge-${userId}`);
            
            // Update status badge immediately (optimistic update)
            if (statusBadge) {
                statusBadge.textContent = newStatus;
                statusBadge.className = `status-badge ${isActive ? 'status-active' : 'status-inactive'}`;
            }
            
            try {
                const response = await fetch('api/users.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: userId,
                        status: newStatus
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Status already updated in UI, no need to reload
                } else {
                    alert('Failed to update user status: ' + result.error);
                    loadUsers(); // Reload to reset toggle and status badge
                }
            } catch (error) {
                console.error('Error updating user status:', error);
                alert('Error updating user status');
                loadUsers(); // Reload to reset toggle and status badge
            }
        }
        
        function openAddUserModal() {
            document.getElementById('addUserModal').classList.add('active');
            document.getElementById('addUserForm').reset();
            document.getElementById('formError').style.display = 'none';
            document.getElementById('passwordError').style.display = 'none';
        }
        
        function closeAddUserModal() {
            document.getElementById('addUserModal').classList.remove('active');
            document.getElementById('addUserForm').reset();
            document.getElementById('formError').style.display = 'none';
            document.getElementById('passwordError').style.display = 'none';
        }
        
        async function saveUser(event) {
            event.preventDefault();
            
            const form = document.getElementById('addUserForm');
            const formData = new FormData(form);
            
            const data = {
                full_name: formData.get('full_name'),
                username: formData.get('username'),
                email: formData.get('email'),
                password: formData.get('password'),
                role: formData.get('role')
            };
            
            // Validate password
            const password = data.password;
            if (!/[A-Z]/.test(password) || !/[0-9!@#$%^&*(),.?":{}|<>]/.test(password)) {
                document.getElementById('passwordError').style.display = 'block';
                return;
            }
            
            try {
                const response = await fetch('api/users.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    closeAddUserModal();
                    loadUsers();
                } else {
                    document.getElementById('formError').textContent = result.error || 'Failed to create user';
                    document.getElementById('formError').style.display = 'block';
                }
            } catch (error) {
                console.error('Error creating user:', error);
                document.getElementById('formError').textContent = 'Network error. Please try again.';
                document.getElementById('formError').style.display = 'block';
            }
        }
        
        async function editUser(userId) {
            try {
                const response = await fetch(`api/users.php?id=${userId}`);
                const result = await response.json();
                
                if (result.success && result.user) {
                    const user = result.user;
                    
                    // Populate edit form
                    document.getElementById('editUserId').value = user.id;
                    document.getElementById('editFullName').value = user.full_name || '';
                    document.getElementById('editUsername').value = user.username || '';
                    document.getElementById('editEmail').value = user.email || '';
                    document.getElementById('editPassword').value = '';
                    document.getElementById('editRole').value = user.role || 'User';
                    
                    // Clear errors
                    document.getElementById('editFormError').style.display = 'none';
                    document.getElementById('editPasswordError').style.display = 'none';
                    
                    // Open modal
                    document.getElementById('editUserModal').classList.add('active');
                } else {
                    alert('Failed to load user data: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error loading user:', error);
                alert('Error loading user data. Please try again.');
            }
        }
        
        function closeEditUserModal() {
            document.getElementById('editUserModal').classList.remove('active');
            document.getElementById('editUserForm').reset();
            document.getElementById('editFormError').style.display = 'none';
            document.getElementById('editPasswordError').style.display = 'none';
        }
        
        async function saveEditUser(event) {
            event.preventDefault();
            
            const form = document.getElementById('editUserForm');
            const formData = new FormData(form);
            
            const userId = parseInt(document.getElementById('editUserId').value);
            const data = {
                id: userId,
                full_name: formData.get('full_name'),
                username: formData.get('username'),
                email: formData.get('email'),
                role: formData.get('role')
            };
            
            // Only include password if provided
            const password = formData.get('password');
            if (password && password.trim() !== '') {
                // Validate password
                if (!/[A-Z]/.test(password) || !/[0-9!@#$%^&*(),.?":{}|<>]/.test(password)) {
                    document.getElementById('editPasswordError').style.display = 'block';
                    return;
                }
                data.password = password;
            }
            
            try {
                const response = await fetch('api/users.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    closeEditUserModal();
                    loadUsers(); // Reload users to show updated data
                } else {
                    document.getElementById('editFormError').textContent = result.error || 'Failed to update user';
                    document.getElementById('editFormError').style.display = 'block';
                }
            } catch (error) {
                console.error('Error updating user:', error);
                document.getElementById('editFormError').textContent = 'Network error. Please try again.';
                document.getElementById('editFormError').style.display = 'block';
            }
        }
        
        function filterUsers() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('usersTable');
            const tr = table.getElementsByTagName('tr');
            
            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < td.length; j++) {
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                tr[i].style.display = found ? '' : 'none';
            }
        }
        
        // Close modal when clicking outside (only if clicking the modal backdrop)
        window.onclick = function(event) {
            const addModal = document.getElementById('addUserModal');
            const editModal = document.getElementById('editUserModal');
            if (event.target === addModal) {
                closeAddUserModal();
            }
            if (event.target === editModal) {
                closeEditUserModal();
            }
        }
    </script>
</body>
</html>


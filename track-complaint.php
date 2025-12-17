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
    <title>Track Complaint - Alertara</title>
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
        }
        
        .nav-module {
            margin-bottom: 0.125rem;
            display: block !important;
            visibility: visible !important;
        }
        
        .sidebar.collapsed .nav-module {
            display: block !important;
            visibility: visible !important;
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
        
        .content-area {
            padding: 2rem;
            flex: 1;
            background: #f5f5f5;
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
        
        .search-box {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
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
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
        }
        
        thead {
            background: var(--tertiary-color);
            color: #fff;
        }
        
        th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
        }
        
        tbody tr:hover {
            background: #f9f9f9;
        }
        
        tbody tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-processing {
            background: #cfe2ff;
            color: #084298;
        }
        
        .status-resolved {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #842029;
        }
        
        .priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .priority-low {
            background: #e7f3ff;
            color: #0066cc;
        }
        
        .priority-medium {
            background: #fff4e6;
            color: #cc6600;
        }
        
        .priority-high {
            background: #ffe6e6;
            color: #cc0000;
        }
        
        .priority-urgent {
            background: #ff0000;
            color: #fff;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-view, .btn-edit {
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-view:hover {
            background: #4ca8a6;
        }
        
        .btn-edit {
            background: #ff9800;
        }
        
        .btn-edit:hover {
            background: #f57c00;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 2rem;
            max-width: 700px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-header h2 {
            margin: 0;
            color: var(--tertiary-color);
            font-size: 1.5rem;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            transition: color 0.2s ease;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }
        
        .close-modal:hover {
            color: var(--text-color);
            background: rgba(0, 0, 0, 0.05);
        }
        
        .complaint-details {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        
        .detail-row {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-color);
            font-size: 0.9rem;
        }
        
        .detail-value {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        .detail-value.description {
            background: #f9f9f9;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            white-space: pre-wrap;
        }
        
        .detail-row.inline {
            flex-direction: row;
            align-items: center;
            gap: 1rem;
        }
        
        .detail-row.inline .detail-label {
            min-width: 120px;
        }
        
        .attachment-preview {
            margin-top: 0.5rem;
        }
        
        .attachment-preview img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .no-attachment {
            color: var(--text-secondary);
            font-style: italic;
            font-size: 0.9rem;
        }
        
        /* Edit Form Styles */
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: var(--font-family);
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .btn-cancel, .btn-save {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-cancel {
            background: #e5e5e5;
            color: var(--text-color);
        }
        
        .btn-cancel:hover {
            background: #d5d5d5;
        }
        
        .btn-save {
            background: var(--primary-color);
            color: #fff;
        }
        
        .btn-save:hover {
            background: #4ca8a6;
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
            
            <div class="nav-module">
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
            
            <div class="nav-module active">
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
                    <a href="track-complaint.php" class="nav-submodule active" data-tooltip="Track Complaint">
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
    
    <!-- Main Content Area -->
    <div class="main-wrapper">
        <header class="top-header">
            <div class="top-header-content">
                <button class="content-burger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
                    <span></span>
                </button>
                <h1 class="page-title">Track Complaint</h1>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>
        
        <main class="content-area">
            <div class="page-content">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search by complaint ID, complainant name, or contact number..." onkeyup="filterComplaints()">
                </div>
                
                <div class="table-container">
                    <table id="complaintsTable">
                        <thead>
                            <tr>
                                <th>Complaint ID</th>
                                <th>Complainant</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="complaintsTableBody">
                            <tr data-complaint-id="COMP-2025-001">
                                <td>COMP-2025-001</td>
                                <td>Juan Rizal</td>
                                <td>Noise</td>
                                <td>2025-01-15</td>
                                <td><span class="priority-badge priority-high">High</span></td>
                                <td><span class="status-badge status-processing">Processing</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewComplaint('COMP-2025-001')">View</button>
                                        <button class="btn-edit" onclick="editComplaint('COMP-2025-001')">Edit</button>
                                    </div>
                                </td>
                            </tr>
                            <tr data-complaint-id="COMP-2025-002">
                                <td>COMP-2025-002</td>
                                <td>Maria Aquino</td>
                                <td>Vandalism</td>
                                <td>2025-01-14</td>
                                <td><span class="priority-badge priority-urgent">Urgent</span></td>
                                <td><span class="status-badge status-resolved">Resolved</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewComplaint('COMP-2025-002')">View</button>
                                        <button class="btn-edit" onclick="editComplaint('COMP-2025-002')">Edit</button>
                                    </div>
                                </td>
                            </tr>
                            <tr data-complaint-id="COMP-2025-003">
                                <td>COMP-2025-003</td>
                                <td>Roberto Magsaysay</td>
                                <td>Safety</td>
                                <td>2025-01-13</td>
                                <td><span class="priority-badge priority-medium">Medium</span></td>
                                <td><span class="status-badge status-pending">Pending</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewComplaint('COMP-2025-003')">View</button>
                                        <button class="btn-edit" onclick="editComplaint('COMP-2025-003')">Edit</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Complaint Details Modal -->
    <div id="complaintModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Complaint Details</h2>
                <button class="close-modal" onclick="closeComplaintModal()">&times;</button>
            </div>
            <div class="complaint-details" id="complaintDetails">
                <!-- Details will be populated by JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- Edit Complaint Modal -->
    <div id="editComplaintModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Complaint</h2>
                <button class="close-modal" onclick="closeEditComplaintModal()">&times;</button>
            </div>
            <form id="editComplaintForm" onsubmit="saveComplaintEdit(event)">
                <input type="hidden" id="editComplaintId" name="complaintId">
                
                <div class="form-group">
                    <label for="editComplainant">Complainant Name *</label>
                    <input type="text" id="editComplainant" name="complainant" required>
                </div>
                
                <div class="form-group">
                    <label for="editContact">Contact Number *</label>
                    <input type="text" id="editContact" name="contact" required>
                </div>
                
                <div class="form-group">
                    <label for="editType">Complaint Type *</label>
                    <select id="editType" name="type" required>
                        <option value="">Select Type</option>
                        <option value="Noise">Noise</option>
                        <option value="Vandalism">Vandalism</option>
                        <option value="Safety">Safety</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="editPriority">Priority *</label>
                    <select id="editPriority" name="priority" required>
                        <option value="">Select Priority</option>
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                        <option value="Urgent">Urgent</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="editStatus">Status *</label>
                    <select id="editStatus" name="status" required>
                        <option value="">Select Status</option>
                        <option value="Pending">Pending</option>
                        <option value="Processing">Processing</option>
                        <option value="Resolved">Resolved</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="editLocation">Location *</label>
                    <input type="text" id="editLocation" name="location" required>
                </div>
                
                <div class="form-group">
                    <label for="editDescription">Description *</label>
                    <textarea id="editDescription" name="description" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="editAssignedTo">Assigned To</label>
                    <input type="text" id="editAssignedTo" name="assignedTo">
                </div>
                
                <div class="form-group">
                    <label for="editNotes">Notes</label>
                    <textarea id="editNotes" name="notes"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeEditComplaintModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
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
                
                document.querySelectorAll('.nav-module').forEach(m => {
                    m.classList.remove('active');
                });
                module.classList.add('active');
                
                const firstSubmodule = module.querySelector('.nav-submodule');
                if (firstSubmodule && firstSubmodule.href && firstSubmodule.href !== '#') {
                    window.location.href = firstSubmodule.href;
                }
                return;
            }
            
            document.querySelectorAll('.nav-module').forEach(m => {
                m.classList.remove('active');
            });
            
            if (!isActive) {
                module.classList.add('active');
            }
        }
        
        function filterComplaints() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('complaintsTableBody');
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
        
        // Complaint data storage
        const complaintData = {
            'COMP-2025-001': {
                id: 'COMP-2025-001',
                complainant: 'Juan Rizal',
                contact: '0912-345-6789',
                type: 'Noise',
                date: '2025-01-15',
                time: '10:30 AM',
                priority: 'High',
                status: 'Processing',
                description: 'Excessive noise from neighboring property during late hours. Loud music and shouting heard from 10 PM to 2 AM. This has been ongoing for the past week and is disturbing the peace of the neighborhood.',
                location: '123 Bonifacio Street, Barangay San Agustin, Quezon City',
                attachment: null,
                assignedTo: 'Officer Maria Aquino',
                lastUpdated: '2025-01-15 2:30 PM',
                notes: 'Initial investigation conducted. Spoke with property owner. Warning issued. Follow-up scheduled for next week.'
            },
            'COMP-2025-002': {
                id: 'COMP-2025-002',
                complainant: 'Maria Aquino',
                contact: '0917-890-1234',
                type: 'Vandalism',
                date: '2025-01-14',
                time: '8:15 AM',
                priority: 'Urgent',
                status: 'Resolved',
                description: 'Graffiti found on the community center wall. Property damage includes spray paint on the main entrance and side walls. Estimated repair cost: PHP 5,000.',
                location: 'Barangay San Agustin Hall, Quezon City',
                attachment: null,
                assignedTo: 'Officer Pedro Aguinaldo',
                lastUpdated: '2025-01-14 4:00 PM',
                notes: 'Graffiti removed. Security cameras reviewed. Suspect identified and case filed. Community center restored to original condition.'
            },
            'COMP-2025-003': {
                id: 'COMP-2025-003',
                complainant: 'Roberto Magsaysay',
                contact: '0918-567-8901',
                type: 'Safety',
                date: '2025-01-13',
                time: '3:45 PM',
                priority: 'Medium',
                status: 'Pending',
                description: 'Broken streetlight on Rizal Street corner Quezon Avenue. Area is dark at night, posing safety concerns for pedestrians and motorists. Request immediate repair.',
                location: 'Rizal Street corner Quezon Avenue, Barangay San Agustin, Quezon City',
                attachment: null,
                assignedTo: 'Pending Assignment',
                lastUpdated: '2025-01-13 4:00 PM',
                notes: 'Report forwarded to Quezon City Engineering Department. Awaiting response for repair schedule.'
            }
        };
        
        function viewComplaint(id) {
            const complaint = complaintData[id];
            if (!complaint) {
                alert('Complaint details not found for: ' + id);
                return;
            }
            
            const modal = document.getElementById('complaintModal');
            const detailsContainer = document.getElementById('complaintDetails');
            
            // Build the details HTML
            let detailsHTML = `
                <div class="detail-row inline">
                    <span class="detail-label">Complaint ID:</span>
                    <span class="detail-value"><strong>${complaint.id}</strong></span>
                </div>
                
                <div class="detail-row inline">
                    <span class="detail-label">Status:</span>
                    <span class="status-badge status-${complaint.status.toLowerCase()}">${complaint.status}</span>
                </div>
                
                <div class="detail-row inline">
                    <span class="detail-label">Priority:</span>
                    <span class="priority-badge priority-${complaint.priority.toLowerCase()}">${complaint.priority}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Complainant Name:</span>
                    <span class="detail-value">${complaint.complainant}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Contact Number:</span>
                    <span class="detail-value">${complaint.contact}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Complaint Type:</span>
                    <span class="detail-value">${complaint.type}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Date & Time:</span>
                    <span class="detail-value">${complaint.date} at ${complaint.time}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Location:</span>
                    <span class="detail-value">${complaint.location}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Description:</span>
                    <div class="detail-value description">${complaint.description}</div>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Assigned To:</span>
                    <span class="detail-value">${complaint.assignedTo}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Last Updated:</span>
                    <span class="detail-value">${complaint.lastUpdated}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Notes:</span>
                    <div class="detail-value description">${complaint.notes}</div>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Attachment:</span>
                    <div class="detail-value">
                        ${complaint.attachment ? 
                            `<div class="attachment-preview"><img src="${complaint.attachment}" alt="Complaint Attachment"></div>` : 
                            '<span class="no-attachment">No attachment provided</span>'
                        }
                    </div>
                </div>
            `;
            
            detailsContainer.innerHTML = detailsHTML;
            modal.classList.add('active');
        }
        
        function closeComplaintModal() {
            document.getElementById('complaintModal').classList.remove('active');
        }
        
        function editComplaint(id) {
            const complaint = complaintData[id];
            if (!complaint) {
                alert('Complaint details not found for: ' + id);
                return;
            }
            
            // Populate form fields
            document.getElementById('editComplaintId').value = complaint.id;
            document.getElementById('editComplainant').value = complaint.complainant;
            document.getElementById('editContact').value = complaint.contact;
            document.getElementById('editType').value = complaint.type;
            document.getElementById('editPriority').value = complaint.priority;
            document.getElementById('editStatus').value = complaint.status;
            document.getElementById('editLocation').value = complaint.location;
            document.getElementById('editDescription').value = complaint.description;
            document.getElementById('editAssignedTo').value = complaint.assignedTo;
            document.getElementById('editNotes').value = complaint.notes;
            
            // Open modal
            document.getElementById('editComplaintModal').classList.add('active');
        }
        
        function closeEditComplaintModal() {
            document.getElementById('editComplaintModal').classList.remove('active');
            document.getElementById('editComplaintForm').reset();
        }
        
        function saveComplaintEdit(event) {
            event.preventDefault();
            
            const complaintId = document.getElementById('editComplaintId').value;
            const complaint = complaintData[complaintId];
            
            if (!complaint) {
                alert('Complaint not found!');
                return;
            }
            
            // Update complaint data
            complaint.complainant = document.getElementById('editComplainant').value;
            complaint.contact = document.getElementById('editContact').value;
            complaint.type = document.getElementById('editType').value;
            complaint.priority = document.getElementById('editPriority').value;
            complaint.status = document.getElementById('editStatus').value;
            complaint.location = document.getElementById('editLocation').value;
            complaint.description = document.getElementById('editDescription').value;
            complaint.assignedTo = document.getElementById('editAssignedTo').value;
            complaint.notes = document.getElementById('editNotes').value;
            complaint.lastUpdated = new Date().toLocaleString('en-US', { 
                year: 'numeric', 
                month: '2-digit', 
                day: '2-digit', 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            
            // Update table row
            updateComplaintRow(complaintId);
            
            alert('Complaint updated successfully!');
            closeEditComplaintModal();
        }
        
        function updateComplaintRow(id) {
            const complaint = complaintData[id];
            const row = document.querySelector(`tr[data-complaint-id="${id}"]`);
            
            if (!row) return;
            
            const cells = row.querySelectorAll('td');
            
            // Update complainant
            cells[1].textContent = complaint.complainant;
            
            // Update type
            cells[2].textContent = complaint.type;
            
            // Update priority badge
            const priorityBadge = cells[4].querySelector('.priority-badge');
            priorityBadge.textContent = complaint.priority;
            priorityBadge.className = `priority-badge priority-${complaint.priority.toLowerCase()}`;
            
            // Update status badge
            const statusBadge = cells[5].querySelector('.status-badge');
            statusBadge.textContent = complaint.status;
            statusBadge.className = `status-badge status-${complaint.status.toLowerCase()}`;
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const viewModal = document.getElementById('complaintModal');
            const editModal = document.getElementById('editComplaintModal');
            
            if (event.target == viewModal) {
                closeComplaintModal();
            }
            if (event.target == editModal) {
                closeEditComplaintModal();
            }
        }
    </script>
</body>
</html>


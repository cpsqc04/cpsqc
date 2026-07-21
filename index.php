<?php
session_start();

// Check if user is logged in, redirect to login if not
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Dashboard</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/admin-sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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
        
        /* Notification Bell */
        .notification-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .notification-bell {
            position: relative;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            color: var(--text-color);
            font-size: 1.25rem;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .notification-bell:hover {
            background: rgba(28, 37, 65, 0.05);
            color: var(--primary-color);
        }
        
        .notification-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            background: #ef4444;
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
            display: none;
        }
        
        .notification-badge.show {
            display: block;
        }
        
        .notification-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 380px;
            max-height: 500px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            display: none;
            flex-direction: column;
            z-index: 1000;
            overflow: hidden;
        }
        
        .notification-dropdown.show {
            display: flex;
        }
        
        .notification-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--header-bg);
        }
        
        .notification-header h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .notification-header button {
            background: transparent;
            border: none;
            color: var(--primary-color);
            font-size: 0.85rem;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            transition: background 0.2s ease;
        }
        
        .notification-header button:hover {
            background: rgba(76, 138, 137, 0.1);
        }
        
        .notification-list {
            flex: 1;
            overflow-y: auto;
            max-height: 400px;
        }
        
        .notification-item {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.2s ease;
            display: flex;
            gap: 0.75rem;
            position: relative;
        }
        
        .notification-item:hover {
            background: #f8f9fa;
        }
        
        .notification-item.unread {
            background: #f0f9ff;
            border-left: 3px solid var(--primary-color);
        }
        
        .notification-item.unread::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 6px;
            height: 6px;
            background: var(--primary-color);
            border-radius: 50%;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        
        .notification-icon.complaint {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .notification-icon.tip {
            background: #fef3c7;
            color: #d97706;
        }
        
        .notification-icon.volunteer {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .notification-icon.event {
            background: #d1fae5;
            color: #059669;
        }
        
        .notification-icon.login {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .notification-icon.logout {
            background: #e0e7ff;
            color: #6366f1;
        }
        
        .notification-content {
            flex: 1;
            min-width: 0;
        }
        
        .notification-title {
            font-weight: 600;
            color: var(--text-color);
            font-size: 0.95rem;
            margin: 0 0 0.25rem 0;
        }
        
        .notification-message {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin: 0 0 0.5rem 0;
            line-height: 1.4;
        }
        
        .notification-time {
            color: var(--text-secondary);
            font-size: 0.75rem;
        }
        
        .notification-empty {
            padding: 3rem 1.5rem;
            text-align: center;
            color: var(--text-secondary);
        }
        
        .notification-empty i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
        
        /* Date and Time Display */
        .datetime-display {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-color);
            font-size: 0.9rem;
            font-weight: 500;
            margin-right: 1rem;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .datetime-display .date-part {
            color: var(--text-secondary);
        }
        
        .datetime-display .time-part {
            color: var(--text-color);
            font-weight: 600;
        }
        
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
        
        /* Top header logout button (removed, keeping for backward compatibility) */
        .logout-btn {
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: background 0.2s ease;
            display: none; /* Hide from header */
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
        
        .dashboard-charts {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .dashboard-charts-row {
            display: grid;
            gap: 1.5rem;
        }

        .dashboard-charts-row--summary {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .dashboard-charts-row--trends {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        @media (max-width: 1100px) {
            .dashboard-charts-row--summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .dashboard-charts-row--summary,
            .dashboard-charts-row--trends {
                grid-template-columns: 1fr;
            }
        }

        .chart-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem 1.5rem 1.5rem;
            box-shadow: 0 2px 8px var(--shadow);
            min-width: 0;
        }

        .chart-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            gap: 1rem;
        }

        .chart-card-header h3 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--tertiary-color);
        }

        .chart-wrapper {
            position: relative;
            height: 280px;
        }

        .chart-wrapper.tall {
            height: 320px;
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
        .activity-icon.volunteer { background: #dbeafe; color: #2563eb; }
        
        .activity-empty {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }
        
        .activity-empty i {
            font-size: 2rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
        
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
    <link rel="stylesheet" href="css/mobile-responsive.css">
</head>
<body>
    <!-- Sidebar Navigation -->
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
            <!-- Dashboard Link -->
            <a href="index.php" class="nav-module-header" data-tooltip="Dashboard" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.5rem; color: rgba(255, 255, 255, 0.9); cursor: pointer; transition: background-color 0.2s ease; font-weight: 500; user-select: none; gap: 0.75rem; <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'background: rgba(76, 138, 137, 0.25); border-left: 3px solid #4c8a89;' : ''; ?>">
                <span class="nav-module-icon"><i class="fas fa-home"></i></span>
                <span class="nav-module-header-text">Dashboard</span>
            </a>
            
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
                <h2>Overview</h2>
                <p style="margin-bottom: 2rem; color: var(--text-secondary);">Welcome back! Here's what's happening in Barangay San Agustin, Quezon City.</p>
                
                <!-- Analytics Charts -->
                <div class="dashboard-charts">
                    <div class="dashboard-charts-row dashboard-charts-row--summary">
                        <div class="chart-card">
                            <div class="chart-card-header">
                                <h3>Complaints by Status</h3>
                            </div>
                            <div class="chart-wrapper"><canvas id="complaintsStatusChart"></canvas></div>
                        </div>

                        <div class="chart-card">
                            <div class="chart-card-header">
                                <h3>Tips by Status</h3>
                            </div>
                            <div class="chart-wrapper"><canvas id="tipsStatusChart"></canvas></div>
                        </div>

                        <div class="chart-card">
                            <div class="chart-card-header">
                                <h3>Events Overview</h3>
                            </div>
                            <div class="chart-wrapper"><canvas id="eventsOverviewChart"></canvas></div>
                        </div>
                    </div>

                    <div class="dashboard-charts-row dashboard-charts-row--trends">
                        <div class="chart-card">
                            <div class="chart-card-header">
                                <h3>Complaints — Last 7 Days</h3>
                            </div>
                            <div class="chart-wrapper tall"><canvas id="complaintsTrendChart"></canvas></div>
                        </div>

                        <div class="chart-card">
                            <div class="chart-card-header">
                                <h3>Tips — Last 7 Days</h3>
                            </div>
                            <div class="chart-wrapper tall"><canvas id="tipsTrendChart"></canvas></div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="dashboard-section">
                    <h3>Recent Activity</h3>
                    <div class="activity-list" id="recentActivityList">
                        <div class="activity-empty" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>Loading recent activities...</p>
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
            
            loadDashboardData();
            updateTipOutcomeAnalytics();
        });
        
        // Load dashboard statistics from API
        let dashboardCharts = {};

        async function loadDashboardData() {
            try {
                const response = await fetch('api/dashboard.php');
                const data = await response.json();
                
                if (data.success) {
                    if (data.charts) {
                        renderDashboardCharts(data.charts);
                    }

                    if (data.recentActivity && Array.isArray(data.recentActivity)) {
                        displayRecentActivity(data.recentActivity);
                    }
                }
            } catch (error) {
                console.error('Error loading dashboard data:', error);
                const activityList = document.getElementById('recentActivityList');
                if (activityList) {
                    activityList.innerHTML = '<div class="activity-empty" style="text-align: center; padding: 2rem; color: var(--text-secondary);"><p>Error loading activities</p></div>';
                }
            }
        }

        function destroyDashboardCharts() {
            Object.values(dashboardCharts).forEach(chart => {
                if (chart) chart.destroy();
            });
            dashboardCharts = {};
        }

        function formatChartDate(dateStr) {
            const date = new Date(dateStr + 'T00:00:00');
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }

        function mapToChartData(dataObj, fallbackLabel) {
            const entries = Object.entries(dataObj || {});
            if (entries.length === 0) {
                return {
                    labels: [fallbackLabel || 'No data'],
                    values: [0]
                };
            }
            return {
                labels: entries.map(([label]) => label),
                values: entries.map(([, value]) => value)
            };
        }

        function renderDashboardCharts(charts) {
            if (typeof Chart === 'undefined') {
                console.error('Chart.js is not loaded');
                return;
            }

            destroyDashboardCharts();

            const chartTheme = {
                primary: '#4c8a89',
                primaryDark: '#3d6f6e',
                primaryLight: '#6baa91',
                primaryLighter: '#8bc4a8',
                accent: '#10b981',
                muted: '#94a3b8'
            };
            const statusColors = [
                chartTheme.primary,
                chartTheme.accent,
                chartTheme.primaryLight,
                chartTheme.primaryDark,
                chartTheme.primaryLighter,
                chartTheme.muted
            ];
            const doughnutOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, padding: 14 }
                    }
                }
            };

            const lineBarOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, precision: 0 }
                    }
                }
            };

            const complaintsStatus = mapToChartData(charts.complaintsByStatus, 'No complaints');
            dashboardCharts.complaintsStatus = new Chart(document.getElementById('complaintsStatusChart'), {
                type: 'doughnut',
                data: {
                    labels: complaintsStatus.labels,
                    datasets: [{
                        data: complaintsStatus.values,
                        backgroundColor: statusColors,
                        borderWidth: 0
                    }]
                },
                options: doughnutOptions
            });

            const tipsStatus = mapToChartData(charts.tipsByStatus, 'No tips');
            dashboardCharts.tipsStatus = new Chart(document.getElementById('tipsStatusChart'), {
                type: 'doughnut',
                data: {
                    labels: tipsStatus.labels,
                    datasets: [{
                        data: tipsStatus.values,
                        backgroundColor: [
                            chartTheme.primary,
                            chartTheme.accent,
                            chartTheme.primaryLight,
                            chartTheme.primaryDark,
                            chartTheme.muted
                        ],
                        borderWidth: 0
                    }]
                },
                options: doughnutOptions
            });

            const complaintsTrend = charts.complaintsOverTime || {};
            const complaintDates = Object.keys(complaintsTrend);
            dashboardCharts.complaintsTrend = new Chart(document.getElementById('complaintsTrendChart'), {
                type: 'bar',
                data: {
                    labels: complaintDates.map(formatChartDate),
                    datasets: [{
                        label: 'Complaints',
                        data: complaintDates.map(date => complaintsTrend[date] || 0),
                        backgroundColor: 'rgba(76, 138, 137, 0.75)',
                        borderColor: chartTheme.primary,
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: lineBarOptions
            });

            const tipsTrend = charts.tipsOverTime || {};
            const tipDates = Object.keys(tipsTrend);
            dashboardCharts.tipsTrend = new Chart(document.getElementById('tipsTrendChart'), {
                type: 'line',
                data: {
                    labels: tipDates.map(formatChartDate),
                    datasets: [{
                        label: 'Tips',
                        data: tipDates.map(date => tipsTrend[date] || 0),
                        borderColor: chartTheme.accent,
                        backgroundColor: 'rgba(16, 185, 129, 0.15)',
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: chartTheme.accent,
                        pointRadius: 4
                    }]
                },
                options: lineBarOptions
            });

            const eventsOverview = mapToChartData(charts.eventsOverview, 'No events');
            dashboardCharts.eventsOverview = new Chart(document.getElementById('eventsOverviewChart'), {
                type: 'doughnut',
                data: {
                    labels: eventsOverview.labels,
                    datasets: [{
                        data: eventsOverview.values,
                        backgroundColor: [
                            chartTheme.primary,
                            chartTheme.accent,
                            chartTheme.muted
                        ],
                        borderWidth: 0
                    }]
                },
                options: doughnutOptions
            });
        }
        
        function displayRecentActivity(activities) {
            const activityList = document.getElementById('recentActivityList');
            if (!activityList) return;
            
            if (activities.length === 0) {
                activityList.innerHTML = '<div class="activity-empty" style="text-align: center; padding: 2rem; color: var(--text-secondary);"><i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.3;"></i><p>No recent activities</p></div>';
                return;
            }
            
            const iconMap = {
                'complaint': { class: 'complaint', icon: 'fa-file-alt' },
                'tip': { class: 'tip', icon: 'fa-comments' },
                'event': { class: 'event', icon: 'fa-bullhorn' },
                'patrol': { class: 'patrol', icon: 'fa-walking' },
                'member': { class: 'member', icon: 'fa-user-plus' },
                'volunteer': { class: 'volunteer', icon: 'fa-handshake' }
            };
            
            activityList.innerHTML = activities.map(activity => {
                const iconInfo = iconMap[activity.type] || { class: 'event', icon: 'fa-circle' };
                return `
                    <div class="activity-item">
                        <div class="activity-icon ${iconInfo.class}">
                            <i class="fas ${iconInfo.icon}"></i>
                        </div>
                        <div class="activity-content">
                            <p class="activity-title">${escapeHtml(activity.title)}</p>
                            <p class="activity-details">${escapeHtml(activity.details)}</p>
                            <p class="activity-time">${activity.time_ago || 'Unknown time'}</p>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
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
        // Date and Time Display
        function updateDateTime() {
            const now = new Date();
            const dateOptions = { 
                weekday: 'short', 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            };
            const timeOptions = { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            };
            
            const dateStr = now.toLocaleDateString('en-US', dateOptions);
            const timeStr = now.toLocaleTimeString('en-US', timeOptions);
            
            const dateEl = document.getElementById('currentDate');
            const timeEl = document.getElementById('currentTime');
            
            if (dateEl) dateEl.textContent = dateStr;
            if (timeEl) timeEl.textContent = timeStr;
        }
        
        // Update date/time immediately and then every second
        updateDateTime();
        setInterval(updateDateTime, 1000);
    </script>
    <?php require __DIR__ . '/includes/admin_notifications_script.php'; ?>
    <script src="js/mobile-shell.js"></script>
</body>
</html>


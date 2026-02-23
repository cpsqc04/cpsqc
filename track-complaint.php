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
        
        .logout-btn {
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: background 0.2s ease;
            display: none;
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
                <div class="user-name-display">
                    <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
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
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin'): ?>
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
                    <a href="login-history.php" class="nav-submodule <?php echo basename($_SERVER['PHP_SELF']) == 'login-history.php' ? 'active' : ''; ?>" data-tooltip="Login History">
                        <span class="nav-submodule-icon"><i class="fas fa-history"></i></span>
                        <span class="nav-submodule-text">Login History</span>
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
                <h1 class="page-title">Track Complaint</h1>
            </div>
            <div class="user-info">
                <div class="datetime-display">
                    <span class="date-part" id="currentDate"></span>
                    <span class="time-part" id="currentTime"></span>
                </div>
                <div class="notification-container">
                    <button class="notification-bell" onclick="toggleNotifications()" aria-label="Notifications">
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
                            <!-- Complaints will be loaded from database via JavaScript -->
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
                    <label for="editAddress">Address *</label>
                    <input type="text" id="editAddress" name="address" required>
                </div>
                
                <div class="form-group">
                    <label for="editIncidentDate">Date of Incident *</label>
                    <input type="date" id="editIncidentDate" name="incidentDate" required>
                </div>
                
                <div class="form-group">
                    <label for="editType">Complaint Type *</label>
                    <select id="editType" name="type" required>
                        <option value="">Select Type</option>
                        <option value="Noise">Noise Complaint</option>
                        <option value="Vandalism">Vandalism</option>
                        <option value="Trespassing">Trespassing</option>
                        <option value="Safety">Safety Concern</option>
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
        
        // Complaint data storage (loaded from database)
        let complaintData = {};
        
        // Load complaints from database
        async function loadComplaints() {
            try {
                const response = await fetch('api/complaints.php');
                const result = await response.json();
                
                if (!result.success) {
                    console.error(result.message || 'Failed to load complaints');
                    return;
                }
                
                const complaints = result.data || [];
                const tbody = document.getElementById('complaintsTableBody');
                tbody.innerHTML = '';
                
                // Store complaints by complaint_id for easy lookup
                complaintData = {};
                complaints.forEach(c => {
                    complaintData[c.complaint_id] = c;
                });
                
                // Populate table
                complaints.forEach(c => {
                    const row = document.createElement('tr');
                    row.setAttribute('data-complaint-id', c.complaint_id);
                    
                    // Format date
                    const date = new Date(c.incident_date);
                    const formattedDate = date.toLocaleDateString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit', timeZone: 'Asia/Manila' });
                    
                    // Format submitted_at for time display
                    let timeDisplay = '';
                    if (c.submitted_at) {
                        const submittedDate = new Date(c.submitted_at);
                        timeDisplay = submittedDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila' });
                    }
                    
                    row.innerHTML = `
                        <td>${c.complaint_id}</td>
                        <td>${c.complainant_name}</td>
                        <td>${c.complaint_type}</td>
                        <td>${formattedDate}</td>
                        <td><span class="priority-badge priority-${c.priority.toLowerCase()}">${c.priority}</span></td>
                        <td><span class="status-badge status-${c.status.toLowerCase()}">${c.status}</span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-view" onclick="viewComplaint('${c.complaint_id}')">View</button>
                                <button class="btn-edit" onclick="editComplaint('${c.complaint_id}')">Edit</button>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            } catch (e) {
                console.error('Error loading complaints:', e);
            }
        }
        
        // Load complaints on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadComplaints();
        });
        
        function viewComplaint(complaintId) {
            const complaint = complaintData[complaintId];
            if (!complaint) {
                alert('Complaint details not found for: ' + complaintId);
                return;
            }
            
            const modal = document.getElementById('complaintModal');
            const detailsContainer = document.getElementById('complaintDetails');
            
            // Format dates
            const incidentDate = new Date(complaint.incident_date);
            const formattedIncidentDate = incidentDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'Asia/Manila' });
            const formattedTime = incidentDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila' });
            
            let lastUpdated = 'N/A';
            if (complaint.submitted_at) {
                const submittedDate = new Date(complaint.submitted_at);
                lastUpdated = submittedDate.toLocaleString('en-US', { 
                    year: 'numeric', 
                    month: '2-digit', 
                    day: '2-digit', 
                    hour: '2-digit', 
                    minute: '2-digit',
                    hour12: true 
                });
            }
            
            // Build the details HTML
            let detailsHTML = `
                <div class="detail-row inline">
                    <span class="detail-label">Complaint ID:</span>
                    <span class="detail-value"><strong>${complaint.complaint_id}</strong></span>
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
                    <span class="detail-value">${complaint.complainant_name}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Contact Number:</span>
                    <span class="detail-value">${complaint.contact_number}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Address:</span>
                    <span class="detail-value">${complaint.address}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Complaint Type:</span>
                    <span class="detail-value">${complaint.complaint_type}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Date & Time:</span>
                    <span class="detail-value">${formattedIncidentDate} at ${formattedTime}</span>
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
                    <span class="detail-value">${complaint.assigned_to || 'Pending Assignment'}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Last Updated:</span>
                    <span class="detail-value">${lastUpdated}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Notes:</span>
                    <div class="detail-value description">${complaint.notes || 'No notes available.'}</div>
                </div>
            `;
            
            detailsContainer.innerHTML = detailsHTML;
            modal.classList.add('active');
        }
        
        function closeComplaintModal() {
            document.getElementById('complaintModal').classList.remove('active');
        }
        
        function editComplaint(complaintId) {
            const complaint = complaintData[complaintId];
            if (!complaint) {
                alert('Complaint details not found for: ' + complaintId);
                return;
            }
            
            // Populate form fields
            document.getElementById('editComplaintId').value = complaint.id;
            document.getElementById('editComplainant').value = complaint.complainant_name;
            document.getElementById('editContact').value = complaint.contact_number;
            document.getElementById('editAddress').value = complaint.address || '';
            document.getElementById('editIncidentDate').value = complaint.incident_date || '';
            document.getElementById('editType').value = complaint.complaint_type;
            document.getElementById('editPriority').value = complaint.priority;
            document.getElementById('editStatus').value = complaint.status;
            document.getElementById('editLocation').value = complaint.location;
            document.getElementById('editDescription').value = complaint.description;
            document.getElementById('editAssignedTo').value = complaint.assigned_to || '';
            document.getElementById('editNotes').value = complaint.notes || '';
            
            // Open modal
            document.getElementById('editComplaintModal').classList.add('active');
        }
        
        function closeEditComplaintModal() {
            document.getElementById('editComplaintModal').classList.remove('active');
            document.getElementById('editComplaintForm').reset();
        }
        
        function saveComplaintEdit(event) {
            event.preventDefault();
            
            const id = parseInt(document.getElementById('editComplaintId').value);
            if (!id) {
                alert('Invalid complaint ID!');
                return;
            }
            
            // Find the complaint by database ID to get address and incident_date
            const complaint = Object.values(complaintData).find(c => c.id === id);
            if (!complaint) {
                alert('Complaint not found!');
                return;
            }
            
            const formData = {
                action: 'update',
                id: id,
                complainant_name: document.getElementById('editComplainant').value.trim(),
                contact_number: document.getElementById('editContact').value.trim(),
                address: document.getElementById('editAddress').value.trim(),
                incident_date: document.getElementById('editIncidentDate').value,
                complaint_type: document.getElementById('editType').value,
                priority: document.getElementById('editPriority').value,
                status: document.getElementById('editStatus').value,
                location: document.getElementById('editLocation').value.trim(),
                description: document.getElementById('editDescription').value.trim(),
                assigned_to: document.getElementById('editAssignedTo').value.trim(),
                notes: document.getElementById('editNotes').value.trim()
            };
            
            fetch('api/complaints.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            })
            .then(res => res.json())
            .then(result => {
                if (!result.success) {
                    alert(result.message || 'Failed to update complaint.');
                    return;
                }
                
                // Reload complaints to refresh the table
                loadComplaints();
                
                alert('Complaint updated successfully!');
                closeEditComplaintModal();
            })
            .catch(err => {
                console.error('Error updating complaint:', err);
                alert('Error updating complaint. Please try again.');
            });
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
        
        // Notification System
        let notificationDropdown = null;
        let notificationBadge = null;
        let notificationList = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            notificationDropdown = document.getElementById('notificationDropdown');
            notificationBadge = document.getElementById('notificationBadge');
            notificationList = document.getElementById('notificationList');
            
            if (notificationDropdown && notificationBadge && notificationList) {
                loadNotifications();
                // Refresh notifications every 30 seconds
                setInterval(loadNotifications, 30000);
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (notificationDropdown && !event.target.closest('.notification-container')) {
                        notificationDropdown.classList.remove('show');
                    }
                });
            }
        });
        
        function toggleNotifications() {
            if (notificationDropdown) {
                notificationDropdown.classList.toggle('show');
                if (notificationDropdown.classList.contains('show')) {
                    loadNotifications();
                }
            }
        }
        
        async function loadNotifications() {
            try {
                // Sync activities first
                await fetch('api/notifications.php?action=sync');
                
                // Then load notifications
                const response = await fetch('api/notifications.php?action=list');
                const data = await response.json();
                
                if (data.success) {
                    updateNotificationBadge(data.unread_count);
                    renderNotifications(data.notifications);
                }
            } catch (error) {
                console.error('Error loading notifications:', error);
            }
        }
        
        function updateNotificationBadge(count) {
            if (notificationBadge) {
                if (count > 0) {
                    notificationBadge.textContent = count > 99 ? '99+' : count;
                    notificationBadge.classList.add('show');
                } else {
                    notificationBadge.classList.remove('show');
                }
            }
        }
        
        function renderNotifications(notifications) {
            if (!notificationList) return;
            
            if (notifications.length === 0) {
                notificationList.innerHTML = `
                    <div class="notification-empty">
                        <i class="fas fa-bell-slash"></i>
                        <p>No notifications</p>
                    </div>
                `;
                return;
            }
            
            notificationList.innerHTML = notifications.map(notif => {
                let iconClass, icon;
                if (notif.type === 'complaint' || notif.type === 'incident') {
                    iconClass = 'complaint';
                    icon = 'fa-file-alt';
                } else if (notif.type === 'tip') {
                    iconClass = 'tip';
                    icon = 'fa-comments';
                } else if (notif.type === 'volunteer' || notif.type === 'volunteer_request') {
                    iconClass = 'volunteer';
                    icon = 'fa-handshake';
                } else if (notif.type === 'login') {
                    iconClass = 'login';
                    icon = 'fa-sign-in-alt';
                } else if (notif.type === 'logout') {
                    iconClass = 'logout';
                    icon = 'fa-sign-out-alt';
                } else if (notif.type === 'event' || notif.type === 'event_report' || notif.type === 'patrol') {
                    iconClass = 'event';
                    icon = 'fa-bullhorn';
                } else {
                    iconClass = 'event';
                    icon = 'fa-bullhorn';
                }
                
                const safeLink = (notif.link || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                
                return `
                    <div class="notification-item ${notif.is_read ? '' : 'unread'}" 
                         onclick="handleNotificationClick(${notif.id}, '${safeLink}')">
                        <div class="notification-icon ${iconClass}">
                            <i class="fas ${icon}"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">${escapeHtml(notif.title)}</div>
                            <div class="notification-message">${escapeHtml(notif.message)}</div>
                            <div class="notification-time">${notif.time_ago}</div>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        function handleNotificationClick(id, link) {
            // Mark as read
            fetch('api/notifications.php?action=mark_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + id
            });
            
            // Remove unread class
            const item = event.currentTarget;
            item.classList.remove('unread');
            
            // Navigate if link exists
            if (link && link !== '') {
                window.location.href = link;
            }
            
            // Reload notifications to update badge
            loadNotifications();
        }
        
        async function markAllAsRead() {
            try {
                await fetch('api/notifications.php?action=mark_read', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                });
                loadNotifications();
            } catch (error) {
                console.error('Error marking all as read:', error);
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>


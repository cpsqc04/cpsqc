<?php
session_start();

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
    <title>Track Complaint - Alertara</title>
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

        .status-forwarded {
            background: #e2d9f3;
            color: #432874;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-view, .btn-manage {
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
        
        .btn-manage {
            background: #ff9800;
        }
        
        .btn-manage:hover {
            background: #f57c00;
        }

        .manage-complaint-ref {
            margin: 0 0 0.75rem;
            color: var(--tertiary-color);
            font-weight: 600;
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

        .btn-forward {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #5b4b8a;
            color: #fff;
        }

        .btn-forward:hover {
            background: #4a3d72;
        }

        .btn-forward:disabled {
            background: #b8b0cc;
            cursor: not-allowed;
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
                <h1 class="page-title">Track Complaint</h1>
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
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search by complaint ID, complainant, defendant, or contact number..." onkeyup="filterComplaints()">
                </div>
                
                <div class="table-container">
                    <table id="complaintsTable">
                        <thead>
                            <tr>
                                <th>Complaint ID</th>
                                <th>Complainant</th>
                                <th>Defendant</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Time</th>
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
    
    <!-- Manage Complaint Modal -->
    <div id="manageComplaintModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Manage Complaint</h2>
                <button class="close-modal" onclick="closeManageComplaintModal()">&times;</button>
            </div>
            <p class="manage-complaint-ref" id="manageComplaintRef"></p>
            <form id="manageComplaintForm" onsubmit="saveComplaintManage(event)">
                <input type="hidden" id="manageComplaintId" name="complaintId">

                <div class="form-group" id="manageAssignedPatrolGroup">
                    <label for="manageAssignedPatrol">Assign BPSO Personnel</label>
                    <select id="manageAssignedPatrol" name="assignedPatrol">
                        <option value="">Pending Assignment</option>
                    </select>
                    <small style="display:block;margin-top:0.35rem;color:var(--text-secondary);font-size:0.85rem;">Only personnel <strong>currently at the barangay hall</strong> (timed in today) are shown.</small>
                </div>

                <div class="form-group">
                    <label for="manageStatus">Status *</label>
                    <select id="manageStatus" name="status" required>
                        <option value="">Select Status</option>
                        <option value="Pending">Pending</option>
                        <option value="Processing">Processing</option>
                        <option value="Resolved">Resolved</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Forwarded to Digital Blotter">Forwarded to Digital Blotter</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="manageNotes">Admin Notes (internal)</label>
                    <textarea id="manageNotes" name="notes" placeholder="Internal notes visible to admins only"></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-forward" id="manageForwardBtn" onclick="forwardComplaintFromManage()">Forward to Digital Blotter</button>
                    <button type="button" class="btn-cancel" onclick="closeManageComplaintModal()">Cancel</button>
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
        
        function formatIncidentTime(timeValue) {
            if (!timeValue) return '—';
            const normalized = timeValue.length === 5 ? `${timeValue}:00` : timeValue;
            return new Date(`1970-01-01T${normalized}`).toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true,
                timeZone: 'Asia/Manila'
            });
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
        let originalAssignedPatrolId = '';

        function statusClass(status) {
            const map = {
                'Pending': 'pending',
                'Processing': 'processing',
                'Resolved': 'resolved',
                'Rejected': 'rejected',
                'Forwarded to Digital Blotter': 'forwarded'
            };
            return map[status] || String(status || '').toLowerCase().replace(/\s+/g, '-');
        }

        function formatComplaintTypeLabel(complaint) {
            const type = (complaint.complaint_type || '').trim();
            const other = (complaint.complaint_type_other || '').trim();
            if (type === 'Other' && other !== '') {
                return 'Other — ' + other;
            }
            const legacyMatch = type.match(/^Other:\s*(.+)$/i);
            if (legacyMatch) {
                return 'Other — ' + legacyMatch[1].trim();
            }
            return type || 'Unknown';
        }

        function toggleManageFieldsForForwarded(complaint) {
            const forwarded = isComplaintForwarded(complaint);
            const patrolGroup = document.getElementById('manageAssignedPatrolGroup');
            const statusSelect = document.getElementById('manageStatus');
            if (patrolGroup) {
                patrolGroup.style.display = forwarded ? 'none' : '';
            }
            if (statusSelect) {
                statusSelect.disabled = forwarded;
            }
        }

        function isComplaintForwarded(complaint) {
            return Boolean(complaint && (complaint.forwarded_at || complaint.status === 'Forwarded to Digital Blotter'));
        }

        function updateForwardButtonState(button, complaint) {
            if (!button) {
                return;
            }
            const forwarded = isComplaintForwarded(complaint);
            button.disabled = forwarded;
            button.textContent = forwarded ? 'Already Forwarded' : 'Forward to Digital Blotter';
        }

        function updateAssignedPatrolFieldVisibility(complaint) {
            toggleManageFieldsForForwarded(complaint);
        }

        async function loadAvailablePersonnel(selectedPatrolId) {
            const select = document.getElementById('manageAssignedPatrol');
            if (!select) return;

            select.innerHTML = '<option value="">Pending Assignment</option>';

            try {
                const [patrolResponse, hallResponse] = await Promise.all([
                    fetch('api/patrols.php'),
                    fetch('api/bpso_attendance.php?view=at_hall')
                ]);
                const result = await patrolResponse.json();
                const hallResult = await hallResponse.json();
                if (!result.success || !result.data) return;

                const atHallIds = new Set(
                    (hallResult.success ? (hallResult.data || []) : [])
                        .map(row => String(row.patrol_id))
                );

                result.data
                    .filter(p => atHallIds.has(String(p.id)) || String(p.id) === String(selectedPatrolId || ''))
                    .forEach(personnel => {
                        const option = document.createElement('option');
                        option.value = personnel.id;
                        const atHall = atHallIds.has(String(personnel.id));
                        const statusLabel = atHall ? 'At Hall' : 'Not at Hall';
                        option.textContent = `${personnel.bpso_personnel_id} - ${personnel.personnel_name} (${statusLabel})`;
                        if (String(personnel.id) === String(selectedPatrolId || '')) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });
            } catch (e) {
                console.error('Error loading BPSO personnel:', e);
            }
        }
        
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
                    const formattedTime = formatIncidentTime(c.incident_time);
                    
                    row.innerHTML = `
                        <td>${c.complaint_id}</td>
                        <td>${c.complainant_name}</td>
                        <td>${c.defendant_name || '—'}</td>
                        <td>${formatComplaintTypeLabel(c)}</td>
                        <td>${formattedDate}</td>
                        <td>${formattedTime}</td>
                        <td><span class="status-badge status-${statusClass(c.status)}">${c.status}</span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-view" onclick="viewComplaint('${c.complaint_id}')">View</button>
                                <button class="btn-manage" onclick="manageComplaint('${c.complaint_id}')">Manage</button>
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
            const formattedTime = formatIncidentTime(complaint.incident_time);
            
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
                    <span class="status-badge status-${statusClass(complaint.status)}">${complaint.status}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Complainant's Name:</span>
                    <span class="detail-value">${complaint.complainant_name}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Complainant's Address:</span>
                    <span class="detail-value">${complaint.address}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Complainant's Contact Number:</span>
                    <span class="detail-value">${complaint.contact_number}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">${formattedIncidentDate}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Time:</span>
                    <span class="detail-value">${formattedTime}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Defendant's Name:</span>
                    <span class="detail-value">${complaint.defendant_name || 'N/A'}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Defendant's Address:</span>
                    <span class="detail-value">${complaint.defendant_address || 'N/A'}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Defendant's Contact Number:</span>
                    <span class="detail-value">${complaint.defendant_contact_number || 'N/A'}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Complaint Type:</span>
                    <span class="detail-value">${formatComplaintTypeLabel(complaint)}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Description:</span>
                    <div class="detail-value description">${complaint.description}</div>
                </div>
                
                ${!isComplaintForwarded(complaint) ? `
                <div class="detail-row">
                    <span class="detail-label">Assigned To:</span>
                    <span class="detail-value">${complaint.assigned_to || 'Pending Assignment'}</span>
                </div>
                ` : ''}
                
                ${complaint.resolution_report ? `
                <div class="detail-row">
                    <span class="detail-label">Resolution Report:</span>
                    <div class="detail-value description">${complaint.resolution_report}</div>
                </div>
                ` : ''}
                
                ${complaint.resolved_at ? `
                <div class="detail-row">
                    <span class="detail-label">Resolved At:</span>
                    <span class="detail-value">${new Date(complaint.resolved_at).toLocaleString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: true })}</span>
                </div>
                ` : ''}
                
                ${complaint.blotter_reference_id ? `
                <div class="detail-row">
                    <span class="detail-label">Digital Blotter Reference:</span>
                    <span class="detail-value"><strong>${complaint.blotter_reference_id}</strong></span>
                </div>
                ` : ''}

                ${complaint.forwarded_at ? `
                <div class="detail-row">
                    <span class="detail-label">Forwarded At:</span>
                    <span class="detail-value">${new Date(complaint.forwarded_at).toLocaleString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: true })}</span>
                </div>
                ` : ''}
                
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
        
        function manageComplaint(complaintId) {
            const complaint = complaintData[complaintId];
            if (!complaint) {
                alert('Complaint details not found for: ' + complaintId);
                return;
            }

            document.getElementById('manageComplaintId').value = complaint.id;
            document.getElementById('manageComplaintRef').textContent = 'Complaint ID: ' + complaint.complaint_id;
            document.getElementById('manageStatus').value = complaint.status;
            document.getElementById('manageNotes').value = complaint.notes || '';
            originalAssignedPatrolId = complaint.assigned_patrol_id || '';
            loadAvailablePersonnel(originalAssignedPatrolId);
            updateForwardButtonState(document.getElementById('manageForwardBtn'), complaint);
            updateAssignedPatrolFieldVisibility(complaint);

            document.getElementById('manageComplaintModal').classList.add('active');
        }

        function closeManageComplaintModal() {
            document.getElementById('manageComplaintModal').classList.remove('active');
            document.getElementById('manageComplaintForm').reset();
            document.getElementById('manageStatus').disabled = false;
        }

        function forwardComplaintFromManage() {
            const id = parseInt(document.getElementById('manageComplaintId').value, 10);
            if (!id) {
                alert('Invalid complaint ID.');
                return;
            }
            forwardComplaintById(id);
        }

        function saveComplaintManage(event) {
            event.preventDefault();

            const id = parseInt(document.getElementById('manageComplaintId').value);
            if (!id) {
                alert('Invalid complaint ID!');
                return;
            }

            const complaint = Object.values(complaintData).find(c => c.id === id);
            if (!complaint) {
                alert('Complaint not found!');
                return;
            }

            const newPatrolId = document.getElementById('manageAssignedPatrol').value;
            const assignmentChanged = String(newPatrolId || '') !== String(originalAssignedPatrolId || '');

            const saveManage = () => fetch('api/complaints.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'manage',
                    id: id,
                    status: document.getElementById('manageStatus').value,
                    notes: document.getElementById('manageNotes').value.trim()
                })
            }).then(res => res.json());

            const assignIfNeeded = () => {
                if (!assignmentChanged || isComplaintForwarded(complaint)) {
                    return Promise.resolve({ success: true });
                }
                return fetch('api/complaints.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'assign',
                        id: id,
                        assigned_patrol_id: parseInt(newPatrolId, 10) || 0
                    })
                }).then(res => res.json());
            };

            assignIfNeeded()
                .then(assignResult => {
                    if (!assignResult.success) {
                        alert(assignResult.message || 'Failed to assign complaint.');
                        return null;
                    }
                    return saveManage();
                })
                .then(result => {
                    if (!result) return;
                    if (!result.success) {
                        alert(result.message || 'Failed to update complaint.');
                        return;
                    }
                    loadComplaints();
                    alert('Complaint updated successfully!');
                    closeManageComplaintModal();
                })
                .catch(err => {
                    console.error('Error updating complaint:', err);
                    alert('Error updating complaint. Please try again.');
                });
        }

        function forwardComplaintById(dbId) {
            const complaint = Object.values(complaintData).find(c => c.id === dbId);
            if (!complaint) {
                alert('Complaint not found.');
                return;
            }

            if (isComplaintForwarded(complaint)) {
                alert('This complaint was already forwarded to the Digital Blotter System.');
                return;
            }

            if (!confirm('Forward this complaint to the Digital Blotter System? Group 1 will receive the full complaint details via API.')) {
                return;
            }

            const forwardBtn = document.getElementById('manageForwardBtn');
            if (forwardBtn) {
                forwardBtn.disabled = true;
                forwardBtn.textContent = 'Forwarding...';
            }

            fetch('api/complaints.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'forward', id: dbId })
            })
            .then(response => response.json().then(data => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (!ok || !data.success) {
                    throw new Error(data.message || 'Failed to forward complaint.');
                }

                complaint.status = data.data?.status || 'Forwarded to Digital Blotter';
                complaint.forwarded_at = data.data?.forwarded_at || new Date().toISOString();
                complaint.blotter_reference_id = data.data?.blotter_reference_id || '';
                if (data.data?.blotter_reference_id) {
                    const timestamp = new Date().toLocaleString('en-US');
                    const refNote = `[${timestamp}] Forwarded to Digital Blotter System (Ref: ${data.data.blotter_reference_id}).`;
                    complaint.notes = (complaint.notes || '') + '\n\n' + refNote;
                }

                loadComplaints();

                let message = data.message || 'Complaint forwarded successfully.';
                if (data.data?.blotter_reference_id) {
                    message += '\nDigital Blotter Reference: ' + data.data.blotter_reference_id;
                }
                alert(message);

                updateForwardButtonState(forwardBtn, complaint);
                updateAssignedPatrolFieldVisibility(complaint);
            })
            .catch(error => {
                alert(error.message || 'Failed to forward complaint.');
                updateForwardButtonState(forwardBtn, complaint);
            });
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const viewModal = document.getElementById('complaintModal');
            const manageModal = document.getElementById('manageComplaintModal');
            
            if (event.target == viewModal) {
                closeComplaintModal();
            }
            if (event.target == manageModal) {
                closeManageComplaintModal();
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
    </script>
    <?php require __DIR__ . '/includes/admin_notifications_script.php'; ?>
    <script src="js/mobile-shell.js"></script>
</body>
</html>


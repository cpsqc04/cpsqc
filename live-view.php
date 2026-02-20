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
        .badge-weapon { background: #f8d7da; color: #842029; }
        .object-details { font-size: 0.9rem; line-height: 1.6; }
        .object-details p { margin: 0.25rem 0; }
        .object-details strong { color: var(--tertiary-color); }
        .live-feed-container { flex: 1; background: #000; border-radius: 12px; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .live-feed-container:hover { transform: scale(1.01); box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); }
        /* Ensure container can hold double-buffered images */
        .live-feed-container > img { position: absolute; top: 0; left: 0; }
        .live-feed-placeholder { width: 100%; height: 100%; background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; position: relative; }
        .live-video-stream { 
            width: 100%; 
            height: 100%; 
            object-fit: contain; 
            display: block; 
            image-rendering: auto; /* Smooth rendering for video */
            opacity: 1;
            visibility: visible;
            /* Force hardware acceleration for smooth rendering */
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            transform: translateZ(0);
            -webkit-transform: translateZ(0);
            will-change: contents;
            /* Optimize rendering */
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        /* Dual buffer styling - prevents glitching with instant switching */
        .stream-buffer {
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0;
            visibility: hidden;
            /* NO transitions - instant switch prevents flickering */
            transition: none !important;
        }
        .stream-buffer.active {
            opacity: 1;
            visibility: visible;
            z-index: 2;
        }
        .stream-buffer.loading {
            opacity: 0;
            visibility: hidden;
            z-index: 1;
        }
        .stream-loading { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; text-align: center; }
        .stream-loading i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        .stream-error { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #ff6b6b; text-align: center; padding: 2rem; }
        .stream-error i { font-size: 3rem; margin-bottom: 1rem; }
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
        
        /* Multi-Camera Grid Modal */
        .camera-grid-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 10000;
            padding: 2rem;
            overflow-y: auto;
        }
        .camera-grid-modal.active {
            display: flex;
            flex-direction: column;
        }
        .camera-grid-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            color: #fff;
        }
        .camera-grid-header h2 {
            margin: 0;
            font-size: 2rem;
            color: #fff;
        }
        .camera-grid-close {
            background: var(--primary-color);
            color: #fff;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.2s ease;
        }
        .camera-grid-close:hover {
            background: #4ca8a6;
        }
        .camera-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            flex: 1;
        }
        .camera-grid-item {
            aspect-ratio: 16/9;
            background: #1a1a1a;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
            border: 2px solid #333;
            cursor: pointer;
            transition: border-color 0.2s ease, transform 0.2s ease;
        }
        .camera-grid-item:hover {
            border-color: var(--primary-color);
            transform: scale(1.02);
        }
        .camera-grid-item.active {
            border-color: var(--primary-color);
            border-width: 3px;
        }
        .camera-grid-item:not(.empty) {
            cursor: pointer;
        }
        .camera-grid-item:not(.empty)::after {
            content: 'Double-click for fullscreen';
            position: absolute;
            bottom: 0.5rem;
            left: 0.5rem;
            right: 0.5rem;
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            text-align: center;
            opacity: 0;
            transition: opacity 0.2s ease;
            pointer-events: none;
        }
        .camera-grid-item:not(.empty):hover::after {
            opacity: 1;
        }
        
        /* Fullscreen Camera View */
        .camera-fullscreen-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.98);
            z-index: 20000;
            padding: 2rem;
        }
        .camera-fullscreen-modal.active {
            display: flex;
            flex-direction: column;
        }
        .fullscreen-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            color: #fff;
        }
        .fullscreen-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: #fff;
        }
        .fullscreen-close {
            background: var(--primary-color);
            color: #fff;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.2s ease;
        }
        .fullscreen-close:hover {
            background: #4ca8a6;
        }
        .fullscreen-video-container {
            flex: 1;
            background: #000;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .fullscreen-video-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .fullscreen-info {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            padding: 1rem;
            border-radius: 8px;
            z-index: 10;
        }
        .fullscreen-info p {
            margin: 0.25rem 0;
        }
        .camera-grid-item img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .camera-grid-item.empty {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 1rem;
            color: #666;
        }
        .camera-grid-item.empty:hover {
            background: #252525;
            color: var(--primary-color);
        }
        .camera-grid-item.empty i {
            font-size: 4rem;
        }
        .camera-grid-item.empty span {
            font-size: 1.2rem;
            font-weight: 500;
        }
        .camera-label {
            position: absolute;
            top: 0.5rem;
            left: 0.5rem;
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
        }
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
            <!-- Dashboard Link -->
            <a href="index.php" class="nav-module-header" data-tooltip="Dashboard" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.5rem; color: rgba(255, 255, 255, 0.9); cursor: pointer; transition: background-color 0.2s ease; font-weight: 500; user-select: none; gap: 0.75rem; <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'background: rgba(76, 138, 137, 0.25); border-left: 3px solid #4c8a89;' : ''; ?>">
                <span class="nav-module-icon"><i class="fas fa-home"></i></span>
                <span class="nav-module-header-text">Dashboard</span>
            </a>
            
            <!-- User Management Link -->
            <a href="user-management.php" class="nav-module-header" data-tooltip="User Management" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.5rem; color: rgba(255, 255, 255, 0.9); cursor: pointer; transition: background-color 0.2s ease; font-weight: 500; user-select: none; gap: 0.75rem; <?php echo basename($_SERVER['PHP_SELF']) == 'user-management.php' ? 'background: rgba(76, 138, 137, 0.25); border-left: 3px solid #4c8a89;' : ''; ?>">
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
            <div class="nav-module active">
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="CCTV Surveillance System Management">
                    <span class="nav-module-icon"><i class="fas fa-video"></i></span>
                    <span class="nav-module-header-text">CCTV Surveillance System Management</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="open-surveillance-app.php" class="nav-submodule active" data-tooltip="Open Surveillance App">
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
                    <div class="live-feed-container" onclick="openCameraGrid()" title="Click to view all cameras">
                        <!-- Dual buffering: Two images for seamless frame swapping -->
                        <img id="liveStream1" class="live-video-stream stream-buffer" src="" alt="Live Camera Feed" style="display: none;" loading="eager" decoding="async">
                        <img id="liveStream2" class="live-video-stream stream-buffer" src="" alt="Live Camera Feed" style="display: none;" loading="eager" decoding="async">
                        <div id="streamLoading" class="stream-loading">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Connecting to camera stream...</p>
                        </div>
                        <div id="streamError" class="stream-error" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p>Unable to load camera stream</p>
                            <p style="font-size: 0.9rem; margin-top: 0.5rem;">Please ensure the detection script is running</p>
                        </div>
                        <div class="live-feed-placeholder" id="streamPlaceholder" style="display: none;">
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
                        </div>
                        <!-- Click hint -->
                        <div style="position: absolute; bottom: 1rem; right: 1rem; background: rgba(0, 0, 0, 0.7); color: #fff; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.85rem; pointer-events: none;">
                            <i class="fas fa-expand"></i> Click to view all cameras
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Multi-Camera Grid Modal -->
    <div id="cameraGridModal" class="camera-grid-modal">
        <div class="camera-grid-header">
            <h2>Camera Grid View</h2>
            <button class="camera-grid-close" onclick="closeCameraGrid()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
        <div class="camera-grid" id="cameraGrid">
            <!-- Cameras will be loaded dynamically -->
        </div>
    </div>
    
    <!-- Fullscreen Camera View Modal -->
    <div id="cameraFullscreenModal" class="camera-fullscreen-modal">
        <div class="fullscreen-header">
            <h2 id="fullscreenCameraName">Camera View</h2>
            <button class="fullscreen-close" onclick="closeFullscreenView()">
                <i class="fas fa-times"></i> Close Fullscreen
            </button>
        </div>
        <div class="fullscreen-video-container">
            <img id="fullscreenVideo" src="" alt="Fullscreen Camera Feed">
            <div class="fullscreen-info" id="fullscreenInfo">
                <p><strong id="fullscreenCameraId">CAM-001</strong></p>
                <p id="fullscreenLocation">Location</p>
            </div>
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
            updateDateTime();
            setInterval(updateDateTime, 1000);
            
            // Start live stream immediately - no delays
            startLiveStream();
            
            loadDetectedObjects();
            setInterval(loadDetectedObjects, 2000); // Refresh detections every 2 seconds
            
            // Additional safety: Force start if stream doesn't start after 1 second
            setTimeout(function() {
                const streamImg = document.getElementById('liveStream');
                if (streamImg && (!streamImg.src || streamImg.src === '')) {
                    console.log('Stream not started, forcing start...');
                    startLiveStream();
                }
            }, 1000);
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
        // Stream state management - Dual buffer system for glitch-free playback
        let streamErrorCount = 0;
        let lastSuccessfulLoad = Date.now();
        let streamRefreshTimer = null;
        let isImageLoading = false;
        let currentFrameIndex = 0;
        let activeBuffer = 1; // 1 or 2 - which buffer is currently visible
        let frameFiles = ['current_frame.jpg', 'current_frame_alt.jpg'];
        
        function startLiveStream() {
            const streamImg1 = document.getElementById('liveStream1');
            const streamImg2 = document.getElementById('liveStream2');
            const loadingDiv = document.getElementById('streamLoading');
            const errorDiv = document.getElementById('streamError');
            const placeholderDiv = document.getElementById('streamPlaceholder');
            
            // Clear any existing timers
            if (streamRefreshTimer) {
                clearInterval(streamRefreshTimer);
                streamRefreshTimer = null;
            }
            
            // Show loading indicator initially
            loadingDiv.style.display = 'block';
            errorDiv.style.display = 'none';
            placeholderDiv.style.display = 'none';
            streamImg1.style.display = 'none';
            streamImg2.style.display = 'none';
            
            // Get currently active and inactive buffers
            function getBuffers() {
                const active = activeBuffer === 1 ? streamImg1 : streamImg2;
                const inactive = activeBuffer === 1 ? streamImg2 : streamImg1;
                return { active, inactive };
            }
            
            // Switch active buffer (instant switch - no transition to prevent flickering)
            function switchBuffer() {
                const { active, inactive } = getBuffers();
                
                // Instant switch - remove active from current, add to new
                active.classList.remove('active');
                active.classList.add('loading');
                
                // Make inactive buffer active instantly
                inactive.classList.remove('loading');
                inactive.classList.add('active');
                
                // Update active buffer index
                activeBuffer = activeBuffer === 1 ? 2 : 1;
            }
            
            // Unified load handler for both buffers
            function setupImageHandlers(img, bufferNum) {
                img.onload = function() {
                    isImageLoading = false;
                    streamErrorCount = 0;
                    lastSuccessfulLoad = Date.now();
                    
                    // Hide loading/error states
                    loadingDiv.style.display = 'none';
                    errorDiv.style.display = 'none';
                    placeholderDiv.style.display = 'none';
                    
                    // Show the buffer
                    img.style.display = 'block';
                    
                    // If this is the inactive buffer that just loaded, switch to it
                    const { active, inactive } = getBuffers();
                    if (img === inactive) {
                        // Inactive buffer finished loading - switch to it smoothly
                        switchBuffer();
                    } else if (img === active) {
                        // Active buffer loaded - ensure it's visible (initial load)
                        img.classList.add('active');
                        img.classList.remove('loading');
                    }
                };
                
                img.onerror = function() {
                    isImageLoading = false;
                    streamErrorCount++;
                    const timeSinceLastSuccess = Date.now() - lastSuccessfulLoad;
                    
                    // Only show error after extended failure
                    if (timeSinceLastSuccess > 5000 && streamErrorCount > 20) {
                        loadingDiv.style.display = 'none';
                        errorDiv.style.display = 'block';
                        placeholderDiv.style.display = 'none';
                        streamImg1.style.display = 'none';
                        streamImg2.style.display = 'none';
                    } else if (timeSinceLastSuccess > 3000) {
                        loadingDiv.style.display = 'block';
                        errorDiv.style.display = 'none';
                    }
                    // Otherwise keep showing current active buffer
                };
            }
            
            // Setup handlers for both buffers
            setupImageHandlers(streamImg1, 1);
            setupImageHandlers(streamImg2, 2);
            
            // Dual buffer refresh - eliminates glitching with proper state management
            function refreshFrame() {
                // Skip if currently loading - prevents queue buildup
                if (isImageLoading) {
                    return;
                }
                
                // Get inactive buffer (the one we'll load into)
                const { inactive, active } = getBuffers();
                
                // Ensure inactive buffer is properly hidden before loading
                inactive.classList.remove('active');
                inactive.classList.add('loading');
                
                isImageLoading = true;
                
                // Alternate between files for double buffering
                currentFrameIndex = (currentFrameIndex + 1) % 2;
                const imageUrl = frameFiles[currentFrameIndex];
                
                // Use timestamp for cache busting - ensures fresh frames
                const cacheBuster = '?t=' + Date.now();
                const newSrc = imageUrl + cacheBuster;
                
                // Only change src if it's different - prevents unnecessary reloads
                if (inactive.src && inactive.src.split('?')[0].endsWith(imageUrl)) {
                    // Same file, just update cache buster
                    inactive.src = newSrc;
                } else {
                    // Different file or first load
                    inactive.src = newSrc;
                }
            }
            
            // Initial load
            streamImg1.src = frameFiles[0] + '?t=' + Date.now();
            
            // Refresh rate - 10 FPS (100ms) for stable, flicker-free playback
            // Slower refresh reduces flickering and ensures smooth updates
            streamRefreshTimer = setInterval(function() {
                refreshFrame();
            }, 100); // 10 FPS = 100ms intervals - stable and flicker-free, glitch-free
        }
        
        // Global handlers for HTML onload/onerror attributes (keep for compatibility)
        function handleStreamLoad() {
            // This is handled by the onload handler in startLiveStream
            // Keep for compatibility but it shouldn't be called directly
        }
        
        function handleStreamError() {
            // This is handled by the onerror handler in startLiveStream
            // Keep for compatibility but it shouldn't be called directly
        }
        
        function loadDetectedObjects() {
            fetch('api/get_detections.php')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('detectedObjectsList');
                    
                    if (!data.success || !data.detections || data.detections.length === 0) {
                        container.innerHTML = '<div class="empty-state"><i class="fas fa-eye-slash"></i><p>No objects detected</p></div>';
                        return;
                    }
                    
                    // Clear existing content
                    container.innerHTML = '';
                    
                    // Process each detection
                    data.detections.forEach((detection, index) => {
                        const card = document.createElement('div');
                        card.className = 'detected-object-card';
                        
                        const category = detection.category || 'unknown';
                        const badgeClass = category === 'person' ? 'badge-person' : 
                                          category === 'animal' ? 'badge-animal' : 
                                          category === 'vehicle' ? 'badge-vehicle' :
                                          category === 'weapon' ? 'badge-weapon' : 'badge-person';
                        const badgeText = category.charAt(0).toUpperCase() + category.slice(1);
                        
                        // Get emoji/icon for category
                        // Format timestamp
                        const detectionTime = new Date(detection.timestamp || Date.now());
                        const timeStr = detectionTime.toLocaleTimeString();
                        
                        // Use actual detected object image if available, otherwise use emoji placeholder
                        let imageHTML = '';
                        if (detection.image) {
                            // Display actual cropped object image
                            imageHTML = `<img src="${detection.image}" alt="${category}" style="width: 100%; height: 150px; object-fit: cover; border-radius: 6px;" onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\\'width:100%;height:150px;display:flex;align-items:center;justify-content:center;font-size:3rem;\\'>${category === 'person' ? '👤' : category === 'animal' ? '🐾' : category === 'vehicle' ? '🚗' : category === 'weapon' ? '⚠️' : '📦'}</div>';">`;
                        } else {
                            // Fallback to emoji if image not available
                            const categoryEmoji = category === 'person' ? '👤' : 
                                                category === 'animal' ? '🐾' : 
                                                category === 'vehicle' ? '🚗' :
                                                category === 'weapon' ? '⚠️' : '📦';
                            imageHTML = `<div style="width: 100%; height: 150px; display: flex; align-items: center; justify-content: center; font-size: 3rem;">${categoryEmoji}</div>`;
                        }
                        
                        let detailsHTML = '';
                        if (category === 'person') {
                            const gender = detection.gender || 'Unknown';
                            const expression = detection.expression || 'calm';
                            // Handle accessories: if null, undefined, empty string, or "None", show "None"
                            const accessories = (detection.accessories && detection.accessories.trim() !== '' && detection.accessories !== 'null') 
                                ? detection.accessories 
                                : 'None';
                            const clothesColor = detection.clothes_color || 'Unknown';
                            
                            detailsHTML = `
                                <p><strong>Gender:</strong> ${gender}</p>
                                <p><strong>Expression:</strong> ${expression.charAt(0).toUpperCase() + expression.slice(1)}</p>
                                <p><strong>Accessories:</strong> ${accessories}</p>
                                <p><strong>Clothes Color:</strong> ${clothesColor.charAt(0).toUpperCase() + clothesColor.slice(1)}</p>
                                ${detection.weapon ? `<p style="color: #dc3545; font-weight: bold;">⚠️ Weapon: ${detection.weapon.class} (${(detection.weapon.confidence * 100).toFixed(1)}%)</p>` : ''}
                                <p><strong>Confidence:</strong> ${(detection.confidence * 100).toFixed(1)}%</p>
                                <p><strong>Detected:</strong> ${timeStr}</p>
                            `;
                            
                            // If person has weapon, also add weapon image below person image
                            if (detection.weapon && detection.weapon.image) {
                                imageHTML += `<img src="${detection.weapon.image}" alt="Weapon: ${detection.weapon.class}" style="width: 100%; height: 100px; object-fit: cover; border-radius: 6px; margin-top: 0.5rem; border: 2px solid #dc3545;" onerror="this.style.display='none';">`;
                            }
                        } else if (category === 'animal') {
                            detailsHTML = `
                                <p><strong>Class:</strong> ${detection.class || 'Animal'}</p>
                                <p><strong>Confidence:</strong> ${(detection.confidence * 100).toFixed(1)}%</p>
                                <p><strong>Detected:</strong> ${timeStr}</p>
                            `;
                        } else if (category === 'vehicle') {
                            detailsHTML = `
                                <p><strong>Class:</strong> ${detection.class || 'Vehicle'}</p>
                                <p><strong>Confidence:</strong> ${(detection.confidence * 100).toFixed(1)}%</p>
                                <p><strong>Detected:</strong> ${timeStr}</p>
                            `;
                        } else if (category === 'weapon') {
                            detailsHTML = `
                                <p><strong>Class:</strong> ${detection.class || 'Weapon'}</p>
                                <p><strong>Confidence:</strong> ${(detection.confidence * 100).toFixed(1)}%</p>
                                <p><strong>Detected:</strong> ${timeStr}</p>
                                <p style="color: #dc3545; font-weight: bold;">⚠️ Alert: Weapon Detected</p>
                            `;
                        } else {
                            detailsHTML = `
                                <p><strong>Class:</strong> ${detection.class || 'Object'}</p>
                                <p><strong>Confidence:</strong> ${(detection.confidence * 100).toFixed(1)}%</p>
                                <p><strong>Detected:</strong> ${timeStr}</p>
                            `;
                        }
                        
                        card.innerHTML = `
                            <span class="object-type-badge ${badgeClass}">${badgeText}</span>
                            <div class="object-image">${imageHTML}</div>
                            <div class="object-details">
                                ${detailsHTML}
                            </div>
                        `;
                        
                        container.appendChild(card);
                    });
                })
                .catch(error => {
                    console.error('Error loading detections:', error);
                    const container = document.getElementById('detectedObjectsList');
                    container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>Error loading detections</p></div>';
                });
        }
        
        // Camera data
        let camerasData = [];
        let activeCameraId = '1'; // Default active camera
        
        // Load cameras from API
        function loadCamerasForGrid() {
            fetch('api/cameras.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.cameras) {
                        camerasData = data.cameras;
                        populateCameraGrid();
                    }
                })
                .catch(error => {
                    console.error('Error loading cameras:', error);
                    // Fallback to default camera
                    populateCameraGrid();
                });
        }
        
        function populateCameraGrid() {
            const grid = document.getElementById('cameraGrid');
            if (!grid) return;
            
            grid.innerHTML = '';
            
            // Create up to 4 camera slots
            for (let i = 0; i < 4; i++) {
                if (i < camerasData.length) {
                    const camera = camerasData[i];
                    const cameraItem = document.createElement('div');
                    cameraItem.className = 'camera-grid-item';
                    if (camera.id === activeCameraId) {
                        cameraItem.classList.add('active');
                    }
                    cameraItem.setAttribute('data-camera-id', camera.id);
                    
                    // Double-click to view fullscreen and set as active
                    cameraItem.ondblclick = function() {
                        showCameraFullscreen(camera);
                        setActiveCamera(camera.id);
                    };
                    
                    // Single click to select (visual feedback)
                    cameraItem.onclick = function(e) {
                        // Don't trigger if clicking on label
                        if (e.target.classList.contains('camera-label')) return;
                        
                        // Update active state
                        document.querySelectorAll('.camera-grid-item').forEach(item => {
                            item.classList.remove('active');
                        });
                        cameraItem.classList.add('active');
                    };
                    
                    cameraItem.innerHTML = `
                        <div class="camera-label">${camera.cameraId}</div>
                        <img id="gridCamera${i}" src="current_frame.jpg" alt="${camera.name}" onerror="this.parentElement.classList.add('empty')">
                    `;
                    
                    grid.appendChild(cameraItem);
                } else {
                    // Empty slot
                    const emptyItem = document.createElement('div');
                    emptyItem.className = 'camera-grid-item empty';
                    emptyItem.onclick = redirectToAddCamera;
                    emptyItem.innerHTML = `
                        <i class="fas fa-video-slash"></i>
                        <span>Add Camera</span>
                    `;
                    grid.appendChild(emptyItem);
                }
            }
        }
        
        function setActiveCamera(cameraId) {
            activeCameraId = cameraId;
            // Update main live view indicator (if needed)
            const camera = camerasData.find(c => c.id === cameraId);
            if (camera) {
                // Store active camera in localStorage for persistence
                localStorage.setItem('activeCameraId', cameraId);
                
                // Update active state in grid
                document.querySelectorAll('.camera-grid-item').forEach(item => {
                    item.classList.remove('active');
                    if (item.getAttribute('data-camera-id') === cameraId) {
                        item.classList.add('active');
                    }
                });
            }
        }
        
        function showCameraFullscreen(camera) {
            const modal = document.getElementById('cameraFullscreenModal');
            const video = document.getElementById('fullscreenVideo');
            const nameEl = document.getElementById('fullscreenCameraName');
            const idEl = document.getElementById('fullscreenCameraId');
            const locationEl = document.getElementById('fullscreenLocation');
            
            if (!modal || !video) return;
            
            // Update info
            nameEl.textContent = camera.name || 'Camera View';
            idEl.textContent = camera.cameraId || 'CAM-001';
            locationEl.textContent = camera.location || 'Location';
            
            // Start loading camera feed (for now, all use current_frame.jpg - in future could use different endpoints)
            video.src = 'current_frame.jpg?t=' + Date.now();
            
            modal.classList.add('active');
            
            // Start refreshing fullscreen view
            refreshFullscreenCamera();
        }
        
        function closeFullscreenView() {
            const modal = document.getElementById('cameraFullscreenModal');
            if (modal) {
                modal.classList.remove('active');
            }
        }
        
        function refreshFullscreenCamera() {
            const modal = document.getElementById('cameraFullscreenModal');
            if (!modal || !modal.classList.contains('active')) return;
            
            const video = document.getElementById('fullscreenVideo');
            if (video) {
                const timestamp = '?t=' + Date.now();
                video.src = 'current_frame.jpg' + timestamp;
            }
            
            // Continue refreshing while fullscreen is open
            setTimeout(refreshFullscreenCamera, 500); // Refresh every 500ms
        }
        
        // Camera Grid Modal Functions
        function openCameraGrid() {
            const modal = document.getElementById('cameraGridModal');
            if (modal) {
                modal.classList.add('active');
                loadCamerasForGrid();
                refreshGridCameras();
            }
        }
        
        function closeCameraGrid() {
            const modal = document.getElementById('cameraGridModal');
            if (modal) {
                modal.classList.remove('active');
            }
        }
        
        function refreshGridCameras() {
            const modal = document.getElementById('cameraGridModal');
            if (!modal || !modal.classList.contains('active')) return;
            
            // Refresh all camera feeds in grid
            camerasData.forEach((camera, index) => {
                const img = document.getElementById(`gridCamera${index}`);
                if (img && !img.parentElement.classList.contains('empty')) {
                    const timestamp = '?t=' + Date.now();
                    img.src = 'current_frame.jpg' + timestamp;
                }
            });
            
            // Continue refreshing while modal is open
            setTimeout(refreshGridCameras, 500); // Refresh every 500ms
        }
    
        function redirectToAddCamera() {
            // Redirect to camera management and open add camera modal
            window.location.href = 'camera-management.php?action=add';
        }
        
        // Close modals on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCameraGrid();
                closeFullscreenView();
            }
        });
        
        // Load active camera from localStorage on page load
        const savedActiveCamera = localStorage.getItem('activeCameraId');
        if (savedActiveCamera) {
            activeCameraId = savedActiveCamera;
        }
        loadCamerasForGrid();
    </script>
</body>
</html>


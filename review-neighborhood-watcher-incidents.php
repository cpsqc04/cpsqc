<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/admin_auth.php';

define('NW_PAGE_MODE', 'incidents');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Member Incident Reports - Alertara</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/admin-sidebar.css">
    <style>
        body { margin: 0; padding: 0; font-family: var(--font-family); background-color: var(--bg-color); display: flex; min-height: 100vh; }
        .sidebar { width: 320px; background: var(--tertiary-color); color: #fff; position: fixed; left: 0; top: 0; height: 100vh; overflow: hidden; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); z-index: 1000; transition: width 0.3s ease; display: flex; flex-direction: column; }
        .sidebar.collapsed { width: 80px; }
        .sidebar-header { padding: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 118px; flex-shrink: 0; }
        .logo-container { display: flex; flex-direction: column; align-items: center; gap: 0.35rem; }
        .logo-container img { height: 80px; width: 80px; object-fit: contain; transition: all 0.3s ease; }
        .sidebar.collapsed .logo-container img { height: 52px; width: 52px; }
        .user-name-display { color: rgba(255, 255, 255, 0.9); font-size: 0.88rem; font-weight: 500; text-align: center; padding: 0.2rem 0.75rem 0; word-break: break-word; max-width: 100%; line-height: 1.3; }
        .sidebar.collapsed .user-name-display { opacity: 0; height: 0; padding: 0; overflow: hidden; font-size: 0; }
        .sidebar-nav { padding: 0.25rem 0; overflow-y: auto; overflow-x: hidden; flex: 1; display: flex; flex-direction: column; min-height: 0; scrollbar-width: none; -ms-overflow-style: none; }
        .sidebar-nav::-webkit-scrollbar { display: none; width: 0; height: 0; }
        .nav-module { margin-bottom: 0; display: block !important; }
        .nav-module-header { display: flex; align-items: center; justify-content: space-between; padding: 0.7rem 1.25rem; color: rgba(255, 255, 255, 0.9); cursor: pointer; font-weight: 500; font-size: 0.84rem; gap: 0.65rem; line-height: 1.3; }
        .nav-module-header:hover { background: rgba(255, 255, 255, 0.08); color: #fff; }
        .nav-module.active .nav-module-header { background: rgba(255, 255, 255, 0.1); color: #fff; }
        .nav-module-icon { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .nav-module-header-text { flex: 1; }
        .sidebar.collapsed .nav-module-header-text { opacity: 0; width: 0; overflow: hidden; }
        .nav-module-header .arrow { font-size: 0.7rem; color: rgba(255, 255, 255, 0.6); transition: transform 0.3s ease; }
        .nav-module.active .nav-module-header .arrow { transform: rotate(90deg); }
        .nav-submodules { max-height: 0; overflow: hidden; transition: max-height 0.3s ease; background: rgba(0, 0, 0, 0.15); }
        .nav-module.active .nav-submodules { max-height: 500px; }
        .sidebar.collapsed .nav-submodules { display: none !important; }
        .nav-submodule { padding: 0.6rem 1.25rem 0.6rem 3rem; color: rgba(255, 255, 255, 0.75); text-decoration: none; display: flex; align-items: center; gap: 0.65rem; font-size: 0.8rem; position: relative; }
        .nav-submodule:hover { background: rgba(255, 255, 255, 0.05); color: #fff; padding-left: 3.25rem; }
        .nav-submodule.active { background: rgba(76, 138, 137, 0.25); color: #fff; border-left: 3px solid #4c8a89; font-weight: 500; }
        .nav-submodule-icon { width: 22px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .nav-submodule-text { flex: 1; }
        .sidebar-footer { margin-top: auto; padding: 0.75rem 1rem; border-top: 1px solid rgba(255, 255, 255, 0.1); flex-shrink: 0; }
        .sidebar-logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.25rem; background: rgba(239, 68, 68, 0.1); color: rgba(255, 255, 255, 0.9); text-decoration: none; border-radius: 8px; font-size: 0.95rem; font-weight: 500; border: 1px solid rgba(239, 68, 68, 0.2); width: 100%; box-sizing: border-box; }
        .sidebar-logout-btn:hover { background: rgba(239, 68, 68, 0.2); color: #fff; }
        .main-wrapper { margin-left: 320px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; transition: margin-left 0.3s ease; }
        body.sidebar-collapsed .main-wrapper { margin-left: 80px; }
        .top-header { background: var(--header-bg); padding: 1.5rem 2rem 1rem; display: flex; justify-content: space-between; align-items: flex-end; position: sticky; top: 0; z-index: 100; border-bottom: 1px solid var(--border-color); }
        .top-header-content { flex: 1; display: flex; align-items: center; gap: 1rem; }
        .content-burger-btn { background: transparent; border: none; color: var(--tertiary-color); width: 40px; height: 40px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
        .content-burger-btn span { display: block; width: 22px; height: 1.5px; background: var(--tertiary-color); position: relative; }
        .content-burger-btn span::before, .content-burger-btn span::after { content: ''; position: absolute; width: 22px; height: 1.5px; background: var(--tertiary-color); }
        .content-burger-btn span::before { top: -7px; }
        .content-burger-btn span::after { bottom: -7px; }
        .page-title { font-size: 2rem; font-weight: 700; color: var(--tertiary-color); margin: 0; }
        .user-info { display: flex; align-items: center; gap: 1rem; margin-left: 2rem; }
        .notification-container { position: relative; display: flex; align-items: center; }
        .notification-bell { position: relative; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: transparent; border: none; color: var(--text-color); font-size: 1.25rem; cursor: pointer; border-radius: 8px; transition: all 0.2s ease; }
        .notification-bell:hover { background: rgba(28, 37, 65, 0.05); color: var(--primary-color); }
        .notification-badge { position: absolute; top: 4px; right: 4px; background: #ef4444; color: white; font-size: 0.7rem; font-weight: 600; padding: 2px 6px; border-radius: 10px; min-width: 18px; text-align: center; display: none; }
        .notification-badge.show { display: block; }
        .notification-dropdown { position: absolute; top: calc(100% + 10px); right: 0; width: 380px; max-height: 500px; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15); display: none; flex-direction: column; z-index: 1000; overflow: hidden; }
        .notification-dropdown.show { display: flex; }
        .notification-header { padding: 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: var(--header-bg); }
        .notification-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--text-color); }
        .notification-header button { background: transparent; border: none; color: var(--primary-color); font-size: 0.85rem; cursor: pointer; padding: 0.25rem 0.5rem; border-radius: 4px; }
        .notification-header button:hover { background: rgba(76, 138, 137, 0.1); }
        .notification-list { flex: 1; overflow-y: auto; max-height: 400px; }
        .notification-item { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); cursor: pointer; display: flex; gap: 0.75rem; position: relative; }
        .notification-item:hover { background: #f8f9fa; }
        .notification-item.unread { background: #f0f9ff; border-left: 3px solid var(--primary-color); }
        .notification-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .notification-content { flex: 1; min-width: 0; }
        .notification-title { font-weight: 600; color: var(--text-color); font-size: 0.9rem; margin-bottom: 0.25rem; }
        .notification-message { color: var(--text-secondary); font-size: 0.85rem; line-height: 1.4; }
        .notification-time { color: var(--text-secondary); font-size: 0.75rem; margin-top: 0.35rem; }
        .notification-empty { padding: 2rem 1.25rem; text-align: center; color: var(--text-secondary); }
        .notification-empty i { font-size: 2rem; margin-bottom: 0.75rem; opacity: 0.35; display: block; }
        .datetime-display { display: flex; align-items: center; gap: 0.75rem; color: var(--text-color); font-size: 0.9rem; font-weight: 500; }
        .datetime-display .date-part { color: var(--text-secondary); }
        .datetime-display .time-part { color: var(--text-color); font-weight: 600; }
        .content-area { padding: 2rem; flex: 1; background: #f5f5f5; }
        .page-content { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; box-shadow: 0 2px 8px var(--shadow); }
        .toolbar { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .search-box { flex: 1; max-width: 420px; position: relative; }
        .search-box input { width: 100%; padding: 0.75rem 1rem 0.75rem 2.5rem; border: 1px solid var(--border-color); border-radius: 8px; box-sizing: border-box; }
        .search-box i { position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary); }
        .table-container { overflow-x: auto; border-radius: 8px; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; background: var(--card-bg); }
        thead { background: var(--tertiary-color); color: #fff; }
        th { padding: 1rem; text-align: left; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-color); font-size: 0.92rem; }
        tbody tr:hover { background: #f9f9f9; }
        tbody tr:last-child td { border-bottom: none; }
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-view, .btn-manage { padding: 0.5rem 1rem; border: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; color: #fff; }
        .btn-view { background: var(--primary-color); }
        .btn-view:hover { background: #4ca8a6; }
        .btn-manage { background: #ff9800; }
        .btn-manage:hover { background: #f57c00; }
        .status-badge { padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; background: #fef3c7; color: #92400e; display: inline-block; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; padding: 1rem; }
        .modal.active { display: flex; }
        .modal-content { background: #fff; border-radius: 12px; width: 100%; max-width: 720px; max-height: 90vh; overflow-y: auto; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 2px solid var(--border-color); }
        .modal-header h2 { margin: 0; color: var(--tertiary-color); font-size: 1.25rem; }
        .close-modal { background: none; border: none; font-size: 1.75rem; cursor: pointer; color: #aaa; line-height: 1; }
        .detail-row { margin-bottom: 0.85rem; }
        .detail-label { font-weight: 600; margin-bottom: 0.25rem; color: var(--text-color); }
        .detail-value { color: var(--text-secondary); line-height: 1.6; white-space: pre-wrap; }
        .incident-photo {
            max-width: 280px;
            max-height: 200px;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 8px;
            margin-top: 0.5rem;
            display: block;
            border: 1px solid var(--border-color);
            background: #f8f9fa;
            cursor: pointer;
        }
        .incident-photo:hover { opacity: 0.92; }
        .form-field { margin-bottom: 1rem; }
        .form-field label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-color); }
        .form-field select, .form-field textarea { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font: inherit; box-sizing: border-box; }
        .form-field textarea { min-height: 90px; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem; flex-wrap: wrap; }
        .btn-secondary { background: #e5e7eb; color: #111; padding: 0.55rem 1rem; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-save { background: #059669; color: #fff; padding: 0.55rem 1rem; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .manage-report-ref { color: var(--text-secondary); margin: 0 0 1rem; font-size: 0.95rem; }
        .empty-state { text-align: center; padding: 2rem; color: var(--text-secondary); }
        @media (max-width: 768px) {
            .main-wrapper { margin-left: 0; }
            body.sidebar-collapsed .main-wrapper { margin-left: 0; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
        }
    </style>
    <link rel="stylesheet" href="css/mobile-responsive.css">
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <a href="index.php"><img src="images/tara.png" alt="Alertara Logo"></a>
                <div class="user-name-display"><?php echo htmlspecialchars(getAdminDisplayName()); ?></div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php" class="nav-module-header" data-tooltip="Dashboard" style="text-decoration: none;">
                <span class="nav-module-icon"><i class="fas fa-home"></i></span>
                <span class="nav-module-header-text">Dashboard</span>
            </a>

            <?php if (isAdminUser()): ?>
            <div class="nav-module">
                <div class="nav-module-header" onclick="toggleModule(this)" data-tooltip="User Management">
                    <span class="nav-module-icon"><i class="fas fa-users-cog"></i></span>
                    <span class="nav-module-header-text">User Management</span>
                    <span class="arrow">▶</span>
                </div>
                <div class="nav-submodules">
                    <a href="users.php" class="nav-submodule" data-tooltip="Users">
                        <span class="nav-submodule-icon"><i class="fas fa-users"></i></span>
                        <span class="nav-submodule-text">Users</span>
                    </a>
                    <a href="login-history.php" class="nav-submodule" data-tooltip="Audit Trails">
                        <span class="nav-submodule-icon"><i class="fas fa-history"></i></span>
                        <span class="nav-submodule-text">Audit Trails</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <div class="nav-module active">
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
        <div class="sidebar-footer">
            <a href="logout.php" class="sidebar-logout-btn" data-tooltip="Logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <div class="main-wrapper">
        <header class="top-header">
            <div class="top-header-content">
                <button class="content-burger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
                    <span></span>
                </button>
                <h1 class="page-title">Neighborhood Watch Member Incident Reports</h1>
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
                            <button type="button" onclick="markAllAsRead()">Mark all as read</button>
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
                <div class="toolbar">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by report ID, member, or location...">
                    </div>
                </div>
                <div id="tableContainer"><div class="empty-state">Loading reports...</div></div>
            </div>
        </main>
    </div>

    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="viewModalTitle">Incident Report</h2>
                <button type="button" class="close-modal" onclick="closeViewModal()">&times;</button>
            </div>
            <div id="viewModalBody"></div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <div id="manageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Assign Report</h2>
                <button type="button" class="close-modal" onclick="closeManageModal()">&times;</button>
            </div>
            <p class="manage-report-ref" id="manageReportRef"></p>
            <input type="hidden" id="manageReportId">
            <div class="form-field">
                <label for="assignedPatrolSelect">Assign BPSO Personnel</label>
                <select id="assignedPatrolSelect">
                    <option value="">Unassigned</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeManageModal()">Cancel</button>
                <button type="button" class="btn-save" onclick="saveAssignment()">Save Assignment</button>
            </div>
        </div>
    </div>

    <script>
        let reports = [];
        let selectedReport = null;

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        function formatDateTime(value) {
            if (!value) return '—';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return escapeHtml(String(value));
            return escapeHtml(date.toLocaleString());
        }

        function buildReportDetailsHtml(report) {
            let photoHtml = '';
            if (report.photo_data) {
                photoHtml = '<div class="detail-row"><div class="detail-label">Photo</div><img src="' + report.photo_data + '" alt="Incident photo" class="incident-photo" onclick="window.open(this.src, \'_blank\')" title="Click to view full size"></div>';
            }

            return ''
                + '<div class="detail-row"><div class="detail-label">Member</div><div class="detail-value">' + escapeHtml(report.member_name) + ' (' + escapeHtml(report.member_contact) + ')</div></div>'
                + '<div class="detail-row"><div class="detail-label">Email</div><div class="detail-value">' + escapeHtml(report.member_email) + '</div></div>'
                + '<div class="detail-row"><div class="detail-label">Location</div><div class="detail-value">' + escapeHtml(report.location) + '</div></div>'
                + '<div class="detail-row"><div class="detail-label">Description</div><div class="detail-value">' + escapeHtml(report.description) + '</div></div>'
                + '<div class="detail-row"><div class="detail-label">Status</div><div class="detail-value">' + escapeHtml(report.status) + '</div></div>'
                + '<div class="detail-row"><div class="detail-label">Assigned To</div><div class="detail-value">' + escapeHtml(report.assigned_to || 'Unassigned') + '</div></div>'
                + (report.resolution_report ? '<div class="detail-row"><div class="detail-label">Personnel Resolution</div><div class="detail-value">' + escapeHtml(report.resolution_report) + '</div></div>' : '')
                + '<div class="detail-row"><div class="detail-label">Submitted</div><div class="detail-value">' + formatDateTime(report.created_at) + '</div></div>'
                + (report.assigned_at ? '<div class="detail-row"><div class="detail-label">Assigned At</div><div class="detail-value">' + formatDateTime(report.assigned_at) + '</div></div>' : '')
                + (report.resolved_at ? '<div class="detail-row"><div class="detail-label">Resolved At</div><div class="detail-value">' + formatDateTime(report.resolved_at) + '</div></div>' : '')
                + photoHtml;
        }

        function renderTable(data) {
            const container = document.getElementById('tableContainer');
            if (!data.length) {
                container.innerHTML = '<div class="empty-state">No incident reports found.</div>';
                return;
            }

            let html = '<div class="table-container"><table><thead><tr>'
                + '<th>Report ID</th><th>Member</th><th>Location</th><th>Assigned To</th><th>Status</th><th>Submitted</th><th>Actions</th>'
                + '</tr></thead><tbody>';

            data.forEach(function(report) {
                const date = report.created_at ? new Date(report.created_at).toLocaleString() : '-';
                html += '<tr>'
                    + '<td>' + escapeHtml(report.report_id) + '</td>'
                    + '<td>' + escapeHtml(report.member_name) + '</td>'
                    + '<td>' + escapeHtml(report.location) + '</td>'
                    + '<td>' + escapeHtml(report.assigned_to || '—') + '</td>'
                    + '<td><span class="status-badge">' + escapeHtml(report.status) + '</span></td>'
                    + '<td>' + escapeHtml(date) + '</td>'
                    + '<td><div class="action-buttons">'
                    + '<button type="button" class="btn-view" onclick="viewReport(' + report.id + ')">View</button>'
                    + '<button type="button" class="btn-manage" onclick="manageReport(' + report.id + ')">Assign</button>'
                    + '</div></td>'
                    + '</tr>';
            });

            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        async function loadReports() {
            try {
                const response = await fetch('api/neighborhood-watcher-incidents.php');
                const result = await response.json();
                if (!result.success) {
                    document.getElementById('tableContainer').innerHTML = '<div class="empty-state">' + escapeHtml(result.message || 'Failed to load reports.') + '</div>';
                    return;
                }
                reports = result.data || [];
                renderTable(reports);

                const params = new URLSearchParams(window.location.search);
                const reportId = params.get('id');
                if (reportId) {
                    const match = reports.find(function(r) { return r.report_id === reportId; });
                    if (match) viewReport(match.id);
                }
            } catch (err) {
                document.getElementById('tableContainer').innerHTML = '<div class="empty-state">Network error while loading reports.</div>';
            }
        }

        function viewReport(id) {
            const report = reports.find(function(r) { return Number(r.id) === Number(id); });
            if (!report) return;

            document.getElementById('viewModalTitle').textContent = report.report_id;
            document.getElementById('viewModalBody').innerHTML = buildReportDetailsHtml(report);
            document.getElementById('viewModal').classList.add('active');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
        }

        function manageReport(id) {
            selectedReport = reports.find(function(r) { return Number(r.id) === Number(id); });
            if (!selectedReport) return;

            document.getElementById('manageReportId').value = selectedReport.id;
            document.getElementById('manageReportRef').textContent = 'Report ID: ' + selectedReport.report_id;
            loadAvailablePersonnel(selectedReport.assigned_patrol_id || '');
            document.getElementById('manageModal').classList.add('active');
        }

        function closeManageModal() {
            document.getElementById('manageModal').classList.remove('active');
            selectedReport = null;
        }

        async function loadAvailablePersonnel(selectedPatrolId) {
            const select = document.getElementById('assignedPatrolSelect');
            select.innerHTML = '<option value="">Unassigned</option>';

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
                        .map(function(row) { return String(row.patrol_id); })
                );

                result.data
                    .filter(function(p) { return atHallIds.has(String(p.id)) || String(p.id) === String(selectedPatrolId || ''); })
                    .forEach(function(personnel) {
                        const option = document.createElement('option');
                        option.value = personnel.id;
                        const atHall = atHallIds.has(String(personnel.id));
                        const statusLabel = atHall ? 'At Hall' : 'Not at Hall';
                        option.textContent = personnel.bpso_personnel_id + ' - ' + personnel.personnel_name + ' (' + statusLabel + ')';
                        if (String(personnel.id) === String(selectedPatrolId || '')) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });
            } catch (err) {
                console.error('Error loading BPSO personnel:', err);
            }
        }

        async function saveAssignment() {
            if (!selectedReport) return;

            try {
                const response = await fetch('api/neighborhood-watcher-incidents.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'assign',
                        id: selectedReport.id,
                        assigned_patrol_id: parseInt(document.getElementById('assignedPatrolSelect').value, 10) || 0
                    })
                });
                const result = await response.json();
                if (result.success) {
                    closeManageModal();
                    await loadReports();
                    alert(result.message || 'Assignment saved.');
                } else {
                    alert(result.message || 'Failed to assign personnel.');
                }
            } catch (err) {
                alert('Network error. Please try again.');
            }
        }

        document.getElementById('searchInput').addEventListener('input', function(e) {
            const query = e.target.value.trim().toLowerCase();
            if (!query) {
                renderTable(reports);
                return;
            }
            const filtered = reports.filter(function(report) {
                return [report.report_id, report.member_name, report.location, report.status, report.assigned_to]
                    .join(' ').toLowerCase().includes(query);
            });
            renderTable(filtered);
        });

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const isCollapsed = sidebar.classList.contains('collapsed');
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
                return;
            }
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
                document.querySelectorAll('.nav-module').forEach(function(m) { m.classList.remove('active'); });
                module.classList.add('active');
                return;
            }

            document.querySelectorAll('.nav-module').forEach(function(m) { m.classList.remove('active'); });
            if (!isActive) {
                module.classList.add('active');
            }
        }

        function updateDateTime() {
            const now = new Date();
            const dateEl = document.getElementById('currentDate');
            const timeEl = document.getElementById('currentTime');
            if (dateEl) {
                dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            }
            if (timeEl) {
                timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }
        }

        window.addEventListener('click', function(event) {
            if (event.target === document.getElementById('viewModal')) closeViewModal();
            if (event.target === document.getElementById('manageModal')) closeManageModal();
        });

        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
            updateDateTime();
            setInterval(updateDateTime, 1000);
            loadReports();
        });
    </script>
    <?php require __DIR__ . '/includes/admin_notifications_script.php'; ?>
    <script src="js/mobile-shell.js"></script>
</body>
</html>

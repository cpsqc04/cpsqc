<?php
require_once __DIR__ . '/includes/neighborhood-watcher-member-auth.php';
require_once __DIR__ . '/includes/neighborhood-watcher-incident-terms.php';

requireNwMemberLogin();
requireNwMemberPasswordChanged();

$memberEmail = htmlspecialchars(getNwMemberEmail());
$passwordChanged = isset($_GET['password_changed']);
$nwActiveNav = 'bulletin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Neighborhood Watch Portal - Alertara</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/admin-sidebar.css">
    <link rel="stylesheet" href="css/digital-bulletin.css">
    <link rel="stylesheet" href="css/notifications.css">
    <style>
        body { margin: 0; padding: 0; font-family: var(--font-family); background-color: var(--bg-color); display: flex; min-height: 100vh; }
        .sidebar { width: 320px; background: var(--tertiary-color); color: #fff; position: fixed; left: 0; top: 0; height: 100vh; overflow: hidden; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); z-index: 1000; transition: width 0.3s ease; display: flex; flex-direction: column; }
        .sidebar.collapsed { width: 80px; }
        .sidebar-header { padding: 1.5rem 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 160px; }
        .logo-container { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
        .logo-container img { height: 130px; width: 130px; object-fit: contain; transition: all 0.3s ease; }
        .sidebar.collapsed .logo-container img { height: 70px; width: 70px; }
        .user-name-display { color: rgba(255, 255, 255, 0.9); font-size: 0.88rem; font-weight: 500; text-align: center; padding: 0.25rem 0.75rem 0; word-break: break-word; max-width: 100%; line-height: 1.3; }
        .user-id-display { color: rgba(255, 255, 255, 0.7); font-size: 0.78rem; font-weight: 500; text-align: center; padding: 0.15rem 0.75rem 0; word-break: break-word; max-width: 100%; line-height: 1.3; }
        .sidebar.collapsed .user-name-display,
        .sidebar.collapsed .user-id-display { opacity: 0; height: 0; padding: 0; overflow: hidden; font-size: 0; }
        .sidebar-nav { padding: 0.5rem 0; overflow-y: auto; flex: 1; display: flex; flex-direction: column; min-height: 0; }
        .nav-submodule { padding: 0.75rem 1.5rem !important; color: rgba(255, 255, 255, 0.75); text-decoration: none; display: flex; align-items: center; gap: 0.75rem; transition: all 0.2s ease; font-size: 0.84rem !important; cursor: pointer; border: none; background: none; width: 100%; text-align: left; font-family: inherit; position: relative; }
        .nav-submodule:hover { background: rgba(255, 255, 255, 0.08); color: #fff; padding-left: 1.5rem !important; }
        .nav-submodule.active { background: rgba(76, 138, 137, 0.35); color: #fff; border-left: 3px solid var(--primary-color); font-weight: 600; }
        .nav-submodule-icon { width: 22px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .nav-submodule-text { flex: 1; }
        .sidebar.collapsed .nav-submodule-text { opacity: 0; width: 0; overflow: hidden; display: none; }
        .sidebar.collapsed .nav-submodule { padding: 0.75rem; justify-content: center; }
        .sidebar.collapsed .nav-submodule::after { content: attr(data-tooltip); position: absolute; left: 100%; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.9); color: #fff; padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.85rem; white-space: nowrap; opacity: 0; pointer-events: none; margin-left: 0.75rem; z-index: 2000; }
        .sidebar.collapsed .nav-submodule:hover::after { opacity: 1; }
        .sidebar-footer { margin-top: auto; padding: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.5rem; background: rgba(239, 68, 68, 0.1); color: rgba(255, 255, 255, 0.9); text-decoration: none; border-radius: 8px; font-size: 1rem; font-weight: 500; border: 1px solid rgba(239, 68, 68, 0.2); width: 100%; box-sizing: border-box; }
        .sidebar-logout-btn:hover { background: rgba(239, 68, 68, 0.2); color: #fff; }
        .sidebar.collapsed .sidebar-logout-btn span { opacity: 0; width: 0; overflow: hidden; display: none; }
        .sidebar.collapsed .sidebar-logout-btn { justify-content: center; }
        .main-wrapper { margin-left: 320px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; transition: margin-left 0.3s ease; }
        body.sidebar-collapsed .main-wrapper { margin-left: 80px; }
        .top-header { background: var(--header-bg); padding: 1.5rem 2rem 1rem; display: flex; justify-content: space-between; align-items: flex-end; position: sticky; top: 0; z-index: 1100; border-bottom: 1px solid var(--border-color); overflow: visible; }
        .page-title { font-size: 2rem; font-weight: 700; color: var(--tertiary-color); margin: 0; }
        .user-info { display: flex; align-items: center; gap: 1rem; margin-left: 2rem; overflow: visible; position: relative; z-index: 1200; }
        .datetime-display { display: flex; align-items: center; gap: 0.75rem; color: var(--text-color); font-size: 0.9rem; font-weight: 500; }
        .datetime-display .date-part { color: var(--text-secondary); }
        .datetime-display .time-part { color: var(--text-color); font-weight: 600; }
        .content-area { padding: 2rem; flex: 1; background: #f5f5f5; }
        .page-content { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; box-shadow: 0 2px 8px var(--shadow); }
        .section-heading { margin: 0 0 1.5rem; color: var(--tertiary-color); font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .section-block { margin-bottom: 2.5rem; display: none; }
        .section-block.is-active { display: block; }
        .section-block:last-child { margin-bottom: 0; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-color); font-weight: 500; font-size: 0.95rem; }
        .form-group input, .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.95rem; font-family: var(--font-family); box-sizing: border-box; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1); }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .btn-submit { padding: 0.75rem 1.5rem; background: var(--primary-color); color: #fff; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-submit:hover { background: #4ca8a6; }
        .alert { padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.92rem; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .table-container { overflow-x: auto; border-radius: 8px; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; background: var(--card-bg); }
        thead { background: var(--tertiary-color); color: #fff; }
        th { padding: 1rem; text-align: left; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-color); }
        tbody tr:hover { background: #f9f9f9; }
        tbody tr:last-child td { border-bottom: none; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.85rem; font-weight: 500; display: inline-block; }
        .status-under-review { background: #fef3c7; color: #b45309; }
        .status-in-progress { background: #dbeafe; color: #1d4ed8; }
        .status-resolved { background: #d1fae5; color: #047857; }
        .status-closed { background: #e5e7eb; color: #374151; }
        .resolution-cell { max-width: 300px; white-space: normal; line-height: 1.45; font-size: 0.9rem; }
        .resolution-empty { color: #94a3b8; font-style: italic; }
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-view { padding: 0.5rem 1rem; border: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; color: #fff; background: var(--primary-color); }
        .btn-view:hover { background: #4ca8a6; }
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
        .modal-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem; flex-wrap: wrap; }
        .btn-secondary { background: #e5e7eb; color: #111; padding: 0.55rem 1rem; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .report-card-actions { margin-top: 0.85rem; }
        .empty-state { text-align: center; padding: 2.5rem 1rem; color: var(--text-secondary); }
        .empty-state i { font-size: 2rem; margin-bottom: 0.75rem; opacity: 0.4; display: block; }
        #photoPreview img { max-width: 220px; max-height: 160px; border-radius: 8px; margin-top: 0.5rem; border: 1px solid var(--border-color); }
        .report-notice { padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1.25rem; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; font-size: 0.92rem; line-height: 1.5; }
        .terms-group { display: flex; align-items: flex-start; gap: 0.75rem; padding: 1rem; border: 1px solid var(--border-color); border-radius: 8px; background: #fafafa; }
        .terms-group input[type="checkbox"] { margin-top: 0.2rem; width: auto; flex-shrink: 0; }
        .terms-group label { margin: 0; font-weight: 400; line-height: 1.55; cursor: pointer; }
        .terms-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 3000; align-items: center; justify-content: center; padding: 1rem; }
        .terms-modal.active { display: flex; }
        .terms-modal-content { background: #fff; width: 100%; max-width: 640px; max-height: 85vh; overflow-y: auto; border-radius: 12px; padding: 1.5rem; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .terms-modal-content h2 { margin: 0 0 1rem; color: var(--tertiary-color); }
        .terms-modal-content h3 { margin: 1.25rem 0 0.5rem; color: var(--tertiary-color); font-size: 1rem; }
        .terms-modal-content p { margin: 0 0 0.75rem; line-height: 1.6; color: var(--text-color); }
        .terms-modal-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.25rem; flex-wrap: wrap; }
        .btn-cancel-terms { padding: 0.65rem 1.25rem; background: #6c757d; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-confirm-terms { padding: 0.65rem 1.25rem; background: var(--primary-color); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-confirm-terms:disabled { opacity: 0.55; cursor: not-allowed; }
        .report-cards { display: none; flex-direction: column; gap: 0.85rem; }
        .report-card { border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem; background: #fff; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.06); }
        .report-card-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.75rem; }
        .report-card-id { font-weight: 700; color: var(--tertiary-color); font-size: 0.95rem; word-break: break-word; }
        .report-card-meta { display: grid; gap: 0.55rem; }
        .report-card-row { display: grid; grid-template-columns: 6.5rem 1fr; gap: 0.5rem; font-size: 0.9rem; }
        .report-card-label { color: var(--text-secondary); font-weight: 500; }
        .report-card-value { color: var(--text-color); word-break: break-word; }
        .btn-submit { width: auto; }
        @media (max-width: 768px) {
            .main-wrapper { margin-left: 0; }
            body.sidebar-collapsed .main-wrapper { margin-left: 0; }
            .sidebar { transform: translateX(-100%); width: min(320px, 88vw); }
            .sidebar.mobile-open { transform: translateX(0); }
            .sidebar.collapsed { width: min(320px, 88vw); transform: translateX(-100%); }
            .sidebar.collapsed.mobile-open { transform: translateX(0); }
            .top-header { align-items: center; }
            .user-info { width: 100%; margin-left: 0; justify-content: flex-start; }
            .page-title { font-size: 1.25rem; }
            .section-heading { font-size: 1.05rem; flex-wrap: wrap; }
            .content-area { padding: 1rem; }
            .page-content { padding: 1rem; border-radius: 10px; }
            .btn-submit { width: 100%; justify-content: center; }
            .table-container { display: none; }
            .report-cards { display: flex; }
            #photoPreview img { max-width: 100%; height: auto; max-height: none; }
        }
        @media (max-width: 480px) {
            .page-title { font-size: 1.1rem; }
            .report-card-row { grid-template-columns: 1fr; gap: 0.15rem; }
            .datetime-display { width: 100%; justify-content: space-between; }
        }
    </style>
    <link rel="stylesheet" href="css/mobile-responsive.css">
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <?php require __DIR__ . '/includes/neighborhood-watcher-portal-sidebar-header.php'; ?>
        <nav class="sidebar-nav">
            <?php require __DIR__ . '/includes/neighborhood-watcher-portal-sidebar-nav.php'; ?>
        </nav>
        <div class="sidebar-footer">
            <a href="neighborhood-watcher-logout.php" class="sidebar-logout-btn" data-tooltip="Logout">
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
                <div>
                    <h1 class="page-title" id="pageTitle">Digital Bulletin</h1>
                </div>
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
                            <button type="button" onclick="markAllNotificationsRead()">Mark all read</button>
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

        <main class="content-area bulletin-fullscreen">
            <div class="page-content bulletin-fullscreen">
                <?php if ($passwordChanged): ?>
                    <div class="alert alert-success">Your password has been updated successfully.</div>
                <?php endif; ?>

                <section id="bulletinSection" class="section-block is-active">
                    <div id="nwBulletinRoot">
                        <div data-db-carousel></div>
                        <div data-db-announcements></div>
                    </div>
                </section>

                <section id="reportSection" class="section-block">
                    <h2 class="section-heading"><i class="fas fa-exclamation-triangle"></i> Report Incident to BPSO</h2>
                    <div class="report-notice">This form is for genuine community safety incidents. Reports must be truthful and made in good faith.</div>
                    <div id="reportAlert" style="display:none;"></div>
                    <form id="reportForm">
                        <div class="form-group">
                            <label for="incidentLocation">Location *</label>
                            <input id="incidentLocation" type="text" placeholder="Where did the incident occur?" required>
                        </div>
                        <div class="form-group">
                            <label for="incidentDescription">Description *</label>
                            <textarea id="incidentDescription" placeholder="Describe what happened in detail..." required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="incidentPhoto">Photo (optional)</label>
                            <input id="incidentPhoto" type="file" accept="image/*" onchange="previewPhoto(this)">
                            <div id="photoPreview"></div>
                        </div>
                        <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Submit Report</button>
                    </form>
                </section>

                <section id="reportsSection" class="section-block">
                    <h2 class="section-heading"><i class="fas fa-list"></i> My Incident Reports</h2>
                    <div id="reportsContainer">
                        <div class="empty-state"><i class="fas fa-spinner fa-spin"></i> Loading your reports...</div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <div id="termsModal" class="terms-modal" onclick="if (event.target === this) closeTermsModal()">
        <div class="terms-modal-content">
            <h2>Incident Report Terms and Conditions</h2>
            <p style="margin:0 0 1rem;color:var(--text-secondary);line-height:1.55;">Please review and accept the terms below before your report is submitted.</p>
            <?php echo getNwIncidentTermsHtml(); ?>
            <div class="terms-group" style="margin-top:1rem;">
                <input type="checkbox" id="termsAccepted" onchange="updateTermsConfirmButton()">
                <label for="termsAccepted"><?php echo htmlspecialchars(getNwIncidentTermsSummary()); ?></label>
            </div>
            <div class="terms-modal-actions">
                <button type="button" class="btn-cancel-terms" onclick="closeTermsModal()">Cancel</button>
                <button type="button" class="btn-confirm-terms" id="confirmTermsBtn" disabled onclick="confirmTermsAndSubmit()">I Agree and Submit</button>
            </div>
        </div>
    </div>

    <div id="myReportViewModal" class="modal" onclick="if (event.target === this) closeMyReportViewModal()">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="myReportViewTitle">Incident Report</h2>
                <button type="button" class="close-modal" onclick="closeMyReportViewModal()">&times;</button>
            </div>
            <div id="myReportViewBody"></div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeMyReportViewModal()">Close</button>
            </div>
        </div>
    </div>

    <script src="js/photo-lightbox.js"></script>
    <script src="js/digital-bulletin.js"></script>
    <script>
        const NW_INCIDENT_TERMS_VERSION = <?php echo json_encode(getNwIncidentTermsVersion()); ?>;
        let photoDataUrl = null;
        let pendingReportPayload = null;
        let myReports = [];

        function formatDateTime(value) {
            if (!value) return '—';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return escapeHtml(String(value));
            return escapeHtml(date.toLocaleString());
        }

        function buildMyReportDetailsHtml(report) {
            let photoHtml = '';
            if (report.photo_data) {
                photoHtml = '<div class="detail-row"><div class="detail-label">Photo</div>'
                    + '<img src="' + report.photo_data + '" alt="Incident photo" class="incident-photo" '
                    + 'onclick="viewMyReportPhoto(' + Number(report.id) + ')" title="Click to view full size"></div>';
            }

            return ''
                + '<div class="detail-row"><div class="detail-label">Location</div><div class="detail-value">' + escapeHtml(report.location) + '</div></div>'
                + '<div class="detail-row"><div class="detail-label">Description</div><div class="detail-value">' + escapeHtml(report.description) + '</div></div>'
                + '<div class="detail-row"><div class="detail-label">Status</div><div class="detail-value">' + escapeHtml(report.status) + '</div></div>'
                + '<div class="detail-row"><div class="detail-label">Assigned To</div><div class="detail-value">' + escapeHtml(report.assigned_to || 'Unassigned') + '</div></div>'
                + (report.resolution_report
                    ? '<div class="detail-row"><div class="detail-label">BPSO Resolution</div><div class="detail-value">' + escapeHtml(report.resolution_report) + '</div></div>'
                    : '<div class="detail-row"><div class="detail-label">BPSO Resolution</div><div class="detail-value">Pending — waiting for assigned personnel</div></div>')
                + '<div class="detail-row"><div class="detail-label">Submitted</div><div class="detail-value">' + formatDateTime(report.created_at) + '</div></div>'
                + (report.assigned_at ? '<div class="detail-row"><div class="detail-label">Assigned At</div><div class="detail-value">' + formatDateTime(report.assigned_at) + '</div></div>' : '')
                + (report.resolved_at ? '<div class="detail-row"><div class="detail-label">Resolved At</div><div class="detail-value">' + formatDateTime(report.resolved_at) + '</div></div>' : '')
                + photoHtml;
        }

        function viewMyReportPhoto(reportId) {
            const report = myReports.find(function(r) { return Number(r.id) === Number(reportId); });
            const photoSrc = report && report.photo_data ? String(report.photo_data) : '';
            if (!photoSrc) {
                alert('No photo available for this report.');
                return;
            }
            if (window.AlertaraPhotoLightbox) {
                AlertaraPhotoLightbox.open(photoSrc, 'Incident photo');
                return;
            }
            alert('Photo viewer is unavailable.');
        }

        function viewMyReport(id) {
            const report = myReports.find(function(r) { return Number(r.id) === Number(id); });
            if (!report) return;
            document.getElementById('myReportViewTitle').textContent = report.report_id || 'Incident Report';
            document.getElementById('myReportViewBody').innerHTML = buildMyReportDetailsHtml(report);
            document.getElementById('myReportViewModal').classList.add('active');
        }

        function closeMyReportViewModal() {
            document.getElementById('myReportViewModal').classList.remove('active');
        }

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
                localStorage.setItem('nwSidebarCollapsed', 'false');
            } else {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
                localStorage.setItem('nwSidebarCollapsed', 'true');
            }
        }

        function showPortalSection(sectionId, updateHash, openPostId) {
            const allowed = ['bulletinSection', 'reportSection', 'reportsSection'];
            const targetId = allowed.includes(sectionId) ? sectionId : 'bulletinSection';
            const titles = {
                bulletinSection: 'Digital Bulletin',
                reportSection: 'Report Incident',
                reportsSection: 'My Reports'
            };

            ['bulletinSection', 'reportSection', 'reportsSection'].forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.classList.toggle('is-active', id === targetId);
            });

            document.querySelectorAll('.nav-submodule[data-section]').forEach((link) => {
                link.classList.toggle('active', link.getAttribute('data-section') === targetId);
            });

            const pageTitle = document.getElementById('pageTitle');
            if (pageTitle) pageTitle.textContent = titles[targetId] || 'Neighborhood Watch Portal';

            const contentArea = document.querySelector('.content-area');
            const pageContent = document.querySelector('.page-content');
            const isBulletin = targetId === 'bulletinSection';
            if (contentArea) contentArea.classList.toggle('bulletin-fullscreen', isBulletin);
            if (pageContent) pageContent.classList.toggle('bulletin-fullscreen', isBulletin);

            if (updateHash !== false) {
                let nextHash = '#' + targetId;
                const postId = parseInt(openPostId, 10) || 0;
                if (postId > 0) {
                    nextHash += ':' + postId;
                }
                if (window.location.hash !== nextHash) {
                    history.replaceState(null, '', nextHash);
                }
            }

            if (targetId === 'reportsSection') {
                loadReports();
            }
            if (targetId === 'bulletinSection' && window.DigitalBulletin) {
                DigitalBulletin.mount({
                    root: '#nwBulletinRoot',
                    audience: 'watcher',
                    openPostId: parseInt(openPostId, 10) || 0
                });
            }
        }

        window.showPortalSection = showPortalSection;

        function scrollToSection(sectionId) {
            showPortalSection(sectionId, true);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function updateDateTime() {
            const now = new Date();
            const dateEl = document.getElementById('currentDate');
            const timeEl = document.getElementById('currentTime');
            if (dateEl) {
                dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
            }
            if (timeEl) {
                timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        function statusClass(status) {
            const normalized = (status || '').toLowerCase().replace(/\s+/g, '-');
            if (normalized === 'under-review') return 'status-under-review';
            if (normalized === 'in-progress') return 'status-in-progress';
            if (normalized === 'resolved') return 'status-resolved';
            return 'status-closed';
        }

        function showReportAlert(message, isError) {
            const el = document.getElementById('reportAlert');
            el.className = 'alert ' + (isError ? 'alert-error' : 'alert-success');
            el.textContent = message;
            el.style.display = 'block';
        }

        function updateTermsConfirmButton() {
            document.getElementById('confirmTermsBtn').disabled = !document.getElementById('termsAccepted').checked;
        }

        function openTermsModal() {
            document.getElementById('termsAccepted').checked = false;
            updateTermsConfirmButton();
            document.getElementById('termsModal').classList.add('active');
        }

        function closeTermsModal() {
            document.getElementById('termsModal').classList.remove('active');
            pendingReportPayload = null;
        }

        function previewPhoto(input) {
            const preview = document.getElementById('photoPreview');
            photoDataUrl = null;
            preview.innerHTML = '';
            const file = input.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    const maxWidth = 1200;
                    let width = img.width;
                    let height = img.height;
                    if (width > maxWidth) {
                        height = Math.round(height * (maxWidth / width));
                        width = maxWidth;
                    }
                    canvas.width = width;
                    canvas.height = height;
                    canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                    photoDataUrl = canvas.toDataURL('image/jpeg', 0.85);
                    preview.innerHTML = '<img src="' + photoDataUrl + '" alt="Preview">';
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        async function confirmTermsAndSubmit() {
            if (!pendingReportPayload || !document.getElementById('termsAccepted').checked) {
                return;
            }

            const confirmBtn = document.getElementById('confirmTermsBtn');
            confirmBtn.disabled = true;

            try {
                const response = await fetch('api/neighborhood-watcher-incidents.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create',
                        ...pendingReportPayload,
                        terms_accepted: true,
                        terms_version: NW_INCIDENT_TERMS_VERSION
                    })
                });
                const result = await response.json();

                if (result.success) {
                    closeTermsModal();
                    document.getElementById('reportForm').reset();
                    photoDataUrl = null;
                    document.getElementById('photoPreview').innerHTML = '';
                    showReportAlert(result.message || 'Report submitted successfully.', false);
                    showPortalSection('reportsSection', true);
                    return;
                }

                closeTermsModal();
                showReportAlert(result.message || 'Failed to submit report.', true);
            } catch (err) {
                closeTermsModal();
                showReportAlert('Network error. Please try again.', true);
            } finally {
                confirmBtn.disabled = !document.getElementById('termsAccepted').checked;
            }
        }

        async function loadReports() {
            const container = document.getElementById('reportsContainer');
            try {
                const response = await fetch('api/neighborhood-watcher-incidents.php');
                const result = await response.json();
                if (!result.success) {
                    container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i>' + escapeHtml(result.message || 'Failed to load reports.') + '</div>';
                    return;
                }

                const reports = result.data || [];
                myReports = reports;
                if (!reports.length) {
                    container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i>No incident reports yet.</div>';
                    return;
                }

                let html = '<div class="table-container"><table><thead><tr><th>Report ID</th><th>Location</th><th>Assigned To</th><th>Status</th><th>Submitted</th><th>Actions</th></tr></thead><tbody>';
                let cardsHtml = '<div class="report-cards">';
                reports.forEach(function(report) {
                    const date = report.created_at ? new Date(report.created_at).toLocaleString() : '-';
                    html += '<tr>'
                        + '<td>' + escapeHtml(report.report_id) + '</td>'
                        + '<td>' + escapeHtml(report.location) + '</td>'
                        + '<td>' + escapeHtml(report.assigned_to || '—') + '</td>'
                        + '<td><span class="status-badge ' + statusClass(report.status) + '">' + escapeHtml(report.status) + '</span></td>'
                        + '<td>' + escapeHtml(date) + '</td>'
                        + '<td><div class="action-buttons"><button type="button" class="btn-view" onclick="viewMyReport(' + Number(report.id) + ')">View</button></div></td>'
                        + '</tr>';
                    cardsHtml += '<article class="report-card">'
                        + '<div class="report-card-top">'
                        + '<div class="report-card-id">' + escapeHtml(report.report_id) + '</div>'
                        + '<span class="status-badge ' + statusClass(report.status) + '">' + escapeHtml(report.status) + '</span>'
                        + '</div>'
                        + '<div class="report-card-meta">'
                        + '<div class="report-card-row"><span class="report-card-label">Location</span><span class="report-card-value">' + escapeHtml(report.location) + '</span></div>'
                        + '<div class="report-card-row"><span class="report-card-label">Assigned To</span><span class="report-card-value">' + escapeHtml(report.assigned_to || '—') + '</span></div>'
                        + '<div class="report-card-row"><span class="report-card-label">Submitted</span><span class="report-card-value">' + escapeHtml(date) + '</span></div>'
                        + '</div>'
                        + '<div class="report-card-actions"><button type="button" class="btn-view" onclick="viewMyReport(' + Number(report.id) + ')">View</button></div>'
                        + '</article>';
                });
                html += '</tbody></table></div>';
                cardsHtml += '</div>';
                container.innerHTML = html + cardsHtml;
            } catch (err) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-wifi"></i>Network error while loading reports.</div>';
            }
        }

        document.getElementById('reportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const location = document.getElementById('incidentLocation').value.trim();
            const description = document.getElementById('incidentDescription').value.trim();

            if (!location || !description) {
                showReportAlert('Please fill in location and description.', true);
                return;
            }

            pendingReportPayload = {
                location: location,
                description: description,
                photo: photoDataUrl
            };

            document.getElementById('reportAlert').style.display = 'none';
            openTermsModal();
        });

        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const isMobile = window.matchMedia('(max-width: 768px)').matches;
            if (!isMobile && localStorage.getItem('nwSidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
            updateDateTime();
            setInterval(updateDateTime, 1000);

            document.querySelectorAll('.nav-submodule[data-section]').forEach((link) => {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    const sectionId = this.getAttribute('data-section');
                    showPortalSection(sectionId, true);
                    if (window.innerWidth <= 768 && typeof window.closeMobileSidebar === 'function') {
                        window.closeMobileSidebar();
                    } else if (window.innerWidth <= 768) {
                        sidebar.classList.remove('mobile-open');
                        document.body.classList.remove('sidebar-mobile-open');
                    }
                });
            });

            function parseSectionHash(rawHash) {
                const hash = String(rawHash || '').replace(/^#/, '');
                if (!hash) {
                    return { sectionId: 'bulletinSection', openPostId: 0 };
                }
                const parts = hash.split(':');
                return {
                    sectionId: parts[0] || 'bulletinSection',
                    openPostId: parseInt(parts[1], 10) || 0
                };
            }

            window.addEventListener('hashchange', function() {
                const parsed = parseSectionHash(window.location.hash);
                showPortalSection(parsed.sectionId, false, parsed.openPostId);
            });

            const initial = parseSectionHash(window.location.hash);
            showPortalSection(initial.sectionId || 'bulletinSection', false, initial.openPostId);
        });
    </script>
    <?php require __DIR__ . '/includes/nw_notifications_script.php'; ?>
    <script src="js/mobile-shell.js"></script>
</body>
</html>

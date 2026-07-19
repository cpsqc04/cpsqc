<?php
require_once __DIR__ . '/includes/neighborhood-watcher-member-auth.php';

requireNwMemberLogin();
requireNwMemberPasswordChanged();

$memberName = htmlspecialchars(getNwMemberName());
$memberEmail = htmlspecialchars(getNwMemberEmail());
$passwordChanged = isset($_GET['password_changed']);
$nwActiveNav = 'report';
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
    <style>
        body { margin: 0; padding: 0; font-family: var(--font-family); background-color: var(--bg-color); display: flex; min-height: 100vh; }
        .sidebar { width: 320px; background: var(--tertiary-color); color: #fff; position: fixed; left: 0; top: 0; height: 100vh; overflow: hidden; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); z-index: 1000; transition: width 0.3s ease; display: flex; flex-direction: column; }
        .sidebar.collapsed { width: 80px; }
        .sidebar-header { padding: 1.5rem 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 160px; }
        .logo-container { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
        .logo-container img { height: 130px; width: 130px; object-fit: contain; transition: all 0.3s ease; }
        .sidebar.collapsed .logo-container img { height: 70px; width: 70px; }
        .user-name-display { color: rgba(255, 255, 255, 0.9); font-size: 0.95rem; font-weight: 500; text-align: center; padding: 0.5rem 1rem; word-break: break-word; max-width: 100%; }
        .sidebar.collapsed .user-name-display { opacity: 0; height: 0; padding: 0; overflow: hidden; font-size: 0; }
        .sidebar.collapsed .member-status-chip { opacity: 0; height: 0; padding: 0; margin: 0; overflow: hidden; font-size: 0; }
        .member-status-chip { display: inline-flex; align-items: center; gap: 0.4rem; margin-top: 0.35rem; padding: 0.35rem 0.75rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; background: rgba(16,185,129,0.2); color: #a7f3d0; }
        .sidebar-nav { padding: 0.5rem 0; overflow-y: auto; flex: 1; display: flex; flex-direction: column; min-height: 0; }
        .nav-submodule { padding: 0.75rem 1.5rem; color: rgba(255, 255, 255, 0.75); text-decoration: none; display: flex; align-items: center; gap: 0.75rem; transition: all 0.2s ease; font-size: 0.85rem; cursor: pointer; border: none; background: none; width: 100%; text-align: left; font-family: inherit; position: relative; }
        .nav-submodule:hover { background: rgba(255, 255, 255, 0.08); color: #fff; padding-left: 2rem; }
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
        .top-header { background: var(--header-bg); padding: 1.5rem 2rem 1rem; display: flex; justify-content: space-between; align-items: flex-end; position: sticky; top: 0; z-index: 100; border-bottom: 1px solid var(--border-color); }
        .top-header-content { flex: 1; display: flex; align-items: center; gap: 1rem; }
        .content-burger-btn { background: transparent; border: none; color: var(--tertiary-color); width: 40px; height: 40px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
        .content-burger-btn span { display: block; width: 22px; height: 1.5px; background: var(--tertiary-color); position: relative; }
        .content-burger-btn span::before, .content-burger-btn span::after { content: ''; position: absolute; width: 22px; height: 1.5px; background: var(--tertiary-color); }
        .content-burger-btn span::before { top: -7px; }
        .content-burger-btn span::after { bottom: -7px; }
        .page-title { font-size: 2rem; font-weight: 700; color: var(--tertiary-color); margin: 0; }
        .user-info { display: flex; align-items: center; gap: 1rem; margin-left: 2rem; }
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
        .empty-state { text-align: center; padding: 2.5rem 1rem; color: var(--text-secondary); }
        .empty-state i { font-size: 2rem; margin-bottom: 0.75rem; opacity: 0.4; display: block; }
        #photoPreview img { max-width: 220px; max-height: 160px; border-radius: 8px; margin-top: 0.5rem; border: 1px solid var(--border-color); }
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
        <div class="sidebar-header">
            <div class="logo-container">
                <a href="neighborhood-watcher-dashboard.php">
                    <img src="images/tara.png" alt="Alertara Logo">
                </a>
                <div class="user-name-display"><?php echo $memberName; ?></div>
                <div class="member-status-chip"><i class="fas fa-user-shield"></i> Active Member</div>
            </div>
        </div>
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
                    <h1 class="page-title">Neighborhood Watch Portal</h1>
                </div>
            </div>
            <div class="user-info">
                <div class="datetime-display">
                    <span class="date-part" id="currentDate"></span>
                    <span class="time-part" id="currentTime"></span>
                </div>
            </div>
        </header>

        <main class="content-area">
            <div class="page-content">
                <?php if ($passwordChanged): ?>
                    <div class="alert alert-success">Your password has been updated successfully.</div>
                <?php endif; ?>

                <section id="reportSection" class="section-block is-active">
                    <h2 class="section-heading"><i class="fas fa-exclamation-triangle"></i> Report Incident to BPSO</h2>
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

    <script>
        let photoDataUrl = null;

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

        function showPortalSection(sectionId, updateHash) {
            const reportSection = document.getElementById('reportSection');
            const reportsSection = document.getElementById('reportsSection');
            const targetId = sectionId === 'reportsSection' ? 'reportsSection' : 'reportSection';

            if (reportSection) reportSection.classList.toggle('is-active', targetId === 'reportSection');
            if (reportsSection) reportsSection.classList.toggle('is-active', targetId === 'reportsSection');

            document.querySelectorAll('.nav-submodule[data-section]').forEach((link) => {
                link.classList.toggle('active', link.getAttribute('data-section') === targetId);
            });

            if (updateHash !== false) {
                const nextHash = targetId === 'reportsSection' ? '#reportsSection' : '#reportSection';
                if (window.location.hash !== nextHash) {
                    history.replaceState(null, '', nextHash);
                }
            }

            if (targetId === 'reportsSection') {
                loadReports();
            }
        }

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
                if (!reports.length) {
                    container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i>No incident reports yet.</div>';
                    return;
                }

                let html = '<div class="table-container"><table><thead><tr><th>Report ID</th><th>Location</th><th>Assigned To</th><th>Status</th><th>BPSO Resolution</th><th>Submitted</th></tr></thead><tbody>';
                let cardsHtml = '<div class="report-cards">';
                reports.forEach(function(report) {
                    const date = report.created_at ? new Date(report.created_at).toLocaleString() : '-';
                    const resolutionText = report.resolution_report || 'Pending — waiting for assigned personnel';
                    const resolutionHtml = report.resolution_report
                        ? '<td class="resolution-cell">' + escapeHtml(report.resolution_report) + '</td>'
                        : '<td class="resolution-cell"><span class="resolution-empty">Pending — waiting for assigned personnel</span></td>';
                    html += '<tr>'
                        + '<td>' + escapeHtml(report.report_id) + '</td>'
                        + '<td>' + escapeHtml(report.location) + '</td>'
                        + '<td>' + escapeHtml(report.assigned_to || '—') + '</td>'
                        + '<td><span class="status-badge ' + statusClass(report.status) + '">' + escapeHtml(report.status) + '</span></td>'
                        + resolutionHtml
                        + '<td>' + escapeHtml(date) + '</td>'
                        + '</tr>';
                    cardsHtml += '<article class="report-card">'
                        + '<div class="report-card-top">'
                        + '<div class="report-card-id">' + escapeHtml(report.report_id) + '</div>'
                        + '<span class="status-badge ' + statusClass(report.status) + '">' + escapeHtml(report.status) + '</span>'
                        + '</div>'
                        + '<div class="report-card-meta">'
                        + '<div class="report-card-row"><span class="report-card-label">Location</span><span class="report-card-value">' + escapeHtml(report.location) + '</span></div>'
                        + '<div class="report-card-row"><span class="report-card-label">Assigned To</span><span class="report-card-value">' + escapeHtml(report.assigned_to || '—') + '</span></div>'
                        + '<div class="report-card-row"><span class="report-card-label">Resolution</span><span class="report-card-value">' + escapeHtml(resolutionText) + '</span></div>'
                        + '<div class="report-card-row"><span class="report-card-label">Submitted</span><span class="report-card-value">' + escapeHtml(date) + '</span></div>'
                        + '</div>'
                        + '</article>';
                });
                html += '</tbody></table></div>';
                cardsHtml += '</div>';
                container.innerHTML = html + cardsHtml;
            } catch (err) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-wifi"></i>Network error while loading reports.</div>';
            }
        }

        document.getElementById('reportForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const location = document.getElementById('incidentLocation').value.trim();
            const description = document.getElementById('incidentDescription').value.trim();

            if (!location || !description) {
                showReportAlert('Please fill in location and description.', true);
                return;
            }

            try {
                const response = await fetch('api/neighborhood-watcher-incidents.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create',
                        location: location,
                        description: description,
                        photo: photoDataUrl
                    })
                });
                const result = await response.json();

                if (result.success) {
                    document.getElementById('reportForm').reset();
                    photoDataUrl = null;
                    document.getElementById('photoPreview').innerHTML = '';
                    showReportAlert(result.message || 'Report submitted successfully.', false);
                    showPortalSection('reportsSection', true);
                } else {
                    showReportAlert(result.message || 'Failed to submit report.', true);
                }
            } catch (err) {
                showReportAlert('Network error. Please try again.', true);
            }
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

            window.addEventListener('hashchange', function() {
                const hash = window.location.hash.replace('#', '');
                showPortalSection(hash === 'reportsSection' ? 'reportsSection' : 'reportSection', false);
            });

            const initialHash = window.location.hash.replace('#', '');
            showPortalSection(initialHash === 'reportsSection' ? 'reportsSection' : 'reportSection', false);
        });
    </script>
    <script src="js/mobile-shell.js"></script>
</body>
</html>

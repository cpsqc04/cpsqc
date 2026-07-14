<?php
require_once __DIR__ . '/includes/nw_member_auth.php';

requireNwMemberLogin();
requireNwMemberPasswordChanged();

$memberName = htmlspecialchars(getNwMemberName());
$nwActiveNav = 'account';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Alertara</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/theme.css">
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
        .nav-submodule { padding: 0.75rem 1.5rem; color: rgba(255, 255, 255, 0.75); text-decoration: none; display: flex; align-items: center; gap: 0.75rem; transition: all 0.2s ease; font-size: 0.85rem; position: relative; }
        .nav-submodule:hover { background: rgba(255, 255, 255, 0.08); color: #fff; padding-left: 2rem; }
        .nav-submodule.active { background: rgba(76, 138, 137, 0.35); color: #fff; border-left: 3px solid var(--primary-color); font-weight: 600; }
        .nav-submodule-icon { width: 22px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .sidebar.collapsed .nav-submodule-text { opacity: 0; width: 0; overflow: hidden; display: none; }
        .sidebar.collapsed .nav-submodule { padding: 0.75rem; justify-content: center; }
        .sidebar-footer { margin-top: auto; padding: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.5rem; background: rgba(239, 68, 68, 0.1); color: rgba(255, 255, 255, 0.9); text-decoration: none; border-radius: 8px; font-size: 1rem; font-weight: 500; border: 1px solid rgba(239, 68, 68, 0.2); width: 100%; box-sizing: border-box; }
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
        .datetime-display { display: flex; align-items: center; gap: 0.75rem; color: var(--text-color); font-size: 0.9rem; font-weight: 500; }
        .datetime-display .date-part { color: var(--text-secondary); }
        .datetime-display .time-part { color: var(--text-color); font-weight: 600; }
        .content-area { padding: 2rem; flex: 1; background: #f5f5f5; }
        .page-content { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; box-shadow: 0 2px 8px var(--shadow); }
        .section-heading { margin: 0 0 1.5rem; color: var(--tertiary-color); font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .section-block { margin-bottom: 2.5rem; padding-bottom: 2.5rem; border-bottom: 1px solid var(--border-color); }
        .section-block:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem; }
        .form-group { margin-bottom: 0; min-width: 0; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-color); font-weight: 500; font-size: 0.95rem; }
        .form-group input, .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.95rem; font-family: var(--font-family); box-sizing: border-box; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1); }
        .form-group input[readonly] { background: #f8fafc; color: var(--text-secondary); cursor: not-allowed; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .btn-submit { padding: 0.75rem 1.5rem; background: var(--primary-color); color: #fff; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; margin-top: 1.25rem; }
        .btn-submit:hover { background: #4ca8a6; }
        .alert { padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.92rem; display: none; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        @media (max-width: 768px) {
            .main-wrapper { margin-left: 0; }
            body.sidebar-collapsed .main-wrapper { margin-left: 0; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <a href="nw-dashboard.php">
                    <img src="images/tara.png" alt="Alertara Logo">
                </a>
                <div class="user-name-display" id="sidebarMemberName"><?php echo $memberName; ?></div>
                <div class="member-status-chip"><i class="fas fa-user-shield"></i> Active Member</div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <?php require __DIR__ . '/includes/nw_portal_sidebar_nav.php'; ?>
        </nav>
        <div class="sidebar-footer">
            <a href="nw-logout.php" class="sidebar-logout-btn" data-tooltip="Logout">
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
                    <h1 class="page-title">Account Settings</h1>
                </div>
            </div>
            <div class="datetime-display">
                <span class="date-part" id="currentDate"></span>
                <span class="time-part" id="currentTime"></span>
            </div>
        </header>

        <main class="content-area">
            <div class="page-content">
                <section class="section-block">
                    <h2 class="section-heading"><i class="fas fa-user"></i> Personal Information</h2>
                    <div id="profileAlert" class="alert"></div>
                    <form id="profileForm" autocomplete="off">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="profileName">Full Name *</label>
                                <input id="profileName" type="text" required>
                            </div>
                            <div class="form-group">
                                <label for="profileContact">Contact Number *</label>
                                <input id="profileContact" type="tel" class="contact-number-input" required>
                            </div>
                            <div class="form-group">
                                <label for="profileEmail">Email *</label>
                                <input id="profileEmail" type="email" required>
                            </div>
                            <div class="form-group">
                                <label for="profileMemberCode">Member Code</label>
                                <input id="profileMemberCode" type="text" readonly>
                            </div>
                            <div class="form-group full-width">
                                <label for="profileAddress">Address *</label>
                                <textarea id="profileAddress" required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="profileEmergencyName">Emergency Contact Name *</label>
                                <input id="profileEmergencyName" type="text" required>
                            </div>
                            <div class="form-group">
                                <label for="profileEmergencyContact">Emergency Contact Number *</label>
                                <input id="profileEmergencyContact" type="tel" class="contact-number-input" required>
                            </div>
                        </div>
                        <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Save Personal Info</button>
                    </form>
                </section>

                <section class="section-block">
                    <h2 class="section-heading"><i class="fas fa-key"></i> Change Password</h2>
                    <div id="passwordAlert" class="alert"></div>
                    <form id="passwordForm" autocomplete="off">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="currentPassword">Current Password *</label>
                                <input id="currentPassword" type="password" required>
                            </div>
                            <div class="form-group">
                                <label for="newPassword">New Password *</label>
                                <input id="newPassword" type="password" minlength="6" required>
                            </div>
                            <div class="form-group">
                                <label for="confirmPassword">Confirm New Password *</label>
                                <input id="confirmPassword" type="password" minlength="6" required>
                            </div>
                        </div>
                        <button type="submit" class="btn-submit"><i class="fas fa-lock"></i> Update Password</button>
                    </form>
                </section>
            </div>
        </main>
    </div>

    <script src="js/form-contact-validation.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
                return;
            }
            const isCollapsed = sidebar.classList.contains('collapsed');
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

        function showAlert(elementId, message, isError) {
            const el = document.getElementById(elementId);
            el.className = 'alert ' + (isError ? 'alert-error' : 'alert-success');
            el.textContent = message;
            el.style.display = 'block';
        }

        function fillProfileForm(data) {
            document.getElementById('profileName').value = data.name || '';
            document.getElementById('profileContact').value = data.contact || '';
            document.getElementById('profileEmail').value = data.email || '';
            document.getElementById('profileMemberCode').value = data.member_code || '';
            document.getElementById('profileAddress').value = data.address || '';
            document.getElementById('profileEmergencyName').value = data.emergency_contact_name || '';
            document.getElementById('profileEmergencyContact').value = data.emergency_contact_number || '';
            const sidebarName = document.getElementById('sidebarMemberName');
            if (sidebarName && data.name) sidebarName.textContent = data.name;
        }

        async function loadProfile() {
            try {
                const response = await fetch('api/nw_member_profile.php');
                const result = await response.json();
                if (result.success && result.data) {
                    fillProfileForm(result.data);
                } else {
                    showAlert('profileAlert', result.message || 'Failed to load profile.', true);
                }
            } catch (err) {
                showAlert('profileAlert', 'Network error while loading profile.', true);
            }
        }

        document.getElementById('profileForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const contactError = AlertaraFormEnhancements.validateContactInput(document.getElementById('profileContact'), 'Contact number');
            if (contactError) {
                showAlert('profileAlert', contactError, true);
                return;
            }
            const emergencyContactError = AlertaraFormEnhancements.validateContactInput(document.getElementById('profileEmergencyContact'), 'Emergency contact number');
            if (emergencyContactError) {
                showAlert('profileAlert', emergencyContactError, true);
                return;
            }

            try {
                const response = await fetch('api/nw_member_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_profile',
                        name: document.getElementById('profileName').value.trim(),
                        contact: document.getElementById('profileContact').value.trim(),
                        email: document.getElementById('profileEmail').value.trim(),
                        address: document.getElementById('profileAddress').value.trim(),
                        emergency_contact_name: document.getElementById('profileEmergencyName').value.trim(),
                        emergency_contact_number: document.getElementById('profileEmergencyContact').value.trim()
                    })
                });
                const result = await response.json();
                if (result.success) {
                    if (result.data) fillProfileForm(result.data);
                    showAlert('profileAlert', result.message || 'Profile updated.', false);
                } else {
                    showAlert('profileAlert', result.message || 'Failed to update profile.', true);
                }
            } catch (err) {
                showAlert('profileAlert', 'Network error. Please try again.', true);
            }
        });

        document.getElementById('passwordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            try {
                const response = await fetch('api/nw_member_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'change_password',
                        current_password: document.getElementById('currentPassword').value,
                        new_password: document.getElementById('newPassword').value,
                        confirm_password: document.getElementById('confirmPassword').value
                    })
                });
                const result = await response.json();
                if (result.success) {
                    document.getElementById('passwordForm').reset();
                    showAlert('passwordAlert', result.message || 'Password updated.', false);
                } else {
                    showAlert('passwordAlert', result.message || 'Failed to update password.', true);
                }
            } catch (err) {
                showAlert('passwordAlert', 'Network error. Please try again.', true);
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            if (localStorage.getItem('nwSidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
            updateDateTime();
            setInterval(updateDateTime, 1000);
            loadProfile();
        });
    </script>
</body>
</html>

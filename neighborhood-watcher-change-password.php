<?php
require_once __DIR__ . '/includes/neighborhood-watcher-member-auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api/neighborhood-watcher-members-schema.php';
require_once __DIR__ . '/includes/neighborhood-watcher-member-credentials.php';
require_once __DIR__ . '/includes/volunteer_notifications.php';
require_once __DIR__ . '/includes/login_otp.php';

$autoloadPath = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

nwMemberSessionStart();
requireNwMemberLogin();

if (!nwMemberMustChangePassword() && empty($_SESSION['pending_nw_password_change_otp'])) {
    header('Location: neighborhood-watcher-login.php');
    exit;
}

// Prefer the modal flow on the login page.
header('Location: neighborhood-watcher-login.php');
exit;

<?php
require_once __DIR__ . '/includes/bpso_auth.php';

bpsoSessionStart();

unset(
    $_SESSION['bpso_logged_in'],
    $_SESSION['bpso_patrol_id'],
    $_SESSION['bpso_personnel_name'],
    $_SESSION['bpso_personnel_code'],
    $_SESSION['bpso_email'],
    $_SESSION['bpso_must_change_password'],
    $_SESSION['pending_bpso_login']
);

header('Location: patrol-login.php');
exit;

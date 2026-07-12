<?php
require_once __DIR__ . '/includes/nw_member_auth.php';

nwMemberSessionStart();

unset(
    $_SESSION['nw_member_logged_in'],
    $_SESSION['nw_member_id'],
    $_SESSION['nw_member_name'],
    $_SESSION['nw_member_code'],
    $_SESSION['nw_member_email'],
    $_SESSION['nw_member_must_change_password']
);

header('Location: nw-login.php');
exit;

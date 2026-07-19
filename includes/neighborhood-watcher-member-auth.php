<?php

/**
 * Neighborhood Watch member session helpers (separate from admin and BPSO).
 */
function nwMemberSessionStart(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isNwMemberLoggedIn(): bool
{
    nwMemberSessionStart();
    return !empty($_SESSION['nw_member_logged_in']) && !empty($_SESSION['nw_member_id']);
}

function requireNwMemberLogin(): void
{
    if (!isNwMemberLoggedIn()) {
        header('Location: neighborhood-watcher-login.php');
        exit;
    }
}

function getNwMemberId(): int
{
    nwMemberSessionStart();
    return (int) ($_SESSION['nw_member_id'] ?? 0);
}

function getNwMemberName(): string
{
    nwMemberSessionStart();
    return (string) ($_SESSION['nw_member_name'] ?? '');
}

function getNwMemberEmail(): string
{
    nwMemberSessionStart();
    return (string) ($_SESSION['nw_member_email'] ?? '');
}

function nwMemberMustChangePassword(): bool
{
    nwMemberSessionStart();
    return !empty($_SESSION['nw_member_must_change_password']);
}

function requireNwMemberPasswordChanged(): void
{
    requireNwMemberLogin();
    if (nwMemberMustChangePassword() || !empty($_SESSION['pending_nw_password_change_otp'])) {
        header('Location: neighborhood-watcher-login.php');
        exit;
    }
}

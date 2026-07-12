<?php

/**
 * BPSO personnel session helpers (separate from admin session).
 */
function bpsoSessionStart(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isBpsoLoggedIn(): bool
{
    bpsoSessionStart();
    return !empty($_SESSION['bpso_logged_in']) && !empty($_SESSION['bpso_patrol_id']);
}

function requireBpsoLogin(): void
{
    if (!isBpsoLoggedIn()) {
        header('Location: bpso-login.php');
        exit;
    }
}

function getBpsoPatrolId(): int
{
    bpsoSessionStart();
    return (int)($_SESSION['bpso_patrol_id'] ?? 0);
}

function getBpsoPersonnelName(): string
{
    bpsoSessionStart();
    return (string)($_SESSION['bpso_personnel_name'] ?? '');
}

function isAdminLoggedIn(): bool
{
    bpsoSessionStart();
    return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireAdminLogin(): void
{
    if (!isAdminLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

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

function bpsoMustChangePassword(): bool
{
    bpsoSessionStart();
    return !empty($_SESSION['bpso_must_change_password']);
}

function requireBpsoLogin(): void
{
    if (!isBpsoLoggedIn()) {
        header('Location: patrol-login.php');
        exit;
    }

    if (bpsoMustChangePassword()) {
        header('Location: patrol-login.php');
        exit;
    }
}

function getBpsoPatrolId(): int
{
    bpsoSessionStart();
    return (int) ($_SESSION['bpso_patrol_id'] ?? 0);
}

function getBpsoPersonnelName(): string
{
    bpsoSessionStart();
    return (string) ($_SESSION['bpso_personnel_name'] ?? '');
}

function getBpsoPersonnelCode(): string
{
    bpsoSessionStart();
    $patrolId = (int) ($_SESSION['bpso_patrol_id'] ?? 0);
    if ($patrolId > 0) {
        require_once __DIR__ . '/../db.php';
        global $pdo;
        if ($pdo instanceof PDO) {
            try {
                require_once __DIR__ . '/managed_user_display_ids.php';
                syncBpsoPersonnelIdsToPatFormat($pdo);
                $stmt = $pdo->prepare('SELECT bpso_personnel_id FROM patrols WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $patrolId]);
                $code = trim((string) $stmt->fetchColumn());
                if ($code !== '') {
                    $_SESSION['bpso_personnel_code'] = $code;
                    return $code;
                }
            } catch (Throwable $e) {
                // Fall back to session value below.
            }
        }
    }

    return (string) ($_SESSION['bpso_personnel_code'] ?? '');
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

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

function getNwMemberCode(): string
{
    nwMemberSessionStart();
    $memberId = (int) ($_SESSION['nw_member_id'] ?? 0);
    if ($memberId > 0) {
        require_once __DIR__ . '/../db.php';
        global $pdo;
        if ($pdo instanceof PDO) {
            try {
                require_once __DIR__ . '/managed_user_display_ids.php';
                require_once __DIR__ . '/../api/neighborhood-watcher-members-schema.php';
                ensureNwMembersTable($pdo);
                syncNwMemberCodesToDisplayIds($pdo);
                $stmt = $pdo->prepare('SELECT member_code FROM nw_members WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $memberId]);
                $code = trim((string) $stmt->fetchColumn());
                if ($code !== '') {
                    $_SESSION['nw_member_code'] = $code;
                    return $code;
                }

                $code = resolveNwMemberDisplayCode($pdo, $memberId);
                if ($code !== '') {
                    $_SESSION['nw_member_code'] = $code;
                    return $code;
                }
            } catch (Throwable $e) {
                // Fall back to session value below.
            }
        }
    }

    return (string) ($_SESSION['nw_member_code'] ?? '');
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

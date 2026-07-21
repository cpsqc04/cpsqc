<?php

function syncAdminSessionRole(PDO $pdo): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['admin_logged_in'])) {
        return;
    }

    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        if (empty($_SESSION['user_role'])) {
            $_SESSION['user_role'] = 'Admin';
        }
        return;
    }

    try {
        $stmt = $pdo->prepare('SELECT username, full_name, role FROM admins WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            if (empty($_SESSION['user_role'])) {
                $_SESSION['user_role'] = 'Admin';
            }
            return;
        }

        $username = trim((string) ($row['username'] ?? ''));
        $fullName = trim((string) ($row['full_name'] ?? ''));
        $role = trim((string) ($row['role'] ?? ''));

        if ($username !== '') {
            $_SESSION['username'] = $username;
        }
        $_SESSION['full_name'] = $fullName !== '' ? $fullName : ($username !== '' ? $username : 'Admin');
        $_SESSION['user_role'] = $role !== '' ? $role : 'Admin';
    } catch (PDOException $e) {
        error_log('Failed to sync admin session profile: ' . $e->getMessage());
        if (empty($_SESSION['user_role'])) {
            $_SESSION['user_role'] = 'Admin';
        }
    }
}

/**
 * Display name for the logged-in admin (full name preferred).
 */
function getAdminDisplayName(): string
{
    $fullName = trim((string) ($_SESSION['full_name'] ?? ''));
    if ($fullName !== '') {
        return $fullName;
    }

    $username = trim((string) ($_SESSION['username'] ?? ''));
    return $username !== '' ? $username : 'Admin';
}

function isAdminUser(): bool
{
    if (empty($_SESSION['admin_logged_in'])) {
        return false;
    }

    $role = trim((string) ($_SESSION['user_role'] ?? ''));
    if ($role === '') {
        return true;
    }

    return strcasecmp($role, 'Admin') === 0;
}

function normalizeUserRole(?string $role): string
{
    $role = trim((string) $role);
    if ($role === '' || strcasecmp($role, 'User') === 0) {
        return 'BPSO Personnel';
    }

    return $role;
}

function isBpsoPersonnelRole(?string $role): bool
{
    return strcasecmp(normalizeUserRole($role), 'BPSO Personnel') === 0;
}

function formatUserRoleLabel(?string $role): string
{
    return normalizeUserRole($role);
}

<?php

function syncAdminSessionRole(PDO $pdo): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['admin_logged_in'])) {
        return;
    }

    if (!empty($_SESSION['user_role'])) {
        return;
    }

    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        $_SESSION['user_role'] = 'Admin';
        return;
    }

    try {
        $stmt = $pdo->prepare('SELECT role FROM admins WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        $role = trim((string) ($row['role'] ?? ''));
        $_SESSION['user_role'] = $role !== '' ? $role : 'Admin';
    } catch (PDOException $e) {
        error_log('Failed to sync admin session role: ' . $e->getMessage());
        $_SESSION['user_role'] = 'Admin';
    }
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

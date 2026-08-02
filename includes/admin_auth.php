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
        $stmt = $pdo->prepare('SELECT username, full_name, role, must_change_password FROM admins WHERE id = :id LIMIT 1');
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
        if ($role === '' || strcasecmp($role, 'Admin') === 0) {
            $_SESSION['user_role'] = 'Admin';
        } else {
            $_SESSION['user_role'] = $role;
        }
        $_SESSION['admin_must_change_password'] = !empty($row['must_change_password']);
    } catch (PDOException $e) {
        // Column may not exist yet on first boot; fall back without blocking login.
        try {
            $stmt = $pdo->prepare('SELECT username, full_name, role FROM admins WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $username = trim((string) ($row['username'] ?? ''));
                $fullName = trim((string) ($row['full_name'] ?? ''));
                $role = trim((string) ($row['role'] ?? ''));
                if ($username !== '') {
                    $_SESSION['username'] = $username;
                }
                $_SESSION['full_name'] = $fullName !== '' ? $fullName : ($username !== '' ? $username : 'Admin');
                $_SESSION['user_role'] = ($role === '' || strcasecmp($role, 'Admin') === 0) ? 'Admin' : $role;
            }
        } catch (PDOException $inner) {
            error_log('Failed to sync admin session profile: ' . $inner->getMessage());
        }
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
    // Empty role is treated as Admin for legacy sessions.
    if ($role === '') {
        return true;
    }

    // Only the Admin role may access admin-side management features.
    return strcasecmp($role, 'Admin') === 0;
}

function adminMustChangePassword(): bool
{
    return !empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_must_change_password']);
}

/**
 * Keep Admin accounts on login.php until they set a permanent password.
 */
function enforceAdminPasswordChangeGate(): void
{
    if (!adminMustChangePassword()) {
        return;
    }

    $scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $script = basename($scriptPath);
    if ($script === 'login.php' || $script === 'logout.php' || $script === 'reset-password.php') {
        return;
    }
    if (str_contains($scriptPath, '/api/')) {
        return;
    }

    header('Location: login.php');
    exit;
}

/**
 * Normalize stored admin roles so Admin accounts keep full portal access.
 */
function ensureAdminRolesNormalized(PDO $pdo): void
{
    try {
        // Legacy "User" rows were BPSO mirrors; keep them out of Admin access.
        $pdo->exec("UPDATE admins SET role = 'BPSO Personnel' WHERE role = 'User'");
        // Canonicalize Admin casing / blanks so every Admin can open User Management.
        $pdo->exec("UPDATE admins SET role = 'Admin' WHERE role IS NULL OR TRIM(role) = '' OR LOWER(TRIM(role)) = 'admin'");
    } catch (PDOException $e) {
        error_log('Failed to normalize admin roles: ' . $e->getMessage());
    }
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

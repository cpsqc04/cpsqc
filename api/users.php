<?php
// User Management API
header('Content-Type: application/json');
ob_start();

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/neighborhood-watcher-members-schema.php';
require_once __DIR__ . '/../includes/password_reset_tokens.php';
require_once __DIR__ . '/../includes/login_otp.php';
require_once __DIR__ . '/../includes/app_url.php';
require_once __DIR__ . '/../includes/admin_credentials.php';
require_once __DIR__ . '/../includes/managed_user_display_ids.php';

if (!isAdminUser()) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

function tableHasColumn(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE :column');
        $stmt->execute([':column' => $column]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
}

function ensureUsersTable(PDO $pdo): void
{
    $adminColumns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM admins') as $row) {
        $adminColumns[$row['Field']] = true;
    }

    if (!isset($adminColumns['email'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN email VARCHAR(255) DEFAULT NULL');
    }

    if (!isset($adminColumns['full_name'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN full_name VARCHAR(255) DEFAULT NULL');
    }

    if (!isset($adminColumns['created_at'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    }

    if (!isset($adminColumns['role'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN role VARCHAR(50) DEFAULT "Admin"');
    }

    if (!isset($adminColumns['status'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN status VARCHAR(20) DEFAULT "Active"');
    }

    if (!isset($adminColumns['must_change_password'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash');
    }

    // Cross-check email uniqueness against BPSO accounts — ensure that table/column exists too.
    $patrolColumns = [];
    $patrolsExist = false;
    try {
        foreach ($pdo->query('SHOW COLUMNS FROM patrols') as $row) {
            $patrolColumns[$row['Field']] = true;
            $patrolsExist = true;
        }
    } catch (PDOException $e) {
        $patrolsExist = false;
    }

    if ($patrolsExist && !isset($patrolColumns['email'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN email VARCHAR(255) NULL');
    }

    $pdo->exec("UPDATE admins SET role = 'BPSO Personnel' WHERE role = 'User'");
    ensureAdminRolesNormalized($pdo);
}

/**
 * Returns true when another row already uses this email.
 * Skips checks for tables/columns that are not present yet.
 */
function emailExistsOnAccountTables(PDO $pdo, string $email, ?string $excludeType = null, ?int $excludeId = null): bool
{
    if (tableHasColumn($pdo, 'admins', 'email')) {
        if ($excludeType === 'admin' && $excludeId) {
            $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = :email AND id != :id LIMIT 1');
            $stmt->execute([':email' => $email, ':id' => $excludeId]);
        } else {
            $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
        }
        if ($stmt->fetch()) {
            return true;
        }
    }

    if (tableHasColumn($pdo, 'patrols', 'email')) {
        if ($excludeType === 'bpso' && $excludeId) {
            $stmt = $pdo->prepare('SELECT id FROM patrols WHERE email = :email AND id != :id LIMIT 1');
            $stmt->execute([':email' => $email, ':id' => $excludeId]);
        } else {
            $stmt = $pdo->prepare('SELECT id FROM patrols WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
        }
        if ($stmt->fetch()) {
            return true;
        }
    }

    if (tableHasColumn($pdo, 'nw_members', 'email') || tableHasColumn($pdo, 'volunteers', 'email')) {
        try {
            require_once __DIR__ . '/neighborhood-watcher-members-schema.php';
            ensureNwMembersTable($pdo);
            $table = nwMembersTableName();
            if ($excludeType === 'nw' && $excludeId) {
                $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE email = :email AND id != :id LIMIT 1");
                $stmt->execute([':email' => $email, ':id' => $excludeId]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $email]);
            }
            if ($stmt->fetch()) {
                return true;
            }
        } catch (PDOException $e) {
            // NW table may be unavailable.
        }
    }

    return false;
}

function formatCreatedAt(?string $createdAt): ?string
{
    if (!$createdAt) {
        return null;
    }

    return date('Y-m-d H:i:s', strtotime($createdAt));
}

function mapBpsoUser(array $row): array
{
    return [
        'id' => 'bpso-' . $row['id'],
        'numeric_id' => (int) $row['id'],
        'account_type' => 'bpso',
        'full_name' => $row['personnel_name'],
        'username' => $row['bpso_personnel_id'],
        'email' => $row['email'],
        'role' => 'BPSO Personnel',
        'role_locked' => true,
        'status' => $row['status'] ?? 'Available',
        'created_at' => formatCreatedAt($row['created_at'] ?? null),
    ];
}

function mapNwUser(array $row): array
{
    return [
        'id' => 'nw-' . $row['id'],
        'numeric_id' => (int) $row['id'],
        'account_type' => 'nw',
        'full_name' => $row['name'] ?? '',
        'username' => $row['member_code'] ?? ('NW-' . $row['id']),
        'email' => $row['email'] ?? '',
        'role' => 'Neighborhood Watcher',
        'role_locked' => true,
        'status' => $row['status'] ?? 'Pending',
        'created_at' => formatCreatedAt($row['created_at'] ?? null),
    ];
}

function mapAdminUser(array $row): array
{
    return [
        'id' => 'admin-' . $row['id'],
        'numeric_id' => (int) $row['id'],
        'account_type' => 'admin',
        'full_name' => $row['full_name'],
        'username' => $row['username'],
        'email' => $row['email'],
        'role' => 'Admin',
        'role_locked' => false,
        'status' => $row['status'] ?? 'Active',
        'created_at' => formatCreatedAt($row['created_at'] ?? null),
    ];
}

function fetchAllManagedUsers(PDO $pdo): array
{
    $users = [];
    $adminRows = [];
    $bpsoRows = [];
    $nwRows = [];

    $stmt = $pdo->query('SELECT id, full_name, username, email, role, status, created_at FROM admins ORDER BY created_at DESC');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isBpsoPersonnelRole($row['role'] ?? '')) {
            continue;
        }
        $adminRows[] = $row;
    }

    try {
        $stmt = $pdo->query('SELECT id, bpso_personnel_id, personnel_name, email, status, created_at FROM patrols ORDER BY created_at DESC');
        $bpsoRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Patrol table may not exist yet on older installs.
    }

    try {
        ensureNwMembersTable($pdo);
        $table = nwMembersTableName();
        $stmt = $pdo->query("SELECT id, name, email, member_code, status, created_at FROM {$table} WHERE status = 'Active' ORDER BY created_at DESC");
        $nwRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // NW table may not exist yet, or schema prep failed — still return other users.
    }

    $adminDisplayMap = buildAdminDisplayIdMap($adminRows);
    $bpsoDisplayMap = buildBpsoDisplayIdMap($bpsoRows);
    $nwDisplayMap = buildNwMemberDisplayIdMap($nwRows);

    foreach ($adminRows as $row) {
        $user = mapAdminUser($row);
        $user['display_id'] = $adminDisplayMap[(int) $row['id']] ?? formatAdminDisplayId(1);
        $users[] = $user;
    }

    foreach ($bpsoRows as $row) {
        $user = mapBpsoUser($row);
        $user['display_id'] = $bpsoDisplayMap[(int) $row['id']] ?? formatBpsoDisplayId(1);
        $users[] = $user;
    }

    foreach ($nwRows as $row) {
        $user = mapNwUser($row);
        $user['display_id'] = $nwDisplayMap[(int) $row['id']] ?? formatNwMemberDisplayId(1);
        $users[] = $user;
    }

    return $users;
}

function attachManagedUserDisplayId(PDO $pdo, array $user): array
{
    $type = $user['account_type'] ?? '';
    $numericId = (int) ($user['numeric_id'] ?? 0);

    if ($type === 'bpso') {
        try {
            $stmt = $pdo->query('SELECT id FROM patrols ORDER BY id ASC');
            $user['display_id'] = buildBpsoDisplayIdMap($stmt->fetchAll(PDO::FETCH_ASSOC))[$numericId] ?? formatBpsoDisplayId(1);
        } catch (PDOException $e) {
            $user['display_id'] = formatBpsoDisplayId(1);
        }
        return $user;
    }

    if ($type === 'nw') {
        try {
            ensureNwMembersTable($pdo);
            $table = nwMembersTableName();
            $stmt = $pdo->query("SELECT id, status FROM {$table} WHERE status = 'Active' ORDER BY id ASC");
            $user['display_id'] = buildNwMemberDisplayIdMap($stmt->fetchAll(PDO::FETCH_ASSOC))[$numericId] ?? formatNwMemberDisplayId(1);
        } catch (Throwable $e) {
            $user['display_id'] = formatNwMemberDisplayId(1);
        }
        return $user;
    }

    $adminRows = [];
    $stmt = $pdo->query('SELECT id, role FROM admins ORDER BY id ASC');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isBpsoPersonnelRole($row['role'] ?? '')) {
            continue;
        }
        $adminRows[] = $row;
    }
    $user['display_id'] = buildAdminDisplayIdMap($adminRows)[$numericId] ?? formatAdminDisplayId(1);

    return $user;
}

function parseManagedUserId($rawId): array
{
    $rawId = trim((string) $rawId);

    if (preg_match('/^admin-(\d+)$/', $rawId, $matches)) {
        return ['type' => 'admin', 'id' => (int) $matches[1]];
    }

    if (preg_match('/^bpso-(\d+)$/', $rawId, $matches)) {
        return ['type' => 'bpso', 'id' => (int) $matches[1]];
    }

    if (preg_match('/^nw-(\d+)$/', $rawId, $matches)) {
        return ['type' => 'nw', 'id' => (int) $matches[1]];
    }

    if (ctype_digit($rawId)) {
        return ['type' => 'admin', 'id' => (int) $rawId];
    }

    return ['type' => '', 'id' => 0];
}

function fetchManagedUser(PDO $pdo, string $rawId): ?array
{
    $parsed = parseManagedUserId($rawId);
    if ($parsed['id'] <= 0) {
        return null;
    }

    if ($parsed['type'] === 'bpso') {
        $stmt = $pdo->prepare('SELECT id, bpso_personnel_id, personnel_name, email, status, created_at FROM patrols WHERE id = :id');
        $stmt->execute([':id' => $parsed['id']]);
        $row = $stmt->fetch();
        return $row ? attachManagedUserDisplayId($pdo, mapBpsoUser($row)) : null;
    }

    if ($parsed['type'] === 'nw') {
        try {
            ensureNwMembersTable($pdo);
            $table = nwMembersTableName();
            $stmt = $pdo->prepare("SELECT id, name, email, member_code, status, created_at FROM {$table} WHERE id = :id");
            $stmt->execute([':id' => $parsed['id']]);
            $row = $stmt->fetch();
            return $row ? attachManagedUserDisplayId($pdo, mapNwUser($row)) : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    $stmt = $pdo->prepare('SELECT id, full_name, username, email, role, status, created_at FROM admins WHERE id = :id');
    $stmt->execute([':id' => $parsed['id']]);
    $row = $stmt->fetch();
    return $row ? attachManagedUserDisplayId($pdo, mapAdminUser($row)) : null;
}

function validatePassword(string $password): bool
{
    return preg_match('/[A-Z]/', $password) && preg_match('/[0-9!@#$%^&*(),.?":{}|<>]/', $password);
}

try {
    ensureUsersTable($pdo);
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $userId = trim((string) ($_GET['id'] ?? ''));

    try {
        if ($userId !== '') {
            $user = fetchManagedUser($pdo, $userId);
            if (!$user) {
                ob_clean();
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'User not found']);
                exit;
            }

            ob_clean();
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            ob_clean();
            echo json_encode(['success' => true, 'users' => fetchAllManagedUsers($pdo)]);
        }
    } catch (PDOException $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch users: ' . $e->getMessage()]);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = trim((string) ($data['action'] ?? ''));

    if ($action === 'send_reset_link') {
        $parsed = parseManagedUserId($data['id'] ?? '');
        if ($parsed['id'] <= 0 || $parsed['type'] === '') {
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
            exit;
        }

        $user = fetchManagedUser($pdo, (string) ($data['id'] ?? ''));
        if (!$user) {
            ob_clean();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }

        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'This account has no valid email address. Add an email first.']);
            exit;
        }

        try {
            $rawToken = createPasswordResetToken($pdo, $parsed['type'], $parsed['id'], $email, 60);
            $resetUrl = buildPasswordResetUrl($rawToken);
            $portal = $parsed['type'] === 'bpso' ? 'bpso' : ($parsed['type'] === 'nw' ? 'nw' : 'admin');
            if (!sendPasswordResetLinkEmail($email, $resetUrl, $portal, (string) ($user['full_name'] ?? ''))) {
                ob_clean();
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to send reset email. Check mail configuration.']);
                exit;
            }

            $atPos = strpos($email, '@');
            $masked = substr($email, 0, 3) . '***' . ($atPos !== false ? substr($email, $atPos) : '');
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Password reset link sent to ' . $masked . '.',
            ]);
        } catch (Throwable $e) {
            error_log('send_reset_link failed: ' . $e->getMessage());
            ob_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create reset link.']);
        }
        exit;
    }

    $fullName = trim($data['full_name'] ?? '');
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $role = normalizeUserRole(trim($data['role'] ?? 'Admin'));

    if ($role !== 'Admin') {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Only Admin accounts can be created here. Register BPSO personnel under Patrol List.']);
        exit;
    }

    if ($fullName === '' || $username === '' || $email === '') {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Full name, username, and email are required']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email address']);
        exit;
    }

    try {
        ensureAdminMustChangePasswordColumn($pdo);

        $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = :username');
        $stmt->execute([':username' => $username]);
        if ($stmt->fetch()) {
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Username already exists']);
            exit;
        }

        if (emailExistsOnAccountTables($pdo, $email)) {
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Email already exists']);
            exit;
        }

        $tempPassword = generateAdminTempPassword();
        $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('INSERT INTO admins (full_name, username, email, password_hash, must_change_password, role, status, created_at) VALUES (:full_name, :username, :email, :password_hash, 1, :role, "Active", NOW())');
        $stmt->execute([
            ':full_name' => $fullName,
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':role' => 'Admin',
        ]);

        $newId = (int) $pdo->lastInsertId();
        $mailResult = sendAdminWelcomeCredentialsEmail($email, $fullName, $username, $tempPassword);
        $user = fetchManagedUser($pdo, 'admin-' . $newId);

        ob_clean();
        echo json_encode([
            'success' => true,
            'user' => $user,
            'email_sent' => !empty($mailResult['success']),
            'message' => !empty($mailResult['success'])
                ? 'Admin account created. Temporary password emailed to ' . $email . '.'
                : 'Admin account created, but the welcome email failed to send. Use Send Password Reset Link from Edit User.',
            'email_error' => $mailResult['error'] ?? null,
        ]);
    } catch (PDOException $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create user: ' . $e->getMessage()]);
    }
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $parsed = parseManagedUserId($data['id'] ?? '');

    if ($parsed['id'] <= 0 || $parsed['type'] === '') {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
        exit;
    }

    try {
        $isStatusOnly = isset($data['status'])
            && !isset($data['full_name'])
            && !isset($data['username'])
            && !isset($data['email'])
            && !isset($data['password'])
            && !isset($data['role']);

        if ($isStatusOnly) {
            $status = trim($data['status'] ?? '');

            if ($parsed['type'] === 'bpso') {
                $allowed = ['Available', 'Assigned', 'Assigned to Simulation', 'On Patrol', 'Unavailable'];
                if ($status === 'Off Duty' || $status === 'Off-Duty') {
                    $status = 'Unavailable';
                }
                if (!in_array($status, $allowed, true)) {
                    ob_clean();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid status']);
                    exit;
                }

                $stmt = $pdo->prepare('UPDATE patrols SET status = :status WHERE id = :id');
                $stmt->execute([':status' => $status, ':id' => $parsed['id']]);
            } elseif ($parsed['type'] === 'nw') {
                if (!in_array($status, ['Active', 'Inactive'], true)) {
                    ob_clean();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid status']);
                    exit;
                }
                ensureNwMembersTable($pdo);
                $table = nwMembersTableName();
                $stmt = $pdo->prepare("UPDATE {$table} SET status = :status WHERE id = :id");
                $stmt->execute([':status' => $status, ':id' => $parsed['id']]);
            } else {
                if (!in_array($status, ['Active', 'Inactive'], true)) {
                    ob_clean();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid status']);
                    exit;
                }

                if ($status === 'Inactive') {
                    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM admins WHERE role = "Admin" AND status = "Active" AND id != :id');
                    $stmt->execute([':id' => $parsed['id']]);
                    $result = $stmt->fetch();
                    if ($result && (int) $result['count'] === 0) {
                        ob_clean();
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Cannot deactivate the last active admin']);
                        exit;
                    }
                }

                $stmt = $pdo->prepare('UPDATE admins SET status = :status WHERE id = :id');
                $stmt->execute([':status' => $status, ':id' => $parsed['id']]);
            }

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'User status updated']);
        } else {
            $fullName = trim($data['full_name'] ?? '');
            $username = trim($data['username'] ?? '');
            $email = trim($data['email'] ?? '');
            $password = $data['password'] ?? '';
            $role = normalizeUserRole(trim($data['role'] ?? 'Admin'));

            if ($fullName === '' || $email === '') {
                ob_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Full name and email are required']);
                exit;
            }

            if ($parsed['type'] === 'bpso') {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    ob_clean();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
                    exit;
                }

                if (emailExistsOnAccountTables($pdo, $email, 'bpso', $parsed['id'])) {
                    ob_clean();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email already exists']);
                    exit;
                }

                // Role is fixed for BPSO / patrol accounts — ignore any role payload.
                $updateFields = ['personnel_name = :full_name', 'email = :email'];
                $params = [
                    ':full_name' => $fullName,
                    ':email' => $email,
                    ':id' => $parsed['id'],
                ];

                if ($password !== '') {
                    if (!validatePassword($password)) {
                        ob_clean();
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Password must contain at least one capital letter and one number or special character']);
                        exit;
                    }
                    $updateFields[] = 'password_hash = :password_hash';
                    $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }

                $sql = 'UPDATE patrols SET ' . implode(', ', $updateFields) . ' WHERE id = :id';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } elseif ($parsed['type'] === 'nw') {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    ob_clean();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
                    exit;
                }

                if (emailExistsOnAccountTables($pdo, $email, 'nw', $parsed['id'])) {
                    ob_clean();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email already exists']);
                    exit;
                }

                ensureNwMembersTable($pdo);
                $table = nwMembersTableName();
                $updateFields = ['name = :full_name', 'email = :email'];
                $params = [
                    ':full_name' => $fullName,
                    ':email' => $email,
                    ':id' => $parsed['id'],
                ];

                if ($password !== '') {
                    if (!validatePassword($password)) {
                        ob_clean();
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Password must contain at least one capital letter and one number or special character']);
                        exit;
                    }
                    $updateFields[] = 'password_hash = :password_hash';
                    $updateFields[] = 'must_change_password = 0';
                    $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }

                $sql = "UPDATE {$table} SET " . implode(', ', $updateFields) . ' WHERE id = :id';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                if ($username === '') {
                    ob_clean();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Username is required']);
                    exit;
                }

                if ($role !== 'Admin') {
                    ob_clean();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid role']);
                    exit;
                }

                $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = :username AND id != :id');
                $stmt->execute([':username' => $username, ':id' => $parsed['id']]);
                if ($stmt->fetch()) {
                    ob_clean();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Username already exists']);
                    exit;
                }

                if (emailExistsOnAccountTables($pdo, $email, 'admin', $parsed['id'])) {
                    ob_clean();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email already exists']);
                    exit;
                }

                $updateFields = ['full_name = :full_name', 'username = :username', 'email = :email', 'role = :role'];
                $params = [
                    ':full_name' => $fullName,
                    ':username' => $username,
                    ':email' => $email,
                    ':role' => 'Admin',
                    ':id' => $parsed['id'],
                ];

                if ($password !== '') {
                    if (!validatePassword($password)) {
                        ob_clean();
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Password must contain at least one capital letter and one number or special character']);
                        exit;
                    }
                    $updateFields[] = 'password_hash = :password_hash';
                    $updateFields[] = 'must_change_password = 0';
                    $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }

                $stmt = $pdo->prepare('SELECT role, status FROM admins WHERE id = :id');
                $stmt->execute([':id' => $parsed['id']]);
                $currentUser = $stmt->fetch();

                if ($currentUser && ($currentUser['role'] ?? '') === 'Admin' && ($currentUser['status'] ?? '') === 'Active') {
                    if (isset($data['status']) && $data['status'] === 'Inactive') {
                        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM admins WHERE role = "Admin" AND status = "Active" AND id != :id');
                        $stmt->execute([':id' => $parsed['id']]);
                        $result = $stmt->fetch();
                        if ($result && (int) $result['count'] === 0) {
                            ob_clean();
                            http_response_code(400);
                            echo json_encode(['success' => false, 'error' => 'Cannot deactivate the last active admin']);
                            exit;
                        }
                    }
                }

                try {
                    $sql = 'UPDATE admins SET ' . implode(', ', $updateFields) . ' WHERE id = :id';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                } catch (PDOException $e) {
                    // Older installs may not have must_change_password yet.
                    if (str_contains($e->getMessage(), 'must_change_password')) {
                        $updateFields = array_values(array_filter($updateFields, static fn ($f) => !str_contains($f, 'must_change_password')));
                        $sql = 'UPDATE admins SET ' . implode(', ', $updateFields) . ' WHERE id = :id';
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                    } else {
                        throw $e;
                    }
                }

                if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) $parsed['id']) {
                    $_SESSION['username'] = $username;
                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['user_role'] = 'Admin';
                    if ($password !== '') {
                        $_SESSION['admin_must_change_password'] = false;
                    }
                }
            }

            $user = fetchManagedUser($pdo, ($parsed['type'] === 'bpso' ? 'bpso-' : ($parsed['type'] === 'nw' ? 'nw-' : 'admin-')) . $parsed['id']);

            ob_clean();
            echo json_encode(['success' => true, 'user' => $user, 'message' => 'User updated successfully']);
        }
    } catch (PDOException $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update user: ' . $e->getMessage()]);
    }
} else {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

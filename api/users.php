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

    return false;
}

function formatCreatedAt(?string $createdAt): ?string
{
    if (!$createdAt) {
        return null;
    }

    return date('Y-m-d H:i:s', strtotime($createdAt));
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
        'role' => formatUserRoleLabel($row['role'] ?? 'Admin'),
        'status' => $row['status'] ?? 'Active',
        'created_at' => formatCreatedAt($row['created_at'] ?? null),
    ];
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
        'status' => $row['status'] ?? 'Available',
        'created_at' => formatCreatedAt($row['created_at'] ?? null),
    ];
}

function fetchAllManagedUsers(PDO $pdo): array
{
    $users = [];

    $stmt = $pdo->query('SELECT id, full_name, username, email, role, status, created_at FROM admins ORDER BY created_at DESC');
    foreach ($stmt->fetchAll() as $row) {
        if (isBpsoPersonnelRole($row['role'] ?? '')) {
            continue;
        }
        $users[] = mapAdminUser($row);
    }

    try {
        $stmt = $pdo->query('SELECT id, bpso_personnel_id, personnel_name, email, status, created_at FROM patrols ORDER BY created_at DESC');
        foreach ($stmt->fetchAll() as $row) {
            $users[] = mapBpsoUser($row);
        }
    } catch (PDOException $e) {
        // Patrol table may not exist yet on older installs.
    }

    return $users;
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
        return $row ? mapBpsoUser($row) : null;
    }

    $stmt = $pdo->prepare('SELECT id, full_name, username, email, role, status, created_at FROM admins WHERE id = :id');
    $stmt->execute([':id' => $parsed['id']]);
    $row = $stmt->fetch();
    return $row ? mapAdminUser($row) : null;
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
    $data = json_decode(file_get_contents('php://input'), true);

    $fullName = trim($data['full_name'] ?? '');
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $role = normalizeUserRole(trim($data['role'] ?? 'Admin'));

    if ($role !== 'Admin') {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Only Admin accounts can be created here. Register BPSO personnel under Patrol List.']);
        exit;
    }

    if ($fullName === '' || $username === '' || $email === '' || $password === '') {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        exit;
    }

    if (!validatePassword($password)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Password must contain at least one capital letter and one number or special character']);
        exit;
    }

    try {
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

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('INSERT INTO admins (full_name, username, email, password_hash, role, status, created_at) VALUES (:full_name, :username, :email, :password_hash, :role, "Active", NOW())');
        $stmt->execute([
            ':full_name' => $fullName,
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':role' => 'Admin',
        ]);

        $user = fetchManagedUser($pdo, 'admin-' . $pdo->lastInsertId());

        ob_clean();
        echo json_encode(['success' => true, 'user' => $user]);
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
                $allowed = ['Available', 'Assigned', 'Off Duty'];
                if (!in_array($status, $allowed, true)) {
                    ob_clean();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid status']);
                    exit;
                }

                $stmt = $pdo->prepare('UPDATE patrols SET status = :status WHERE id = :id');
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

                $sql = 'UPDATE admins SET ' . implode(', ', $updateFields) . ' WHERE id = :id';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) $parsed['id']) {
                    $_SESSION['username'] = $username;
                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['user_role'] = 'Admin';
                }
            }

            $user = fetchManagedUser($pdo, ($parsed['type'] === 'bpso' ? 'bpso-' : 'admin-') . $parsed['id']);

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

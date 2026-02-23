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

/**
 * Ensure admins table has required columns for user management
 */
function ensureUsersTable(PDO $pdo): void
{
    // Check if role column exists
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM admins') as $row) {
        $columns[$row['Field']] = true;
    }
    
    if (!isset($columns['role'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN role VARCHAR(50) DEFAULT "User"');
    }
    
    if (!isset($columns['status'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN status VARCHAR(20) DEFAULT "Active"');
    }
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
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    // Get all users or a single user by ID
    $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    try {
        if ($userId > 0) {
            // Get single user
            $stmt = $pdo->prepare('SELECT id, full_name, username, email, role, status, created_at FROM admins WHERE id = :id');
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                ob_clean();
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'User not found']);
                exit;
            }
            
            if ($user['created_at']) {
                $user['created_at'] = date('Y-m-d H:i:s', strtotime($user['created_at']));
            }
            
            ob_clean();
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            // Get all users
            $stmt = $pdo->query('SELECT id, full_name, username, email, role, status, created_at FROM admins ORDER BY created_at DESC');
            $users = $stmt->fetchAll();
            
            // Format dates
            foreach ($users as &$user) {
                if ($user['created_at']) {
                    $user['created_at'] = date('Y-m-d H:i:s', strtotime($user['created_at']));
                }
            }
            
            ob_clean();
            echo json_encode(['success' => true, 'users' => $users]);
        }
    } catch (PDOException $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch users: ' . $e->getMessage()]);
    }
    
} elseif ($method === 'POST') {
    // Create new user
    $data = json_decode(file_get_contents('php://input'), true);
    
    $fullName = trim($data['full_name'] ?? '');
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $role = trim($data['role'] ?? 'User');
    
    // Validation
    if (empty($fullName) || empty($username) || empty($email) || empty($password)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        exit;
    }
    
    // Password validation: at least one capital letter, one number or special character
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9!@#$%^&*(),.?":{}|<>]/', $password)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Password must contain at least one capital letter and one number or special character']);
        exit;
    }
    
    // Role validation
    if (!in_array($role, ['Admin', 'User'])) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid role']);
        exit;
    }
    
    try {
        // Check if username already exists
        $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = :username');
        $stmt->execute([':username' => $username]);
        if ($stmt->fetch()) {
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Username already exists']);
            exit;
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = :email');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Email already exists']);
            exit;
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $pdo->prepare('INSERT INTO admins (full_name, username, email, password_hash, role, status, created_at) VALUES (:full_name, :username, :email, :password_hash, :role, "Active", NOW())');
        $stmt->execute([
            ':full_name' => $fullName,
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':role' => $role
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Fetch the created user
        $stmt = $pdo->prepare('SELECT id, full_name, username, email, role, status, created_at FROM admins WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        
        if ($user && $user['created_at']) {
            $user['created_at'] = date('Y-m-d H:i:s', strtotime($user['created_at']));
        }
        
        ob_clean();
        echo json_encode(['success' => true, 'user' => $user]);
    } catch (PDOException $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create user: ' . $e->getMessage()]);
    }
    
} elseif ($method === 'PUT') {
    // Update user (status or full update)
    $data = json_decode(file_get_contents('php://input'), true);
    
    $userId = (int)($data['id'] ?? 0);
    
    if ($userId <= 0) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
        exit;
    }
    
    try {
        // Check if this is a status-only update
        if (isset($data['status']) && !isset($data['full_name']) && !isset($data['username']) && !isset($data['email']) && !isset($data['password']) && !isset($data['role'])) {
            // Status-only update
            $status = trim($data['status'] ?? '');
            
            if (!in_array($status, ['Active', 'Inactive'])) {
                ob_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid status']);
                exit;
            }
            
            // Check if this is the last active admin
            if ($status === 'Inactive') {
                $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM admins WHERE role = "Admin" AND status = "Active" AND id != :id');
                $stmt->execute([':id' => $userId]);
                $result = $stmt->fetch();
                
                if ($result && (int)$result['count'] === 0) {
                    ob_clean();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Cannot deactivate the last active admin']);
                    exit;
                }
            }
            
            // Update status
            $stmt = $pdo->prepare('UPDATE admins SET status = :status WHERE id = :id');
            $stmt->execute([
                ':status' => $status,
                ':id' => $userId
            ]);
            
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'User status updated']);
        } else {
            // Full user update
            $fullName = trim($data['full_name'] ?? '');
            $username = trim($data['username'] ?? '');
            $email = trim($data['email'] ?? '');
            $password = $data['password'] ?? '';
            $role = trim($data['role'] ?? '');
            
            // Validation
            if (empty($fullName) || empty($username) || empty($email)) {
                ob_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Full name, username, and email are required']);
                exit;
            }
            
            // Role validation
            if (!in_array($role, ['Admin', 'User'])) {
                ob_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid role']);
                exit;
            }
            
            // Check if username already exists (excluding current user)
            $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = :username AND id != :id');
            $stmt->execute([':username' => $username, ':id' => $userId]);
            if ($stmt->fetch()) {
                ob_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Username already exists']);
                exit;
            }
            
            // Check if email already exists (excluding current user)
            $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = :email AND id != :id');
            $stmt->execute([':email' => $email, ':id' => $userId]);
            if ($stmt->fetch()) {
                ob_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Email already exists']);
                exit;
            }
            
            // Build update query
            $updateFields = ['full_name = :full_name', 'username = :username', 'email = :email', 'role = :role'];
            $params = [
                ':full_name' => $fullName,
                ':username' => $username,
                ':email' => $email,
                ':role' => $role,
                ':id' => $userId
            ];
            
            // Update password only if provided
            if (!empty($password)) {
                // Password validation: at least one capital letter, one number or special character
                if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9!@#$%^&*(),.?":{}|<>]/', $password)) {
                    ob_clean();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Password must contain at least one capital letter and one number or special character']);
                    exit;
                }
                $updateFields[] = 'password_hash = :password_hash';
                $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
            
            // Check if this is the last active admin and trying to change role or deactivate
            $stmt = $pdo->prepare('SELECT role, status FROM admins WHERE id = :id');
            $stmt->execute([':id' => $userId]);
            $currentUser = $stmt->fetch();
            
            if ($currentUser && $currentUser['role'] === 'Admin' && $currentUser['status'] === 'Active') {
                // If changing role from Admin to User, or if status would become Inactive
                if ($role === 'User' || (isset($data['status']) && $data['status'] === 'Inactive')) {
                    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM admins WHERE role = "Admin" AND status = "Active" AND id != :id');
                    $stmt->execute([':id' => $userId]);
                    $result = $stmt->fetch();
                    
                    if ($result && (int)$result['count'] === 0) {
                        ob_clean();
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Cannot change role or deactivate the last active admin']);
                        exit;
                    }
                }
            }
            
            // Execute update
            $sql = 'UPDATE admins SET ' . implode(', ', $updateFields) . ' WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Fetch the updated user
            $stmt = $pdo->prepare('SELECT id, full_name, username, email, role, status, created_at FROM admins WHERE id = :id');
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch();
            
            if ($user && $user['created_at']) {
                $user['created_at'] = date('Y-m-d H:i:s', strtotime($user['created_at']));
            }
            
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



<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

/**
 * Ensure the admins table exists
 */
function ensureAdminsTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        full_name VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM admins') as $row) {
        $columns[$row['Field']] = true;
    }
    if (!isset($columns['email'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN email VARCHAR(255) DEFAULT NULL');
    }
    if (!isset($columns['full_name'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN full_name VARCHAR(255) DEFAULT NULL');
    }
    if (!isset($columns['created_at'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    }
}

try {
    ensureAdminsTable($pdo);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'list') {
    try {
        $stmt = $pdo->query('SELECT id, username, email, full_name, created_at FROM admins ORDER BY created_at DESC');
        $admins = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $admins]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch admins: ' . $e->getMessage()]);
    }
    
} elseif ($method === 'GET' && $action === 'get') {
    $id = $_GET['id'] ?? 0;
    try {
        $stmt = $pdo->prepare('SELECT id, username, email, full_name, created_at FROM admins WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $admin = $stmt->fetch();
        if ($admin) {
            echo json_encode(['success' => true, 'data' => $admin]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Admin not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch admin: ' . $e->getMessage()]);
    }
    
} elseif ($method === 'POST' && $action === 'create') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $email = trim($data['email'] ?? '');
    $full_name = trim($data['full_name'] ?? '');
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Username and password are required']);
        exit;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters long']);
        exit;
    }
    
    try {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash, email, full_name) VALUES (:u, :p, :e, :f)');
        $stmt->execute([
            ':u' => $username,
            ':p' => $passwordHash,
            ':e' => $email ?: null,
            ':f' => $full_name ?: null
        ]);
        $adminId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'message' => 'Admin created successfully', 'id' => $adminId]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(['success' => false, 'error' => 'Username already exists']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create admin: ' . $e->getMessage()]);
        }
    }
    
} elseif ($method === 'PUT' && $action === 'update') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $full_name = trim($data['full_name'] ?? '');
    $password = $data['password'] ?? '';
    
    if (empty($username)) {
        echo json_encode(['success' => false, 'error' => 'Username is required']);
        exit;
    }
    
    try {
        if (!empty($password)) {
            if (strlen($password) < 6) {
                echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters long']);
                exit;
            }
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE admins SET username = :u, password_hash = :p, email = :e, full_name = :f WHERE id = :id');
            $stmt->execute([
                ':id' => $id,
                ':u' => $username,
                ':p' => $passwordHash,
                ':e' => $email ?: null,
                ':f' => $full_name ?: null
            ]);
        } else {
            $stmt = $pdo->prepare('UPDATE admins SET username = :u, email = :e, full_name = :f WHERE id = :id');
            $stmt->execute([
                ':id' => $id,
                ':u' => $username,
                ':e' => $email ?: null,
                ':f' => $full_name ?: null
            ]);
        }
        echo json_encode(['success' => true, 'message' => 'Admin updated successfully']);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(['success' => false, 'error' => 'Username already exists']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update admin: ' . $e->getMessage()]);
        }
    }
    
} elseif ($method === 'DELETE' && $action === 'delete') {
    $id = $_GET['id'] ?? 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'error' => 'Admin ID is required']);
        exit;
    }
    
    // Prevent deleting yourself
    if (isset($_SESSION['admin_id']) && $id == $_SESSION['admin_id']) {
        echo json_encode(['success' => false, 'error' => 'You cannot delete your own account']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare('DELETE FROM admins WHERE id = :id');
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Admin deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Admin not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to delete admin: ' . $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}




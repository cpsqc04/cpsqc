<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';

/**
 * Ensure the members table (and required columns) exist in the database.
 */
function ensureMembersTable(PDO $pdo): void
{
    // Get existing columns
    $columns = [];
    $tableExists = false;
    
    try {
        foreach ($pdo->query('SHOW COLUMNS FROM members') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        // Table doesn't exist yet, will create it
        $tableExists = false;
    }

    // Create table if it doesn't exist
    if (!$tableExists) {
        $pdo->exec("CREATE TABLE members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            age INT NOT NULL,
            address TEXT NOT NULL,
            gender VARCHAR(20) NOT NULL,
            photo_data LONGTEXT NULL,
            id_data LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return; // Table created with all columns, no need to check further
    }

    // Table exists - check and add missing columns
    // For NOT NULL columns, add with temporary defaults to avoid errors with existing rows
    if (!isset($columns['name'])) {
        $pdo->exec('ALTER TABLE members ADD COLUMN name VARCHAR(255) NOT NULL DEFAULT "" AFTER id');
    }
    if (!isset($columns['age'])) {
        $pdo->exec('ALTER TABLE members ADD COLUMN age INT NOT NULL DEFAULT 0 AFTER name');
    }
    if (!isset($columns['address'])) {
        $pdo->exec('ALTER TABLE members ADD COLUMN address TEXT NOT NULL DEFAULT "" AFTER age');
    }
    if (!isset($columns['gender'])) {
        $pdo->exec('ALTER TABLE members ADD COLUMN gender VARCHAR(20) NOT NULL DEFAULT "" AFTER address');
    }
    if (!isset($columns['photo_data'])) {
        $pdo->exec('ALTER TABLE members ADD COLUMN photo_data LONGTEXT NULL AFTER gender');
    }
    if (!isset($columns['id_data'])) {
        $pdo->exec('ALTER TABLE members ADD COLUMN id_data LONGTEXT NULL AFTER photo_data');
    }
    if (!isset($columns['created_at'])) {
        $pdo->exec('ALTER TABLE members ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    }
}

try {
    ensureMembersTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare members table: ' . $e->getMessage()]);
    exit;
}

// Basic auth check – only allow logged-in admins to use this API
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Return all members
    try {
        $stmt = $pdo->query('SELECT id, name, age, address, gender, photo_data, id_data FROM members ORDER BY id DESC');
        $members = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $members,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load members: ' . $e->getMessage()]);
    }
    exit;
}

// For create/update/delete we expect JSON
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

if ($method === 'POST') {
    if ($action === 'create') {
        $name = trim($input['name'] ?? '');
        $age = (int)($input['age'] ?? 0);
        $address = trim($input['address'] ?? '');
        $gender = trim($input['gender'] ?? '');
        $photo = $input['photo'] ?? null;     // base64/data URL or null
        $idData = $input['validId'] ?? null;  // base64/data URL or null

        if ($name === '' || $age <= 0 || $address === '' || $gender === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO members (name, age, address, gender, photo_data, id_data) VALUES (:name, :age, :address, :gender, :photo, :id_data)');
            $stmt->execute([
                ':name'   => $name,
                ':age'    => $age,
                ':address'=> $address,
                ':gender' => $gender,
                ':photo'  => $photo,
                ':id_data'=> $idData,
            ]);

            $id = (int)$pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'data' => [
                    'id'       => $id,
                    'name'     => $name,
                    'age'      => $age,
                    'address'  => $address,
                    'gender'   => $gender,
                    'photo_data' => $photo,
                    'id_data'    => $idData,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save member: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $age = (int)($input['age'] ?? 0);
        $address = trim($input['address'] ?? '');
        $gender = trim($input['gender'] ?? '');
        $photo = $input['photo'] ?? null;
        $idData = $input['validId'] ?? null;

        if ($id <= 0 || $name === '' || $age <= 0 || $address === '' || $gender === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        try {
            // Fetch current record so we can preserve existing photo / ID if not updated
            $stmt = $pdo->prepare('SELECT photo_data, id_data FROM members WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $current = $stmt->fetch();
            if (!$current) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Member not found.']);
                exit;
            }

            $photoToSave = $photo !== null ? $photo : $current['photo_data'];
            $idToSave = $idData !== null ? $idData : $current['id_data'];

            $stmt = $pdo->prepare('UPDATE members SET name = :name, age = :age, address = :address, gender = :gender, photo_data = :photo, id_data = :id_data WHERE id = :id');
            $stmt->execute([
                ':name'   => $name,
                ':age'    => $age,
                ':address'=> $address,
                ':gender' => $gender,
                ':photo'  => $photoToSave,
                ':id_data'=> $idToSave,
                ':id'     => $id,
            ]);

            echo json_encode([
                'success' => true,
                'data' => [
                    'id'       => $id,
                    'name'     => $name,
                    'age'      => $age,
                    'address'  => $address,
                    'gender'   => $gender,
                    'photo_data' => $photoToSave,
                    'id_data'    => $idToSave,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update member: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid member ID.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM members WHERE id = :id');
            $stmt->execute([':id' => $id]);

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete member: ' . $e->getMessage()]);
        }
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);



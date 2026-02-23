<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';

/**
 * Ensure the patrols table (and required columns) exist in the database.
 */
function ensurePatrolsTable(PDO $pdo): void
{
    // Get existing columns
    $columns = [];
    $tableExists = false;
    
    try {
        foreach ($pdo->query('SHOW COLUMNS FROM patrols') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        // Table doesn't exist yet, will create it
        $tableExists = false;
    }

    // Create table if it doesn't exist
    if (!$tableExists) {
        $pdo->exec("CREATE TABLE patrols (
            id INT AUTO_INCREMENT PRIMARY KEY,
            badge_number VARCHAR(50) NOT NULL UNIQUE,
            officer_name VARCHAR(255) NOT NULL,
            contact_number VARCHAR(50) NOT NULL,
            schedule VARCHAR(255) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return; // Table created with all columns, no need to check further
    }

    // Table exists - check and add missing columns
    if (!isset($columns['badge_number'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN badge_number VARCHAR(50) NOT NULL UNIQUE DEFAULT "" AFTER id');
    }
    if (!isset($columns['officer_name'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN officer_name VARCHAR(255) NOT NULL DEFAULT "" AFTER badge_number');
    }
    if (!isset($columns['contact_number'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN contact_number VARCHAR(50) NOT NULL DEFAULT "" AFTER officer_name');
    }
    if (!isset($columns['schedule'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN schedule VARCHAR(255) NOT NULL DEFAULT "" AFTER contact_number');
    }
    if (!isset($columns['status'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT "Available" AFTER schedule');
    }
    if (!isset($columns['created_at'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    }
    
    // Handle existing badge_number UNIQUE constraint if it causes issues
    if (isset($columns['badge_number'])) {
        try {
            // Check if there are duplicate empty badge numbers and fix them
            $pdo->exec("UPDATE patrols SET badge_number = CONCAT('PO-', id) WHERE badge_number = '' OR badge_number IS NULL");
        } catch (PDOException $e) {
            // Ignore if update fails
        }
    }
}

try {
    ensurePatrolsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare patrols table: ' . $e->getMessage()]);
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
    // Return all patrols
    try {
        $stmt = $pdo->query('SELECT id, badge_number, officer_name, contact_number, schedule, status, created_at FROM patrols ORDER BY id DESC');
        $patrols = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $patrols,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load patrols: ' . $e->getMessage()]);
    }
    exit;
}

// For create/update/delete we expect JSON
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

if ($method === 'POST') {
    if ($action === 'create') {
        $badgeNumber = trim($input['badge_number'] ?? '');
        $officerName = trim($input['officer_name'] ?? '');
        $contactNumber = trim($input['contact_number'] ?? '');
        $schedule = trim($input['schedule'] ?? '');
        $status = trim($input['status'] ?? 'Available');

        if ($badgeNumber === '' || $officerName === '' || $contactNumber === '' || $schedule === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO patrols (badge_number, officer_name, contact_number, schedule, status) VALUES (:badge_number, :officer_name, :contact_number, :schedule, :status)');
            $stmt->execute([
                ':badge_number' => $badgeNumber,
                ':officer_name' => $officerName,
                ':contact_number' => $contactNumber,
                ':schedule' => $schedule,
                ':status' => $status,
            ]);

            $id = (int)$pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $id,
                    'badge_number' => $badgeNumber,
                    'officer_name' => $officerName,
                    'contact_number' => $contactNumber,
                    'schedule' => $schedule,
                    'status' => $status,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo json_encode(['success' => false, 'message' => 'Badge number already exists. Please use a unique badge number.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save patrol officer: ' . $e->getMessage()]);
            }
        }
        exit;
    }

    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $badgeNumber = trim($input['badge_number'] ?? '');
        $officerName = trim($input['officer_name'] ?? '');
        $contactNumber = trim($input['contact_number'] ?? '');
        $schedule = trim($input['schedule'] ?? '');
        $status = trim($input['status'] ?? '');

        if ($id <= 0 || $badgeNumber === '' || $officerName === '' || $contactNumber === '' || $schedule === '' || $status === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        try {
            // Check if badge number is being changed and if new one already exists
            $checkStmt = $pdo->prepare('SELECT badge_number FROM patrols WHERE id = :id');
            $checkStmt->execute([':id' => $id]);
            $current = $checkStmt->fetch();
            
            if ($current && $current['badge_number'] !== $badgeNumber) {
                $duplicateStmt = $pdo->prepare('SELECT COUNT(*) FROM patrols WHERE badge_number = :badge_number AND id != :id');
                $duplicateStmt->execute([':badge_number' => $badgeNumber, ':id' => $id]);
                if ($duplicateStmt->fetchColumn() > 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Badge number already exists. Please use a unique badge number.']);
                    exit;
                }
            }

            $stmt = $pdo->prepare('UPDATE patrols SET badge_number = :badge_number, officer_name = :officer_name, contact_number = :contact_number, schedule = :schedule, status = :status WHERE id = :id');
            $stmt->execute([
                ':badge_number' => $badgeNumber,
                ':officer_name' => $officerName,
                ':contact_number' => $contactNumber,
                ':schedule' => $schedule,
                ':status' => $status,
                ':id' => $id,
            ]);

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $id,
                    'badge_number' => $badgeNumber,
                    'officer_name' => $officerName,
                    'contact_number' => $contactNumber,
                    'schedule' => $schedule,
                    'status' => $status,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update patrol officer: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid patrol officer ID.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM patrols WHERE id = :id');
            $stmt->execute([':id' => $id]);

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete patrol officer: ' . $e->getMessage()]);
        }
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);


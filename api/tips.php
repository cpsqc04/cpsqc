<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';

/**
 * Ensure the tips table (and required columns) exist in the database.
 */
function ensureTipsTable(PDO $pdo): void
{
    // Get existing columns
    $columns = [];
    $tableExists = false;
    
    try {
        foreach ($pdo->query('SHOW COLUMNS FROM tips') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        // Table doesn't exist yet, will create it
        $tableExists = false;
    }

    // Create table if it doesn't exist
    if (!$tableExists) {
        $pdo->exec("CREATE TABLE tips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tip_id VARCHAR(255) UNIQUE NOT NULL,
            location TEXT NOT NULL,
            description TEXT NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Under Review',
            outcome VARCHAR(100) DEFAULT 'No Outcome Yet',
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return; // Table created with all columns, no need to check further
    }

    // Table exists - check and add missing columns
    if (!isset($columns['tip_id'])) {
        $pdo->exec('ALTER TABLE tips ADD COLUMN tip_id VARCHAR(255) UNIQUE NOT NULL DEFAULT "" AFTER id');
    }
    if (!isset($columns['location'])) {
        $pdo->exec('ALTER TABLE tips ADD COLUMN location TEXT NOT NULL DEFAULT "" AFTER tip_id');
    }
    if (!isset($columns['description'])) {
        $pdo->exec('ALTER TABLE tips ADD COLUMN description TEXT NOT NULL DEFAULT "" AFTER location');
    }
    if (!isset($columns['status'])) {
        $pdo->exec('ALTER TABLE tips ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT "Under Review" AFTER description');
    }
    if (!isset($columns['outcome'])) {
        $pdo->exec('ALTER TABLE tips ADD COLUMN outcome VARCHAR(100) DEFAULT "No Outcome Yet" AFTER status');
    }
    if (!isset($columns['submitted_at'])) {
        $pdo->exec('ALTER TABLE tips ADD COLUMN submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER outcome');
    }
    if (!isset($columns['created_at'])) {
        $pdo->exec('ALTER TABLE tips ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    }
}

try {
    ensureTipsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare tips table: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

// For GET, UPDATE, DELETE - require admin authentication
// For 'create' action, no authentication is required (public submission)
if ($action !== 'create' && (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($method === 'GET') {
    // Return all tips
    try {
        $stmt = $pdo->query('SELECT id, tip_id, location, description, status, outcome, submitted_at FROM tips ORDER BY id DESC');
        $tips = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $tips,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load tips: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    if ($action === 'create') {
        $location = trim($input['location'] ?? '');
        $description = trim($input['description'] ?? '');

        if ($location === '' || $description === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Location and description are required.']);
            exit;
        }

        // Generate unique tip ID
        $year = date('Y');
        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(tip_id, LOCATE('-', tip_id, 5) + 1) AS UNSIGNED)) AS max_num FROM tips WHERE tip_id LIKE 'TIP-{$year}-%'");
        $maxNum = $stmt->fetchColumn();
        $nextNum = ($maxNum === false || $maxNum === null) ? 1 : $maxNum + 1;
        $tipId = "TIP-{$year}-" . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        try {
            $stmt = $pdo->prepare('INSERT INTO tips (tip_id, location, description, status, outcome) VALUES (:tip_id, :location, :description, :status, :outcome)');
            $stmt->execute([
                ':tip_id' => $tipId,
                ':location' => $location,
                ':description' => $description,
                ':status' => 'Under Review',
                ':outcome' => 'No Outcome Yet',
            ]);

            $id = (int)$pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Tip submitted successfully!',
                'data' => [
                    'id' => $id,
                    'tip_id' => $tipId,
                    'location' => $location,
                    'description' => $description,
                    'status' => 'Under Review',
                    'outcome' => 'No Outcome Yet',
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to submit tip: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $status = trim($input['status'] ?? '');
        $outcome = trim($input['outcome'] ?? 'No Outcome Yet');

        if ($id <= 0 || $status === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('UPDATE tips SET status = :status, outcome = :outcome WHERE id = :id');
            $stmt->execute([
                ':status' => $status,
                ':outcome' => $outcome,
                ':id' => $id,
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Tip updated successfully!',
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update tip: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid tip ID.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM tips WHERE id = :id');
            $stmt->execute([':id' => $id]);

            echo json_encode(['success' => true, 'message' => 'Tip deleted successfully!']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete tip: ' . $e->getMessage()]);
        }
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);


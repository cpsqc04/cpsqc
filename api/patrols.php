<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/patrol_logs_schema.php';
require_once __DIR__ . '/../includes/contact_validation.php';

/**
 * Generate the next BPSO personnel ID in PER-XX format.
 */
function generateNextBpsoPersonnelId(PDO $pdo): string
{
    $stmt = $pdo->query('SELECT bpso_personnel_id FROM patrols');
    $max = 0;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = strtoupper(trim((string)($row['bpso_personnel_id'] ?? '')));
        if (preg_match('/^PER-(\d+)$/', $id, $matches)) {
            $max = max($max, (int)$matches[1]);
        }
    }

    return sprintf('PER-%02d', $max + 1);
}

/**
 * Ensure the patrols table (and required columns) exist in the database.
 */
function ensurePatrolsTable(PDO $pdo): void
{
    $columns = [];
    $tableExists = false;

    try {
        foreach ($pdo->query('SHOW COLUMNS FROM patrols') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        $pdo->exec("CREATE TABLE patrols (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bpso_personnel_id VARCHAR(50) NOT NULL UNIQUE,
            personnel_name VARCHAR(255) NOT NULL,
            contact_number VARCHAR(50) NOT NULL,
            schedule VARCHAR(255) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Available',
            email VARCHAR(255) NULL,
            password_hash VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }

    if (isset($columns['badge_number']) && !isset($columns['bpso_personnel_id'])) {
        $pdo->exec('ALTER TABLE patrols CHANGE badge_number bpso_personnel_id VARCHAR(50) NOT NULL');
        unset($columns['badge_number']);
        $columns['bpso_personnel_id'] = true;
    }

    if (isset($columns['officer_name']) && !isset($columns['personnel_name'])) {
        $pdo->exec('ALTER TABLE patrols CHANGE officer_name personnel_name VARCHAR(255) NOT NULL');
        unset($columns['officer_name']);
        $columns['personnel_name'] = true;
    }

    if (!isset($columns['bpso_personnel_id'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN bpso_personnel_id VARCHAR(50) NOT NULL UNIQUE DEFAULT "" AFTER id');
    }
    if (!isset($columns['personnel_name'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN personnel_name VARCHAR(255) NOT NULL DEFAULT "" AFTER bpso_personnel_id');
    }
    if (!isset($columns['contact_number'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN contact_number VARCHAR(50) NOT NULL DEFAULT "" AFTER personnel_name');
    }
    if (!isset($columns['schedule'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN schedule VARCHAR(255) NOT NULL DEFAULT "" AFTER contact_number');
    }
    if (!isset($columns['status'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT "Available" AFTER schedule');
    }
    if (!isset($columns['email'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN email VARCHAR(255) NULL UNIQUE AFTER status');
    }
    if (!isset($columns['password_hash'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN password_hash VARCHAR(255) NULL AFTER email');
    }
    if (!isset($columns['created_at'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    }

    try {
        $pdo->exec("UPDATE patrols SET bpso_personnel_id = CONCAT('BPSO-', id) WHERE bpso_personnel_id = '' OR bpso_personnel_id IS NULL");
    } catch (PDOException $e) {
        // Ignore if update fails
    }
}

try {
    ensurePatrolsTable($pdo);
    ensurePatrolLogsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare patrol tables: ' . $e->getMessage()]);
    exit;
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        if (isset($_GET['next_id'])) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'bpso_personnel_id' => generateNextBpsoPersonnelId($pdo),
                ],
            ]);
            exit;
        }

        $stmt = $pdo->query('SELECT id, bpso_personnel_id, personnel_name, contact_number, email, schedule, status, created_at FROM patrols ORDER BY id DESC');
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

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

if ($method === 'POST') {
    if ($action === 'create') {
        $personnelName = trim($input['personnel_name'] ?? '');
        $contactNumber = trim($input['contact_number'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $schedule = trim($input['schedule'] ?? '');
        $status = trim($input['status'] ?? 'Available');

        if ($personnelName === '' || $contactNumber === '' || $email === '' || $password === '' || $schedule === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        $contactNumber = normalizeContactDigits($contactNumber);
        $contactError = validateContactNumber($contactNumber, 'Contact number');
        if ($contactError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $contactError]);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
            exit;
        }

        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9!@#$%^&*(),.?":{}|<>]/', $password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password must contain at least one capital letter and one number or special character.']);
            exit;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $emailCheck = $pdo->prepare('SELECT COUNT(*) FROM patrols WHERE email = :email');
            $emailCheck->execute([':email' => $email]);
            if ($emailCheck->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Email address already exists.']);
                exit;
            }

            $personnelId = generateNextBpsoPersonnelId($pdo);
            $stmt = $pdo->prepare('INSERT INTO patrols (bpso_personnel_id, personnel_name, contact_number, email, password_hash, schedule, status) VALUES (:bpso_personnel_id, :personnel_name, :contact_number, :email, :password_hash, :schedule, :status)');

            $inserted = false;
            for ($attempt = 0; $attempt < 5; $attempt++) {
                try {
                    $stmt->execute([
                        ':bpso_personnel_id' => $personnelId,
                        ':personnel_name' => $personnelName,
                        ':contact_number' => $contactNumber,
                        ':email' => $email,
                        ':password_hash' => $passwordHash,
                        ':schedule' => $schedule,
                        ':status' => $status,
                    ]);
                    $inserted = true;
                    break;
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'email') === false) {
                        $personnelId = generateNextBpsoPersonnelId($pdo);
                        continue;
                    }
                    throw $e;
                }
            }

            if (!$inserted) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to generate a unique BPSO Personnel ID.']);
                exit;
            }

            $id = (int)$pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $id,
                    'bpso_personnel_id' => $personnelId,
                    'personnel_name' => $personnelName,
                    'contact_number' => $contactNumber,
                    'email' => $email,
                    'schedule' => $schedule,
                    'status' => $status,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'email') !== false) {
                echo json_encode(['success' => false, 'message' => 'Email address already exists.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save BPSO personnel: ' . $e->getMessage()]);
            }
        }
        exit;
    }

    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $personnelId = trim($input['bpso_personnel_id'] ?? '');
        $personnelName = trim($input['personnel_name'] ?? '');
        $contactNumber = trim($input['contact_number'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $schedule = trim($input['schedule'] ?? '');
        $status = trim($input['status'] ?? '');

        if ($id <= 0 || $personnelId === '' || $personnelName === '' || $contactNumber === '' || $email === '' || $schedule === '' || $status === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        $contactNumber = normalizeContactDigits($contactNumber);
        $contactError = validateContactNumber($contactNumber, 'Contact number');
        if ($contactError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $contactError]);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
            exit;
        }

        if ($password !== '') {
            if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9!@#$%^&*(),.?":{}|<>]/', $password)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Password must contain at least one capital letter and one number or special character.']);
                exit;
            }
        }

        try {
            $checkStmt = $pdo->prepare('SELECT bpso_personnel_id FROM patrols WHERE id = :id');
            $checkStmt->execute([':id' => $id]);
            $current = $checkStmt->fetch();

            if (!$current) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'BPSO personnel not found.']);
                exit;
            }

            if ($current['bpso_personnel_id'] !== $personnelId) {
                $duplicateStmt = $pdo->prepare('SELECT COUNT(*) FROM patrols WHERE bpso_personnel_id = :bpso_personnel_id AND id != :id');
                $duplicateStmt->execute([':bpso_personnel_id' => $personnelId, ':id' => $id]);
                if ($duplicateStmt->fetchColumn() > 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'BPSO Personnel ID already exists. Please use a unique BPSO Personnel ID.']);
                    exit;
                }
            }

            $emailCheck = $pdo->prepare('SELECT COUNT(*) FROM patrols WHERE email = :email AND id != :id');
            $emailCheck->execute([':email' => $email, ':id' => $id]);
            if ($emailCheck->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Email address already exists.']);
                exit;
            }

            if ($password !== '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE patrols SET bpso_personnel_id = :bpso_personnel_id, personnel_name = :personnel_name, contact_number = :contact_number, email = :email, password_hash = :password_hash, schedule = :schedule, status = :status WHERE id = :id');
                $stmt->execute([
                    ':bpso_personnel_id' => $personnelId,
                    ':personnel_name' => $personnelName,
                    ':contact_number' => $contactNumber,
                    ':email' => $email,
                    ':password_hash' => $passwordHash,
                    ':schedule' => $schedule,
                    ':status' => $status,
                    ':id' => $id,
                ]);
            } else {
                $stmt = $pdo->prepare('UPDATE patrols SET bpso_personnel_id = :bpso_personnel_id, personnel_name = :personnel_name, contact_number = :contact_number, email = :email, schedule = :schedule, status = :status WHERE id = :id');
                $stmt->execute([
                    ':bpso_personnel_id' => $personnelId,
                    ':personnel_name' => $personnelName,
                    ':contact_number' => $contactNumber,
                    ':email' => $email,
                    ':schedule' => $schedule,
                    ':status' => $status,
                    ':id' => $id,
                ]);
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $id,
                    'bpso_personnel_id' => $personnelId,
                    'personnel_name' => $personnelName,
                    'contact_number' => $contactNumber,
                    'email' => $email,
                    'schedule' => $schedule,
                    'status' => $status,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'email') !== false) {
                echo json_encode(['success' => false, 'message' => 'Email address already exists.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update BPSO personnel: ' . $e->getMessage()]);
            }
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid BPSO personnel record ID.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM patrols WHERE id = :id');
            $stmt->execute([':id' => $id]);

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete BPSO personnel: ' . $e->getMessage()]);
        }
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

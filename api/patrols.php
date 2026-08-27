<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/patrol_logs_schema.php';
require_once __DIR__ . '/notifications_schema.php';
require_once __DIR__ . '/../includes/contact_validation.php';
require_once __DIR__ . '/../includes/patrol_shifts.php';
require_once __DIR__ . '/../includes/patrol_availability.php';
require_once __DIR__ . '/../includes/bpso_credentials.php';
require_once __DIR__ . '/../includes/managed_user_display_ids.php';

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
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
            must_change_password TINYINT(1) NOT NULL DEFAULT 0,
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
    if (!isset($columns['duty_shift'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN duty_shift VARCHAR(50) NOT NULL DEFAULT "" AFTER schedule');
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
    if (!isset($columns['must_change_password'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash');
    }
    if (!isset($columns['created_at'])) {
        $pdo->exec('ALTER TABLE patrols ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    }

    try {
        $pdo->exec("UPDATE patrols SET bpso_personnel_id = CONCAT('BPSO-', id) WHERE bpso_personnel_id = '' OR bpso_personnel_id IS NULL");
        $pdo->exec("UPDATE patrols SET duty_shift = schedule WHERE (duty_shift = '' OR duty_shift IS NULL) AND schedule IN ('Day Shift', 'Night Shift')");
    } catch (PDOException $e) {
        // Ignore if update fails
    }

    syncBpsoPersonnelIdsToPatFormat($pdo);
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

        $stmt = $pdo->query('SELECT id, bpso_personnel_id, personnel_name, contact_number, email, schedule, duty_shift, status, created_at FROM patrols ORDER BY id DESC');
        $patrols = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Use stored status on list reads (mutations already call refreshPatrolAvailabilityStatus).
        // Optional ?resolve=1 re-computes live status (expensive: several queries per officer).
        $resolveLive = isset($_GET['resolve']) && (string) $_GET['resolve'] === '1';

        foreach ($patrols as $index => $patrol) {
            $status = normalizePatrolAvailabilityStatus((string) ($patrol['status'] ?? 'Available'));
            if ($resolveLive) {
                $resolved = resolvePatrolAvailabilityStatus(
                    $pdo,
                    (int) ($patrol['id'] ?? 0),
                    $status
                );
                if ($resolved !== $status) {
                    setPatrolAvailabilityStatus($pdo, (int) $patrol['id'], $resolved);
                }
                $status = $resolved;
            }
            $patrols[$index]['status'] = $status;
            $patrols[$index]['status_class'] = patrolAvailabilityStatusCssClass($status);
        }

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
        $schedule = trim($input['schedule'] ?? '');
        $dutyShift = trim($input['duty_shift'] ?? $schedule);
        $status = normalizePatrolAvailabilityStatus(trim($input['status'] ?? 'Available'));
        if (!isValidPatrolAvailabilityStatus($status)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid personnel status.']);
            exit;
        }

        if ($personnelName === '' || $contactNumber === '' || $email === '' || !isValidPatrolShift($dutyShift)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields. Select a duty shift (Day Shift or Night Shift).']);
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

        $tempPassword = generateBpsoTempPassword();
        $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

        try {
            $emailCheck = $pdo->prepare('SELECT COUNT(*) FROM patrols WHERE email = :email');
            $emailCheck->execute([':email' => $email]);
            if ($emailCheck->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Email address already exists.']);
                exit;
            }

            $personnelId = generateNextBpsoPersonnelId($pdo);
            $stmt = $pdo->prepare('INSERT INTO patrols (bpso_personnel_id, personnel_name, contact_number, email, password_hash, must_change_password, schedule, duty_shift, status) VALUES (:bpso_personnel_id, :personnel_name, :contact_number, :email, :password_hash, 1, :schedule, :duty_shift, :status)');

            $inserted = false;
            for ($attempt = 0; $attempt < 5; $attempt++) {
                try {
                    $stmt->execute([
                        ':bpso_personnel_id' => $personnelId,
                        ':personnel_name' => $personnelName,
                        ':contact_number' => $contactNumber,
                        ':email' => $email,
                        ':password_hash' => $passwordHash,
                        ':schedule' => $dutyShift,
                        ':duty_shift' => $dutyShift,
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

            $id = (int) $pdo->lastInsertId();

            $mailResult = sendBpsoWelcomeCredentialsEmail(
                $email,
                $personnelName,
                $personnelId,
                $tempPassword
            );

            if (empty($mailResult['success'])) {
                // Roll back account if credentials email cannot be delivered
                $pdo->prepare('DELETE FROM patrols WHERE id = :id')->execute([':id' => $id]);
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Personnel was not saved because the credentials email failed to send. ' . ($mailResult['error'] ?? 'Please check mail settings and try again.'),
                ]);
                exit;
            }

            createPatrolNotification(
                $pdo,
                $id,
                'duty_schedule',
                'Duty Schedule Set',
                'Your duty schedule has been set to ' . $dutyShift . '.',
                'duty:' . $id . ':' . time()
            );

            echo json_encode([
                'success' => true,
                'message' => 'BPSO personnel added. Portal URL and temporary password were sent to ' . $email . '.',
                'data' => [
                    'id' => $id,
                    'bpso_personnel_id' => $personnelId,
                    'personnel_name' => $personnelName,
                    'contact_number' => $contactNumber,
                    'email' => $email,
                    'schedule' => $dutyShift,
                    'duty_shift' => $dutyShift,
                    'status' => $status,
                    'credentials_emailed' => true,
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
        // Profile/password edits belong in BPSO Account Settings — not admin Patrol List.
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'BPSO personnel details are managed in their Account Settings. Admins can only view or add personnel.',
        ]);
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

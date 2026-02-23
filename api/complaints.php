<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';

/**
 * Ensure the complaints table (and required columns) exist in the database.
 */
function ensureComplaintsTable(PDO $pdo): void
{
    // Get existing columns
    $columns = [];
    $tableExists = false;
    
    try {
        foreach ($pdo->query('SHOW COLUMNS FROM complaints') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        // Table doesn't exist yet, will create it
        $tableExists = false;
    }

    // Create table if it doesn't exist
    if (!$tableExists) {
        $pdo->exec("CREATE TABLE complaints (
            id INT AUTO_INCREMENT PRIMARY KEY,
            complaint_id VARCHAR(50) NOT NULL UNIQUE,
            complainant_name VARCHAR(255) NOT NULL,
            contact_number VARCHAR(50) NOT NULL,
            address TEXT NOT NULL,
            incident_date DATE NOT NULL,
            complaint_type VARCHAR(100) NOT NULL,
            location TEXT NOT NULL,
            description TEXT NOT NULL,
            priority VARCHAR(20) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Pending',
            assigned_to VARCHAR(255) DEFAULT 'Pending Assignment',
            notes TEXT DEFAULT NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return; // Table created with all columns, no need to check further
    }

    // Table exists - check and add missing columns
    if (!isset($columns['complaint_id'])) {
        $pdo->exec('ALTER TABLE complaints ADD COLUMN complaint_id VARCHAR(50) NOT NULL UNIQUE AFTER id');
    }
    if (!isset($columns['complainant_name'])) {
        $pdo->exec('ALTER TABLE complaints ADD COLUMN complainant_name VARCHAR(255) NOT NULL DEFAULT "" AFTER complaint_id');
    }
    if (!isset($columns['contact_number'])) {
        $pdo->exec('ALTER TABLE complaints ADD COLUMN contact_number VARCHAR(50) NOT NULL DEFAULT "" AFTER complainant_name');
    }
    if (!isset($columns['address'])) {
        $pdo->exec('ALTER TABLE complaints ADD COLUMN address TEXT NOT NULL DEFAULT "" AFTER contact_number');
    }
    if (!isset($columns['incident_date'])) {
        $pdo->exec('ALTER TABLE complaints ADD COLUMN incident_date DATE NOT NULL DEFAULT "1970-01-01" AFTER address');
    }
    if (!isset($columns['complaint_type'])) {
        $pdo->exec('ALTER TABLE complaints ADD COLUMN complaint_type VARCHAR(100) NOT NULL DEFAULT "" AFTER incident_date');
    }
    if (!isset($columns['location'])) {
        $pdo->exec('ALTER TABLE complaints ADD COLUMN location TEXT NOT NULL DEFAULT "" AFTER complaint_type');
    }
    if (!isset($columns['description'])) {
        $pdo->exec('ALTER TABLE complaints ADD COLUMN description TEXT NOT NULL DEFAULT "" AFTER location');
    }
    if (!isset($columns['priority'])) {
        $pdo->exec('ALTER TABLE complaints ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT "Low" AFTER description');
    }
    if (!isset($columns['status'])) {
        $pdo->exec('ALTER TABLE complaints ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT "Pending" AFTER priority');
    }
    if (!isset($columns['assigned_to'])) {
        $pdo->exec('ALTER TABLE complaints ADD COLUMN assigned_to VARCHAR(255) DEFAULT "Pending Assignment" AFTER status');
    }
    if (!isset($columns['notes'])) {
        $pdo->exec('ALTER TABLE complaints ADD COLUMN notes TEXT DEFAULT NULL AFTER assigned_to');
    }
    if (!isset($columns['submitted_at'])) {
        $pdo->exec('ALTER TABLE complaints ADD COLUMN submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER notes');
    }
    if (!isset($columns['created_at'])) {
        $pdo->exec('ALTER TABLE complaints ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    }
}

try {
    ensureComplaintsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare complaints table: ' . $e->getMessage()]);
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
    // Return all complaints
    try {
        $stmt = $pdo->query('SELECT id, complaint_id, complainant_name, contact_number, address, incident_date, complaint_type, location, description, priority, status, assigned_to, notes, submitted_at, created_at FROM complaints ORDER BY id DESC');
        $complaints = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $complaints,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load complaints: ' . $e->getMessage()]);
    }
    exit;
}

// For create/update/delete we expect JSON
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

if ($method === 'POST') {
    if ($action === 'create') {
        $complaintId = trim($input['complaint_id'] ?? '');
        $complainantName = trim($input['complainant_name'] ?? '');
        $contactNumber = trim($input['contact_number'] ?? '');
        $address = trim($input['address'] ?? '');
        $incidentDate = trim($input['incident_date'] ?? '');
        $complaintType = trim($input['complaint_type'] ?? '');
        $location = trim($input['location'] ?? '');
        $description = trim($input['description'] ?? '');
        $priority = trim($input['priority'] ?? 'Low');
        $status = trim($input['status'] ?? 'Pending');
        $assignedTo = trim($input['assigned_to'] ?? 'Pending Assignment');
        $notes = trim($input['notes'] ?? 'Complaint submitted and awaiting review.');

        if ($complaintId === '' || $complainantName === '' || $contactNumber === '' || $address === '' || $incidentDate === '' || $complaintType === '' || $location === '' || $description === '' || $priority === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO complaints (complaint_id, complainant_name, contact_number, address, incident_date, complaint_type, location, description, priority, status, assigned_to, notes, submitted_at) VALUES (:complaint_id, :complainant_name, :contact_number, :address, :incident_date, :complaint_type, :location, :description, :priority, :status, :assigned_to, :notes, NOW())');
            $stmt->execute([
                ':complaint_id' => $complaintId,
                ':complainant_name' => $complainantName,
                ':contact_number' => $contactNumber,
                ':address' => $address,
                ':incident_date' => $incidentDate,
                ':complaint_type' => $complaintType,
                ':location' => $location,
                ':description' => $description,
                ':priority' => $priority,
                ':status' => $status,
                ':assigned_to' => $assignedTo,
                ':notes' => $notes,
            ]);

            $id = (int)$pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $id,
                    'complaint_id' => $complaintId,
                    'complainant_name' => $complainantName,
                    'contact_number' => $contactNumber,
                    'address' => $address,
                    'incident_date' => $incidentDate,
                    'complaint_type' => $complaintType,
                    'location' => $location,
                    'description' => $description,
                    'priority' => $priority,
                    'status' => $status,
                    'assigned_to' => $assignedTo,
                    'notes' => $notes,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save complaint: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $complainantName = trim($input['complainant_name'] ?? '');
        $contactNumber = trim($input['contact_number'] ?? '');
        $address = trim($input['address'] ?? '');
        $incidentDate = trim($input['incident_date'] ?? '');
        $complaintType = trim($input['complaint_type'] ?? '');
        $location = trim($input['location'] ?? '');
        $description = trim($input['description'] ?? '');
        $priority = trim($input['priority'] ?? '');
        $status = trim($input['status'] ?? '');
        $assignedTo = trim($input['assigned_to'] ?? '');
        $notes = trim($input['notes'] ?? '');

        if ($id <= 0 || $complainantName === '' || $contactNumber === '' || $address === '' || $incidentDate === '' || $complaintType === '' || $location === '' || $description === '' || $priority === '' || $status === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('UPDATE complaints SET complainant_name = :complainant_name, contact_number = :contact_number, address = :address, incident_date = :incident_date, complaint_type = :complaint_type, location = :location, description = :description, priority = :priority, status = :status, assigned_to = :assigned_to, notes = :notes WHERE id = :id');
            $stmt->execute([
                ':complainant_name' => $complainantName,
                ':contact_number' => $contactNumber,
                ':address' => $address,
                ':incident_date' => $incidentDate,
                ':complaint_type' => $complaintType,
                ':location' => $location,
                ':description' => $description,
                ':priority' => $priority,
                ':status' => $status,
                ':assigned_to' => $assignedTo,
                ':notes' => $notes,
                ':id' => $id,
            ]);

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $id,
                    'complainant_name' => $complainantName,
                    'contact_number' => $contactNumber,
                    'address' => $address,
                    'incident_date' => $incidentDate,
                    'complaint_type' => $complaintType,
                    'location' => $location,
                    'description' => $description,
                    'priority' => $priority,
                    'status' => $status,
                    'assigned_to' => $assignedTo,
                    'notes' => $notes,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update complaint: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid complaint ID.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM complaints WHERE id = :id');
            $stmt->execute([':id' => $id]);

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete complaint: ' . $e->getMessage()]);
        }
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);


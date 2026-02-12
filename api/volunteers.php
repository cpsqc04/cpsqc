<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';

/**
 * Ensure the volunteers table (and required columns) exist in the database.
 */
function ensureVolunteersTable(PDO $pdo): void
{
    // Get existing columns
    $columns = [];
    $tableExists = false;
    
    try {
        foreach ($pdo->query('SHOW COLUMNS FROM volunteers') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        // Table doesn't exist yet, will create it
        $tableExists = false;
    }

    // Create table if it doesn't exist
    if (!$tableExists) {
        $pdo->exec("CREATE TABLE volunteers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            contact VARCHAR(50) NOT NULL,
            email VARCHAR(255) NOT NULL,
            address TEXT NOT NULL,
            category VARCHAR(100) NOT NULL,
            skills TEXT NOT NULL,
            availability VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Pending',
            notes TEXT DEFAULT NULL,
            photo_data LONGTEXT NULL,
            photo_id_data LONGTEXT NULL,
            certifications_data LONGTEXT NULL,
            certifications_description TEXT DEFAULT NULL,
            emergency_contact_name VARCHAR(255) NOT NULL,
            emergency_contact_number VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return; // Table created with all columns, no need to check further
    }

    // Table exists - check and add missing columns
    if (!isset($columns['name'])) {
        $pdo->exec('ALTER TABLE volunteers ADD COLUMN name VARCHAR(255) NOT NULL DEFAULT "" AFTER id');
    }
    if (!isset($columns['contact'])) {
        $pdo->exec('ALTER TABLE volunteers ADD COLUMN contact VARCHAR(50) NOT NULL DEFAULT "" AFTER name');
    }
    if (!isset($columns['email'])) {
        $pdo->exec('ALTER TABLE volunteers ADD COLUMN email VARCHAR(255) NOT NULL DEFAULT "" AFTER contact');
    }
    if (!isset($columns['address'])) {
        $pdo->exec('ALTER TABLE volunteers ADD COLUMN address TEXT NOT NULL DEFAULT "" AFTER email');
    }
    if (!isset($columns['category'])) {
        $pdo->exec('ALTER TABLE volunteers ADD COLUMN category VARCHAR(100) NOT NULL DEFAULT "" AFTER address');
    }
    if (!isset($columns['skills'])) {
        $pdo->exec('ALTER TABLE volunteers ADD COLUMN skills TEXT NOT NULL DEFAULT "" AFTER category');
    }
    if (!isset($columns['availability'])) {
        $pdo->exec('ALTER TABLE volunteers ADD COLUMN availability VARCHAR(50) NOT NULL DEFAULT "Flexible" AFTER skills');
    }
    if (!isset($columns['status'])) {
        $pdo->exec('ALTER TABLE volunteers ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT "Pending" AFTER availability');
    }
    if (!isset($columns['notes'])) {
        $pdo->exec('ALTER TABLE volunteers ADD COLUMN notes TEXT DEFAULT NULL AFTER status');
    }
    if (!isset($columns['photo_data'])) {
        $pdo->exec('ALTER TABLE volunteers ADD COLUMN photo_data LONGTEXT NULL AFTER notes');
    }
    if (!isset($columns['photo_id_data'])) {
        $pdo->exec('ALTER TABLE volunteers ADD COLUMN photo_id_data LONGTEXT NULL AFTER photo_data');
    }
    if (!isset($columns['certifications_data'])) {
        $pdo->exec('ALTER TABLE volunteers ADD COLUMN certifications_data LONGTEXT NULL AFTER photo_id_data');
    }
    if (!isset($columns['certifications_description'])) {
        $pdo->exec('ALTER TABLE volunteers ADD COLUMN certifications_description TEXT DEFAULT NULL AFTER certifications_data');
    }
    if (!isset($columns['emergency_contact_name'])) {
        $pdo->exec('ALTER TABLE volunteers ADD COLUMN emergency_contact_name VARCHAR(255) NOT NULL DEFAULT "" AFTER certifications_description');
    }
    if (!isset($columns['emergency_contact_number'])) {
        $pdo->exec('ALTER TABLE volunteers ADD COLUMN emergency_contact_number VARCHAR(50) NOT NULL DEFAULT "" AFTER emergency_contact_name');
    }
    if (!isset($columns['created_at'])) {
        $pdo->exec('ALTER TABLE volunteers ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    }
    
    // Handle existing volunteer_code column if it exists (from old schema)
    if (isset($columns['volunteer_code'])) {
        // Make the column nullable and remove UNIQUE constraint to avoid duplicate entry errors
        try {
            // First, try to drop the unique index/constraint
            $indexes = [];
            foreach ($pdo->query("SHOW INDEX FROM volunteers WHERE Key_name = 'volunteer_code'") as $row) {
                $indexes[] = $row['Key_name'];
            }
            if (!empty($indexes)) {
                $pdo->exec('ALTER TABLE volunteers DROP INDEX volunteer_code');
            }
        } catch (PDOException $e) {
            // Index might not exist, continue
        }
        
        // Make the column nullable
        try {
            $pdo->exec('ALTER TABLE volunteers MODIFY COLUMN volunteer_code VARCHAR(50) NULL DEFAULT NULL');
        } catch (PDOException $e) {
            // Column might already be nullable, or modification failed - continue
        }
    }
}

try {
    ensureVolunteersTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare volunteers table: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// For create/update/delete we expect JSON
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

// For GET, UPDATE, DELETE - require admin authentication
if ($method === 'GET' || ($method === 'POST' && ($action === 'update' || $action === 'delete'))) {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

if ($method === 'GET') {
    // Return all volunteers
    try {
        $stmt = $pdo->query('SELECT id, name, contact, email, address, category, skills, availability, status, notes, photo_data, photo_id_data, certifications_data, certifications_description, emergency_contact_name, emergency_contact_number, created_at FROM volunteers ORDER BY id DESC');
        $volunteers = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $volunteers,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load volunteers: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    if ($action === 'create') {
        $name = trim($input['name'] ?? '');
        $contact = trim($input['contact'] ?? '');
        $email = trim($input['email'] ?? '');
        $address = trim($input['address'] ?? '');
        $category = trim($input['category'] ?? '');
        $skills = trim($input['skills'] ?? '');
        $availability = trim($input['availability'] ?? '');
        $status = trim($input['status'] ?? 'Pending');
        $notes = trim($input['notes'] ?? '');
        $photo = $input['photo'] ?? null;
        $photoId = $input['photo_id'] ?? null;
        $certifications = $input['certifications'] ?? null; // JSON string of array
        $certificationsDescription = trim($input['certifications_description'] ?? '');
        $emergencyName = trim($input['emergency_contact_name'] ?? '');
        $emergencyContact = trim($input['emergency_contact_number'] ?? '');

        if ($name === '' || $contact === '' || $email === '' || $address === '' || $category === '' || $skills === '' || $availability === '' || $emergencyName === '' || $emergencyContact === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        try {
            // Check if volunteer_code column exists
            $hasVolunteerCode = false;
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM volunteers LIKE 'volunteer_code'");
                $hasVolunteerCode = $checkStmt->rowCount() > 0;
            } catch (PDOException $e) {
                // Column doesn't exist, continue without it
            }
            
            // Generate unique volunteer code if column exists
            $volunteerCode = null;
            if ($hasVolunteerCode) {
                $year = date('Y');
                $maxAttempts = 100;
                for ($i = 0; $i < $maxAttempts; $i++) {
                    $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $volunteerCode = "VOL-{$year}-{$random}";
                    
                    // Check if code already exists
                    $checkCode = $pdo->prepare('SELECT COUNT(*) FROM volunteers WHERE volunteer_code = :code');
                    $checkCode->execute([':code' => $volunteerCode]);
                    if ($checkCode->fetchColumn() == 0) {
                        break; // Unique code found
                    }
                }
            }
            
            if ($hasVolunteerCode && $volunteerCode) {
                $stmt = $pdo->prepare('INSERT INTO volunteers (volunteer_code, name, contact, email, address, category, skills, availability, status, notes, photo_data, photo_id_data, certifications_data, certifications_description, emergency_contact_name, emergency_contact_number) VALUES (:code, :name, :contact, :email, :address, :category, :skills, :availability, :status, :notes, :photo, :photo_id, :certifications, :certifications_desc, :emergency_name, :emergency_contact)');
                $stmt->execute([
                    ':code' => $volunteerCode,
                    ':name' => $name,
                    ':contact' => $contact,
                    ':email' => $email,
                    ':address' => $address,
                    ':category' => $category,
                    ':skills' => $skills,
                    ':availability' => $availability,
                    ':status' => $status,
                    ':notes' => $notes,
                    ':photo' => $photo,
                    ':photo_id' => $photoId,
                    ':certifications' => $certifications ? json_encode($certifications) : null,
                    ':certifications_desc' => $certificationsDescription,
                    ':emergency_name' => $emergencyName,
                    ':emergency_contact' => $emergencyContact,
                ]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO volunteers (name, contact, email, address, category, skills, availability, status, notes, photo_data, photo_id_data, certifications_data, certifications_description, emergency_contact_name, emergency_contact_number) VALUES (:name, :contact, :email, :address, :category, :skills, :availability, :status, :notes, :photo, :photo_id, :certifications, :certifications_desc, :emergency_name, :emergency_contact)');
                $stmt->execute([
                    ':name' => $name,
                    ':contact' => $contact,
                    ':email' => $email,
                    ':address' => $address,
                    ':category' => $category,
                    ':skills' => $skills,
                    ':availability' => $availability,
                    ':status' => $status,
                    ':notes' => $notes,
                    ':photo' => $photo,
                    ':photo_id' => $photoId,
                    ':certifications' => $certifications ? json_encode($certifications) : null,
                    ':certifications_desc' => $certificationsDescription,
                    ':emergency_name' => $emergencyName,
                    ':emergency_contact' => $emergencyContact,
                ]);
            }

            $id = (int)$pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $id,
                    'name' => $name,
                    'contact' => $contact,
                    'email' => $email,
                    'address' => $address,
                    'category' => $category,
                    'skills' => $skills,
                    'availability' => $availability,
                    'status' => $status,
                    'notes' => $notes,
                    'photo_data' => $photo,
                    'photo_id_data' => $photoId,
                    'certifications_data' => $certifications,
                    'certifications_description' => $certificationsDescription,
                    'emergency_contact_name' => $emergencyName,
                    'emergency_contact_number' => $emergencyContact,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save volunteer: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $contact = trim($input['contact'] ?? '');
        $email = trim($input['email'] ?? '');
        $address = trim($input['address'] ?? '');
        $category = trim($input['category'] ?? '');
        $skills = trim($input['skills'] ?? '');
        $availability = trim($input['availability'] ?? '');
        $status = trim($input['status'] ?? '');
        $notes = trim($input['notes'] ?? '');
        $photo = $input['photo'] ?? null;
        $photoId = $input['photo_id'] ?? null;
        $certifications = $input['certifications'] ?? null;
        $certificationsDescription = trim($input['certifications_description'] ?? '');
        $emergencyName = trim($input['emergency_contact_name'] ?? '');
        $emergencyContact = trim($input['emergency_contact_number'] ?? '');

        if ($id <= 0 || $name === '' || $contact === '' || $email === '' || $address === '' || $category === '' || $skills === '' || $availability === '' || $status === '' || $emergencyName === '' || $emergencyContact === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        try {
            // Fetch current record to preserve existing photos/certifications if not updated
            $stmt = $pdo->prepare('SELECT photo_data, photo_id_data, certifications_data FROM volunteers WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $current = $stmt->fetch();
            if (!$current) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Volunteer not found.']);
                exit;
            }

            $photoToSave = $photo !== null ? $photo : $current['photo_data'];
            $photoIdToSave = $photoId !== null ? $photoId : $current['photo_id_data'];
            $certsToSave = $certifications !== null ? (is_string($certifications) ? $certifications : json_encode($certifications)) : $current['certifications_data'];

            $stmt = $pdo->prepare('UPDATE volunteers SET name = :name, contact = :contact, email = :email, address = :address, category = :category, skills = :skills, availability = :availability, status = :status, notes = :notes, photo_data = :photo, photo_id_data = :photo_id, certifications_data = :certifications, certifications_description = :certifications_desc, emergency_contact_name = :emergency_name, emergency_contact_number = :emergency_contact WHERE id = :id');
            $stmt->execute([
                ':name' => $name,
                ':contact' => $contact,
                ':email' => $email,
                ':address' => $address,
                ':category' => $category,
                ':skills' => $skills,
                ':availability' => $availability,
                ':status' => $status,
                ':notes' => $notes,
                ':photo' => $photoToSave,
                ':photo_id' => $photoIdToSave,
                ':certifications' => $certsToSave,
                ':certifications_desc' => $certificationsDescription,
                ':emergency_name' => $emergencyName,
                ':emergency_contact' => $emergencyContact,
                ':id' => $id,
            ]);

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $id,
                    'name' => $name,
                    'contact' => $contact,
                    'email' => $email,
                    'address' => $address,
                    'category' => $category,
                    'skills' => $skills,
                    'availability' => $availability,
                    'status' => $status,
                    'notes' => $notes,
                    'photo_data' => $photoToSave,
                    'photo_id_data' => $photoIdToSave,
                    'certifications_data' => $certsToSave,
                    'certifications_description' => $certificationsDescription,
                    'emergency_contact_name' => $emergencyName,
                    'emergency_contact_number' => $emergencyContact,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update volunteer: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid volunteer ID.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM volunteers WHERE id = :id');
            $stmt->execute([':id' => $id]);

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete volunteer: ' . $e->getMessage()]);
        }
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);


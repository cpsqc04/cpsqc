<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/nw_members_schema.php';
require_once __DIR__ . '/../includes/contact_validation.php';
require_once __DIR__ . '/../includes/volunteer_media.php';
require_once __DIR__ . '/../includes/volunteer_notifications.php';
require_once __DIR__ . '/../includes/nw_member_credentials.php';
require_once __DIR__ . '/../includes/app_url.php';

try {
    $pdo->exec('SET SESSION max_allowed_packet = 67108864');
} catch (PDOException $e) {
    // Ignore if the server does not allow changing this session value.
}

try {
    ensureNwMembersTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare nw_members table: ' . $e->getMessage()]);
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
    // Return all neighborhood watch members
    try {
        $stmt = $pdo->query('SELECT id, name, contact, email, address, category, skills, availability, status, notes, photo_data, photo_id_data, certifications_data, certifications_description, emergency_contact_name, emergency_contact_number, created_at FROM nw_members ORDER BY id DESC');
        $nw_members = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $nw_members,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load members: ' . $e->getMessage()]);
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
        $availability = trim($input['availability'] ?? '') ?: 'Flexible';
        $status = trim($input['status'] ?? 'Pending');
        $notes = trim($input['notes'] ?? '');
        $photo = $input['photo'] ?? null;
        $photoId = $input['photo_id'] ?? null;
        $certifications = $input['certifications'] ?? null; // JSON string of array
        $certificationsDescription = trim($input['certifications_description'] ?? '');
        $emergencyName = trim($input['emergency_contact_name'] ?? '');
        $emergencyContact = trim($input['emergency_contact_number'] ?? '');

        if ($name === '' || $contact === '' || $email === '' || $address === '' || $emergencyName === '' || $emergencyContact === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        $contact = normalizeContactDigits($contact);
        $emergencyContact = normalizeContactDigits($emergencyContact);
        $contactError = validateContactNumber($contact, 'Contact number');
        if ($contactError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $contactError]);
            exit;
        }
        $emergencyContactError = validateContactNumber($emergencyContact, 'Emergency contact number');
        if ($emergencyContactError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $emergencyContactError]);
            exit;
        }

        $category = '';
        $skills = '';
        $certifications = null;
        $certificationsDescription = '';

        try {
            $hasVolunteerCode = false;
            // Check if volunteer_code column exists
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM nw_members LIKE 'volunteer_code'");
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
                    $checkCode = $pdo->prepare('SELECT COUNT(*) FROM nw_members WHERE volunteer_code = :code');
                    $checkCode->execute([':code' => $volunteerCode]);
                    if ($checkCode->fetchColumn() == 0) {
                        break; // Unique code found
                    }
                }
            }

            $pdo->beginTransaction();

            if ($hasVolunteerCode && $volunteerCode) {
                $stmt = $pdo->prepare('INSERT INTO nw_members (volunteer_code, name, contact, email, address, category, skills, availability, status, notes, photo_data, photo_id_data, certifications_data, certifications_description, emergency_contact_name, emergency_contact_number) VALUES (:code, :name, :contact, :email, :address, :category, :skills, :availability, :status, :notes, :photo, :photo_id, :certifications, :certifications_desc, :emergency_name, :emergency_contact)');
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
                    ':photo' => null,
                    ':photo_id' => null,
                    ':certifications' => $certifications ? json_encode($certifications) : null,
                    ':certifications_desc' => $certificationsDescription,
                    ':emergency_name' => $emergencyName,
                    ':emergency_contact' => $emergencyContact,
                ]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO nw_members (name, contact, email, address, category, skills, availability, status, notes, photo_data, photo_id_data, certifications_data, certifications_description, emergency_contact_name, emergency_contact_number) VALUES (:name, :contact, :email, :address, :category, :skills, :availability, :status, :notes, :photo, :photo_id, :certifications, :certifications_desc, :emergency_name, :emergency_contact)');
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
                    ':photo' => null,
                    ':photo_id' => null,
                    ':certifications' => $certifications ? json_encode($certifications) : null,
                    ':certifications_desc' => $certificationsDescription,
                    ':emergency_name' => $emergencyName,
                    ':emergency_contact' => $emergencyContact,
                ]);
            }

            $id = (int)$pdo->lastInsertId();

            $photoPath = volunteerMediaStore($photo, 'photo', $id);
            $photoIdPath = volunteerMediaStore($photoId, 'photo_id', $id);

            if (!$photoPath || !$photoIdPath) {
                throw new RuntimeException('Member photo and valid ID photo are required.');
            }

            $updatePhotos = $pdo->prepare('UPDATE nw_members SET photo_data = :photo, photo_id_data = :photo_id WHERE id = :id');
            $updatePhotos->execute([
                ':photo' => $photoPath,
                ':photo_id' => $photoIdPath,
                ':id' => $id,
            ]);

            $pdo->commit();

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
                    'photo_data' => $photoPath,
                    'photo_id_data' => $photoIdPath,
                    'certifications_data' => $certifications,
                    'certifications_description' => $certificationsDescription,
                    'emergency_contact_name' => $emergencyName,
                    'emergency_contact_number' => $emergencyContact,
                ],
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save member: ' . $e->getMessage()]);
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
        $availability = trim($input['availability'] ?? '') ?: 'Flexible';
        $status = trim($input['status'] ?? '');
        $notes = trim($input['notes'] ?? '');
        $photo = $input['photo'] ?? null;
        $photoId = $input['photo_id'] ?? null;
        $certifications = $input['certifications'] ?? null;
        $certificationsDescription = trim($input['certifications_description'] ?? '');
        $emergencyName = trim($input['emergency_contact_name'] ?? '');
        $emergencyContact = trim($input['emergency_contact_number'] ?? '');

        if ($id <= 0 || $name === '' || $contact === '' || $email === '' || $address === '' || $status === '' || $emergencyName === '' || $emergencyContact === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        $contact = normalizeContactDigits($contact);
        $emergencyContact = normalizeContactDigits($emergencyContact);
        $contactError = validateContactNumber($contact, 'Contact number');
        if ($contactError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $contactError]);
            exit;
        }
        $emergencyContactError = validateContactNumber($emergencyContact, 'Emergency contact number');
        if ($emergencyContactError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $emergencyContactError]);
            exit;
        }

        $category = '';
        $skills = '';
        $certificationsDescription = '';
        $certifications = null;

        try {
            // Fetch current record to preserve existing photos if not updated
            $stmt = $pdo->prepare('SELECT photo_data, photo_id_data, availability, status, password_hash, member_code FROM nw_members WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $current = $stmt->fetch();
            if (!$current) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Member not found.']);
                exit;
            }

            $previousStatus = trim((string) ($current['status'] ?? ''));

            if (trim($input['availability'] ?? '') === '') {
                $availability = $current['availability'] ?? 'Flexible';
            }

            $photoToSave = $current['photo_data'];
            $photoIdToSave = $current['photo_id_data'];
            $certsToSave = null;

            if ($photo !== null && volunteerMediaIsDataUrl($photo)) {
                volunteerMediaDelete($current['photo_data']);
                $photoToSave = volunteerMediaStore($photo, 'photo', $id);
            } elseif ($photo !== null) {
                $photoToSave = $photo;
            }

            if ($photoId !== null && volunteerMediaIsDataUrl($photoId)) {
                volunteerMediaDelete($current['photo_id_data']);
                $photoIdToSave = volunteerMediaStore($photoId, 'photo_id', $id);
            } elseif ($photoId !== null) {
                $photoIdToSave = $photoId;
            }

            $stmt = $pdo->prepare('UPDATE nw_members SET name = :name, contact = :contact, email = :email, address = :address, category = :category, skills = :skills, availability = :availability, status = :status, notes = :notes, photo_data = :photo, photo_id_data = :photo_id, certifications_data = :certifications, certifications_description = :certifications_desc, emergency_contact_name = :emergency_name, emergency_contact_number = :emergency_contact WHERE id = :id');
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

            $emailSent = null;
            $tempPassword = null;
            $memberCode = $current['member_code'] ?? null;

            if ($previousStatus !== $status && $status === 'Active') {
                $credentials = provisionNwMemberCredentials($pdo, $id);
                $tempPassword = $credentials['temp_password'];
                $memberCode = $credentials['member_code'];
                $emailSent = sendVolunteerApplicationStatusEmail(
                    $email,
                    $name,
                    $status,
                    $tempPassword,
                    getNwMemberPortalUrl(),
                    $memberCode
                );
            } elseif ($previousStatus !== $status && $status === 'Rejected') {
                $emailSent = sendVolunteerApplicationStatusEmail($email, $name, $status);
            }

            echo json_encode([
                'success' => true,
                'email_sent' => $emailSent,
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
        } catch (Throwable $e) {
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
            $stmt = $pdo->prepare('SELECT photo_data, photo_id_data FROM nw_members WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $existing = $stmt->fetch();

            if ($existing) {
                volunteerMediaDelete($existing['photo_data'] ?? null);
                volunteerMediaDelete($existing['photo_id_data'] ?? null);
            }

            $stmt = $pdo->prepare('DELETE FROM nw_members WHERE id = :id');
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


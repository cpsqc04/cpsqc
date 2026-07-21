<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/neighborhood-watcher-members-schema.php';
require_once __DIR__ . '/../includes/contact_validation.php';
require_once __DIR__ . '/../includes/volunteer_media.php';
require_once __DIR__ . '/../includes/volunteer_notifications.php';
require_once __DIR__ . '/../includes/neighborhood-watcher-member-credentials.php';
require_once __DIR__ . '/../includes/app_url.php';

function nwNormalizeNameParts(array $input): array
{
    $firstName = trim((string) ($input['first_name'] ?? ''));
    $middleName = trim((string) ($input['middle_name'] ?? ''));
    $lastName = trim((string) ($input['last_name'] ?? ''));
    $legacyName = trim((string) ($input['name'] ?? ''));

    if (($firstName === '' || $lastName === '') && $legacyName !== '') {
        if (preg_match('/^([^,]+),\s*(.+)$/', $legacyName, $m)) {
            $lastName = $lastName !== '' ? $lastName : trim($m[1]);
            $rest = trim($m[2]);
            if ($firstName === '') {
                $parts = preg_split('/\s+/', $rest) ?: [];
                $firstName = trim((string) ($parts[0] ?? ''));
                if ($middleName === '' && count($parts) > 1) {
                    $middleName = trim(implode(' ', array_slice($parts, 1)));
                }
            }
        } else {
            $parts = preg_split('/\s+/', $legacyName) ?: [];
            if ($firstName === '' && !empty($parts)) {
                $firstName = trim((string) $parts[0]);
            }
            if ($lastName === '' && count($parts) > 1) {
                $lastName = trim((string) $parts[count($parts) - 1]);
            }
            if ($middleName === '' && count($parts) > 2) {
                $middleName = trim(implode(' ', array_slice($parts, 1, -1)));
            }
        }
    }

    $fullName = buildNwMemberDisplayName($firstName, $middleName, $lastName, $legacyName);
    return [
        'first_name' => $firstName !== '' ? $firstName : null,
        'middle_name' => $middleName !== '' ? $middleName : null,
        'last_name' => $lastName !== '' ? $lastName : null,
        'name' => $fullName,
    ];
}

function nwNormalizeGender(?string $gender): ?string
{
    $gender = trim((string) $gender);
    $allowed = ['Male', 'Female', 'Other'];
    return in_array($gender, $allowed, true) ? $gender : null;
}

function nwNormalizeMaritalStatus(?string $status): ?string
{
    $status = trim((string) $status);
    $allowed = ['Single', 'Married', 'Separated', 'Widowed', 'Common-law (live-in)'];
    return in_array($status, $allowed, true) ? $status : null;
}

function nwNormalizeAddressParts(array $input): array
{
    $unitStreet = trim((string) ($input['address_unit_street'] ?? $input['unit_street'] ?? ''));
    $subdivision = trim((string) ($input['address_subdivision'] ?? $input['subdivision'] ?? ''));
    $barangay = trim((string) ($input['address_barangay'] ?? $input['barangay'] ?? ''));
    $city = trim((string) ($input['address_city'] ?? $input['city'] ?? ''));
    $postal = trim((string) ($input['address_postal_code'] ?? $input['postal_code'] ?? ''));
    $country = trim((string) ($input['address_country'] ?? $input['country'] ?? ''));
    $legacyAddress = trim((string) ($input['address'] ?? ''));

    $allowedSubdivisions = [
        'T.S. Cruz Subdivision',
        'Clemente Subdivision',
        'Greenfields III Subdivision',
        'Millieville Subdivision',
        'Nova Homes Subdivision',
        'St. Francis/Blueville Subdivision',
    ];

    if ($subdivision !== '' && !in_array($subdivision, $allowedSubdivisions, true)) {
        $subdivision = '';
    }
    if ($barangay !== '' && $barangay !== 'San Agustin') {
        $barangay = '';
    }
    if ($city !== '' && $city !== 'Quezon City') {
        $city = '';
    }
    if ($country !== '' && $country !== 'Philippines') {
        $country = '';
    }

    // Auto postal code for San Agustin, Quezon City, Philippines
    if ($barangay === 'San Agustin' && $city === 'Quezon City' && $country === 'Philippines') {
        $postal = '1117';
    }

    $parts = array_values(array_filter([
        $unitStreet,
        $subdivision,
        $barangay !== '' ? 'Barangay ' . $barangay : '',
        $city,
        $postal,
        $country,
    ], static fn($p) => $p !== ''));

    $formatted = !empty($parts) ? implode(', ', $parts) : $legacyAddress;

    return [
        'address' => $formatted,
        'address_unit_street' => $unitStreet !== '' ? $unitStreet : null,
        'address_subdivision' => $subdivision !== '' ? $subdivision : null,
        'address_barangay' => $barangay !== '' ? $barangay : null,
        'address_city' => $city !== '' ? $city : null,
        'address_postal_code' => $postal !== '' ? $postal : null,
        'address_country' => $country !== '' ? $country : null,
        'complete' => $unitStreet !== '' && $subdivision !== '' && $barangay !== '' && $city !== '' && $postal !== '' && $country !== '',
    ];
}

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
        $stmt = $pdo->query('SELECT id, name, first_name, middle_name, last_name, gender, marital_status, contact, email, address, address_unit_street, address_subdivision, address_barangay, address_city, address_postal_code, address_country, birthday, id_number, category, skills, availability, status, notes, photo_data, photo_id_data, barangay_clearance_data, eligibility_answers, rejection_reason, certifications_data, certifications_description, emergency_contact_name, emergency_contact_number, created_at FROM nw_members ORDER BY id DESC');
        $nw_members = $stmt->fetchAll();

        foreach ($nw_members as &$member) {
            if (empty($member['first_name']) && empty($member['last_name']) && !empty($member['name'])) {
                $parts = nwNormalizeNameParts(['name' => $member['name']]);
                $member['first_name'] = $parts['first_name'];
                $member['middle_name'] = $parts['middle_name'];
                $member['last_name'] = $parts['last_name'];
                $member['name'] = $parts['name'];
            } else {
                $member['name'] = buildNwMemberDisplayName(
                    $member['first_name'] ?? null,
                    $member['middle_name'] ?? null,
                    $member['last_name'] ?? null,
                    $member['name'] ?? null
                );
            }
        }
        unset($member);

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
        $nameParts = nwNormalizeNameParts($input);
        $name = $nameParts['name'];
        $firstName = $nameParts['first_name'];
        $middleName = $nameParts['middle_name'];
        $lastName = $nameParts['last_name'];
        $gender = nwNormalizeGender($input['gender'] ?? null);
        $maritalStatus = nwNormalizeMaritalStatus($input['marital_status'] ?? null);
        $addressParts = nwNormalizeAddressParts($input);
        $address = $addressParts['address'];
        $contact = trim($input['contact'] ?? '');
        $email = trim($input['email'] ?? '');
        $birthday = trim($input['birthday'] ?? '');
        $idNumber = trim($input['id_number'] ?? '');
        $category = trim($input['category'] ?? '');
        $skills = trim($input['skills'] ?? '');
        $availability = trim($input['availability'] ?? '') ?: 'Flexible';
        $status = trim($input['status'] ?? 'Pending');
        $notes = trim($input['notes'] ?? '');
        $photo = $input['photo'] ?? null;
        $photoId = $input['photo_id'] ?? null;
        $barangayClearance = $input['barangay_clearance'] ?? null;
        $eligibilityAnswersRaw = $input['eligibility_answers'] ?? null;
        $certifications = $input['certifications'] ?? null; // JSON string of array
        $certificationsDescription = trim($input['certifications_description'] ?? '');
        $emergencyName = trim($input['emergency_contact_name'] ?? '');
        $emergencyContact = trim($input['emergency_contact_number'] ?? '');

        if ($name === '' || $firstName === null || $lastName === null || $contact === '' || $email === '' || $address === '' || $emergencyName === '' || $emergencyContact === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        if (!empty($input['unit_street']) || !empty($input['address_unit_street']) || !empty($input['subdivision']) || !empty($input['address_subdivision'])) {
            if (!$addressParts['complete']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Please complete all address fields.']);
                exit;
            }
        }

        // Gender/marital are required for public applications; admin creates may omit them.
        if (array_key_exists('gender', $input) && $gender === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select a valid gender.']);
            exit;
        }
        if (array_key_exists('marital_status', $input) && $maritalStatus === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select a valid marital status.']);
            exit;
        }

        if ($photo === null || $photo === '' || $photoId === null || $photoId === '' || $barangayClearance === null || $barangayClearance === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Member photo, valid ID, and barangay clearance are required.']);
            exit;
        }

        $eligibilityAnswers = null;
        if (is_array($eligibilityAnswersRaw)) {
            $eligibilityAnswers = json_encode($eligibilityAnswersRaw);
        } elseif (is_string($eligibilityAnswersRaw) && trim($eligibilityAnswersRaw) !== '') {
            $decodedEligibility = json_decode($eligibilityAnswersRaw, true);
            $eligibilityAnswers = is_array($decodedEligibility) ? json_encode($decodedEligibility) : null;
        }

        if ($birthday !== '') {
            $birthdayTs = strtotime(str_replace('/', '-', $birthday));
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $birthday, $parts)) {
                $birthdayTs = strtotime(sprintf('%04d-%02d-%02d', (int) $parts[3], (int) $parts[1], (int) $parts[2]));
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
                $birthdayTs = strtotime($birthday);
            }
            if ($birthdayTs === false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid birthday. Use MM/DD/YYYY.']);
                exit;
            }
            $birthday = date('Y-m-d', $birthdayTs);
            $age = (new DateTimeImmutable($birthday))->diff(new DateTimeImmutable('today'))->y;
            if ($age < 18) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'must be 18 years old and above']);
                exit;
            }
        } else {
            $birthday = null;
        }
        if ($idNumber === '') {
            $idNumber = null;
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
                $stmt = $pdo->prepare('INSERT INTO nw_members (volunteer_code, name, contact, email, address, birthday, id_number, category, skills, availability, status, notes, photo_data, photo_id_data, certifications_data, certifications_description, emergency_contact_name, emergency_contact_number) VALUES (:code, :name, :contact, :email, :address, :birthday, :id_number, :category, :skills, :availability, :status, :notes, :photo, :photo_id, :certifications, :certifications_desc, :emergency_name, :emergency_contact)');
                $stmt->execute([
                    ':code' => $volunteerCode,
                    ':name' => $name,
                    ':contact' => $contact,
                    ':email' => $email,
                    ':address' => $address,
                    ':birthday' => $birthday,
                    ':id_number' => $idNumber,
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
                $stmt = $pdo->prepare('INSERT INTO nw_members (name, contact, email, address, birthday, id_number, category, skills, availability, status, notes, photo_data, photo_id_data, certifications_data, certifications_description, emergency_contact_name, emergency_contact_number) VALUES (:name, :contact, :email, :address, :birthday, :id_number, :category, :skills, :availability, :status, :notes, :photo, :photo_id, :certifications, :certifications_desc, :emergency_name, :emergency_contact)');
                $stmt->execute([
                    ':name' => $name,
                    ':contact' => $contact,
                    ':email' => $email,
                    ':address' => $address,
                    ':birthday' => $birthday,
                    ':id_number' => $idNumber,
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
            $clearancePath = volunteerMediaStore($barangayClearance, 'barangay_clearance', $id);

            if (!$photoPath || !$photoIdPath || !$clearancePath) {
                throw new RuntimeException('Member photo, valid ID photo, and barangay clearance are required.');
            }

            $updatePhotos = $pdo->prepare('UPDATE nw_members SET first_name = :first_name, middle_name = :middle_name, last_name = :last_name, gender = :gender, marital_status = :marital_status, name = :name, address = :address, address_unit_street = :unit_street, address_subdivision = :subdivision, address_barangay = :barangay, address_city = :city, address_postal_code = :postal_code, address_country = :country, photo_data = :photo, photo_id_data = :photo_id, barangay_clearance_data = :clearance, eligibility_answers = :eligibility WHERE id = :id');
            $updatePhotos->execute([
                ':first_name' => $firstName,
                ':middle_name' => $middleName,
                ':last_name' => $lastName,
                ':gender' => $gender,
                ':marital_status' => $maritalStatus,
                ':name' => $name,
                ':address' => $address,
                ':unit_street' => $addressParts['address_unit_street'],
                ':subdivision' => $addressParts['address_subdivision'],
                ':barangay' => $addressParts['address_barangay'],
                ':city' => $addressParts['address_city'],
                ':postal_code' => $addressParts['address_postal_code'],
                ':country' => $addressParts['address_country'],
                ':photo' => $photoPath,
                ':photo_id' => $photoIdPath,
                ':clearance' => $clearancePath,
                ':eligibility' => $eligibilityAnswers,
                ':id' => $id,
            ]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $id,
                    'name' => $name,
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'last_name' => $lastName,
                    'gender' => $gender,
                    'marital_status' => $maritalStatus,
                    'contact' => $contact,
                    'email' => $email,
                    'address' => $address,
                    'address_unit_street' => $addressParts['address_unit_street'],
                    'address_subdivision' => $addressParts['address_subdivision'],
                    'address_barangay' => $addressParts['address_barangay'],
                    'address_city' => $addressParts['address_city'],
                    'address_postal_code' => $addressParts['address_postal_code'],
                    'address_country' => $addressParts['address_country'],
                    'birthday' => $birthday,
                    'id_number' => $idNumber,
                    'category' => $category,
                    'skills' => $skills,
                    'availability' => $availability,
                    'status' => $status,
                    'notes' => $notes,
                    'photo_data' => $photoPath,
                    'photo_id_data' => $photoIdPath,
                    'barangay_clearance_data' => $clearancePath,
                    'eligibility_answers' => $eligibilityAnswers ? json_decode($eligibilityAnswers, true) : null,
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
        $nameParts = nwNormalizeNameParts($input);
        $name = $nameParts['name'];
        $firstName = $nameParts['first_name'];
        $middleName = $nameParts['middle_name'];
        $lastName = $nameParts['last_name'];
        $gender = array_key_exists('gender', $input) ? nwNormalizeGender($input['gender'] ?? null) : null;
        $maritalStatus = array_key_exists('marital_status', $input) ? nwNormalizeMaritalStatus($input['marital_status'] ?? null) : null;
        $addressParts = nwNormalizeAddressParts($input);
        $contact = trim($input['contact'] ?? '');
        $email = trim($input['email'] ?? '');
        $address = $addressParts['address'] !== '' ? $addressParts['address'] : trim($input['address'] ?? '');
        $birthday = trim($input['birthday'] ?? '');
        $idNumber = trim($input['id_number'] ?? '');
        $category = trim($input['category'] ?? '');
        $skills = trim($input['skills'] ?? '');
        $availability = trim($input['availability'] ?? '') ?: 'Flexible';
        $status = trim($input['status'] ?? '');
        $notes = trim($input['notes'] ?? '');
        $photo = $input['photo'] ?? null;
        $photoId = $input['photo_id'] ?? null;
        $barangayClearance = $input['barangay_clearance'] ?? null;
        $rejectionReason = trim($input['rejection_reason'] ?? '');
        $certifications = $input['certifications'] ?? null;
        $certificationsDescription = trim($input['certifications_description'] ?? '');
        $emergencyName = trim($input['emergency_contact_name'] ?? '');
        $emergencyContact = trim($input['emergency_contact_number'] ?? '');

        if ($id <= 0 || $name === '' || $contact === '' || $email === '' || $address === '' || $status === '' || $emergencyName === '' || $emergencyContact === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        $allowedRejectionReasons = [
            'Maximum of slots reached',
            'Incomplete document requirements',
            'Discrepancy in residency requirements',
        ];

        if ($status === 'Rejected') {
            if ($rejectionReason === '' || !in_array($rejectionReason, $allowedRejectionReasons, true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Please select a valid rejection reason.']);
                exit;
            }
        } else {
            $rejectionReason = '';
        }

        if ($birthday !== '') {
            $birthdayTs = strtotime($birthday);
            if ($birthdayTs === false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid birthday.']);
                exit;
            }
            $birthday = date('Y-m-d', $birthdayTs);
        } else {
            $birthday = null;
        }
        if ($idNumber === '') {
            $idNumber = null;
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
            $stmt = $pdo->prepare('SELECT photo_data, photo_id_data, barangay_clearance_data, availability, status, password_hash, member_code, email, name, first_name, middle_name, last_name, gender, marital_status, address_unit_street, address_subdivision, address_barangay, address_city, address_postal_code, address_country, rejection_reason, notes FROM nw_members WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $current = $stmt->fetch();
            if (!$current) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Member not found.']);
                exit;
            }

            $previousStatus = trim((string) ($current['status'] ?? ''));
            $recipientEmail = trim((string) ($current['email'] ?? ''));
            if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                $recipientEmail = $email;
            }
            if ($firstName === null) {
                $firstName = $current['first_name'] ?? null;
            }
            if ($middleName === null) {
                $middleName = $current['middle_name'] ?? null;
            }
            if ($lastName === null) {
                $lastName = $current['last_name'] ?? null;
            }
            if ($gender === null) {
                $gender = $current['gender'] ?? null;
            }
            if ($maritalStatus === null) {
                $maritalStatus = $current['marital_status'] ?? null;
            }
            $name = buildNwMemberDisplayName($firstName, $middleName, $lastName, $name ?: ($current['name'] ?? ''));
            $recipientName = $name;

            if (trim($input['availability'] ?? '') === '') {
                $availability = $current['availability'] ?? 'Flexible';
            }

            $photoToSave = $current['photo_data'];
            $photoIdToSave = $current['photo_id_data'];
            $clearanceToSave = $current['barangay_clearance_data'] ?? null;
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

            if ($barangayClearance !== null && volunteerMediaIsDataUrl($barangayClearance)) {
                volunteerMediaDelete($current['barangay_clearance_data'] ?? null);
                $clearanceToSave = volunteerMediaStore($barangayClearance, 'barangay_clearance', $id);
            } elseif ($barangayClearance !== null) {
                $clearanceToSave = $barangayClearance;
            }

            $stmt = $pdo->prepare('UPDATE nw_members SET name = :name, first_name = :first_name, middle_name = :middle_name, last_name = :last_name, gender = :gender, marital_status = :marital_status, contact = :contact, email = :email, address = :address, address_unit_street = :unit_street, address_subdivision = :subdivision, address_barangay = :barangay, address_city = :city, address_postal_code = :postal_code, address_country = :country, birthday = :birthday, id_number = :id_number, category = :category, skills = :skills, availability = :availability, status = :status, notes = :notes, rejection_reason = :rejection_reason, photo_data = :photo, photo_id_data = :photo_id, barangay_clearance_data = :clearance, certifications_data = :certifications, certifications_description = :certifications_desc, emergency_contact_name = :emergency_name, emergency_contact_number = :emergency_contact WHERE id = :id');
            $stmt->execute([
                ':name' => $name,
                ':first_name' => $firstName,
                ':middle_name' => $middleName,
                ':last_name' => $lastName,
                ':gender' => $gender,
                ':marital_status' => $maritalStatus,
                ':contact' => $contact,
                ':email' => $email,
                ':address' => $address,
                ':unit_street' => $addressParts['address_unit_street'] ?? ($current['address_unit_street'] ?? null),
                ':subdivision' => $addressParts['address_subdivision'] ?? ($current['address_subdivision'] ?? null),
                ':barangay' => $addressParts['address_barangay'] ?? ($current['address_barangay'] ?? null),
                ':city' => $addressParts['address_city'] ?? ($current['address_city'] ?? null),
                ':postal_code' => $addressParts['address_postal_code'] ?? ($current['address_postal_code'] ?? null),
                ':country' => $addressParts['address_country'] ?? ($current['address_country'] ?? null),
                ':birthday' => $birthday,
                ':id_number' => $idNumber,
                ':category' => $category,
                ':skills' => $skills,
                ':availability' => $availability,
                ':status' => $status,
                ':notes' => $notes,
                ':rejection_reason' => $status === 'Rejected' ? $rejectionReason : null,
                ':photo' => $photoToSave,
                ':photo_id' => $photoIdToSave,
                ':clearance' => $clearanceToSave,
                ':certifications' => $certsToSave,
                ':certifications_desc' => $certificationsDescription,
                ':emergency_name' => $emergencyName,
                ':emergency_contact' => $emergencyContact,
                ':id' => $id,
            ]);

            $emailSent = null;
            $emailError = null;
            $tempPassword = null;
            $memberCode = $current['member_code'] ?? null;
            $forceResendRejection = !empty($input['resend_rejection_email']);

            if ($previousStatus !== $status && $status === 'Active') {
                $credentials = provisionNwMemberCredentials($pdo, $id);
                $tempPassword = $credentials['temp_password'];
                $memberCode = $credentials['member_code'];
                $mailResult = sendVolunteerApplicationStatusEmail(
                    $recipientEmail,
                    $recipientName,
                    $status,
                    $tempPassword,
                    getNwMemberPortalUrl(),
                    $memberCode,
                    null,
                    null,
                    $firstName,
                    $lastName,
                    $middleName
                );
                $emailSent = !empty($mailResult['success']);
                $emailError = $mailResult['error'] ?? null;
            } elseif ($status === 'Rejected' && $rejectionReason !== '' && ($previousStatus !== 'Rejected' || $forceResendRejection)) {
                $mailResult = sendVolunteerApplicationStatusEmail(
                    $recipientEmail,
                    $recipientName,
                    $status,
                    null,
                    null,
                    null,
                    $rejectionReason,
                    $notes,
                    $firstName,
                    $lastName,
                    $middleName
                );
                $emailSent = !empty($mailResult['success']);
                $emailError = $mailResult['error'] ?? null;
                if (!$emailSent) {
                    error_log('NW rejection email failed for member #' . $id . ' to ' . $recipientEmail . ': ' . ($emailError ?: 'unknown error'));
                }
            }

            echo json_encode([
                'success' => true,
                'email_sent' => $emailSent,
                'email_error' => $emailError,
                'email_to' => $recipientEmail,
                'data' => [
                    'id' => $id,
                    'name' => $name,
                    'contact' => $contact,
                    'email' => $email,
                    'address' => $address,
                    'address_unit_street' => $addressParts['address_unit_street'] ?? null,
                    'address_subdivision' => $addressParts['address_subdivision'] ?? null,
                    'address_barangay' => $addressParts['address_barangay'] ?? null,
                    'address_city' => $addressParts['address_city'] ?? null,
                    'address_postal_code' => $addressParts['address_postal_code'] ?? null,
                    'address_country' => $addressParts['address_country'] ?? null,
                    'birthday' => $birthday,
                    'id_number' => $idNumber,
                    'category' => $category,
                    'skills' => $skills,
                    'availability' => $availability,
                    'status' => $status,
                    'notes' => $notes,
                    'rejection_reason' => $status === 'Rejected' ? $rejectionReason : null,
                    'photo_data' => $photoToSave,
                    'photo_id_data' => $photoIdToSave,
                    'barangay_clearance_data' => $clearanceToSave,
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
            $stmt = $pdo->prepare('SELECT photo_data, photo_id_data, barangay_clearance_data FROM nw_members WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $existing = $stmt->fetch();

            if ($existing) {
                volunteerMediaDelete($existing['photo_data'] ?? null);
                volunteerMediaDelete($existing['photo_id_data'] ?? null);
                volunteerMediaDelete($existing['barangay_clearance_data'] ?? null);
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


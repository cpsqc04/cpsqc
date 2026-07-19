<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/neighborhood-watcher-members-schema.php';
require_once __DIR__ . '/../includes/contact_validation.php';
require_once __DIR__ . '/../includes/neighborhood-watcher-member-auth.php';

if (!isNwMemberLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    ensureNwMembersTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare member profile storage.']);
    exit;
}

$memberId = getNwMemberId();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? ($_GET['action'] ?? '');

function fetchMemberProfile(PDO $pdo, int $memberId): ?array
{
    $stmt = $pdo->prepare('SELECT id, name, contact, email, address, member_code, status, emergency_contact_name, emergency_contact_number FROM nw_members WHERE id = :id AND status = :status LIMIT 1');
    $stmt->execute([':id' => $memberId, ':status' => 'Active']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

if ($method === 'GET') {
    try {
        $member = fetchMemberProfile($pdo, $memberId);
        if (!$member) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Member account not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'data' => $member]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load profile.']);
    }
    exit;
}

if ($method === 'POST' && $action === 'update_profile') {
    $name = trim($input['name'] ?? '');
    $contact = trim($input['contact'] ?? '');
    $email = trim($input['email'] ?? '');
    $address = trim($input['address'] ?? '');
    $emergencyName = trim($input['emergency_contact_name'] ?? '');
    $emergencyContact = trim($input['emergency_contact_number'] ?? '');

    if ($name === '' || $contact === '' || $email === '' || $address === '' || $emergencyName === '' || $emergencyContact === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All personal information fields are required.']);
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

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    try {
        $check = $pdo->prepare('SELECT id FROM nw_members WHERE email = :email AND id != :id LIMIT 1');
        $check->execute([':email' => $email, ':id' => $memberId]);
        if ($check->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'That email address is already in use.']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE nw_members SET name = :name, contact = :contact, email = :email, address = :address, emergency_contact_name = :emergency_name, emergency_contact_number = :emergency_contact WHERE id = :id AND status = :status');
        $stmt->execute([
            ':name' => $name,
            ':contact' => $contact,
            ':email' => $email,
            ':address' => $address,
            ':emergency_name' => $emergencyName,
            ':emergency_contact' => $emergencyContact,
            ':id' => $memberId,
            ':status' => 'Active',
        ]);

        $_SESSION['nw_member_name'] = $name;
        $_SESSION['nw_member_email'] = $email;

        $member = fetchMemberProfile($pdo, $memberId);
        echo json_encode([
            'success' => true,
            'message' => 'Personal information updated successfully.',
            'data' => $member,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update profile.']);
    }
    exit;
}

if ($method === 'POST' && $action === 'change_password') {
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All password fields are required.']);
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
        exit;
    }

    if (strlen($newPassword) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT password_hash FROM nw_members WHERE id = :id AND status = :status LIMIT 1');
        $stmt->execute([':id' => $memberId, ':status' => 'Active']);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$member || empty($member['password_hash']) || !password_verify($currentPassword, $member['password_hash'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit;
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $pdo->prepare('UPDATE nw_members SET password_hash = :password_hash, must_change_password = 0 WHERE id = :id');
        $update->execute([
            ':password_hash' => $passwordHash,
            ':id' => $memberId,
        ]);

        $_SESSION['nw_member_must_change_password'] = false;

        echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

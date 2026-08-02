<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/bpso_auth.php';
require_once __DIR__ . '/notifications_schema.php';

if (!isBpsoLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

$patrolId = getBpsoPatrolId();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$action = $input['action'] ?? ($_GET['action'] ?? '');

function isValidPatrolAccountPassword(string $password): bool
{
    if (strlen($password) < 8) {
        return false;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    return (bool) preg_match('/[0-9!@#$%^&*(),.?\":{}|<>_\-]/', $password);
}

function patrolAccountPasswordMessage(): string
{
    return 'Password must be at least 8 characters with uppercase, lowercase, and a number or special character.';
}

function fetchPatrolAccount(PDO $pdo, int $patrolId): ?array
{
    $stmt = $pdo->prepare('SELECT id, personnel_name, bpso_personnel_id, email, contact_number, status, schedule, duty_shift FROM patrols WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $patrolId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

try {
    if ($method === 'GET') {
        $profile = fetchPatrolAccount($pdo, $patrolId);
        if (!$profile) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Profile not found.']);
            exit;
        }
        echo json_encode(['success' => true, 'data' => $profile]);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    $profile = fetchPatrolAccount($pdo, $patrolId);
    if (!$profile) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Profile not found.']);
        exit;
    }
    $personnelName = trim((string) ($profile['personnel_name'] ?? getBpsoPersonnelName()));

    if ($action === 'update_email') {
        $email = trim((string) ($input['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
            exit;
        }

        $check = $pdo->prepare('SELECT id FROM patrols WHERE email = :email AND id != :id LIMIT 1');
        $check->execute([':email' => $email, ':id' => $patrolId]);
        if ($check->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'That email address is already in use.']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE patrols SET email = :email WHERE id = :id');
        $stmt->execute([':email' => $email, ':id' => $patrolId]);

        notifyAdminActorActivity(
            $pdo,
            'patrol',
            $personnelName !== '' ? $personnelName : 'Patrol personnel',
            'updated their registered email address to ' . $email . '.',
            'patrol-attendance.php?activity=email_' . $patrolId . '_' . time()
        );

        $updated = fetchPatrolAccount($pdo, $patrolId);
        echo json_encode([
            'success' => true,
            'message' => 'Email address updated successfully.',
            'data' => $updated,
        ]);
        exit;
    }

    if ($action === 'change_password') {
        $currentPassword = (string) ($input['current_password'] ?? '');
        $newPassword = (string) ($input['new_password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');

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
        if (!isValidPatrolAccountPassword($newPassword)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => patrolAccountPasswordMessage()]);
            exit;
        }

        $hashStmt = $pdo->prepare('SELECT password_hash FROM patrols WHERE id = :id LIMIT 1');
        $hashStmt->execute([':id' => $patrolId]);
        $hashRow = $hashStmt->fetch(PDO::FETCH_ASSOC);
        $hash = (string) ($hashRow['password_hash'] ?? '');
        if ($hash === '' || !password_verify($currentPassword, $hash)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit;
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $pdo->prepare('UPDATE patrols SET password_hash = :password_hash, must_change_password = 0 WHERE id = :id');
        $update->execute([':password_hash' => $newHash, ':id' => $patrolId]);
        $_SESSION['bpso_must_change_password'] = false;

        notifyAdminActorActivity(
            $pdo,
            'patrol',
            $personnelName !== '' ? $personnelName : 'Patrol personnel',
            'changed their account password.',
            'patrol-attendance.php?activity=password_' . $patrolId . '_' . time()
        );

        echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

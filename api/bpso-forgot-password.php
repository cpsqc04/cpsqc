<?php
require_once __DIR__ . '/../includes/bpso_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/login_otp.php';

bpsoSessionStart();
header('Content-Type: application/json');

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($action === 'request') {
    $email = trim($input['email'] ?? '');

    if ($email === '') {
        echo json_encode(['success' => false, 'message' => 'Email is required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT id, personnel_name, email, password_hash FROM patrols WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $personnel = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$personnel || empty($personnel['email']) || empty($personnel['password_hash'])) {
            echo json_encode([
                'success' => true,
                'message' => 'If the email exists, an OTP has been sent to the registered email.',
            ]);
            exit;
        }

        $otp = generateLoginOTP();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $_SESSION['bpso_password_reset'] = [
            'patrol_id' => (int) $personnel['id'],
            'personnel_name' => $personnel['personnel_name'],
            'email' => $personnel['email'],
            'otp' => $otp,
            'expires_at' => $expiresAt,
            'attempts' => 0,
        ];

        if (sendPasswordResetOTPEmail($personnel['email'], $otp, 'bpso')) {
            echo json_encode([
                'success' => true,
                'message' => 'OTP has been sent to your registered email address.',
            ]);
        } else {
            unset($_SESSION['bpso_password_reset']);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to send OTP email. Please contact the administrator or try again later.',
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
    exit;
}

if ($action === 'verify') {
    $otp = trim($input['otp'] ?? '');

    if ($otp === '') {
        echo json_encode(['success' => false, 'message' => 'OTP is required.']);
        exit;
    }

    $resetData = $_SESSION['bpso_password_reset'] ?? null;
    if (!$resetData) {
        echo json_encode(['success' => false, 'message' => 'No password reset request found. Please request a new OTP.']);
        exit;
    }

    if (strtotime($resetData['expires_at']) < time()) {
        unset($_SESSION['bpso_password_reset']);
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
        exit;
    }

    if (($resetData['attempts'] ?? 0) >= 5) {
        unset($_SESSION['bpso_password_reset']);
        echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please request a new OTP.']);
        exit;
    }

    if (!hash_equals((string) $resetData['otp'], $otp)) {
        $_SESSION['bpso_password_reset']['attempts'] = (int) ($resetData['attempts'] ?? 0) + 1;
        echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
        exit;
    }

    $_SESSION['bpso_password_reset']['verified'] = true;
    echo json_encode(['success' => true, 'message' => 'OTP verified successfully.']);
    exit;
}

if ($action === 'reset') {
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';

    if ($newPassword === '' || $confirmPassword === '') {
        echo json_encode(['success' => false, 'message' => 'Password fields are required.']);
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long.']);
        exit;
    }

    $resetData = $_SESSION['bpso_password_reset'] ?? null;
    if (!$resetData || empty($resetData['verified'])) {
        echo json_encode(['success' => false, 'message' => 'OTP verification required.']);
        exit;
    }

    if (strtotime($resetData['expires_at']) < time()) {
        unset($_SESSION['bpso_password_reset']);
        echo json_encode(['success' => false, 'message' => 'Session expired. Please request a new OTP.']);
        exit;
    }

    try {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE patrols SET password_hash = :password_hash WHERE id = :id');
        $stmt->execute([
            ':password_hash' => $passwordHash,
            ':id' => (int) $resetData['patrol_id'],
        ]);

        unset($_SESSION['bpso_password_reset']);
        echo json_encode([
            'success' => true,
            'message' => 'Password has been reset successfully. You can now sign in with your new password.',
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action.']);

<?php
session_start();
ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/login_otp.php';

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonResponse(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

if (!($pdo instanceof PDO)) {
    jsonResponse(['success' => false, 'message' => 'Database connection failed. Please try again later.'], 500);
}

if ($action === 'request') {
    try {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = trim($data['email'] ?? '');

        if ($email === '') {
            jsonResponse(['success' => false, 'message' => 'Email is required.']);
        }

        $stmt = $pdo->prepare('SELECT id, username, email FROM admins WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Don't reveal if user exists for security
            jsonResponse([
                'success' => true,
                'message' => 'If the email exists, an OTP has been sent to the registered email.',
            ]);
        }

        if (empty($user['email'])) {
            jsonResponse([
                'success' => false,
                'message' => 'No email address found for this account. Please contact administrator.',
            ]);
        }

        $otp = generateLoginOTP();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $_SESSION['password_reset'] = [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'otp' => $otp,
            'expires_at' => $expiresAt,
            'attempts' => 0,
        ];

        if (sendPasswordResetOTPEmail($user['email'], $otp, 'admin')) {
            $atPos = strpos($user['email'], '@');
            jsonResponse([
                'success' => true,
                'message' => 'OTP has been sent to your registered email address.',
                'email' => substr($user['email'], 0, 3) . '***' . ($atPos !== false ? substr($user['email'], $atPos) : ''),
            ]);
        }

        unset($_SESSION['password_reset']);
        jsonResponse([
            'success' => false,
            'message' => 'Failed to send OTP email. This may be due to email configuration issues. Please contact the administrator or check if your email address is correct in your account settings.',
        ]);
    } catch (Throwable $e) {
        error_log('Error in forgot password request: ' . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
    }
}

if ($action === 'verify') {
    try {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $otp = trim($data['otp'] ?? '');

        if ($otp === '') {
            jsonResponse(['success' => false, 'message' => 'OTP is required.']);
        }

        if (!isset($_SESSION['password_reset'])) {
            jsonResponse(['success' => false, 'message' => 'No password reset request found. Please request a new OTP.']);
        }

        $resetData = $_SESSION['password_reset'];

        if (strtotime($resetData['expires_at']) < time()) {
            unset($_SESSION['password_reset']);
            jsonResponse(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
        }

        if (($resetData['attempts'] ?? 0) >= 5) {
            unset($_SESSION['password_reset']);
            jsonResponse(['success' => false, 'message' => 'Too many failed attempts. Please request a new OTP.']);
        }

        if (!hash_equals((string) $resetData['otp'], $otp)) {
            $_SESSION['password_reset']['attempts']++;
            jsonResponse(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
        }

        $_SESSION['password_reset']['verified'] = true;
        jsonResponse(['success' => true, 'message' => 'OTP verified successfully.']);
    } catch (Throwable $e) {
        error_log('Error in OTP verification: ' . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
    }
}

if ($action === 'reset') {
    try {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $newPassword = $data['new_password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';

        if ($newPassword === '' || $confirmPassword === '') {
            jsonResponse(['success' => false, 'message' => 'Password fields are required.']);
        }

        if ($newPassword !== $confirmPassword) {
            jsonResponse(['success' => false, 'message' => 'Passwords do not match.']);
        }

        if (strlen($newPassword) < 6) {
            jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters long.']);
        }

        if (!isset($_SESSION['password_reset']['verified'])) {
            jsonResponse(['success' => false, 'message' => 'OTP verification required.']);
        }

        $resetData = $_SESSION['password_reset'];

        if (strtotime($resetData['expires_at']) < time()) {
            unset($_SESSION['password_reset']);
            jsonResponse(['success' => false, 'message' => 'Session expired. Please request a new OTP.']);
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE admins SET password_hash = :p WHERE id = :id');
        $stmt->execute([
            ':p' => $passwordHash,
            ':id' => $resetData['user_id'],
        ]);

        unset($_SESSION['password_reset']);
        jsonResponse([
            'success' => true,
            'message' => 'Password has been reset successfully. You can now login with your new password.',
        ]);
    } catch (Throwable $e) {
        error_log('Error in password reset: ' . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
    }
}

jsonResponse(['success' => false, 'message' => 'Invalid action.']);

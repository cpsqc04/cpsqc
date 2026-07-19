<?php 
// Admin login page
session_start();

require_once __DIR__ . '/db.php';

// Load PHPMailer via Composer autoloader
$autoloadPath = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}
require_once __DIR__ . '/includes/login_otp.php';

/**
 * Ensure the admins table exists and has required columns.
 * Also creates a default admin account (admin / admin123) if table is empty.
 */
function ensureAdminsTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        full_name VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure newer columns exist
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM admins') as $row) {
        $columns[$row['Field']] = true;
    }
    if (!isset($columns['email'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN email VARCHAR(255) DEFAULT NULL');
    }
    if (!isset($columns['full_name'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN full_name VARCHAR(255) DEFAULT NULL');
    }
    if (!isset($columns['created_at'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    }
    if (!isset($columns['failed_attempts'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN failed_attempts INT DEFAULT 0');
    }
    if (!isset($columns['last_failed_at'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN last_failed_at DATETIME DEFAULT NULL');
    }
    if (!isset($columns['locked_until'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN locked_until DATETIME DEFAULT NULL');
    }
    if (!isset($columns['status'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN status VARCHAR(20) DEFAULT "Active"');
    }
    if (!isset($columns['role'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN role VARCHAR(50) DEFAULT "Admin"');
    }

    // Create default admin account if none exists
    $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM admins');
    $count = (int)$stmt->fetch()['cnt'];
    if ($count === 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash, email, full_name, status, role) VALUES (:u, :p, :e, :f, "Active", "Admin")');
        $stmt->execute([
            ':u' => 'admin',
            ':p' => $hash,
            ':e' => null,
            ':f' => 'Default Administrator'
        ]);
    }
}

if (!($pdo instanceof PDO)) {
    $error = 'Unable to connect to the database. Please make sure MySQL is running in XAMPP, then refresh this page.';
} else {
    try {
        ensureAdminsTable($pdo);
    } catch (PDOException $e) {
        $error = 'Login system error: ' . htmlspecialchars($e->getMessage());
    }
}

// Email sending functions
function sendAccountLockEmail($email, $username, $lockedUntil) {
    // Load email config with fallback to direct .env file read
    $mailHost = $_ENV['MAIL_HOST'] ?? 'smtp.resend.com';
    $mailPort = (int)($_ENV['MAIL_PORT'] ?? 465);
    $mailUser = $_ENV['MAIL_USERNAME'] ?? '';
    $mailPass = $_ENV['MAIL_PASSWORD'] ?? '';
    $mailFrom = $_ENV['MAIL_FROM_ADDRESS'] ?? $mailUser;
    $mailFromName = $_ENV['MAIL_FROM_NAME'] ?? 'AlerTara QC';
    $mailEncryption = $_ENV['MAIL_ENCRYPTION'] ?? 'ssl';
    
    // If credentials are empty, try to load from .env file directly
    if (empty($mailUser) || empty($mailPass)) {
        $envPath = __DIR__ . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    $value = trim($value, '"\'');
                    if ($key === 'MAIL_USERNAME' && empty($mailUser)) $mailUser = $value;
                    if ($key === 'MAIL_PASSWORD' && empty($mailPass)) $mailPass = $value;
                    if ($key === 'MAIL_HOST' && empty($mailHost)) $mailHost = $value;
                    if ($key === 'MAIL_PORT' && empty($mailPort)) $mailPort = (int)$value;
                    if ($key === 'MAIL_FROM_ADDRESS' && empty($mailFrom)) $mailFrom = $value;
                    if ($key === 'MAIL_FROM_NAME' && empty($mailFromName)) $mailFromName = $value;
                    if ($key === 'MAIL_ENCRYPTION' && empty($mailEncryption)) $mailEncryption = $value;
                }
            }
        }
    }
    
    if (empty($mailUser) || empty($mailPass) || empty($email)) {
        error_log('Account lock email failed: Email credentials or recipient email not set. MAIL_USERNAME: ' . (!empty($mailUser) ? 'set' : 'empty') . ', MAIL_PASSWORD: ' . (!empty($mailPass) ? 'set' : 'empty') . ', Recipient: ' . ($email ?: 'empty'));
        return false;
    }
    
    // Format unlock time in Philippines timezone
    $dateTime = new DateTime($lockedUntil, new DateTimeZone('UTC'));
    $dateTime->setTimezone(new DateTimeZone('Asia/Manila'));
    $unlockTime = $dateTime->format('F j, Y \a\t g:i A');
    
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $mailHost;
            $mail->SMTPAuth = true;
            $mail->Username = $mailUser;
            $mail->Password = $mailPass;
            
            if ($mailPort === 587 || strtolower($mailEncryption) === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }
            $mail->Port = $mailPort;
            $mail->CharSet = 'UTF-8';
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
            
            $mail->setFrom($mailFrom, $mailFromName);
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'AlerTara QC - Account Temporarily Locked';
            $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                    .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; }
                    .header { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 30px 20px; text-align: center; }
                    .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
                    .body-content { padding: 30px 20px; background: #ffffff; }
                    .body-content p { margin: 0 0 15px 0; color: #333; font-size: 14px; }
                    .warning-box { background: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; border-radius: 4px; }
                    .footer { background: #f5f5f5; padding: 15px 20px; text-align: center; color: #999; font-size: 11px; }
                </style>
            </head>
            <body>
                <div class='email-container'>
                    <div class='header'>
                        <h1>Account Temporarily Locked</h1>
                    </div>
                    <div class='body-content'>
                        <p>Hello,</p>
                        <p>Your account <strong>{$username}</strong> has been temporarily locked due to multiple failed login attempts.</p>
                        <div class='warning-box'>
                            <p><strong>Security Alert:</strong> We detected suspicious activity on your account. For your security, the account has been locked.</p>
                        </div>
                        <p>Your account will be automatically unlocked on <strong>{$unlockTime}</strong> (30 minutes from the last failed attempt).</p>
                        <p>If you did not attempt to log in, please contact an administrator immediately as your account may have been compromised.</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated security message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->send();
            error_log("Account lock email sent successfully to {$email} for user {$username}");
            return true;
        } catch (Exception $e) {
            $errorMsg = 'Account lock email send failed: ' . ($mail->ErrorInfo ?? $e->getMessage());
            error_log($errorMsg);
            error_log('PHPMailer Exception details: ' . $e->getMessage());
            error_log('Email config - Host: ' . $mailHost . ', Port: ' . $mailPort . ', User: ' . ($mailUser ? 'set' : 'empty') . ', Encryption: ' . $mailEncryption);
            return false;
        }
    }
    
    // Fallback to native mail()
    $subject = 'AlerTara QC - Account Temporarily Locked';
    $message = "Your account {$username} has been temporarily locked due to multiple failed login attempts. It will be unlocked on {$unlockTime}.";
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: {$mailFromName} <{$mailFrom}>\r\n";
    return mail($email, $subject, $message, $headers);
}

function sendLoginSuccessEmail($email, $username, $loginTime, $ipAddress) {
    // Load email config with fallback to direct .env file read
    $mailHost = $_ENV['MAIL_HOST'] ?? 'smtp.resend.com';
    $mailPort = (int)($_ENV['MAIL_PORT'] ?? 465);
    $mailUser = $_ENV['MAIL_USERNAME'] ?? '';
    $mailPass = $_ENV['MAIL_PASSWORD'] ?? '';
    $mailFrom = $_ENV['MAIL_FROM_ADDRESS'] ?? $mailUser;
    $mailFromName = $_ENV['MAIL_FROM_NAME'] ?? 'AlerTara QC';
    $mailEncryption = $_ENV['MAIL_ENCRYPTION'] ?? 'ssl';
    
    // If credentials are empty, try to load from .env file directly
    if (empty($mailUser) || empty($mailPass)) {
        $envPath = __DIR__ . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    $value = trim($value, '"\'');
                    if ($key === 'MAIL_USERNAME' && empty($mailUser)) $mailUser = $value;
                    if ($key === 'MAIL_PASSWORD' && empty($mailPass)) $mailPass = $value;
                    if ($key === 'MAIL_HOST' && empty($mailHost)) $mailHost = $value;
                    if ($key === 'MAIL_PORT' && empty($mailPort)) $mailPort = (int)$value;
                    if ($key === 'MAIL_FROM_ADDRESS' && empty($mailFrom)) $mailFrom = $value;
                    if ($key === 'MAIL_FROM_NAME' && empty($mailFromName)) $mailFromName = $value;
                    if ($key === 'MAIL_ENCRYPTION' && empty($mailEncryption)) $mailEncryption = $value;
                }
            }
        }
    }
    
    if (empty($mailUser) || empty($mailPass) || empty($email)) {
        error_log('Login success email failed: Email credentials or recipient email not set. MAIL_USERNAME: ' . (!empty($mailUser) ? 'set' : 'empty') . ', MAIL_PASSWORD: ' . (!empty($mailPass) ? 'set' : 'empty') . ', Recipient: ' . ($email ?: 'empty'));
        return false;
    }
    
    $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/login.php?reset=1';
    
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $mailHost;
            $mail->SMTPAuth = true;
            $mail->Username = $mailUser;
            $mail->Password = $mailPass;
            
            if ($mailPort === 587 || strtolower($mailEncryption) === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }
            $mail->Port = $mailPort;
            $mail->CharSet = 'UTF-8';
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
            
            $mail->setFrom($mailFrom, $mailFromName);
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'AlerTara QC - Successful Login Notification';
            $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                    .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; }
                    .header { background: linear-gradient(135deg, #4c8a89 0%, #2a5a59 100%); color: white; padding: 30px 20px; text-align: center; }
                    .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
                    .body-content { padding: 30px 20px; background: #ffffff; }
                    .body-content p { margin: 0 0 15px 0; color: #333; font-size: 14px; }
                    .info-box { background: #f0f9ff; border-left: 4px solid #4c8a89; padding: 15px; margin: 20px 0; border-radius: 4px; }
                    .warning-box { background: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; border-radius: 4px; }
                    .btn { display: inline-block; padding: 12px 24px; background: #4c8a89; color: white; text-decoration: none; border-radius: 6px; margin-top: 10px; }
                    .footer { background: #f5f5f5; padding: 15px 20px; text-align: center; color: #999; font-size: 11px; }
                </style>
            </head>
            <body>
                <div class='email-container'>
                    <div class='header'>
                        <h1>Successful Login</h1>
                    </div>
                    <div class='body-content'>
                        <p>Hello,</p>
                        <p>This is to notify you that a successful login was made to your AlerTara QC account.</p>
                        <div class='info-box'>
                            <p><strong>Login Details:</strong></p>
                            <p>Username: <strong>{$username}</strong></p>
                            <p>Login Time: <strong>{$loginTime}</strong></p>
                            <p>IP Address: <strong>{$ipAddress}</strong></p>
                        </div>
                        <div class='warning-box'>
                            <p><strong>Security Notice:</strong></p>
                            <p>If you did not log in, please secure your account immediately by resetting your password.</p>
                            <a href='{$resetLink}' class='btn'>Reset Your Password</a>
                        </div>
                    </div>
                    <div class='footer'>
                        <p>This is an automated security message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->send();
            error_log("Login success email sent successfully to {$email} for user {$username}");
            return true;
        } catch (Exception $e) {
            $errorMsg = 'Login success email send failed: ' . ($mail->ErrorInfo ?? $e->getMessage());
            error_log($errorMsg);
            error_log('PHPMailer Exception details: ' . $e->getMessage());
            error_log('Email config - Host: ' . $mailHost . ', Port: ' . $mailPort . ', User: ' . ($mailUser ? 'set' : 'empty') . ', Encryption: ' . $mailEncryption);
            return false;
        }
    }
    
    // Fallback to native mail()
    $subject = 'AlerTara QC - Successful Login Notification';
    $message = "A successful login was made to your account {$username} at {$loginTime} from IP {$ipAddress}. If you did not log in, please reset your password: {$resetLink}";
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: {$mailFromName} <{$mailFrom}>\r\n";
    return mail($email, $subject, $message, $headers);
}

// Get client IP address
function logLoginHistory(PDO $pdo, $userId, $username, $status, $ipAddress = null) {
    try {
        // Ensure login_history table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            username VARCHAR(100) NOT NULL,
            login_time DATETIME NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'Success',
            logout_time DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_username (username),
            INDEX idx_login_time (login_time),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Insert login history
        $stmt = $pdo->prepare('INSERT INTO login_history (user_id, username, login_time, ip_address, status) VALUES (:user_id, :username, NOW(), :ip_address, :status)');
        $stmt->execute([
            ':user_id' => $userId,
            ':username' => $username,
            ':ip_address' => $ipAddress,
            ':status' => $status
        ]);
        
        // Get the inserted login history ID
        $loginHistoryId = $pdo->lastInsertId();
        
        // Create notification for successful logins only
        if ($status === 'Success') {
            try {
                require_once __DIR__ . '/api/notifications_schema.php';
                ensureNotificationsTable($pdo);
                
                // Format login time
                $loginTime = date('M j, Y g:i:s A');
                $message = "User {$username} logged in from IP {$ipAddress} at {$loginTime}";
                
                // Create notification for the user who logged in (user_id = userId)
                try {
                    $notifStmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (:user_id, :type, :title, :message, :link, NOW())');
                    $notifStmt->execute([
                        ':user_id' => $userId,
                        ':type' => 'login',
                        ':title' => 'Login Successful',
                        ':message' => $message,
                        ':link' => 'login-history.php'
                    ]);
                    error_log("Login notification created successfully for user_id: {$userId}");
                } catch (PDOException $notifError) {
                    error_log('Failed to create user login notification: ' . $notifError->getMessage());
                    error_log('Notification insert params - user_id: ' . $userId . ', type: login, title: Login Successful');
                    // Continue to try admin notification
                }
                
                // Also create a notification for admins (user_id = NULL means visible to all admins)
                // This will be filtered in the API based on role
                try {
                    $adminNotifStmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (NULL, :type, :title, :message, :link, NOW())');
                    $adminNotifStmt->execute([
                        ':type' => 'login',
                        ':title' => 'User Login',
                        ':message' => $message,
                        ':link' => 'login-history.php'
                    ]);
                    error_log("Admin login notification created successfully");
                } catch (PDOException $adminNotifError) {
                    error_log('Failed to create admin login notification: ' . $adminNotifError->getMessage());
                    error_log('Admin notification insert params - user_id: NULL, type: login, title: User Login');
                }
            } catch (PDOException $notifTableError) {
                error_log('Failed to create notifications table or insert notifications: ' . $notifTableError->getMessage());
                error_log('Notifications table creation error details: ' . print_r($notifTableError->errorInfo, true));
            }
        }
        
        return $loginHistoryId;
    } catch (PDOException $e) {
        // Log error but don't break login flow
        error_log('Failed to log login history: ' . $e->getMessage());
        return null;
    }
}

function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // Handle IPv6 localhost
    if ($ip === '::1') {
        return '127.0.0.1';
    }
    
    // Check for forwarded IP (behind proxy)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($forwarded[0]);
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    
    return $ip;
}

// Database-backed authentication
$attemptsRemaining = null;
$otpPrompt = null;
$showOtpForm = false;
$otpEmailMasked = null;
$otpExpiresAtText = null;
$otpResendCooldown = 0;

if (isset($_GET['cancel_otp'])) {
    unset($_SESSION['pending_login']);
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['pending_login'])) {
    $pendingLoginSession = $_SESSION['pending_login'];
    $pendingExpiresAt = strtotime((string) ($pendingLoginSession['expires_at'] ?? ''));

    if ($pendingExpiresAt === false || $pendingExpiresAt < time()) {
        unset($_SESSION['pending_login']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $error = 'OTP has expired. Please log in again.';
        }
    } else {
        $showOtpForm = true;
        $otpEmailMasked = maskEmailAddress((string) ($pendingLoginSession['email'] ?? ''));
        $otpExpiresAtText = date('g:i A', $pendingExpiresAt);
        $otpResendCooldown = getOtpResendCooldownSeconds($pendingLoginSession);
        $otpPrompt = buildOtpPromptMessage(false, $pendingLoginSession['email'] ?? null, true);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend_login_otp'])) {
        $pendingLogin = $_SESSION['pending_login'] ?? null;

        if (!$pendingLogin) {
            $error = 'No pending login verification found. Please log in again.';
            $showOtpForm = false;
        } elseif (strtotime((string) ($pendingLogin['expires_at'] ?? '')) < time()) {
            unset($_SESSION['pending_login']);
            $error = 'OTP has expired. Please log in again.';
            $showOtpForm = false;
        } else {
            $otpResendCooldown = getOtpResendCooldownSeconds($pendingLogin);
            if ($otpResendCooldown > 0) {
                $error = "Please wait {$otpResendCooldown} second(s) before requesting another OTP.";
                $showOtpForm = true;
                $otpEmailMasked = maskEmailAddress((string) ($pendingLogin['email'] ?? ''));
                $otpExpiresAtText = date('g:i A', strtotime((string) $pendingLogin['expires_at']));
                $otpPrompt = buildOtpPromptMessage(false, $pendingLogin['email'] ?? null, true);
            } else {
            $otp = generateLoginOTP();
            $_SESSION['pending_login']['otp'] = $otp;
            $_SESSION['pending_login']['expires_at'] = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $_SESSION['pending_login']['attempts'] = 0;
            $_SESSION['pending_login']['last_resend_at'] = time();

            $otpSent = sendLoginOTPEmail(
                $pendingLogin['email'] ?? '',
                $pendingLogin['display_name'] ?? $pendingLogin['username'] ?? 'User',
                $otp
            );

            if ($otpSent) {
                $otpPrompt = buildOtpPromptMessage(true, $pendingLogin['email'] ?? null);
                $otpEmailMasked = maskEmailAddress((string) ($pendingLogin['email'] ?? ''));
                $otpExpiresAtText = date('g:i A', strtotime((string) $_SESSION['pending_login']['expires_at']));
                $otpResendCooldown = 60;
                $showOtpForm = true;
            } else {
                $error = 'Failed to resend login OTP. Please try again later.';
                $showOtpForm = true;
            }
            }
        }
    }

    $isOtpVerification = isset($_POST['verify_login_otp']) && !isset($_POST['resend_login_otp']);

    if ($isOtpVerification && !isset($error)) {
        $enteredOtp = trim($_POST['login_otp'] ?? '');
        $pendingLogin = $_SESSION['pending_login'] ?? null;

        if (!$pendingLogin) {
            $error = 'No pending login verification found. Please log in again.';
            $showOtpForm = false;
        } elseif ($enteredOtp === '' || !preg_match('/^\d{6}$/', $enteredOtp)) {
            $error = 'Please enter a valid 6-digit OTP.';
            $showOtpForm = true;
        } elseif (strtotime($pendingLogin['expires_at']) < time()) {
            unset($_SESSION['pending_login']);
            $error = 'OTP has expired. Please log in again.';
            $showOtpForm = false;
        } elseif (($pendingLogin['attempts'] ?? 0) >= 5) {
            unset($_SESSION['pending_login']);
            $error = 'Too many failed OTP attempts. Please log in again.';
            $showOtpForm = false;
        } elseif (!hash_equals((string)$pendingLogin['otp'], $enteredOtp)) {
            $_SESSION['pending_login']['attempts'] = (int)($pendingLogin['attempts'] ?? 0) + 1;
            $remainingOtpAttempts = max(0, 5 - (int)$_SESSION['pending_login']['attempts']);
            if ($remainingOtpAttempts === 0) {
                unset($_SESSION['pending_login']);
                $error = 'Too many failed OTP attempts. Please log in again.';
                $showOtpForm = false;
            } else {
                $error = "Invalid OTP. {$remainingOtpAttempts} attempt(s) remaining.";
                $showOtpForm = true;
            }
        } else {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['username'] = $pendingLogin['display_name'] ?: $pendingLogin['username'];
            $_SESSION['user_role'] = $pendingLogin['role'] ?? 'Admin';
            $_SESSION['user_id'] = $pendingLogin['user_id'];

            $ipAddress = $pendingLogin['ip_address'] ?? getClientIP();
            logLoginHistory($pdo, $pendingLogin['user_id'], $pendingLogin['username'], 'Success', $ipAddress);

            if (!empty($pendingLogin['email'])) {
                $dateTime = new DateTime('now', new DateTimeZone('Asia/Manila'));
                $loginTime = $dateTime->format('F j, Y \a\t g:i A');
                sendLoginSuccessEmail($pendingLogin['email'], $pendingLogin['username'], $loginTime, $ipAddress);
            }

            unset($_SESSION['pending_login']);
            header('Location: index.php');
            exit;
        }
    } elseif (!isset($_POST['resend_login_otp']) && !isset($error)) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if ($email === '' || $password === '') {
            $error = 'Please enter both email and password';
        } else {
            // Start a fresh login flow
            unset($_SESSION['pending_login']);
            $stmt = $pdo->prepare('SELECT id, username, password_hash, full_name, status, email, role, failed_attempts, last_failed_at, locked_until FROM admins WHERE email = :e LIMIT 1');
            $stmt->execute([':e' => $email]);
            $admin = $stmt->fetch();
        
            if ($admin) {
                $now = date('Y-m-d H:i:s');
                $lockedUntil = $admin['locked_until'] ?? null;
            
            // Check if account is locked
            if ($lockedUntil && strtotime($lockedUntil) > time()) {
                // Ensure locked_until is 30 minutes from last_failed_at (recalculate if needed)
                $lastFailed = $admin['last_failed_at'] ?? null;
                if ($lastFailed) {
                    $correctLockedUntil = date('Y-m-d H:i:s', strtotime($lastFailed . ' +30 minutes'));
                    // Update if the stored value is incorrect
                    if ($lockedUntil !== $correctLockedUntil) {
                        $stmt = $pdo->prepare('UPDATE admins SET locked_until = :locked_until WHERE id = :id');
                        $stmt->execute([
                            ':locked_until' => $correctLockedUntil,
                            ':id' => $admin['id']
                        ]);
                        $lockedUntil = $correctLockedUntil;
                    }
                }
                $error = "Your account has been temporarily locked due to multiple failed login attempts. It will be unlocked after 30 minutes.";
            } else {
                // Unlock account if lock period has passed
                if ($lockedUntil && strtotime($lockedUntil) <= time()) {
                    $stmt = $pdo->prepare('UPDATE admins SET failed_attempts = 0, locked_until = NULL, last_failed_at = NULL WHERE id = :id');
                    $stmt->execute([':id' => $admin['id']]);
                    $admin['failed_attempts'] = 0;
                    $admin['locked_until'] = null;
                }
                
                // Check if account is active
                if (isset($admin['status']) && $admin['status'] !== 'Active') {
                    $error = 'Your account has been disabled. Please contact an administrator.';
                } else {
                    $failedAttempts = (int)($admin['failed_attempts'] ?? 0);
                    
                    // Check password
                    $passwordCorrect = password_verify($password, $admin['password_hash']);
                    
                    // If account has 3 or more failed attempts, lock it even if password is correct
                    if ($failedAttempts >= 3) {
                        // If account is already locked, use existing locked_until (don't extend it)
                        if ($lockedUntil && strtotime($lockedUntil) > time()) {
                            // Account is already locked, don't update anything
                            // Log locked login attempt
                            $ipAddress = getClientIP();
                            logLoginHistory($pdo, $admin['id'], $admin['username'], 'Locked', $ipAddress);
                            $error = "Your account has been temporarily locked due to multiple failed login attempts. It will be unlocked after 30 minutes.";
                        } else {
                            // Calculate lockout time as 30 minutes from last_failed_at (or now if last_failed_at is null)
                            $lastFailed = $admin['last_failed_at'] ?? $now;
                            $lockedUntil = date('Y-m-d H:i:s', strtotime($lastFailed . ' +30 minutes'));
                            
                            $stmt = $pdo->prepare('UPDATE admins SET locked_until = :locked_until, last_failed_at = :last_failed WHERE id = :id');
                            $stmt->execute([
                                ':locked_until' => $lockedUntil,
                                ':last_failed' => $now,
                                ':id' => $admin['id']
                            ]);
                            
                            // Log locked login attempt
                            $ipAddress = getClientIP();
                            logLoginHistory($pdo, $admin['id'], $admin['username'], 'Locked', $ipAddress);
                            
                            // Send lockout email
                            if (!empty($admin['email'])) {
                                sendAccountLockEmail($admin['email'], $admin['username'], $lockedUntil);
                            }
                            
                            $error = "Your account has been temporarily locked due to multiple failed login attempts. It will be unlocked after 30 minutes.";
                        }
                    } elseif ($passwordCorrect) {
                        // Successful login - reset failed attempts
                        $stmt = $pdo->prepare('UPDATE admins SET failed_attempts = 0, locked_until = NULL, last_failed_at = NULL WHERE id = :id');
                        $stmt->execute([':id' => $admin['id']]);

                        if (empty($admin['email'])) {
                            $error = 'This account has no registered email. Please contact an administrator.';
                        } else {
                            $otp = generateLoginOTP();
                            $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                            $ipAddress = getClientIP();

                            $_SESSION['pending_login'] = [
                                'user_id' => $admin['id'],
                                'username' => $admin['username'],
                                'display_name' => $admin['full_name'] ?: $admin['username'],
                                'role' => $admin['role'] ?? 'Admin',
                                'email' => $admin['email'],
                                'otp' => $otp,
                                'expires_at' => $expiresAt,
                                'attempts' => 0,
                                'ip_address' => $ipAddress,
                                'sent_at' => time(),
                                'last_resend_at' => time(),
                            ];

                            $otpSent = sendLoginOTPEmail($admin['email'], $admin['full_name'] ?: $admin['username'], $otp);
                            if ($otpSent) {
                                $otpPrompt = buildOtpPromptMessage(false, $admin['email']);
                                $otpEmailMasked = maskEmailAddress((string) $admin['email']);
                                $otpExpiresAtText = date('g:i A', strtotime($expiresAt));
                                $otpResendCooldown = 60;
                                $showOtpForm = true;
                            } else {
                                unset($_SESSION['pending_login']);
                                $error = 'Failed to send login OTP. Please contact administrator or try again later.';
                            }
                        }
                    } else {
                        // Wrong password - increment failed attempts
                        $newFailedAttempts = $failedAttempts + 1;
                        $lockedUntil = null;
                        $status = 'Failed';
                        
                        // Lock account after 3 failed attempts
                        if ($newFailedAttempts >= 3) {
                            // Calculate lockout time as 30 minutes from the current failed attempt (now)
                            $lockedUntil = date('Y-m-d H:i:s', strtotime($now . ' +30 minutes'));
                            $status = 'Locked';
                            
                            // Send lockout email
                            if (!empty($admin['email'])) {
                                sendAccountLockEmail($admin['email'], $admin['username'], $lockedUntil);
                            }
                        }
                        
                        // Log failed login attempt
                        $ipAddress = getClientIP();
                        logLoginHistory($pdo, $admin['id'], $admin['username'], $status, $ipAddress);
                        
                        $stmt = $pdo->prepare('UPDATE admins SET failed_attempts = :attempts, last_failed_at = :last_failed, locked_until = :locked_until WHERE id = :id');
                        $stmt->execute([
                            ':attempts' => $newFailedAttempts,
                            ':last_failed' => $now,
                            ':locked_until' => $lockedUntil,
                            ':id' => $admin['id']
                        ]);
                        
                        $attemptsRemaining = max(0, 3 - $newFailedAttempts);
                        if ($newFailedAttempts >= 3) {
                            $error = "Your account has been temporarily locked due to multiple failed login attempts. It will be unlocked after 30 minutes.";
                        } else {
                            $error = 'Invalid email or password';
                        }
                    }
                }
            }
            } else {
                $error = 'Invalid email or password';
            }
        }
    }
}

if ($showOtpForm && empty($otpPrompt) && isset($_SESSION['pending_login'])) {
    $pendingLoginSession = $_SESSION['pending_login'];
    $otpEmailMasked = maskEmailAddress((string) ($pendingLoginSession['email'] ?? ''));
    $otpExpiresAtText = date('g:i A', strtotime((string) ($pendingLoginSession['expires_at'] ?? '')));
    $otpResendCooldown = getOtpResendCooldownSeconds($pendingLoginSession);
    $otpPrompt = buildOtpPromptMessage(false, $pendingLoginSession['email'] ?? null, true);
}

// Get attempts remaining for display (if user exists and not already set)
if (!isset($attemptsRemaining) && isset($_POST['email'])) {
    $email = trim($_POST['email'] ?? '');
    if (!empty($email)) {
        $stmt = $pdo->prepare('SELECT failed_attempts, locked_until FROM admins WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $admin = $stmt->fetch();
        if ($admin) {
            $lockedUntil = $admin['locked_until'] ?? null;
            if (!$lockedUntil || strtotime($lockedUntil) <= time()) {
                $failedAttempts = (int)($admin['failed_attempts'] ?? 0);
                $attemptsRemaining = max(0, 3 - $failedAttempts);
            } else {
                // Account is locked
                $attemptsRemaining = 0;
            }
        }
    }
}

// Check if already logged in, redirect to index.php
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$autoOpenLogin = !empty($showOtpForm) || isset($error) || isset($_SESSION['registration_success']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>AlerTara QC — Community Policing &amp; Surveillance</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --teal: #4c8a89;
            --teal-bright: #5ba8a6;
            --teal-deep: #2a5a59;
            --navy: #1c2541;
            --ink: #0b132b;
            --mist: #d7eceb;
            --sand: #e8f0ef;
            --text: #f4f7f7;
            --muted: rgba(244, 247, 247, 0.72);
            --radius: 14px;
            --font-display: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --font-body: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html {
            scroll-behavior: smooth;
            scrollbar-width: thin;
            scrollbar-color: var(--teal) var(--ink);
        }

        body {
            font-family: var(--font-body);
            color: var(--text);
            background: var(--ink);
            overflow-x: hidden;
            line-height: 1.6;
        }

        a { color: inherit; text-decoration: none; }
        img { max-width: 100%; display: block; }

        /* —— Nav —— */
        .site-nav {
            position: fixed;
            inset: 0 0 auto 0;
            z-index: 900;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem clamp(1rem, 4vw, 2.5rem);
            background: rgba(11, 19, 43, 0.55);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid transparent;
            transition: background 0.35s ease, border-color 0.35s ease, padding 0.35s ease;
        }
        .site-nav.scrolled {
            background: rgba(11, 19, 43, 0.92);
            border-bottom-color: rgba(76, 138, 137, 0.28);
            padding-top: 0.65rem;
            padding-bottom: 0.65rem;
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: -0.02em;
            min-width: 0;
        }
        .nav-brand img { height: 64px; width: auto; flex-shrink: 0; }
        .nav-brand-title {
            color: #ffffff;
            font-size: clamp(1rem, 2vw, 1.25rem);
            font-weight: 700;
            line-height: 1.25;
            letter-spacing: -0.01em;
            max-width: 16rem;
        }
        .nav-brand > span:not(.nav-brand-title) { color: var(--teal-bright); }
        .nav-links {
            display: flex;
            align-items: center;
            gap: clamp(0.6rem, 1.5vw, 1.4rem);
            list-style: none;
            font-family: var(--font-display);
            font-size: 0.88rem;
            font-weight: 500;
        }
        .nav-links a {
            color: var(--muted);
            transition: color 0.2s ease;
        }
        .nav-links a:hover,
        .nav-links a.active { color: #fff; }
        .nav-cta {
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.65rem 1.2rem;
            border: none;
            border-radius: 999px;
            cursor: pointer;
            color: #fff;
            background: linear-gradient(135deg, var(--teal), var(--teal-deep));
            box-shadow: 0 10px 28px -12px rgba(76, 138, 137, 0.85);
            transition: transform 0.2s ease, filter 0.2s ease;
        }
        .nav-cta:hover { transform: translateY(-1px); filter: brightness(1.06); }
        .nav-toggle {
            display: none;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.1rem;
        }

        /* —— Buttons —— */
        .btn {
            font-family: var(--font-display);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.95rem 1.55rem;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.98rem;
            color: #fff;
            background: linear-gradient(135deg, var(--teal), var(--teal-deep));
            box-shadow: 0 14px 34px -16px rgba(76, 138, 137, 0.9);
            transition: transform 0.2s ease, filter 0.2s ease;
        }
        .btn:hover { transform: translateY(-2px); filter: brightness(1.05); }
        .btn-ghost {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.28);
            box-shadow: none;
            color: #fff;
        }
        .btn-ghost:hover { background: rgba(255,255,255,0.06); border-color: var(--teal); }
        .btn-secondary {
            background: transparent;
            color: #fff;
            border: 1px solid rgba(255,255,255,0.25);
            box-shadow: none;
        }
        .btn-secondary:hover { background: rgba(255,255,255,0.08); }
        .btn-secondary:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }

        /* —— Progressive reveal —— */
        .reveal {
            opacity: 0;
            transform: translateY(36px);
            transition: opacity 0.8s cubic-bezier(0.22, 1, 0.36, 1), transform 0.8s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .reveal.in {
            opacity: 1;
            transform: translateY(0);
        }
        .reveal-delay-1 { transition-delay: 0.12s; }
        .reveal-delay-2 { transition-delay: 0.24s; }
        .reveal-delay-3 { transition-delay: 0.36s; }

        /* —— Hero —— */
        .hero {
            position: relative;
            min-height: 100vh;
            display: grid;
            align-items: end;
            padding: 7rem clamp(1.25rem, 5vw, 4rem) 4rem;
            overflow: hidden;
            isolation: isolate;
        }
        .hero-bg {
            position: absolute;
            inset: 0;
            z-index: -2;
            background:
                radial-gradient(ellipse 80% 55% at 70% 20%, rgba(76, 138, 137, 0.35), transparent 55%),
                radial-gradient(ellipse 60% 50% at 15% 80%, rgba(42, 90, 89, 0.4), transparent 50%),
                linear-gradient(165deg, #0b132b 0%, #142038 42%, #1c2541 100%);
        }
        .hero-bg::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(76, 138, 137, 0.07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(76, 138, 137, 0.07) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: radial-gradient(ellipse 70% 60% at 60% 40%, #000 20%, transparent 75%);
            animation: gridDrift 28s linear infinite;
        }
        .hero-scan {
            position: absolute;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            background: linear-gradient(180deg, transparent 0%, rgba(91, 168, 166, 0.12) 50%, transparent 100%);
            height: 28%;
            animation: scanSweep 7s ease-in-out infinite;
        }
        .hero-radar {
            position: absolute;
            right: clamp(-4rem, -2vw, 2rem);
            top: 18%;
            width: min(52vw, 520px);
            aspect-ratio: 1;
            border-radius: 50%;
            border: 1px solid rgba(76, 138, 137, 0.28);
            z-index: -1;
            pointer-events: none;
        }
        .hero-radar::before,
        .hero-radar::after {
            content: "";
            position: absolute;
            inset: 18%;
            border-radius: 50%;
            border: 1px solid rgba(76, 138, 137, 0.22);
        }
        .hero-radar::after { inset: 36%; }
        .radar-beam {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: conic-gradient(from 0deg, transparent 0deg, rgba(91, 168, 166, 0.35) 40deg, transparent 70deg);
            animation: radarSpin 5.5s linear infinite;
            opacity: 0.55;
        }
        @keyframes radarSpin { to { transform: rotate(360deg); } }
        @keyframes scanSweep {
            0%, 100% { transform: translateY(-20%); opacity: 0.2; }
            50% { transform: translateY(220%); opacity: 0.65; }
        }
        @keyframes gridDrift {
            from { transform: translateY(0); }
            to { transform: translateY(56px); }
        }
        .hero-content {
            max-width: 720px;
            display: grid;
            gap: 1.35rem;
        }
        .brand-mark {
            font-family: var(--font-display);
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: clamp(2.8rem, 8vw, 5.2rem);
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1;
            animation: brandRise 1.1s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .brand-mark img {
            height: 0.95em;
            width: auto;
            filter: drop-shadow(0 12px 28px rgba(76, 138, 137, 0.45));
        }
        .brand-mark .ler { color: var(--teal-bright); }
        .brand-mark .rest { color: #fff; }
        @keyframes brandRise {
            from { opacity: 0; transform: translateY(28px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .hero h1 {
            font-family: var(--font-display);
            font-size: clamp(1.55rem, 3.6vw, 2.45rem);
            font-weight: 600;
            letter-spacing: -0.025em;
            line-height: 1.2;
            max-width: 16ch;
            color: var(--muted);
            animation: brandRise 1.1s 0.15s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .hero p.lead {
            font-size: clamp(1.05rem, 2vw, 1.25rem);
            color: var(--muted);
            max-width: 44ch;
            animation: brandRise 1.1s 0.28s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .hero-ctas {
            display: flex;
            flex-wrap: wrap;
            gap: 0.85rem;
            margin-top: 0.5rem;
            animation: brandRise 1.1s 0.4s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .scroll-cue {
            position: absolute;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border: 1px solid rgba(91, 168, 166, 0.45);
            border-radius: 50%;
            color: var(--teal-bright);
            font-size: 1.15rem;
            animation: cueBounce 2.2s ease-in-out infinite;
            text-decoration: none;
        }
        @keyframes cueBounce {
            0%, 100% { opacity: 0.55; transform: translate(-50%, 0); }
            50% { opacity: 1; transform: translate(-50%, 8px); }
        }

        /* —— Sections —— */
        .section {
            position: relative;
            padding: clamp(4rem, 9vw, 7rem) clamp(1.25rem, 5vw, 4rem);
        }
        .section-inner { max-width: 1100px; margin: 0 auto; }
        .section-label {
            font-family: var(--font-display);
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--teal-bright);
            margin-bottom: 0.85rem;
        }
        .section h2 {
            font-family: var(--font-display);
            font-size: clamp(1.85rem, 4vw, 2.75rem);
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1.15;
            margin-bottom: 1.1rem;
            max-width: 18ch;
        }
        .section .sub {
            font-size: 1.12rem;
            color: var(--muted);
            max-width: 52ch;
            margin-bottom: 2.25rem;
        }

        .about-section {
            background:
                linear-gradient(180deg, rgba(28, 37, 65, 0.95), rgba(11, 19, 43, 1)),
                radial-gradient(ellipse at 0% 50%, rgba(76, 138, 137, 0.18), transparent 55%);
        }
        .about-copy {
            font-size: 1.15rem;
            color: var(--muted);
            max-width: 62ch;
        }

        .who-section {
            background:
                radial-gradient(ellipse at 50% 0%, rgba(76, 138, 137, 0.2), transparent 55%),
                linear-gradient(180deg, #0b132b, #121c34 45%, #0b132b);
        }
        .role-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.35rem;
            margin-top: 2rem;
        }
        .role-card {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1.6rem 1.45rem 1.45rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: linear-gradient(160deg, rgba(28, 37, 65, 0.9), rgba(15, 24, 44, 0.95));
            box-shadow: 0 18px 40px -28px rgba(0, 0, 0, 0.7);
            scroll-margin-top: 5.5rem;
            transition: transform 0.25s ease, border-color 0.25s ease;
        }
        .role-card:hover {
            transform: translateY(-4px);
            border-color: rgba(76, 138, 137, 0.45);
        }
        .role-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: rgba(76, 138, 137, 0.18);
            color: var(--teal-bright);
            font-size: 1.25rem;
        }
        .role-card h3 {
            font-family: var(--font-display);
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .role-card > p {
            color: var(--muted);
            font-size: 0.98rem;
            flex: 1;
        }
        .role-list {
            list-style: none;
            display: grid;
            gap: 0.55rem;
            margin: 0.25rem 0 0.5rem;
        }
        .role-list li {
            position: relative;
            padding-left: 1.15rem;
            color: rgba(244, 247, 247, 0.82);
            font-size: 0.92rem;
            line-height: 1.45;
        }
        .role-list li::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0.55em;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--teal-bright);
        }
        .role-card .btn {
            width: 100%;
            margin-top: auto;
            border-radius: 12px;
            text-decoration: none;
        }

        .importance-section {
            background:
                radial-gradient(ellipse at 100% 0%, rgba(76, 138, 137, 0.22), transparent 45%),
                linear-gradient(160deg, #0f1a30, #13233d 60%, #0b132b);
        }
        .importance-list {
            display: grid;
            gap: 1.75rem;
            counter-reset: importance;
            list-style: none;
            margin-top: 2rem;
        }
        .importance-list li {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 1.25rem;
            align-items: start;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .importance-list li::before {
            counter-increment: importance;
            content: counter(importance, decimal-leading-zero);
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 1.4rem;
            color: var(--teal-bright);
            line-height: 1;
        }
        .importance-list h3 {
            font-family: var(--font-display);
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
        }
        .importance-list p { color: var(--muted); max-width: 58ch; }

        .mvv-section {
            background: linear-gradient(180deg, #0b132b, #152540 50%, #0b132b);
        }
        .mvv-stack {
            display: grid;
            gap: 3.5rem;
        }
        .mvv-block {
            display: grid;
            gap: 0.75rem;
            padding-left: 1.25rem;
            border-left: 3px solid var(--teal);
            scroll-margin-top: 5.5rem;
        }
        .mvv-block h3 {
            font-family: var(--font-display);
            font-size: clamp(1.4rem, 3vw, 1.85rem);
            font-weight: 700;
        }
        .mvv-block p {
            color: var(--muted);
            font-size: 1.08rem;
            max-width: 58ch;
        }

        .faq-section {
            background:
                radial-gradient(ellipse at 20% 100%, rgba(42, 90, 89, 0.25), transparent 50%),
                #101a32;
        }
        .faq-list { display: grid; gap: 0.75rem; margin-top: 1.5rem; }
        details.faq {
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 1rem 0;
        }
        details.faq summary {
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 1.05rem;
            cursor: pointer;
            list-style: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }
        details.faq summary::-webkit-details-marker { display: none; }
        details.faq summary::after {
            content: "+";
            font-size: 1.4rem;
            color: var(--teal-bright);
            transition: transform 0.25s ease;
        }
        details.faq[open] summary::after { transform: rotate(45deg); }
        details.faq p {
            margin-top: 0.85rem;
            color: var(--muted);
            max-width: 62ch;
            animation: faqOpen 0.35s ease;
        }
        @keyframes faqOpen {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .contact-section {
            background: linear-gradient(165deg, #122038, #1c2541 55%, #0b132b);
        }
        .contact-info { display: grid; gap: 1.25rem; color: var(--muted); max-width: 42rem; }
        .contact-info strong {
            display: block;
            font-family: var(--font-display);
            color: #fff;
            font-weight: 600;
            margin-bottom: 0.2rem;
        }
        .field input {
            width: 100%;
            padding: 0.95rem 1.1rem;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.16);
            background: rgba(255,255,255,0.06);
            color: #fff;
            font: inherit;
            font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .field input:focus {
            outline: none;
            border-color: var(--teal);
            box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.25);
        }

        /* —— Footer —— */
        .site-footer {
            padding: 3rem clamp(1.25rem, 5vw, 4rem) 2rem;
            background: #080e20;
            border-top: 1px solid rgba(76, 138, 137, 0.2);
        }
        .footer-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            gap: 2rem;
        }
        .footer-brand {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 1.25rem;
        }
        .footer-brand span { color: var(--teal-bright); }
        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem 1.75rem;
            font-family: var(--font-display);
            font-size: 0.9rem;
        }
        .footer-links button,
        .footer-links a {
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font: inherit;
            padding: 0;
            transition: color 0.2s ease;
        }
        .footer-links button:hover,
        .footer-links a:hover { color: #fff; }
        .footer-copy {
            font-size: 0.85rem;
            color: rgba(244, 247, 247, 0.45);
        }

        /* —— Progress bar —— */
        .progress-bar {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            width: 0%;
            z-index: 1000;
            background: linear-gradient(90deg, var(--teal-bright), var(--teal));
            transition: width 0.1s linear;
        }

        /* —— Modals —— */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            background: rgba(5, 10, 24, 0.72);
            backdrop-filter: blur(6px);
            align-items: center;
            justify-content: center;
            padding: 1rem;
            animation: fadeIn 0.3s ease;
        }
        .modal-overlay.open { display: flex; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(28px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-panel {
            width: min(480px, 100%);
            max-height: 90vh;
            overflow-y: auto;
            background: linear-gradient(155deg, #1c2541, #152238 55%, #122033);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius);
            box-shadow: 0 28px 60px -20px rgba(0,0,0,0.65);
            padding: clamp(1.5rem, 4vw, 2.25rem);
            animation: slideUp 0.4s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .modal-panel.wide { width: min(640px, 100%); }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.35rem;
            padding-bottom: 0.9rem;
            border-bottom: 1px solid rgba(255,255,255,0.12);
        }
        .modal-header h2 {
            font-family: var(--font-display);
            font-size: 1.45rem;
            font-weight: 650;
            margin: 0;
        }
        .modal-close {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.75);
            font-size: 1.5rem;
            cursor: pointer;
            line-height: 1;
        }
        .modal-close:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .field { display: grid; gap: 0.4rem; margin-bottom: 1rem; }
        .field label {
            font-family: var(--font-display);
            font-size: 0.92rem;
            color: rgba(255,255,255,0.78);
            font-weight: 500;
        }
        .login-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 0.25rem 0 1rem;
            font-family: var(--font-display);
            font-size: 0.9rem;
        }
        .login-actions a { color: var(--teal-bright); }
        .login-actions a:hover { text-decoration: underline; }
        .button-group { display: flex; gap: 0.75rem; }
        .button-group .btn { flex: 1; border-radius: 12px; }
        .alert {
            font-size: 0.9rem;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .alert-error {
            color: #fecaca;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.25);
        }
        .alert-success {
            color: #a7f3d0;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.25);
        }
        .alert-warn {
            color: #fde68a;
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.25);
        }
        .otp-form { display: grid; gap: 1.25rem; }
        .otp-meta { margin: 0; font-size: 0.9rem; color: rgba(255,255,255,0.72); }
        .otp-meta strong { color: #fff; }
        .otp-actions { justify-content: center; }
        .actions a { color: var(--teal-bright); font-family: var(--font-display); font-size: 0.9rem; }
        .actions a:hover { text-decoration: underline; }
        .legal-body {
            color: var(--muted);
            font-size: 0.98rem;
            display: grid;
            gap: 0.9rem;
        }
        .legal-body h3 {
            font-family: var(--font-display);
            color: #fff;
            font-size: 1.05rem;
            margin-top: 0.5rem;
        }

        .success-modal {
            display: none;
            position: fixed;
            z-index: 2500;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(6px);
            align-items: center;
            justify-content: center;
        }
        .success-modal.active { display: flex; }
        .success-modal-content {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border-radius: var(--radius);
            padding: 2.5rem;
            max-width: 480px;
            width: 90%;
            text-align: center;
            animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .success-icon-wrapper {
            width: 88px;
            height: 88px;
            margin: 0 auto 1.25rem;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 2.5rem;
        }
        .success-modal-content h2 {
            color: var(--navy);
            font-family: var(--font-display);
            margin-bottom: 0.75rem;
        }
        .success-modal-content p { color: #575757; margin-bottom: 1.5rem; }
        .success-modal-actions .btn { min-width: 140px; color: #fff; }

        @media (max-width: 1100px) {
            .role-grid { grid-template-columns: repeat(2, 1fr); }
        }

            @media (max-width: 860px) {
            .nav-toggle { display: grid; place-items: center; }
            .site-nav {
                flex-wrap: wrap;
                gap: 0.65rem;
                padding: 0.7rem 1rem;
                padding-top: calc(0.7rem + env(safe-area-inset-top, 0px));
            }
            .nav-brand {
                flex: 1 1 auto;
                min-width: 0;
                gap: 0.5rem;
            }
            .nav-brand img { height: 48px; }
            .nav-brand-title {
                font-size: 0.9rem;
                max-width: 9.5rem;
                color: #ffffff;
                font-weight: 700;
            }
            .nav-cta {
                order: 2;
                padding: 0.55rem 1rem;
                font-size: 0.85rem;
            }
            .nav-toggle { order: 3; }
            .nav-links {
                order: 4;
                position: static;
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                gap: 0;
                padding: 0.5rem;
                background: rgba(11, 19, 43, 0.96);
                border: 1px solid rgba(255,255,255,0.1);
                border-radius: 14px;
                display: none;
                max-height: min(70vh, 28rem);
                overflow-y: auto;
            }
            .nav-links.open { display: flex; }
            .nav-links a {
                padding: 0.85rem 1rem;
                border-radius: 10px;
            }
            .nav-links a:hover { background: rgba(255,255,255,0.05); }

            .hero {
                min-height: 100svh;
                padding: 6.5rem 1.15rem 3.5rem;
                align-items: center;
            }
            .hero-content { max-width: 100%; width: 100%; }
            .hero-radar { opacity: 0.25; width: min(88vw, 320px); right: -18%; top: 12%; }
            .brand-mark { font-size: clamp(2.2rem, 12vw, 3.4rem); }
            .hero h1 {
                max-width: none;
                font-size: clamp(1.45rem, 6.5vw, 2rem);
            }
            .hero p.lead {
                max-width: none;
                font-size: 1.02rem;
            }
            .hero-ctas {
                width: 100%;
                flex-direction: column;
            }
            .hero-ctas .btn,
            .hero-ctas .btn-ghost {
                width: 100%;
            }
            .scroll-cue { bottom: calc(1rem + env(safe-area-inset-bottom, 0px)); }

            .section {
                padding: 3.25rem 1.15rem;
            }
            .section h2 { max-width: none; }
            .section .sub,
            .about-copy { max-width: none; }
            .role-grid { grid-template-columns: 1fr; }
            .role-card { padding: 1.35rem 1.2rem; }
            .mvv-block { scroll-margin-top: 5rem; }
            .importance-list li {
                grid-template-columns: auto 1fr;
                gap: 0.85rem;
            }
            .footer-inner { gap: 1.25rem; }
            .footer-links {
                gap: 0.75rem 1.1rem;
            }
            .site-footer {
                padding: 2.25rem 1.15rem calc(2rem + env(safe-area-inset-bottom, 0px));
            }

            .modal-overlay {
                align-items: flex-end;
                padding: 0;
            }
            .modal-panel,
            .modal-panel.wide {
                width: 100%;
                max-width: 100%;
                max-height: min(92vh, 100%);
                border-radius: 18px 18px 0 0;
                padding: 1.25rem 1.15rem calc(1.25rem + env(safe-area-inset-bottom, 0px));
            }
            .field input,
            .contact-form input,
            .contact-form textarea {
                font-size: 16px;
            }
            .button-group {
                flex-direction: column;
            }
            .button-group .btn { width: 100%; }
        }

        @media (max-width: 480px) {
            .nav-brand img { height: 42px; }
            .nav-brand-title {
                font-size: 0.8rem;
                max-width: 7.5rem;
            }
            .hero {
                padding-top: 5.75rem;
                padding-bottom: 3rem;
            }
            .brand-mark { font-size: clamp(1.9rem, 11vw, 2.6rem); }
            .hero h1 { font-size: clamp(1.35rem, 7vw, 1.75rem); }
            .section-label { letter-spacing: 0.14em; }
        }
    </style>
</head>
<body>
    <div class="progress-bar" id="progressBar" aria-hidden="true"></div>

    <header class="site-nav" id="siteNav">
        <a href="#top" class="nav-brand">
            <img src="images/logo.svg" alt="AlerTara QC">
            <span class="nav-brand-title">Community Policing and Surveillance</span>
        </a>
        <button class="nav-toggle" id="navToggle" aria-label="Menu"><i class="fas fa-bars"></i></button>
        <ul class="nav-links" id="navLinks">
            <li><a href="#about">About</a></li>
            <li><a href="#who-uses">Who Uses This</a></li>
            <li><a href="#importance">Why It Matters</a></li>
            <li><a href="#mission">Mission</a></li>
            <li><a href="#vision">Vision</a></li>
            <li><a href="#values">Values</a></li>
            <li><a href="#faqs">FAQs</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>
        <button type="button" class="nav-cta" onclick="openLoginModal()">Login</button>
    </header>

    <main id="top">
        <section class="hero" aria-label="Hero">
            <div class="hero-bg"></div>
            <div class="hero-scan" aria-hidden="true"></div>
            <div class="hero-radar" aria-hidden="true"><div class="radar-beam"></div></div>
            <div class="hero-content">
                <div class="brand-mark" aria-label="AlerTara QC">
                    <img src="images/tara.png" alt="">
                    <span class="ler">ler</span><span class="rest">Tara QC</span>
                </div>
                <h1>Community Policing and Surveillance</h1>
                <p class="lead">24/7 community policing and surveillance that connects responders across Barangay San Agustin, Novaliches, Quezon City in real time.</p>
                <div class="hero-ctas">
                    <button type="button" class="btn" onclick="openLoginModal()">Login</button>
                    <a class="btn btn-ghost" href="#about">Learn more</a>
                </div>
            </div>
            <a class="scroll-cue" href="#about" aria-label="Scroll to about section">
                <i class="fas fa-chevron-down" aria-hidden="true"></i>
            </a>
        </section>

        <section class="section about-section" id="about">
            <div class="section-inner">
                <p class="section-label reveal">About</p>
                <h2 class="reveal reveal-delay-1">A unified platform for community safety</h2>
                <p class="sub reveal reveal-delay-2">AlerTara QC brings surveillance intelligence and community policing into one coordinated system for Barangay San Agustin, Novaliches, Quezon City.</p>
                <p class="about-copy reveal reveal-delay-3">
                    Built for administrators, barangay peacekeeping officers, and neighborhood watch partners in Barangay San Agustin,
                    AlerTara QC streamlines alerts, incident awareness, and coordinated response—
                    so residents stay informed and protected around the clock.
                </p>
            </div>
        </section>

        <section class="section who-section" id="who-uses">
            <div class="section-inner">
                <p class="section-label reveal">Who uses this</p>
                <h2 class="reveal reveal-delay-1">Choose your portal</h2>
                <p class="sub reveal reveal-delay-2">Four access paths keep Barangay San Agustin residents, community partners, patrol teams, and BPSO administrators in sync—with the right tools for each role.</p>
                <div class="role-grid">
                    <article class="role-card reveal">
                        <div class="role-icon" aria-hidden="true"><i class="fas fa-shield-halved"></i></div>
                        <h3>BPSO Administrator</h3>
                        <p>Barangay peace and security administrators who manage response coordination and community safety operations in Barangay San Agustin.</p>
                        <ul class="role-list">
                            <li>Oversee community policing and surveillance</li>
                            <li>Review patrol and incident reports</li>
                            <li>Coordinate public-safety response</li>
                        </ul>
                        <button type="button" class="btn" onclick="openLoginModal()">Visit</button>
                    </article>
                    <article class="role-card reveal reveal-delay-1">
                        <div class="role-icon" aria-hidden="true"><i class="fas fa-house-user"></i></div>
                        <h3>Neighborhood Watcher</h3>
                        <p>Approved community volunteers who observe local activity and help keep Barangay San Agustin informed.</p>
                        <ul class="role-list">
                            <li>Report incidents and tips to BPSO</li>
                            <li>Track neighborhood watch updates</li>
                            <li>Support early community awareness</li>
                        </ul>
                        <a class="btn" href="nw-login.php">Visit</a>
                    </article>
                    <article class="role-card reveal reveal-delay-2">
                        <div class="role-icon" aria-hidden="true"><i class="fas fa-route"></i></div>
                        <h3>Patrol</h3>
                        <p>Field patrol personnel who move through assigned areas in Barangay San Agustin and document on-ground activity.</p>
                        <ul class="role-list">
                            <li>View assigned patrol schedules</li>
                            <li>Submit patrol logs and reports</li>
                            <li>Coordinate with barangay responders</li>
                        </ul>
                        <a class="btn" href="patrol-login.php">Visit</a>
                    </article>
                    <article class="role-card reveal reveal-delay-3">
                        <div class="role-icon" aria-hidden="true"><i class="fas fa-users"></i></div>
                        <h3>Resident</h3>
                        <p>Barangay San Agustin residents who can submit complaints and send anonymous tips to support community safety.</p>
                        <ul class="role-list">
                            <li>Submit formal barangay complaints</li>
                            <li>Send tips anonymously</li>
                            <li>Support a safer barangay community</li>
                        </ul>
                        <a class="btn" href="resident-portal.php">Visit</a>
                    </article>
                </div>
            </div>
        </section>

        <section class="section importance-section" id="importance">
            <div class="section-inner">
                <p class="section-label reveal">Why it matters</p>
                <h2 class="reveal reveal-delay-1">Community policing &amp; surveillance</h2>
                <p class="sub reveal reveal-delay-2">Technology only works when people and process stay connected. Here’s why this platform matters for Barangay San Agustin.</p>
                <ol class="importance-list">
                    <li class="reveal">
                        <div>
                            <h3>Faster shared awareness</h3>
                            <p>Real-time visibility helps officers and partners in Barangay San Agustin spot risks early and move resources where they are needed most.</p>
                        </div>
                    </li>
                    <li class="reveal reveal-delay-1">
                        <div>
                            <h3>Stronger community trust</h3>
                            <p>Transparent coordination between the barangay, patrols, and watch volunteers builds confidence in public safety efforts across San Agustin.</p>
                        </div>
                    </li>
                    <li class="reveal reveal-delay-2">
                        <div>
                            <h3>Proactive protection</h3>
                            <p>Continuous monitoring turns reactive reporting into preventive action—reducing harm in our barangay before incidents escalate.</p>
                        </div>
                    </li>
                </ol>
            </div>
        </section>

        <section class="section mvv-section">
            <div class="section-inner mvv-stack">
                <div class="mvv-block reveal" id="mission">
                    <p class="section-label">Mission</p>
                    <h3>Our Mission</h3>
                    <p>To provide a unified, efficient, and responsive emergency management system for Barangay San Agustin that protects lives and property through seamless coordination and real-time information sharing.</p>
                </div>
                <div class="mvv-block reveal" id="vision">
                    <p class="section-label">Vision</p>
                    <h3>Our Vision</h3>
                    <p>To become a model barangay for community policing and surveillance in Novaliches, Quezon City—leveraging technology to create a safer, more resilient Barangay San Agustin through proactive and coordinated public safety initiatives.</p>
                </div>
                <div class="mvv-block reveal" id="values">
                    <p class="section-label">Values</p>
                    <h3>Our Values</h3>
                    <p>Integrity, Excellence, Collaboration, and Innovation guide our commitment to serving the people of Barangay San Agustin, Novaliches, Quezon City with dedication and professionalism in every emergency response and public safety operation.</p>
                </div>
            </div>
        </section>

        <section class="section faq-section" id="faqs">
            <div class="section-inner">
                <p class="section-label reveal">FAQs</p>
                <h2 class="reveal reveal-delay-1">Questions, answered</h2>
                <div class="faq-list">
                    <details class="faq reveal">
                        <summary>Who can log in to AlerTara QC?</summary>
                        <p>Authorized administrators and designated personnel assigned to Barangay San Agustin. Access is role-based and protected with OTP verification.</p>
                    </details>
                    <details class="faq reveal reveal-delay-1">
                        <summary>Why is an OTP required after signing in?</summary>
                        <p>One-time passwords add an extra layer of security so only verified account holders can access sensitive community safety data for Barangay San Agustin.</p>
                    </details>
                    <details class="faq reveal reveal-delay-2">
                        <summary>What should I do if I forgot my password?</summary>
                        <p>Use Forgot Password on the login modal. We’ll send a short-lived OTP to your registered email so you can reset securely.</p>
                    </details>
                    <details class="faq reveal">
                        <summary>Is community surveillance used responsibly?</summary>
                        <p>Yes. The platform supports lawful public-safety operations for Barangay San Agustin with accountability measures for authorized barangay partners.</p>
                    </details>
                </div>
            </div>
        </section>

        <section class="section contact-section" id="contact">
            <div class="section-inner">
                <p class="section-label reveal">Contact us</p>
                <h2 class="reveal reveal-delay-1">We're here to help</h2>
                <div class="contact-info reveal reveal-delay-1">
                    <p>Reach the AlerTara QC team in Barangay San Agustin for support, partnership inquiries, or account assistance.</p>
                    <div>
                        <strong>Email</strong>
                        contactcps@alertaraqc.gov.ph
                    </div>
                    <div>
                        <strong>Address</strong>
                        Barangay San Agustin, Novaliches, Quezon City, Metro Manila Philippines
                    </div>
                    <div>
                        <strong>Operation Hours</strong>
                        24/7
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-brand">Aler<span>Tara</span> QC</div>
            <div class="footer-links">
                <button type="button" onclick="openLoginModal()">Login</button>
                <a href="#about">Learn more</a>
                <button type="button" onclick="openLegalModal('privacy')">Privacy Policy</button>
                <button type="button" onclick="openLegalModal('terms')">Terms of Service</button>
                <button type="button" onclick="openLegalModal('cookies')">Cookie Policy</button>
                <a href="#contact">Contact</a>
            </div>
            <p class="footer-copy">&copy; <?php echo date('Y'); ?> AlerTara QC — Community Policing and Surveillance for Barangay San Agustin, Novaliches, Quezon City. All rights reserved.</p>
        </div>
    </footer>

    <!-- Login Modal -->
    <div class="modal-overlay" id="loginModal" role="dialog" aria-modal="true" aria-labelledby="login-title">
        <div class="modal-panel">
            <div class="modal-header">
                <h2 id="login-title">Login</h2>
                <button type="button" class="modal-close" onclick="closeLoginModal()" aria-label="Close">&times;</button>
            </div>
            <?php if (isset($_SESSION['registration_success'])): ?>
                <div class="alert alert-success">
                    Registration successful! You can now login using your registered email address.
                </div>
                <?php unset($_SESSION['registration_success'], $_SESSION['registered_username']); ?>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($otpPrompt)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($otpPrompt); ?></div>
            <?php endif; ?>
            <?php if (!$showOtpForm && isset($attemptsRemaining) && $attemptsRemaining > 0 && $attemptsRemaining < 3): ?>
                <div class="alert alert-warn">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> You have <?php echo $attemptsRemaining; ?> more <?php echo $attemptsRemaining === 1 ? 'attempt' : 'attempts'; ?> remaining before your account is locked for 30 minutes.
                </div>
            <?php elseif (!$showOtpForm && isset($_POST['email']) && isset($attemptsRemaining) && $attemptsRemaining === 0): ?>
                <div class="alert alert-error">
                    <i class="fas fa-lock"></i>
                    <strong>Account Locked:</strong> Your account has been locked due to multiple failed login attempts. Please wait 30 minutes or contact an administrator.
                </div>
            <?php endif; ?>
            <?php if ($showOtpForm): ?>
                <?php renderLoginOtpForm(
                    'login.php?cancel_otp=1',
                    $otpEmailMasked,
                    $otpExpiresAtText,
                    (int) $otpResendCooldown
                ); ?>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" placeholder="Enter your email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" placeholder="••••••••" required>
                    </div>
                    <div class="login-actions">
                        <a href="#" onclick="event.preventDefault(); closeLoginModal(); openForgotPasswordModal();">Forgot password?</a>
                    </div>
                    <div class="button-group">
                        <button class="btn" type="submit" style="width:100%; border-radius:12px;">Sign in</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Legal Modal -->
    <div class="modal-overlay" id="legalModal" role="dialog" aria-modal="true">
        <div class="modal-panel wide">
            <div class="modal-header">
                <h2 id="legalTitle">Policy</h2>
                <button type="button" class="modal-close" onclick="closeLegalModal()" aria-label="Close">&times;</button>
            </div>
            <div class="legal-body" id="legalBody"></div>
        </div>
    </div>

    <!-- Registration Success Modal -->
    <div id="registrationSuccessModal" class="success-modal">
        <div class="success-modal-content">
            <div class="success-icon-wrapper"><i class="fas fa-check"></i></div>
            <h2>Registration Successful!</h2>
            <p>Registration submitted successfully! Please proceed to the barangay hall to get your physical ID.</p>
            <div class="success-modal-actions">
                <button type="button" class="btn" onclick="closeRegistrationSuccessModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal-overlay" style="z-index:2100;">
        <div class="modal-panel">
            <div class="modal-header">
                <h2 id="forgotPasswordTitle">Forgot Password</h2>
                <button type="button" class="modal-close" onclick="closeForgotPasswordModal()" aria-label="Close">&times;</button>
            </div>
            <div id="forgotPasswordStep1">
                <p style="color: rgba(255,255,255,0.85); margin-bottom: 1.25rem;">Enter your email to receive an OTP code.</p>
                <form id="forgotPasswordForm1" onsubmit="requestOTP(event)">
                    <div class="field">
                        <label for="forgotEmail">Email *</label>
                        <input id="forgotEmail" name="email" type="email" placeholder="Enter your email" required>
                    </div>
                    <div id="forgotPasswordMessage" style="display:none;" class="alert"></div>
                    <div class="button-group">
                        <button type="button" class="btn btn-secondary" onclick="closeForgotPasswordModal()">Cancel</button>
                        <button type="submit" class="btn">Send OTP</button>
                    </div>
                </form>
            </div>
            <div id="forgotPasswordStep2" style="display:none;">
                <p style="color: rgba(255,255,255,0.85); margin-bottom: 1.25rem;">Enter the 6-digit OTP code sent to your email address.</p>
                <form id="forgotPasswordForm2" onsubmit="verifyOTP(event)">
                    <div class="field">
                        <label for="forgotOTP">OTP Code *</label>
                        <input id="forgotOTP" name="otp" type="text" placeholder="Enter 6-digit OTP" maxlength="6" required style="text-align:center; letter-spacing:0.4rem; font-size:1.35rem;">
                    </div>
                    <div id="forgotPasswordMessage2" style="display:none;" class="alert"></div>
                    <div class="button-group">
                        <button type="button" class="btn btn-secondary" onclick="backToStep1()">Back</button>
                        <button type="submit" class="btn">Verify OTP</button>
                    </div>
                </form>
            </div>
            <div id="forgotPasswordStep3" style="display:none;">
                <p style="color: rgba(255,255,255,0.85); margin-bottom: 1.25rem;">Enter your new password.</p>
                <form id="forgotPasswordForm3" onsubmit="resetPassword(event)">
                    <div class="field">
                        <label for="newPassword">New Password *</label>
                        <input id="newPassword" name="new_password" type="password" placeholder="Enter new password" required>
                    </div>
                    <div class="field">
                        <label for="confirmPassword">Confirm Password *</label>
                        <input id="confirmPassword" name="confirm_password" type="password" placeholder="Confirm new password" required>
                    </div>
                    <div id="forgotPasswordMessage3" style="display:none;" class="alert"></div>
                    <div class="button-group">
                        <button type="button" class="btn btn-secondary" onclick="backToStep2()">Back</button>
                        <button type="submit" class="btn">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Message Modal -->
    <div id="successModal" class="modal-overlay" style="z-index:3000;">
        <div class="modal-panel" style="text-align:center; background: linear-gradient(145deg, #10b981, #059669);">
            <div style="width:72px;height:72px;margin:0 auto 1rem;background:rgba(255,255,255,0.2);border-radius:50%;display:grid;place-items:center;">
                <i class="fas fa-check-circle" style="font-size:2.2rem;color:#fff;"></i>
            </div>
            <h2 style="margin:0 0 0.75rem;color:#fff;font-family:var(--font-display);">Success!</h2>
            <p id="successMessage" style="margin:0 0 1.5rem;color:rgba(255,255,255,0.95);"></p>
            <button class="btn btn-ghost" onclick="closeSuccessModal()" style="width:100%;">OK</button>
        </div>
    </div>

    <script>
        const autoOpenLogin = <?php echo !empty($autoOpenLogin) ? 'true' : 'false'; ?>;

        const legalContent = {
            privacy: {
                title: 'Privacy Policy',
                html: `
                    <p>AlerTara QC respects your privacy and handles account and operational data responsibly for community safety purposes.</p>
                    <h3>Information we process</h3>
                    <p>Account details (such as name, email, and role), authentication logs, and system activity needed to secure and operate the platform.</p>
                    <h3>How we use information</h3>
                    <p>To verify identity, send OTP and security notices, maintain service integrity, and support authorized public-safety operations.</p>
                    <h3>Sharing</h3>
                    <p>Data is shared only with authorized Barangay San Agustin partners and service providers under appropriate controls, or when required by law.</p>
                    <h3>Your choices</h3>
                    <p>Contact administrators to update account details or raise privacy concerns related to your access.</p>
                `
            },
            terms: {
                title: 'Terms of Service',
                html: `
                    <p>By accessing AlerTara QC, you agree to use the platform only for lawful community policing and surveillance operations in Barangay San Agustin, Novaliches, Quezon City.</p>
                    <h3>Acceptable use</h3>
                    <p>Users must protect credentials, follow role permissions, and avoid unauthorized disclosure of sensitive information.</p>
                    <h3>Accounts</h3>
                    <p>Accounts are for designated personnel. Repeated failed logins may trigger temporary lockouts for security.</p>
                    <h3>Availability</h3>
                    <p>We strive for continuous uptime, but maintenance or unforeseen issues may temporarily affect access.</p>
                    <h3>Changes</h3>
                    <p>These terms may be updated to reflect operational or legal requirements. Continued use means you accept the latest version.</p>
                `
            },
            cookies: {
                title: 'Cookie Policy',
                html: `
                    <p>AlerTara QC uses essential cookies and session storage to keep you signed in securely and remember critical login state.</p>
                    <h3>Essential cookies</h3>
                    <p>Required for authentication sessions, OTP verification flow, and basic security protections.</p>
                    <h3>Preferences</h3>
                    <p>We may store limited interface preferences to improve your experience on return visits.</p>
                    <h3>Control</h3>
                    <p>You can clear cookies in your browser, but doing so may sign you out and require login again.</p>
                `
            }
        };

        function openLoginModal() {
            document.getElementById('loginModal').classList.add('open');
            document.body.style.overflow = 'hidden';
            const email = document.getElementById('email');
            if (email) setTimeout(() => email.focus(), 120);
        }
        function closeLoginModal() {
            document.getElementById('loginModal').classList.remove('open');
            if (![...document.querySelectorAll('.modal-overlay.open')].length) {
                document.body.style.overflow = '';
            }
        }

        function openLegalModal(key) {
            const item = legalContent[key];
            if (!item) return;
            document.getElementById('legalTitle').textContent = item.title;
            document.getElementById('legalBody').innerHTML = item.html;
            document.getElementById('legalModal').classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function closeLegalModal() {
            document.getElementById('legalModal').classList.remove('open');
            if (![...document.querySelectorAll('.modal-overlay.open')].length) {
                document.body.style.overflow = '';
            }
        }

        function closeRegistrationSuccessModal() {
            document.getElementById('registrationSuccessModal').classList.remove('active');
        }

        function showSuccessModal(title, message, isError = false) {
            const modal = document.getElementById('successModal');
            const titleElement = modal.querySelector('h2');
            const messageElement = document.getElementById('successMessage');
            const iconElement = modal.querySelector('i');
            const modalContent = modal.querySelector('.modal-panel');
            titleElement.textContent = title;
            messageElement.innerHTML = message;
            if (isError) {
                modalContent.style.background = 'linear-gradient(145deg, #ef4444, #dc2626)';
                iconElement.className = 'fas fa-exclamation-circle';
            } else {
                modalContent.style.background = 'linear-gradient(145deg, #10b981, #059669)';
                iconElement.className = 'fas fa-check-circle';
            }
            modal.classList.add('open');
        }
        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('open');
        }

        function openForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').classList.add('open');
            resetForgotPasswordModal();
            document.body.style.overflow = 'hidden';
        }
        function closeForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').classList.remove('open');
            resetForgotPasswordModal();
            if (![...document.querySelectorAll('.modal-overlay.open')].length) {
                document.body.style.overflow = '';
            }
        }
        function resetForgotPasswordModal() {
            document.getElementById('forgotPasswordStep1').style.display = 'block';
            document.getElementById('forgotPasswordStep2').style.display = 'none';
            document.getElementById('forgotPasswordStep3').style.display = 'none';
            document.getElementById('forgotPasswordForm1').reset();
            document.getElementById('forgotPasswordForm2').reset();
            document.getElementById('forgotPasswordForm3').reset();
            ['forgotPasswordMessage','forgotPasswordMessage2','forgotPasswordMessage3'].forEach(id => {
                const el = document.getElementById(id);
                el.style.display = 'none';
                el.textContent = '';
            });
            document.getElementById('forgotPasswordTitle').textContent = 'Forgot Password';
        }
        function backToStep1() {
            document.getElementById('forgotPasswordStep1').style.display = 'block';
            document.getElementById('forgotPasswordStep2').style.display = 'none';
            document.getElementById('forgotPasswordTitle').textContent = 'Forgot Password';
        }
        function backToStep2() {
            document.getElementById('forgotPasswordStep2').style.display = 'block';
            document.getElementById('forgotPasswordStep3').style.display = 'none';
            document.getElementById('forgotPasswordTitle').textContent = 'Verify OTP';
        }

        function setMsg(el, text, ok) {
            el.textContent = text;
            el.style.display = 'block';
            el.className = 'alert ' + (ok ? 'alert-success' : 'alert-error');
        }

        async function requestOTP(event) {
            event.preventDefault();
            const email = document.getElementById('forgotEmail').value.trim();
            const messageDiv = document.getElementById('forgotPasswordMessage');
            if (!email) return setMsg(messageDiv, 'Please enter your email.', false);
            try {
                const response = await fetch('api/forgot-password.php?action=request', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });
                const raw = await response.text();
                let result;
                try { result = JSON.parse(raw); }
                catch (e) { throw new Error('Invalid server response while requesting OTP.'); }
                if (result.success) {
                    setMsg(messageDiv, result.message, true);
                    setTimeout(() => {
                        document.getElementById('forgotPasswordStep1').style.display = 'none';
                        document.getElementById('forgotPasswordStep2').style.display = 'block';
                        document.getElementById('forgotPasswordTitle').textContent = 'Verify OTP';
                        document.getElementById('forgotOTP').focus();
                    }, 900);
                } else {
                    setMsg(messageDiv, result.message || 'An error occurred. Please try again.', false);
                }
            } catch (err) {
                setMsg(messageDiv, err.message || 'An error occurred. Please try again.', false);
            }
        }

        async function verifyOTP(event) {
            event.preventDefault();
            const otp = document.getElementById('forgotOTP').value.trim();
            const messageDiv = document.getElementById('forgotPasswordMessage2');
            if (!otp || otp.length !== 6) return setMsg(messageDiv, 'Please enter a valid 6-digit OTP code.', false);
            try {
                const response = await fetch('api/forgot-password.php?action=verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ otp })
                });
                const result = await response.json();
                if (result.success) {
                    document.getElementById('forgotPasswordStep2').style.display = 'none';
                    document.getElementById('forgotPasswordStep3').style.display = 'block';
                    document.getElementById('forgotPasswordTitle').textContent = 'Reset Password';
                    document.getElementById('newPassword').focus();
                } else {
                    setMsg(messageDiv, result.message, false);
                }
            } catch (err) {
                setMsg(messageDiv, 'An error occurred. Please try again.', false);
            }
        }

        async function resetPassword(event) {
            event.preventDefault();
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const messageDiv = document.getElementById('forgotPasswordMessage3');
            if (!newPassword || !confirmPassword) return setMsg(messageDiv, 'Please fill in all fields.', false);
            if (newPassword !== confirmPassword) return setMsg(messageDiv, 'Passwords do not match.', false);
            if (newPassword.length < 6) return setMsg(messageDiv, 'Password must be at least 6 characters long.', false);
            try {
                const response = await fetch('api/forgot-password.php?action=reset', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ new_password: newPassword, confirm_password: confirmPassword })
                });
                const result = await response.json();
                if (result.success) {
                    closeForgotPasswordModal();
                    showSuccessModal('Password Reset Successful!', result.message, false);
                    setTimeout(() => { window.location.href = 'login.php'; }, 2000);
                } else {
                    setMsg(messageDiv, result.message, false);
                }
            } catch (err) {
                setMsg(messageDiv, 'An error occurred. Please try again.', false);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Scroll progress + nav state
            const nav = document.getElementById('siteNav');
            const bar = document.getElementById('progressBar');
            const onScroll = () => {
                const max = document.documentElement.scrollHeight - window.innerHeight;
                const pct = max > 0 ? (window.scrollY / max) * 100 : 0;
                bar.style.width = pct + '%';
                nav.classList.toggle('scrolled', window.scrollY > 24);
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();

            // Progressive reveals
            const io = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('in');
                        io.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.16, rootMargin: '0px 0px -8% 0px' });
            document.querySelectorAll('.reveal').forEach((el) => io.observe(el));

            // Active nav links
            const sections = [
                ...document.querySelectorAll('section[id]'),
                ...document.querySelectorAll('#mission, #vision, #values')
            ];
            const links = [...document.querySelectorAll('#navLinks a')];
            const spy = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    const id = entry.target.id;
                    links.forEach((a) => a.classList.toggle('active', a.getAttribute('href') === '#' + id));
                });
            }, { threshold: 0.35 });
            sections.forEach((s) => spy.observe(s));

            // Mobile nav
            const toggle = document.getElementById('navToggle');
            const navLinks = document.getElementById('navLinks');
            toggle.addEventListener('click', () => navLinks.classList.toggle('open'));
            navLinks.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => navLinks.classList.remove('open')));

            // OTP inputs
            const otpInput = document.getElementById('forgotOTP');
            if (otpInput) otpInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
            });
            const loginOtpInput = document.getElementById('login_otp');
            if (loginOtpInput) loginOtpInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
            });

            if (autoOpenLogin) openLoginModal();
            if (window.location.search.includes('reset=1')) {
                openForgotPasswordModal();
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        window.addEventListener('click', (event) => {
            if (event.target === document.getElementById('loginModal')) closeLoginModal();
            if (event.target === document.getElementById('legalModal')) closeLegalModal();
            if (event.target === document.getElementById('forgotPasswordModal')) closeForgotPasswordModal();
            if (event.target === document.getElementById('successModal')) closeSuccessModal();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeLoginModal();
                closeLegalModal();
                closeForgotPasswordModal();
                closeSuccessModal();
            }
        });
    </script>
</body>
</html>

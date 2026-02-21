<?php 
// Admin login page
session_start();

require_once __DIR__ . '/db.php';

// Load PHPMailer via Composer autoloader
$autoloadPath = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

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

    // Create default admin account if none exists
    $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM admins');
    $count = (int)$stmt->fetch()['cnt'];
    if ($count === 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash, email, full_name, status) VALUES (:u, :p, :e, :f, "Active")');
        $stmt->execute([
            ':u' => 'admin',
            ':p' => $hash,
            ':e' => null,
            ':f' => 'Default Administrator'
        ]);
    }
}

try {
    ensureAdminsTable($pdo);
} catch (PDOException $e) {
    $error = 'Login system error: ' . htmlspecialchars($e->getMessage());
}

// Email sending functions
function sendAccountLockEmail($email, $username, $lockedUntil) {
    $mailHost = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
    $mailPort = (int)($_ENV['MAIL_PORT'] ?? 465);
    $mailUser = $_ENV['MAIL_USERNAME'] ?? '';
    $mailPass = $_ENV['MAIL_PASSWORD'] ?? '';
    $mailFrom = $_ENV['MAIL_FROM_ADDRESS'] ?? $mailUser;
    $mailFromName = $_ENV['MAIL_FROM_NAME'] ?? 'AlerTara QC';
    $mailEncryption = $_ENV['MAIL_ENCRYPTION'] ?? 'ssl';
    
    if (empty($mailUser) || empty($mailPass) || empty($email)) {
        error_log('Email credentials or recipient email not set');
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
            return true;
        } catch (Exception $e) {
            error_log('Account lock email send failed: ' . $mail->ErrorInfo);
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
    $mailHost = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
    $mailPort = (int)($_ENV['MAIL_PORT'] ?? 465);
    $mailUser = $_ENV['MAIL_USERNAME'] ?? '';
    $mailPass = $_ENV['MAIL_PASSWORD'] ?? '';
    $mailFrom = $_ENV['MAIL_FROM_ADDRESS'] ?? $mailUser;
    $mailFromName = $_ENV['MAIL_FROM_NAME'] ?? 'AlerTara QC';
    $mailEncryption = $_ENV['MAIL_ENCRYPTION'] ?? 'ssl';
    
    if (empty($mailUser) || empty($mailPass) || empty($email)) {
        error_log('Email credentials or recipient email not set');
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
            return true;
        } catch (Exception $e) {
            error_log('Login success email send failed: ' . $mail->ErrorInfo);
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
            // Ensure notifications table exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                type VARCHAR(50) NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                link VARCHAR(255) DEFAULT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_is_read (is_read),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Format login time
            $loginTime = date('M j, Y g:i:s A');
            $message = "User {$username} logged in from IP {$ipAddress} at {$loginTime}";
            
            // Create notification for the user who logged in (user_id = userId)
            $notifStmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (:user_id, :type, :title, :message, :link, NOW())');
            $notifStmt->execute([
                ':user_id' => $userId,
                ':type' => 'login',
                ':title' => 'Login Successful',
                ':message' => $message,
                ':link' => 'login-history.php'
            ]);
            
            // Also create a notification for admins (user_id = NULL means visible to all admins)
            // This will be filtered in the API based on role
            $adminNotifStmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (NULL, :type, :title, :message, :link, NOW())');
            $adminNotifStmt->execute([
                ':type' => 'login',
                ':title' => 'User Login',
                ':message' => $message,
                ':link' => 'login-history.php'
            ]);
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error)) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password_hash, full_name, status, email, role, failed_attempts, last_failed_at, locked_until FROM admins WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
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
                        
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['username'] = $admin['full_name'] ?: $admin['username'];
                        $_SESSION['user_role'] = $admin['role'] ?? 'User';
                        $_SESSION['user_id'] = $admin['id'];
                        
                        // Log successful login
                        $ipAddress = getClientIP();
                        logLoginHistory($pdo, $admin['id'], $admin['username'], 'Success', $ipAddress);
                        
                        // Send successful login email
                        if (!empty($admin['email'])) {
                            // Use Philippines timezone
                            $dateTime = new DateTime('now', new DateTimeZone('Asia/Manila'));
                            $loginTime = $dateTime->format('F j, Y \a\t g:i A');
                            sendLoginSuccessEmail($admin['email'], $admin['username'], $loginTime, $ipAddress);
                        }
                        
                        // Redirect to dashboard or home page
                        header('Location: index.php');
                        exit;
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
                            $error = 'Invalid username or password';
                        }
                    }
                }
            }
        } else {
            $error = 'Invalid username or password';
        }
    }
}

// Get attempts remaining for display (if user exists)
if (!isset($attemptsRemaining) && isset($_POST['username'])) {
    $username = trim($_POST['username'] ?? '');
    if (!empty($username)) {
        $stmt = $pdo->prepare('SELECT failed_attempts, locked_until FROM admins WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $admin = $stmt->fetch();
        if ($admin) {
            $lockedUntil = $admin['locked_until'] ?? null;
            if (!$lockedUntil || strtotime($lockedUntil) <= time()) {
                $failedAttempts = (int)($admin['failed_attempts'] ?? 0);
                $attemptsRemaining = max(0, 3 - $failedAttempts);
            }
        }
    }
}

// Check if already logged in, redirect to index.php
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Policing and Surveillance</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        :root { --radius: 12px; }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding: 2rem clamp(1rem, 3vw, 2.5rem);
        }
        .page {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 5rem;
            flex: 1;
        }
        .main-content {
            display: grid;
            grid-template-columns: 1fr minmax(480px, 580px);
            gap: clamp(1.5rem, 3vw, 2.5rem);
            align-items: center;
            flex: 1;
            margin-top: 5rem;
        }
        .hero {
            display: flex;
            flex-direction: column;
            gap: 2rem;
            color: var(--text-color);
            align-items: center;
            justify-content: center;
        }
        .hero h1 {
            font-size: clamp(4rem, 6vw, 6.5rem);
            letter-spacing: -0.02em;
            margin: 0;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            text-align: center;
        }
        .hero h1 .logo-inline {
            height: 1.15em;
            width: auto;
            display: inline-block;
            vertical-align: middle;
            flex-shrink: 0;
        }
        .hero h1 .text-ler {
            color: var(--primary-color);
        }
        .hero h1 .text-taraqc {
            color: #2a2a2a;
        }
        .logo-wrap {
            margin-bottom: 0;
        }
        .logo-wrap img {
            height: 300px;
            width: auto;
            object-fit: contain;
            filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.1));
        }
        .hero .welcome-text {
            margin-top: 0;
            margin-left: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .hero .welcome-text p {
            max-width: 540px;
            color: var(--text-secondary);
            line-height: 1.8;
            margin: 0;
            font-size: clamp(1.3rem, 2vw, 1.6rem);
            text-align: left;
        }
        .login-card {
            background: linear-gradient(145deg, var(--tertiary-color), var(--secondary-color));
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius);
            box-shadow: 0 20px 50px -25px rgba(0, 0, 0, 0.5);
            padding: clamp(2.5rem, 4vw, 3.5rem);
            display: grid;
            gap: 1.75rem;
            width: 100%;
        }
        .login-card h2 {
            margin: 0;
            color: #f8fafc;
            font-size: 1.75rem;
            font-weight: 600;
        }
        .field {
            display: grid;
            gap: 0.35rem;
        }
        .field label {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }
        .field input {
            width: 100%;
            padding: 1.15rem 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: var(--radius);
            font: inherit;
            font-size: 1.1rem;
            color: #f8fafc;
            background: rgba(255, 255, 255, 0.08);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .field input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.25);
        }
        .actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .actions a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 1.1rem;
        }
        .actions a:hover {
            text-decoration: underline;
        }
        .button-group {
            display: flex;
            gap: 0.75rem;
            margin-top: 0.5rem;
        }
        .button-group .btn {
            flex: 1;
        }
        .btn-secondary {
            background: transparent;
            color: #ffffff;
            border: 1px solid #3a3a3a;
            box-shadow: none;
        }
        .btn-secondary:hover {
            background: #2a2a2a;
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }
        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 1.15rem 1.75rem;
            border-radius: var(--radius);
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            color: #fff;
            background: var(--primary-color);
            box-shadow: 0 12px 30px -15px var(--primary-color);
            transition: transform 0.15s ease, box-shadow 0.2s ease, filter 0.2s ease;
        }
        .btn:hover {
            background: #4ca8a6;
            transform: translateY(-1px);
            filter: brightness(1.02);
        }
        .btn:active {
            transform: translateY(0);
            box-shadow: 0 8px 20px -16px var(--secondary-color);
        }
        .mv-section {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-radius: var(--radius);
            padding: 3rem clamp(2rem, 4vw, 3rem);
            box-shadow: 0 10px 40px -15px rgba(0, 0, 0, 0.1);
            margin-top: -1rem;
        }
        .mv-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2.5rem;
        }
        .mv-item {
            display: flex;
            flex-direction: column;
        }
        .mv-item h3 {
            font-size: 1.5rem;
            color: var(--tertiary-color);
            margin: 0 0 1rem 0;
            font-weight: 700;
            position: relative;
            padding-bottom: 0.75rem;
        }
        .mv-item h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }
        .mv-item p {
            color: var(--text-secondary);
            line-height: 1.8;
            margin: 0;
            font-size: 0.95rem;
        }
        @media (max-width: 900px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            .login-card { order: 2; }
            .hero { order: 1; }
            .mv-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            .mv-section {
                padding: 2rem 1.5rem;
            }
            .login-card h2 { color: #ffffff; }
            .field label { color: #a1a1aa; }
        }
        .action-buttons-section {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        @media (max-width: 768px) {
            body {
                padding: 1.5rem 1rem;
            }
            .logo-wrap img {
                height: 200px;
            }
            .action-buttons-section {
                padding: 1.5rem 1rem !important;
            }
            .action-buttons-section button {
                min-width: 100% !important;
                width: 100%;
            }
        }
        .modal input:focus, .modal textarea:focus, .modal select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.25);
        }
        .modal .close:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .success-modal {
            display: none;
            position: fixed;
            z-index: 2500;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(6px);
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        .success-modal.active {
            display: flex;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .success-modal-content {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border-radius: var(--radius);
            padding: 3rem clamp(2rem, 4vw, 3rem);
            max-width: 520px;
            width: 90%;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.4);
            text-align: center;
            position: relative;
            animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .success-icon-wrapper {
            width: 100px;
            height: 100px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            color: #ffffff;
            box-shadow: 0 10px 30px -10px rgba(16, 185, 129, 0.5);
            animation: scaleIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s both;
        }
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .success-icon-wrapper i {
            animation: checkmark 0.6s ease 0.3s both;
        }
        @keyframes checkmark {
            0% {
                transform: scale(0) rotate(-45deg);
            }
            50% {
                transform: scale(1.2) rotate(-45deg);
            }
            100% {
                transform: scale(1) rotate(0deg);
            }
        }
        .success-modal-content h2 {
            color: var(--tertiary-color);
            margin: 0 0 1rem 0;
            font-size: 1.75rem;
            font-weight: 700;
        }
        .success-modal-content p {
            color: var(--text-secondary);
            margin: 0 0 1.5rem 0;
            font-size: 1.05rem;
            line-height: 1.6;
        }
        .success-modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        .success-modal-actions .btn {
            min-width: 140px;
        }

    </style>
</head>
<body>
    <main class="page">
        <div class="main-content">
            <section class="hero">
                <div class="welcome-text">
                    <h1>
                        <img src="images/tara.png" alt="A" class="logo-inline">
                        <span class="text-ler">ler</span><span class="text-taraqc">TaraQC</span>
                    </h1>
                    <p>24/7 surveillance and instant alert system for potential threats.</p>
                </div>
            </section>

            <section class="login-card" aria-labelledby="login-title">
                <h2 id="login-title">Login</h2>
                <?php if (isset($_SESSION['registration_success'])): ?>
                    <div style="color: #10b981; font-size: 0.9rem; padding: 0.75rem; background: rgba(16, 185, 129, 0.1); border-radius: 6px; margin-bottom: 1rem; border: 1px solid rgba(16, 185, 129, 0.2);">
                        Registration successful! You can now login with username: <?php echo htmlspecialchars($_SESSION['registered_username'] ?? ''); ?>
                    </div>
                    <?php unset($_SESSION['registration_success'], $_SESSION['registered_username']); ?>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div style="color: #ef4444; font-size: 0.9rem; padding: 0.75rem; background: rgba(239, 68, 68, 0.1); border-radius: 6px; margin-bottom: 1rem; border: 1px solid rgba(239, 68, 68, 0.2);">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($attemptsRemaining) && $attemptsRemaining > 0 && $attemptsRemaining < 3): ?>
                    <div style="color: #f59e0b; font-size: 0.9rem; padding: 0.75rem; background: rgba(245, 158, 11, 0.1); border-radius: 6px; margin-bottom: 1rem; border: 1px solid rgba(245, 158, 11, 0.2);">
                        <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i>
                        You have <?php echo $attemptsRemaining; ?> more <?php echo $attemptsRemaining === 1 ? 'attempt' : 'attempts'; ?> before your account is locked.
                    </div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" placeholder="Enter your username" required>
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" placeholder="••••••••" required>
                    </div>
                    <div class="actions">
                        <a href="#" onclick="event.preventDefault(); openForgotPasswordModal();" style="cursor: pointer;">Forgot password?</a>
                    </div>
                    <div class="button-group">
                        <button class="btn" type="submit" style="width: 100%;">Sign in</button>
                    </div>
                </form>
            </section>
        </div>

        <section class="mv-section">
            <div class="mv-grid">
                <div class="mv-item">
                    <h3>Our Mission</h3>
                    <p>To provide a unified, efficient, and responsive emergency management system that protects lives and property through seamless inter-departmental coordination and real-time information sharing.</p>
                </div>
                
                <div class="mv-item">
                    <h3>Our Vision</h3>
                    <p>To become the model for smart city emergency management in the Philippines, leveraging technology to create safer, more resilient communities through proactive and coordinated public safety initiatives.</p>
                </div>
                
                <div class="mv-item">
                    <h3>Our Values</h3>
                    <p>Integrity, Excellence, Collaboration, and Innovation guide our commitment to serving the people of Quezon City with dedication and professionalism in every emergency response and public safety operation.</p>
                </div>
            </div>
        </section>
    </main>

    <!-- Registration Success Modal -->
    <div id="registrationSuccessModal" class="success-modal">
        <div class="success-modal-content">
            <div class="success-icon-wrapper">
                <i class="fas fa-check"></i>
            </div>
            <h2>Registration Successful!</h2>
            <p>Registration submitted successfully! Please proceed to the barangay hall to get your physical ID.</p>
            <div class="success-modal-actions">
                <button type="button" class="btn" onclick="closeRegistrationSuccessModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
        <div class="modal-content" style="background: linear-gradient(145deg, var(--tertiary-color), var(--secondary-color)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: var(--radius); box-shadow: 0 20px 50px -25px rgba(0, 0, 0, 0.5); padding: clamp(2.5rem, 4vw, 3.5rem); max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.16);">
                <h2 id="forgotPasswordTitle" style="margin: 0; color: #f8fafc; font-size: 1.75rem; font-weight: 600;">Forgot Password</h2>
                <span class="close" onclick="closeForgotPasswordModal()" style="color: rgba(255, 255, 255, 0.8); font-size: 1.75rem; cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: all 0.2s ease; line-height: 1;">&times;</span>
            </div>
            
            <!-- Step 1: Request OTP -->
            <div id="forgotPasswordStep1">
                <p style="color: rgba(255, 255, 255, 0.85); margin-bottom: 1.5rem; line-height: 1.6;">Enter your username to receive an OTP code via email.</p>
                <form id="forgotPasswordForm1" onsubmit="requestOTP(event)" style="display: grid; gap: 1.75rem;">
                    <div class="field">
                        <label for="forgotUsername" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Username *</label>
                        <input id="forgotUsername" name="username" type="text" placeholder="Enter your username" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                    </div>
                    <div id="forgotPasswordMessage" style="display: none; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem;"></div>
                    <div class="button-group" style="margin-top: 0.5rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeForgotPasswordModal()" style="flex: 1;">Cancel</button>
                        <button type="submit" class="btn" style="flex: 1;">Send OTP</button>
                    </div>
                </form>
            </div>
            
            <!-- Step 2: Verify OTP -->
            <div id="forgotPasswordStep2" style="display: none;">
                <p style="color: rgba(255, 255, 255, 0.85); margin-bottom: 1.5rem; line-height: 1.6;">Enter the 6-digit OTP code sent to your email address.</p>
                <form id="forgotPasswordForm2" onsubmit="verifyOTP(event)" style="display: grid; gap: 1.75rem;">
                    <div class="field">
                        <label for="forgotOTP" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">OTP Code *</label>
                        <input id="forgotOTP" name="otp" type="text" placeholder="Enter 6-digit OTP" maxlength="6" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.5rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; text-align: center; letter-spacing: 0.5rem;">
                    </div>
                    <div id="forgotPasswordMessage2" style="display: none; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem;"></div>
                    <div class="button-group" style="margin-top: 0.5rem;">
                        <button type="button" class="btn btn-secondary" onclick="backToStep1()" style="flex: 1;">Back</button>
                        <button type="submit" class="btn" style="flex: 1;">Verify OTP</button>
                    </div>
                </form>
            </div>
            
            <!-- Step 3: Reset Password -->
            <div id="forgotPasswordStep3" style="display: none;">
                <p style="color: rgba(255, 255, 255, 0.85); margin-bottom: 1.5rem; line-height: 1.6;">Enter your new password.</p>
                <form id="forgotPasswordForm3" onsubmit="resetPassword(event)" style="display: grid; gap: 1.75rem;">
                    <div class="field">
                        <label for="newPassword" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">New Password *</label>
                        <input id="newPassword" name="new_password" type="password" placeholder="Enter new password" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                    </div>
                    <div class="field">
                        <label for="confirmPassword" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Confirm Password *</label>
                        <input id="confirmPassword" name="confirm_password" type="password" placeholder="Confirm new password" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                    </div>
                    <div id="forgotPasswordMessage3" style="display: none; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem;"></div>
                    <div class="button-group" style="margin-top: 0.5rem;">
                        <button type="button" class="btn btn-secondary" onclick="backToStep2()" style="flex: 1;">Back</button>
                        <button type="submit" class="btn" style="flex: 1;">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Message Modal -->
    <div id="successModal" class="modal" style="display: none; position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
        <div class="modal-content" style="background: linear-gradient(145deg, #10b981, #059669); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 16px; box-shadow: 0 20px 50px -25px rgba(0, 0, 0, 0.5); padding: 2.5rem; max-width: 500px; width: 90%; text-align: center; animation: slideIn 0.3s ease-out;">
            <div style="margin-bottom: 1.5rem;">
                <div style="width: 80px; height: 80px; margin: 0 auto; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; animation: scaleIn 0.3s ease-out;">
                    <i class="fas fa-check-circle" style="font-size: 3rem; color: #fff;"></i>
                </div>
            </div>
            <h2 style="margin: 0 0 1rem 0; color: #fff; font-size: 1.75rem; font-weight: 600;">Success!</h2>
            <p id="successMessage" style="margin: 0 0 2rem 0; color: rgba(255, 255, 255, 0.95); font-size: 1.1rem; line-height: 1.6;"></p>
            <button onclick="closeSuccessModal()" style="padding: 0.875rem 2rem; background: rgba(255, 255, 255, 0.2); color: #fff; border: 2px solid rgba(255, 255, 255, 0.3); border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; width: 100%;">OK</button>
        </div>
    </div>

    <script>
        function showSuccessModal(title, message, isError = false) {
            const modal = document.getElementById('successModal');
            const titleElement = modal.querySelector('h2');
            const messageElement = document.getElementById('successMessage');
            const iconElement = modal.querySelector('i');
            const modalContent = modal.querySelector('.modal-content');
            
            titleElement.textContent = title;
            messageElement.innerHTML = message;
            
            if (isError) {
                modalContent.style.background = 'linear-gradient(145deg, #ef4444, #dc2626)';
                iconElement.className = 'fas fa-exclamation-circle';
            } else {
                modalContent.style.background = 'linear-gradient(145deg, #10b981, #059669)';
                iconElement.className = 'fas fa-check-circle';
            }
            
            modal.style.display = 'flex';
        }

        function closeSuccessModal() {
            document.getElementById('successModal').style.display = 'none';
        }

        // Forgot Password Functions
        function openForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').style.display = 'flex';
            resetForgotPasswordModal();
        }

        function closeForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').style.display = 'none';
            resetForgotPasswordModal();
        }

        function resetForgotPasswordModal() {
            document.getElementById('forgotPasswordStep1').style.display = 'block';
            document.getElementById('forgotPasswordStep2').style.display = 'none';
            document.getElementById('forgotPasswordStep3').style.display = 'none';
            document.getElementById('forgotPasswordForm1').reset();
            document.getElementById('forgotPasswordForm2').reset();
            document.getElementById('forgotPasswordForm3').reset();
            document.getElementById('forgotPasswordMessage').style.display = 'none';
            document.getElementById('forgotPasswordMessage2').style.display = 'none';
            document.getElementById('forgotPasswordMessage3').style.display = 'none';
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

        async function requestOTP(event) {
            event.preventDefault();
            const username = document.getElementById('forgotUsername').value.trim();
            const messageDiv = document.getElementById('forgotPasswordMessage');
            
            if (!username) {
                messageDiv.textContent = 'Please enter your username.';
                messageDiv.style.display = 'block';
                messageDiv.style.color = '#ef4444';
                messageDiv.style.background = 'rgba(239, 68, 68, 0.1)';
                messageDiv.style.border = '1px solid rgba(239, 68, 68, 0.2)';
                return;
            }
            
            try {
                const response = await fetch('api/forgot-password.php?action=request', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    messageDiv.textContent = result.message;
                    messageDiv.style.display = 'block';
                    messageDiv.style.color = '#10b981';
                    messageDiv.style.background = 'rgba(16, 185, 129, 0.1)';
                    messageDiv.style.border = '1px solid rgba(16, 185, 129, 0.2)';
                    
                    // Move to step 2
                    setTimeout(() => {
                        document.getElementById('forgotPasswordStep1').style.display = 'none';
                        document.getElementById('forgotPasswordStep2').style.display = 'block';
                        document.getElementById('forgotPasswordTitle').textContent = 'Verify OTP';
                        document.getElementById('forgotOTP').focus();
                    }, 1000);
                } else {
                    messageDiv.textContent = result.message;
                    messageDiv.style.display = 'block';
                    messageDiv.style.color = '#ef4444';
                    messageDiv.style.background = 'rgba(239, 68, 68, 0.1)';
                    messageDiv.style.border = '1px solid rgba(239, 68, 68, 0.2)';
                }
            } catch (err) {
                console.error('Error requesting OTP:', err);
                messageDiv.textContent = 'An error occurred. Please try again.';
                messageDiv.style.display = 'block';
                messageDiv.style.color = '#ef4444';
                messageDiv.style.background = 'rgba(239, 68, 68, 0.1)';
                messageDiv.style.border = '1px solid rgba(239, 68, 68, 0.2)';
            }
        }

        async function verifyOTP(event) {
            event.preventDefault();
            const otp = document.getElementById('forgotOTP').value.trim();
            const messageDiv = document.getElementById('forgotPasswordMessage2');
            
            if (!otp || otp.length !== 6) {
                messageDiv.textContent = 'Please enter a valid 6-digit OTP code.';
                messageDiv.style.display = 'block';
                messageDiv.style.color = '#ef4444';
                messageDiv.style.background = 'rgba(239, 68, 68, 0.1)';
                messageDiv.style.border = '1px solid rgba(239, 68, 68, 0.2)';
                return;
            }
            
            try {
                const response = await fetch('api/forgot-password.php?action=verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ otp })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Move to step 3
                    document.getElementById('forgotPasswordStep2').style.display = 'none';
                    document.getElementById('forgotPasswordStep3').style.display = 'block';
                    document.getElementById('forgotPasswordTitle').textContent = 'Reset Password';
                    document.getElementById('newPassword').focus();
                } else {
                    messageDiv.textContent = result.message;
                    messageDiv.style.display = 'block';
                    messageDiv.style.color = '#ef4444';
                    messageDiv.style.background = 'rgba(239, 68, 68, 0.1)';
                    messageDiv.style.border = '1px solid rgba(239, 68, 68, 0.2)';
                }
            } catch (err) {
                console.error('Error verifying OTP:', err);
                messageDiv.textContent = 'An error occurred. Please try again.';
                messageDiv.style.display = 'block';
                messageDiv.style.color = '#ef4444';
                messageDiv.style.background = 'rgba(239, 68, 68, 0.1)';
                messageDiv.style.border = '1px solid rgba(239, 68, 68, 0.2)';
            }
        }

        async function resetPassword(event) {
            event.preventDefault();
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const messageDiv = document.getElementById('forgotPasswordMessage3');
            
            if (!newPassword || !confirmPassword) {
                messageDiv.textContent = 'Please fill in all fields.';
                messageDiv.style.display = 'block';
                messageDiv.style.color = '#ef4444';
                messageDiv.style.background = 'rgba(239, 68, 68, 0.1)';
                messageDiv.style.border = '1px solid rgba(239, 68, 68, 0.2)';
                return;
            }
            
            if (newPassword !== confirmPassword) {
                messageDiv.textContent = 'Passwords do not match.';
                messageDiv.style.display = 'block';
                messageDiv.style.color = '#ef4444';
                messageDiv.style.background = 'rgba(239, 68, 68, 0.1)';
                messageDiv.style.border = '1px solid rgba(239, 68, 68, 0.2)';
                return;
            }
            
            if (newPassword.length < 6) {
                messageDiv.textContent = 'Password must be at least 6 characters long.';
                messageDiv.style.display = 'block';
                messageDiv.style.color = '#ef4444';
                messageDiv.style.background = 'rgba(239, 68, 68, 0.1)';
                messageDiv.style.border = '1px solid rgba(239, 68, 68, 0.2)';
                return;
            }
            
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
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    messageDiv.textContent = result.message;
                    messageDiv.style.display = 'block';
                    messageDiv.style.color = '#ef4444';
                    messageDiv.style.background = 'rgba(239, 68, 68, 0.1)';
                    messageDiv.style.border = '1px solid rgba(239, 68, 68, 0.2)';
                }
            } catch (err) {
                console.error('Error resetting password:', err);
                messageDiv.textContent = 'An error occurred. Please try again.';
                messageDiv.style.display = 'block';
                messageDiv.style.color = '#ef4444';
                messageDiv.style.background = 'rgba(239, 68, 68, 0.1)';
                messageDiv.style.border = '1px solid rgba(239, 68, 68, 0.2)';
            }
        }

        // Auto-format OTP input
        document.addEventListener('DOMContentLoaded', function() {
            const otpInput = document.getElementById('forgotOTP');
            if (otpInput) {
                otpInput.addEventListener('input', function(e) {
                    e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
                });
            }
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const forgotPasswordModal = document.getElementById('forgotPasswordModal');
            const successModal = document.getElementById('successModal');
            
            if (event.target === forgotPasswordModal) {
                closeForgotPasswordModal();
            }
            if (event.target === successModal) {
                closeSuccessModal();
            }
        });
        
        // Check if reset=1 is in URL and open forgot password modal
        if (window.location.search.includes('reset=1')) {
            openForgotPasswordModal();
            // Remove reset=1 from URL without reload
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    </script>
</body>
</html>

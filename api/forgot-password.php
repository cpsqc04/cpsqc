<?php
session_start();
ob_start();
header('Content-Type: application/json');

// Load database connection (this also loads .env file)
require_once __DIR__ . '/../db.php';

// Load PHPMailer via Composer autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Ensure $_ENV is populated (db.php should load it, but double-check)
if (empty($_ENV) && file_exists(__DIR__ . '/../.env')) {
    // Fallback: load .env manually if not loaded
    $envPath = __DIR__ . '/../.env';
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, '"\'');
            $_ENV[$key] = $value;
        }
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Generate OTP
function generateOTP() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Send OTP via email (using PHPMailer if available, otherwise use native mail)
function sendOTPEmail($email, $otp) {
    // Get email credentials from .env (with fallback to direct file read)
    $mailHost = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
    $mailPort = (int)($_ENV['MAIL_PORT'] ?? 465);
    $mailUser = $_ENV['MAIL_USERNAME'] ?? '';
    $mailPass = $_ENV['MAIL_PASSWORD'] ?? '';
    $mailFrom = $_ENV['MAIL_FROM_ADDRESS'] ?? $mailUser;
    $mailFromName = $_ENV['MAIL_FROM_NAME'] ?? 'AlerTara QC';
    $mailEncryption = $_ENV['MAIL_ENCRYPTION'] ?? 'ssl';
    
    // If credentials are empty, try to load from .env file directly
    if (empty($mailUser) || empty($mailPass)) {
        $envPath = __DIR__ . '/../.env';
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
    
    if (empty($mailUser) || empty($mailPass)) {
        error_log('OTP email failed: Email credentials not set in .env - MAIL_USERNAME: ' . (!empty($mailUser) ? 'set' : 'empty') . ', MAIL_PASSWORD: ' . (!empty($mailPass) ? 'set' : 'empty'));
        error_log('Email config check - Host: ' . $mailHost . ', Port: ' . $mailPort . ', Encryption: ' . $mailEncryption);
        error_log('ENV vars available: ' . implode(', ', array_keys($_ENV)));
        return false;
    }
    
    if (empty($email)) {
        error_log('OTP email failed: Recipient email address is empty');
        return false;
    }
    
    // Check if PHPMailer is available
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'alertaraqc@gmail.com'; // Update with your email
            $mail->Password = 'fyyzywptnqlqemyt'; // Update with your app password
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Recipients
            $mail->setFrom('noreply@alertaraqc.com', 'AlerTara QC');
            $mail->addAddress($email);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset OTP - AlerTara QC';
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
                    .header p { margin: 10px 0 0 0; font-size: 16px; opacity: 0.95; }
                    .body-content { padding: 30px 20px; background: #ffffff; }
                    .body-content p { margin: 0 0 15px 0; color: #333; font-size: 14px; }
                    .otp-box { background: #ffffff; border: 2px solid #4c8a89; border-radius: 8px; padding: 20px; text-align: center; margin: 25px 0; }
                    .otp-code { font-size: 32px; font-weight: 700; color: #4c8a89; letter-spacing: 4px; font-family: 'Courier New', monospace; }
                    .expiry-notice { color: #666; font-size: 13px; margin-top: 15px; }
                    .disclaimer { color: #666; font-size: 12px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0; }
                    .footer { background: #f5f5f5; padding: 15px 20px; text-align: center; color: #999; font-size: 11px; }
                </style>
            </head>
            <body>
                <div class='email-container'>
                    <div class='header'>
                        <h1>AlerTara QC</h1>
                        <p>Password Reset Request</p>
                    </div>
                    <div class='body-content'>
                        <p>Hello,</p>
                        <p>You have requested to reset your password. Please use the following OTP to complete the process:</p>
                        <div class='otp-box'>
                            <div class='otp-code'>{$otp}</div>
                        </div>
                        <p class='expiry-notice'>This OTP will expire in 10 minutes.</p>
                        <p class='disclaimer'>If you did not request this password reset, please ignore this email.</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->send();
            error_log("OTP email sent successfully via PHPMailer to {$email}");
            return true;
        } catch (Exception $e) {
            $errorMsg = 'PHPMailer send failed: ' . ($mail->ErrorInfo ?? $e->getMessage());
            error_log($errorMsg);
            error_log('PHPMailer Exception details: ' . $e->getMessage());
            error_log('Email config - Host: ' . $mailHost . ', Port: ' . $mailPort . ', User: ' . ($mailUser ? 'set' : 'empty') . ', Encryption: ' . $mailEncryption);
            error_log('Recipient: ' . $email);
            // Fall through to try native mail()
        }
    }
    
    // Fallback to native PHP mail() function
    try {
        $subject = 'AlerTara QC Password Reset Request';
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; }
                .header { background: linear-gradient(135deg, #4c8a89 0%, #2a5a59 100%); color: white; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
                .header p { margin: 10px 0 0 0; font-size: 16px; opacity: 0.95; }
                .body-content { padding: 30px 20px; background: #ffffff; }
                .body-content p { margin: 0 0 15px 0; color: #333; font-size: 14px; }
                .otp-box { background: #ffffff; border: 2px solid #4c8a89; border-radius: 8px; padding: 20px; text-align: center; margin: 25px 0; }
                .otp-code { font-size: 32px; font-weight: 700; color: #4c8a89; letter-spacing: 4px; font-family: 'Courier New', monospace; }
                .expiry-notice { color: #666; font-size: 13px; margin-top: 15px; }
                .disclaimer { color: #666; font-size: 12px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0; }
                .footer { background: #f5f5f5; padding: 15px 20px; text-align: center; color: #999; font-size: 11px; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>AlerTara QC</h1>
                    <p>Password Reset Request</p>
                </div>
                <div class='body-content'>
                    <p>Hello,</p>
                    <p>You have requested to reset your password. Please use the following OTP to complete the process:</p>
                    <div class='otp-box'>
                        <div class='otp-code'>{$otp}</div>
                    </div>
                    <p class='expiry-notice'>This OTP will expire in 10 minutes.</p>
                    <p class='disclaimer'>If you did not request this password reset, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$mailFromName} <{$mailFrom}>\r\n";
        $headers .= "Reply-To: {$mailFrom}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        $result = mail($email, $subject, $message, $headers);
        
        if ($result) {
            error_log("OTP email sent successfully via native mail() to {$email}");
            return true;
        } else {
            error_log("Native mail() failed to send OTP to {$email}");
            error_log("Email config - From: {$mailFrom} ({$mailFromName}), Host: {$mailHost}, Port: {$mailPort}");
            // Log OTP for debugging (only in development - remove in production)
            if (isset($_ENV['ENVIRONMENT']) && $_ENV['ENVIRONMENT'] !== 'production') {
                error_log("OTP for {$email}: {$otp}");
            }
            return false;
        }
    } catch (Exception $e) {
        error_log('Native mail() exception: ' . $e->getMessage());
        // Log OTP for debugging
        error_log("OTP for {$email}: {$otp}");
        return false;
    }
}

if ($action === 'request') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = trim($data['email'] ?? '');
        
        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Email is required.']);
            exit;
        }
        
        // Find user by email
        $stmt = $pdo->prepare('SELECT id, username, email FROM admins WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Don't reveal if user exists for security
            echo json_encode(['success' => true, 'message' => 'If the email exists, an OTP has been sent to the registered email.']);
            exit;
        }
        
        if (empty($user['email'])) {
            echo json_encode(['success' => false, 'message' => 'No email address found for this account. Please contact administrator.']);
            exit;
        }
        
        // Generate and store OTP
        $otp = generateOTP();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Store OTP in session (in production, use database or Redis)
        $_SESSION['password_reset'] = [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'otp' => $otp,
            'expires_at' => $expiresAt,
            'attempts' => 0
        ];
        
        // Send OTP email
        ob_clean();
        $emailSent = sendOTPEmail($user['email'], $otp);
        
        if ($emailSent) {
            echo json_encode([
                'success' => true,
                'message' => 'OTP has been sent to your registered email address.',
                'email' => substr($user['email'], 0, 3) . '***' . substr($user['email'], strpos($user['email'], '@'))
            ]);
        } else {
            // Log detailed error information
            error_log('Failed to send OTP email to: ' . $user['email']);
            error_log('Check PHP error logs for detailed email sending errors.');
            
            // Provide user-friendly error message
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to send OTP email. This may be due to email configuration issues. Please contact the administrator or check if your email address is correct in your account settings.'
            ]);
        }
        
    } catch (Exception $e) {
        error_log('Error in forgot password request: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
    
} elseif ($action === 'verify') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $otp = trim($data['otp'] ?? '');
        
        if (empty($otp)) {
            echo json_encode(['success' => false, 'message' => 'OTP is required.']);
            exit;
        }
        
        if (!isset($_SESSION['password_reset'])) {
            echo json_encode(['success' => false, 'message' => 'No password reset request found. Please request a new OTP.']);
            exit;
        }
        
        $resetData = $_SESSION['password_reset'];
        
        // Check expiration
        if (strtotime($resetData['expires_at']) < time()) {
            unset($_SESSION['password_reset']);
            echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
            exit;
        }
        
        // Check attempts
        if ($resetData['attempts'] >= 5) {
            unset($_SESSION['password_reset']);
            echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please request a new OTP.']);
            exit;
        }
        
        // Verify OTP
        if ($resetData['otp'] !== $otp) {
            $_SESSION['password_reset']['attempts']++;
            echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
            exit;
        }
        
        // OTP verified - mark as verified
        $_SESSION['password_reset']['verified'] = true;
        echo json_encode(['success' => true, 'message' => 'OTP verified successfully.']);
        
    } catch (Exception $e) {
        error_log('Error in OTP verification: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
    
} elseif ($action === 'reset') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $newPassword = $data['new_password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';
        
        if (empty($newPassword) || empty($confirmPassword)) {
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
        
        if (!isset($_SESSION['password_reset']) || !isset($_SESSION['password_reset']['verified'])) {
            echo json_encode(['success' => false, 'message' => 'OTP verification required.']);
            exit;
        }
        
        $resetData = $_SESSION['password_reset'];
        
        // Check expiration
        if (strtotime($resetData['expires_at']) < time()) {
            unset($_SESSION['password_reset']);
            echo json_encode(['success' => false, 'message' => 'Session expired. Please request a new OTP.']);
            exit;
        }
        
        // Update password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE admins SET password_hash = :p WHERE id = :id');
        $stmt->execute([
            ':p' => $passwordHash,
            ':id' => $resetData['user_id']
        ]);
        
        // Clear reset session
        unset($_SESSION['password_reset']);
        
        echo json_encode(['success' => true, 'message' => 'Password has been reset successfully. You can now login with your new password.']);
        
    } catch (Exception $e) {
        error_log('Error in password reset: ' . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
    
} else {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}

ob_end_flush();

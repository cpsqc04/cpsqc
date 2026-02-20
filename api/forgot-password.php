<?php
session_start();
ob_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

// Load PHPMailer via Composer autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Generate OTP
function generateOTP() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Send OTP via email (using PHPMailer if available, otherwise use native mail)
function sendOTPEmail($email, $otp) {
    // Get email credentials from .env
    $mailHost = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
    $mailPort = (int)($_ENV['MAIL_PORT'] ?? 465);
    $mailUser = $_ENV['MAIL_USERNAME'] ?? 'cpsqc04@gmail.com';
    $mailPass = $_ENV['MAIL_PASSWORD'] ?? 'izoanendvacbwftf';
    $mailFrom = $_ENV['MAIL_FROM_ADDRESS'] ?? $mailUser;
    $mailFromName = $_ENV['MAIL_FROM_NAME'] ?? 'AlerTara QC';
    $mailEncryption = $_ENV['MAIL_ENCRYPTION'] ?? 'ssl';
    
    if (empty($mailUser) || empty($mailPass)) {
        error_log('Email credentials not set in .env - MAIL_USERNAME: ' . (!empty($mailUser) ? 'set' : 'empty') . ', MAIL_PASSWORD: ' . (!empty($mailPass) ? 'set' : 'empty'));
        return false;
    }
    
    // Check if PHPMailer is available
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
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
            
            // Recipients
            $mail->setFrom($mailFrom, $mailFromName);
            $mail->addAddress($email);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'AlerTara QC Password Reset Request';
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
            error_log("OTP email sent successfully to {$email}");
            return true;
        } catch (Exception $e) {
            error_log('PHPMailer send failed: ' . $mail->ErrorInfo);
            error_log('Exception: ' . $e->getMessage());
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
            error_log("Native mail() sent OTP to {$email}");
            return true;
        } else {
            error_log("Native mail() failed to send to {$email}");
            // Log OTP for debugging
            error_log("OTP for {$email}: {$otp}");
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
        $username = trim($data['username'] ?? '');
        
        if (empty($username)) {
            echo json_encode(['success' => false, 'message' => 'Username is required.']);
            exit;
        }
        
        // Find user by username
        $stmt = $pdo->prepare('SELECT id, username, email FROM admins WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Don't reveal if user exists for security
            echo json_encode(['success' => true, 'message' => 'If the username exists, an OTP has been sent to the registered email.']);
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
            // For debugging: check error log
            error_log('Failed to send OTP email. Check error logs for details.');
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to send OTP. Please check your email configuration or contact administrator. Check error logs for details.'
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

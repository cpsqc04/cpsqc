<?php
/**
 * Email Configuration Test Script
 * Use this to test if email configuration is working
 * Access: https://surveillance.alertaraqc.com/api/test-email.php?email=your-email@example.com
 */

session_start();
header('Content-Type: application/json');

// Only allow admins or localhost
$isLocalhost = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost']);
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!$isLocalhost && !$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

require_once __DIR__ . '/../db.php';

// Load PHPMailer
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo json_encode([
        'success' => false, 
        'error' => 'PHPMailer not installed',
        'message' => 'Run: composer install'
    ]);
    exit;
}
require_once $autoloadPath;

// Get test email from query parameter
$testEmail = $_GET['email'] ?? $_POST['email'] ?? '';

if (empty($testEmail)) {
    echo json_encode([
        'success' => false,
        'error' => 'Email parameter required',
        'usage' => '?email=your-email@example.com'
    ]);
    exit;
}

// Load email configuration
$mailHost = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
$mailPort = (int)($_ENV['MAIL_PORT'] ?? 465);
$mailUser = $_ENV['MAIL_USERNAME'] ?? '';
$mailPass = $_ENV['MAIL_PASSWORD'] ?? '';
$mailFrom = $_ENV['MAIL_FROM_ADDRESS'] ?? $mailUser;
$mailFromName = $_ENV['MAIL_FROM_NAME'] ?? 'AlerTara QC';
$mailEncryption = $_ENV['MAIL_ENCRYPTION'] ?? 'ssl';

// Fallback: load from .env file directly
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
                if ($key === 'MAIL_USERNAME') $mailUser = $value;
                if ($key === 'MAIL_PASSWORD') $mailPass = $value;
                if ($key === 'MAIL_HOST') $mailHost = $value;
                if ($key === 'MAIL_PORT') $mailPort = (int)$value;
                if ($key === 'MAIL_FROM_ADDRESS') $mailFrom = $value;
                if ($key === 'MAIL_FROM_NAME') $mailFromName = $value;
                if ($key === 'MAIL_ENCRYPTION') $mailEncryption = $value;
            }
        }
    }
}

$result = [
    'success' => false,
    'config' => [
        'host' => $mailHost,
        'port' => $mailPort,
        'username' => $mailUser ? '***set***' : 'NOT SET',
        'password' => $mailPass ? '***set***' : 'NOT SET',
        'from' => $mailFrom,
        'from_name' => $mailFromName,
        'encryption' => $mailEncryption,
    ],
    'errors' => []
];

if (empty($mailUser) || empty($mailPass)) {
    $result['errors'][] = 'MAIL_USERNAME or MAIL_PASSWORD not set in .env file';
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

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
    $mail->addAddress($testEmail);
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'AlerTara QC - Email Test';
    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='success'>
                <h2>Email Test Successful!</h2>
                <p>If you received this email, your email configuration is working correctly.</p>
                <p><strong>Test Time:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p><strong>Server:</strong> " . $_SERVER['SERVER_NAME'] . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $mail->send();
    $result['success'] = true;
    $result['message'] = "Test email sent successfully to {$testEmail}";
    error_log("Test email sent successfully to {$testEmail}");
    
} catch (Exception $e) {
    $result['success'] = false;
    $result['errors'][] = 'PHPMailer Error: ' . ($mail->ErrorInfo ?? $e->getMessage());
    $result['errors'][] = 'Exception: ' . $e->getMessage();
    error_log('Test email failed: ' . ($mail->ErrorInfo ?? $e->getMessage()));
}

echo json_encode($result, JSON_PRETTY_PRINT);


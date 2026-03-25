<?php
// Load PHPMailer via Composer autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}
require_once __DIR__ . '/../db.php';

echo "Email Configuration Test\n";
echo "========================\n\n";

$mailHost = $_ENV['MAIL_HOST'] ?? '';
$mailPort = $_ENV['MAIL_PORT'] ?? '';
$mailUser = $_ENV['MAIL_USERNAME'] ?? '';
$mailPassRaw = $_ENV['MAIL_PASSWORD'] ?? '';
$mailFrom = $_ENV['MAIL_FROM_ADDRESS'] ?? '';
$mailFromName = $_ENV['MAIL_FROM_NAME'] ?? '';
$mailEncryption = $_ENV['MAIL_ENCRYPTION'] ?? '';

echo "MAIL_HOST: " . ($mailHost !== '' ? $mailHost : 'not set') . "\n";
echo "MAIL_PORT: " . ($mailPort !== '' ? $mailPort : 'not set') . "\n";
echo "MAIL_USERNAME: " . ($mailUser !== '' ? '***set***' : 'not set') . "\n";
echo "MAIL_PASSWORD: " . ($mailPassRaw !== '' ? '***set***' : 'not set') . "\n";
echo "MAIL_FROM_ADDRESS: " . ($mailFrom !== '' ? $mailFrom : 'not set') . "\n";
echo "MAIL_FROM_NAME: " . ($mailFromName !== '' ? $mailFromName : 'not set') . "\n";
echo "MAIL_ENCRYPTION: " . ($mailEncryption !== '' ? $mailEncryption : 'not set') . "\n\n";

// Check if PHPMailer is available
if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    echo "PHPMailer: Available\n";
} else {
    echo "PHPMailer: Not available (will use native mail())\n";
}

echo "\nAll email credentials are " . (($mailUser === '' || $mailPassRaw === '') ? "MISSING" : "SET") . "\n";


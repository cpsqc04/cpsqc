<?php
// Load PHPMailer via Composer autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}
require_once __DIR__ . '/../db.php';

echo "Email Configuration Test\n";
echo "========================\n\n";

$mailHost = $_ENV['MAIL_HOST'] ?? 'not set';
$mailPort = $_ENV['MAIL_PORT'] ?? 'not set';
$mailUser = $_ENV['MAIL_USERNAME'] ?? 'not set';
$mailPass = !empty($_ENV['MAIL_PASSWORD']) ? '***set***' : 'not set';
$mailFrom = $_ENV['MAIL_FROM_ADDRESS'] ?? 'not set';
$mailFromName = $_ENV['MAIL_FROM_NAME'] ?? 'not set';
$mailEncryption = $_ENV['MAIL_ENCRYPTION'] ?? 'not set';

echo "MAIL_HOST: {$mailHost}\n";
echo "MAIL_PORT: {$mailPort}\n";
echo "MAIL_USERNAME: {$mailUser}\n";
echo "MAIL_PASSWORD: {$mailPass}\n";
echo "MAIL_FROM_ADDRESS: {$mailFrom}\n";
echo "MAIL_FROM_NAME: {$mailFromName}\n";
echo "MAIL_ENCRYPTION: {$mailEncryption}\n\n";

// Check if PHPMailer is available
if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    echo "PHPMailer: Available\n";
} else {
    echo "PHPMailer: Not available (will use native mail())\n";
}

echo "\nAll email credentials are " . (empty($mailUser) || empty($mailPass) ? "MISSING" : "SET") . "\n";


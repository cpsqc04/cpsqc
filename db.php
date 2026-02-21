<?php
// Central database connection for CPS using the LGU database.
// All modules should include this file (require_once 'db.php') to use the same PDO instance.
// Uses .env file for configuration with automatic environment detection.

// Set default timezone to Philippines (Asia/Manila)
date_default_timezone_set('Asia/Manila');

/**
 * Load environment variables from .env file
 */
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }
    
    $env = [];
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove quotes if present
            $value = trim($value, '"\'');
            $env[$key] = $value;
        }
    }
    
    return $env;
}

/**
 * Detect if we're on production server
 */
function isProduction() {
    // Check if environment is explicitly set
    if (isset($_ENV['ENVIRONMENT']) && $_ENV['ENVIRONMENT'] !== 'auto') {
        return $_ENV['ENVIRONMENT'] === 'production';
    }
    
    // Auto-detect based on HTTP_HOST
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $productionDomains = ['surveillance.alertaraqc.com', 'www.surveillance.alertaraqc.com'];
    
    foreach ($productionDomains as $domain) {
        if (strpos($host, $domain) !== false) {
            return true;
        }
    }
    
    return false;
}

// Load .env file
$envPath = __DIR__ . DIRECTORY_SEPARATOR . '.env';
$env = loadEnv($envPath);

// Merge into $_ENV for easy access
foreach ($env as $key => $value) {
    $_ENV[$key] = $value;
}

// Determine environment
$isProd = isProduction();

// Set database credentials based on environment
if ($isProd) {
    // Production credentials
    $dbHost = $_ENV['DB_HOST_PROD'] ?? 'localhost';
    $dbName = $_ENV['DB_NAME_PROD'] ?? 'LGU';
    $dbUser = $_ENV['DB_USER_PROD'] ?? 'root';
    $dbPass = $_ENV['DB_PASS_PROD'] ?? 'YsqnXk6q#145';
    $dbPort = (int)($_ENV['DB_PORT_PROD'] ?? 3306);
} else {
    // Local development credentials
    $dbHost = $_ENV['DB_HOST_LOCAL'] ?? 'localhost';
    $dbName = $_ENV['DB_NAME_LOCAL'] ?? 'LGU';
    $dbUser = $_ENV['DB_USER_LOCAL'] ?? 'root';
    $dbPass = $_ENV['DB_PASS_LOCAL'] ?? '';
    $dbPort = (int)($_ENV['DB_PORT_LOCAL'] ?? 3306);
}

try {
    // Attempt to connect to MySQL server without specifying a database
    $pdo_no_db = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Create the database if it doesn't exist
    $pdo_no_db->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Now connect to the specific database
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

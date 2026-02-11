<?php
// 1. Online (Production) Credentials
// vvv  REPLACE THESE WITH YOUR ACTUAL PRODUCTION CREDENTIALS vvv

$online_creds = [
    'host' => 'localhost',        // e.g., 'your-db-hostname'
    'db'   => 'LGU',              // Database name
    'user' => 'root',             // Database username
    'pass' => 'YsqnXk6q#145',     // Database password
    'port' => 3306,               // MySQL port
];

// Simple PDO connection using the production credentials above.
// You can `require_once 'db_prod.php';` wherever you need a production DB connection.

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $online_creds['host'],
        $online_creds['port'],
        $online_creds['db']
    );

    $pdo = new PDO($dsn, $online_creds['user'], $online_creds['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('Production DB connection failed: ' . htmlspecialchars($e->getMessage()));
}



<?php
// Central database connection for CPS.
// For local XAMPP: uses default MySQL (empty password, cps_db database)
// For production: change to LGU database credentials

$dbHost = 'localhost';
$dbName = 'cps_db';  // Change to 'LGU' for production
$dbUser = 'root';
$dbPass = '';  // Empty password for XAMPP default. Change to 'YsqnXk6q#145' for production LGU database
$dbPort = 3306;

try {
    // First, connect without database to create it if needed
    $dsn_no_db = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
    $pdo_temp = new PDO($dsn_no_db, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    // Create database if it doesn't exist
    $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
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



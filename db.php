<?php
// Simple database connection helper for cps_db.
// Adjust the credentials below to match your XAMPP/MySQL setup if needed.

$dbHost = '127.0.0.1';
$dbName = 'cps_db';
$dbUser = 'root';
$dbPass = ''; // Default XAMPP MySQL root has no password

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}





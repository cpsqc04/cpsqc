<?php
// Central database connection for CPS using the LGU database.
// All modules should include this file (require_once 'db.php') to use the same PDO instance.

$dbHost = 'localhost';
$dbName = 'LGU';
$dbUser = 'root';
$dbPass = 'YsqnXk6q#145';
$dbPort = 3306;

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}



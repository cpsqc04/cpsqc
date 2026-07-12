<?php

/**
 * Ensure the patrol_logs table exists and uses personnel_name.
 */
function ensurePatrolLogsTable(PDO $pdo): void
{
    $columns = [];
    $tableExists = false;

    try {
        foreach ($pdo->query('SHOW COLUMNS FROM patrol_logs') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        $pdo->exec("CREATE TABLE patrol_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patrol_id INT NULL,
            schedule_id INT NULL,
            personnel_name VARCHAR(255) NOT NULL,
            route VARCHAR(255) NOT NULL DEFAULT '',
            date DATE NOT NULL,
            time VARCHAR(50) DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Scheduled',
            incidents TEXT DEFAULT NULL,
            details TEXT DEFAULT NULL,
            location TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_patrol_id (patrol_id),
            INDEX idx_schedule_id (schedule_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }

    if (isset($columns['officer_name']) && !isset($columns['personnel_name'])) {
        $pdo->exec('ALTER TABLE patrol_logs CHANGE officer_name personnel_name VARCHAR(255) NOT NULL');
        unset($columns['officer_name']);
        $columns['personnel_name'] = true;
    }

    if (!isset($columns['personnel_name'])) {
        $pdo->exec('ALTER TABLE patrol_logs ADD COLUMN personnel_name VARCHAR(255) NOT NULL DEFAULT "" AFTER id');
    }
    if (!isset($columns['patrol_id'])) {
        $pdo->exec('ALTER TABLE patrol_logs ADD COLUMN patrol_id INT NULL AFTER id');
    }
    if (!isset($columns['schedule_id'])) {
        $pdo->exec('ALTER TABLE patrol_logs ADD COLUMN schedule_id INT NULL AFTER patrol_id');
    }
    if (!isset($columns['route'])) {
        $pdo->exec('ALTER TABLE patrol_logs ADD COLUMN route VARCHAR(255) NOT NULL DEFAULT "" AFTER personnel_name');
    }
    if (!isset($columns['date'])) {
        $pdo->exec('ALTER TABLE patrol_logs ADD COLUMN date DATE NOT NULL DEFAULT "1970-01-01" AFTER route');
    }
    if (!isset($columns['time'])) {
        $pdo->exec('ALTER TABLE patrol_logs ADD COLUMN time VARCHAR(50) DEFAULT NULL AFTER date');
    }
    if (!isset($columns['status'])) {
        $pdo->exec('ALTER TABLE patrol_logs ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT "Scheduled" AFTER time');
    }
    if (!isset($columns['incidents'])) {
        $pdo->exec('ALTER TABLE patrol_logs ADD COLUMN incidents TEXT DEFAULT NULL AFTER status');
    }
    if (!isset($columns['details'])) {
        $pdo->exec('ALTER TABLE patrol_logs ADD COLUMN details TEXT DEFAULT NULL AFTER incidents');
    }
    if (!isset($columns['location'])) {
        $pdo->exec('ALTER TABLE patrol_logs ADD COLUMN location TEXT DEFAULT NULL AFTER details');
    }
    if (!isset($columns['created_at'])) {
        $pdo->exec('ALTER TABLE patrol_logs ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    }
}

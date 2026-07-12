<?php

/**
 * Ensure patrol_schedules table exists.
 */
function ensurePatrolSchedulesTable(PDO $pdo): void
{
    $columns = [];
    $tableExists = false;

    try {
        foreach ($pdo->query('SHOW COLUMNS FROM patrol_schedules') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        $pdo->exec("CREATE TABLE patrol_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patrol_id INT NOT NULL,
            personnel_name VARCHAR(255) NOT NULL,
            route VARCHAR(255) NOT NULL,
            location TEXT DEFAULT NULL,
            schedule_date DATE NOT NULL,
            schedule_time VARCHAR(50) NOT NULL,
            notes TEXT DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_patrol_id (patrol_id),
            INDEX idx_schedule_date (schedule_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }

    if (!isset($columns['patrol_id'])) {
        $pdo->exec('ALTER TABLE patrol_schedules ADD COLUMN patrol_id INT NOT NULL DEFAULT 0 AFTER id');
    }
    if (!isset($columns['personnel_name'])) {
        $pdo->exec('ALTER TABLE patrol_schedules ADD COLUMN personnel_name VARCHAR(255) NOT NULL DEFAULT "" AFTER patrol_id');
    }
    if (!isset($columns['route'])) {
        $pdo->exec('ALTER TABLE patrol_schedules ADD COLUMN route VARCHAR(255) NOT NULL DEFAULT "" AFTER personnel_name');
    }
    if (!isset($columns['location'])) {
        $pdo->exec('ALTER TABLE patrol_schedules ADD COLUMN location TEXT DEFAULT NULL AFTER route');
    }
    if (!isset($columns['schedule_date'])) {
        $pdo->exec('ALTER TABLE patrol_schedules ADD COLUMN schedule_date DATE NOT NULL DEFAULT "1970-01-01" AFTER location');
    }
    if (!isset($columns['schedule_time'])) {
        $pdo->exec('ALTER TABLE patrol_schedules ADD COLUMN schedule_time VARCHAR(50) NOT NULL DEFAULT "" AFTER schedule_date');
    }
    if (!isset($columns['notes'])) {
        $pdo->exec('ALTER TABLE patrol_schedules ADD COLUMN notes TEXT DEFAULT NULL AFTER schedule_time');
    }
    if (!isset($columns['status'])) {
        $pdo->exec('ALTER TABLE patrol_schedules ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT "Scheduled" AFTER notes');
    }
    if (!isset($columns['created_at'])) {
        $pdo->exec('ALTER TABLE patrol_schedules ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    }
}

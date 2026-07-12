<?php

/**
 * Ensure bpso_attendance table exists for barangay hall time in/out.
 */
function ensureBpsoAttendanceTable(PDO $pdo): void
{
    $columns = [];
    $tableExists = false;

    try {
        foreach ($pdo->query('SHOW COLUMNS FROM bpso_attendance') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        $pdo->exec("CREATE TABLE bpso_attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patrol_id INT NOT NULL,
            personnel_name VARCHAR(255) NOT NULL,
            bpso_personnel_id VARCHAR(50) DEFAULT NULL,
            attendance_date DATE NOT NULL,
            time_in DATETIME NOT NULL,
            time_out DATETIME DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_patrol_id (patrol_id),
            INDEX idx_attendance_date (attendance_date),
            INDEX idx_open_session (attendance_date, time_out)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }

    $additions = [
        'patrol_id' => 'ALTER TABLE bpso_attendance ADD COLUMN patrol_id INT NOT NULL DEFAULT 0 AFTER id',
        'personnel_name' => 'ALTER TABLE bpso_attendance ADD COLUMN personnel_name VARCHAR(255) NOT NULL DEFAULT "" AFTER patrol_id',
        'bpso_personnel_id' => 'ALTER TABLE bpso_attendance ADD COLUMN bpso_personnel_id VARCHAR(50) DEFAULT NULL AFTER personnel_name',
        'attendance_date' => 'ALTER TABLE bpso_attendance ADD COLUMN attendance_date DATE NOT NULL DEFAULT "1970-01-01" AFTER bpso_personnel_id',
        'time_in' => 'ALTER TABLE bpso_attendance ADD COLUMN time_in DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER attendance_date',
        'time_out' => 'ALTER TABLE bpso_attendance ADD COLUMN time_out DATETIME DEFAULT NULL AFTER time_in',
        'notes' => 'ALTER TABLE bpso_attendance ADD COLUMN notes TEXT DEFAULT NULL AFTER time_out',
        'created_at' => 'ALTER TABLE bpso_attendance ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];

    foreach ($additions as $column => $sql) {
        if (!isset($columns[$column])) {
            $pdo->exec($sql);
        }
    }
}

function bpsoAttendanceSelectColumns(string $prefix = ''): string
{
    $p = $prefix !== '' ? $prefix . '.' : '';
    return "{$p}id, {$p}patrol_id, {$p}personnel_name, {$p}bpso_personnel_id, {$p}attendance_date, {$p}time_in, {$p}time_out, {$p}notes, {$p}created_at";
}

function enrichAttendanceRow(array $row): array
{
    $row['is_at_hall'] = empty($row['time_out']);
    $row['status_label'] = empty($row['time_out']) ? 'At Hall' : 'Timed Out';
    return $row;
}

function isPatrolAtHall(PDO $pdo, int $patrolId): bool
{
    if ($patrolId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM bpso_attendance
         WHERE patrol_id = :patrol_id AND attendance_date = CURDATE() AND time_out IS NULL
         LIMIT 1'
    );
    $stmt->execute([':patrol_id' => $patrolId]);

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

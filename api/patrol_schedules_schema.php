<?php

<<<<<<< HEAD
require_once __DIR__ . '/../includes/patrol_shifts.php';

=======
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
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
<<<<<<< HEAD
            patrol_zone VARCHAR(255) NOT NULL DEFAULT '',
            route VARCHAR(255) NOT NULL DEFAULT '',
            location TEXT DEFAULT NULL,
            schedule_date DATE NOT NULL,
            schedule_time VARCHAR(50) NOT NULL DEFAULT '',
            shift VARCHAR(50) NOT NULL DEFAULT '',
            patrol_start VARCHAR(50) DEFAULT NULL,
            patrol_end VARCHAR(50) DEFAULT NULL,
            duration_minutes INT NOT NULL DEFAULT 0,
=======
            route VARCHAR(255) NOT NULL,
            location TEXT DEFAULT NULL,
            schedule_date DATE NOT NULL,
            schedule_time VARCHAR(50) NOT NULL,
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
            notes TEXT DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_patrol_id (patrol_id),
            INDEX idx_schedule_date (schedule_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
<<<<<<< HEAD

        return;
    }

    $additions = [
        'patrol_id' => 'ALTER TABLE patrol_schedules ADD COLUMN patrol_id INT NOT NULL DEFAULT 0 AFTER id',
        'personnel_name' => 'ALTER TABLE patrol_schedules ADD COLUMN personnel_name VARCHAR(255) NOT NULL DEFAULT "" AFTER patrol_id',
        'patrol_zone' => 'ALTER TABLE patrol_schedules ADD COLUMN patrol_zone VARCHAR(255) NOT NULL DEFAULT "" AFTER personnel_name',
        'route' => 'ALTER TABLE patrol_schedules ADD COLUMN route VARCHAR(255) NOT NULL DEFAULT "" AFTER patrol_zone',
        'location' => 'ALTER TABLE patrol_schedules ADD COLUMN location TEXT DEFAULT NULL AFTER route',
        'schedule_date' => 'ALTER TABLE patrol_schedules ADD COLUMN schedule_date DATE NOT NULL DEFAULT "1970-01-01" AFTER location',
        'schedule_time' => 'ALTER TABLE patrol_schedules ADD COLUMN schedule_time VARCHAR(50) NOT NULL DEFAULT "" AFTER schedule_date',
        'shift' => 'ALTER TABLE patrol_schedules ADD COLUMN shift VARCHAR(50) NOT NULL DEFAULT "" AFTER schedule_time',
        'patrol_start' => 'ALTER TABLE patrol_schedules ADD COLUMN patrol_start VARCHAR(50) DEFAULT NULL AFTER shift',
        'patrol_end' => 'ALTER TABLE patrol_schedules ADD COLUMN patrol_end VARCHAR(50) DEFAULT NULL AFTER patrol_start',
        'duration_minutes' => 'ALTER TABLE patrol_schedules ADD COLUMN duration_minutes INT NOT NULL DEFAULT 0 AFTER patrol_end',
        'notes' => 'ALTER TABLE patrol_schedules ADD COLUMN notes TEXT DEFAULT NULL AFTER duration_minutes',
        'status' => 'ALTER TABLE patrol_schedules ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT "Scheduled" AFTER notes',
        'created_at' => 'ALTER TABLE patrol_schedules ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];

    foreach ($additions as $column => $sql) {
        if (!isset($columns[$column])) {
            $pdo->exec($sql);
        }
    }
}

function patrolSchedulesSelectColumns(string $prefix = ''): string
{
    $p = $prefix !== '' ? $prefix . '.' : '';

    return implode(', ', [
        "{$p}id",
        "{$p}patrol_id",
        "{$p}personnel_name",
        "{$p}patrol_zone",
        "{$p}route",
        "{$p}location",
        "{$p}schedule_date",
        "{$p}schedule_time",
        "{$p}shift",
        "{$p}patrol_start",
        "{$p}patrol_end",
        "{$p}duration_minutes",
        "{$p}notes",
        "{$p}status",
        "{$p}created_at",
    ]);
}

function enrichPatrolScheduleRow(array $row): array
{
    $status = (string) ($row['status'] ?? 'Scheduled');
    $start = $row['patrol_start'] ?? $row['schedule_time'] ?? '';
    $end = $row['patrol_end'] ?? '';

    if ($status === 'In Progress' && $start !== '' && $end === '') {
        $now = new DateTime();
        $row['duration_minutes'] = calculatePatrolDurationMinutes(
            (string) $row['schedule_date'],
            (string) $start,
            $now->format('H:i:s')
        );
    } elseif ($start !== '' && $end !== '' && (int) ($row['duration_minutes'] ?? 0) <= 0) {
        $row['duration_minutes'] = calculatePatrolDurationMinutes(
            (string) $row['schedule_date'],
            (string) $start,
            (string) $end
        );
    }

    $row['patrol_start_display'] = $start !== ''
        ? formatPatrolTimeDisplay((string) $start)
        : ($status === 'Scheduled' ? 'Pending' : '—');
    $row['patrol_end_display'] = $end !== ''
        ? formatPatrolTimeDisplay((string) $end)
        : ($status === 'In Progress' ? 'In progress' : ($status === 'Scheduled' ? 'Pending' : '—'));
    $row['duration_label'] = formatPatrolDurationLabel(
        isset($row['duration_minutes']) ? (int) $row['duration_minutes'] : null,
        $status
    );

    if ($row['duration_label'] === '—' && $status === 'In Progress') {
        $row['duration_label'] = 'In progress';
    }

    return $row;
}
=======
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
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69

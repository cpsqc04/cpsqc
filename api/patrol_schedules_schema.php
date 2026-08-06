<?php

require_once __DIR__ . '/../includes/patrol_shifts.php';

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
            patrol_zone VARCHAR(255) NOT NULL DEFAULT '',
            route VARCHAR(255) NOT NULL DEFAULT '',
            location TEXT DEFAULT NULL,
            schedule_date DATE NOT NULL,
            schedule_time VARCHAR(50) NOT NULL DEFAULT '',
            shift VARCHAR(50) NOT NULL DEFAULT '',
            patrol_start VARCHAR(50) DEFAULT NULL,
            patrol_end VARCHAR(50) DEFAULT NULL,
            duration_minutes INT NOT NULL DEFAULT 0,
            notes TEXT DEFAULT NULL,
            assignment_type VARCHAR(30) NOT NULL DEFAULT 'patrol',
            patrol_request_id INT DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_patrol_id (patrol_id),
            INDEX idx_schedule_date (schedule_date),
            INDEX idx_assignment_type (assignment_type),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
        'assignment_type' => "ALTER TABLE patrol_schedules ADD COLUMN assignment_type VARCHAR(30) NOT NULL DEFAULT 'patrol' AFTER notes",
        'patrol_request_id' => 'ALTER TABLE patrol_schedules ADD COLUMN patrol_request_id INT DEFAULT NULL AFTER assignment_type',
        'status' => 'ALTER TABLE patrol_schedules ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT "Scheduled" AFTER patrol_request_id',
        'created_at' => 'ALTER TABLE patrol_schedules ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];

    foreach ($additions as $column => $sql) {
        if (!isset($columns[$column])) {
            $pdo->exec($sql);
            $columns[$column] = true;
        }
    }

    // Keep status after the newer assignment columns when upgrading older tables.
    if (isset($columns['assignment_type'], $columns['status'])) {
        try {
            $pdo->exec('ALTER TABLE patrol_schedules MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT "Scheduled" AFTER patrol_request_id');
        } catch (PDOException $e) {
            // Ignore reorder failures on older MySQL variants.
        }
    }

    backfillPatrolScheduleAssignmentTypes($pdo);
}

/**
 * Mark legacy request-linked schedules as event/marshal assignments.
 */
function backfillPatrolScheduleAssignmentTypes(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo->exec(
            "UPDATE patrol_schedules
             SET assignment_type = 'event'
             WHERE (assignment_type = '' OR assignment_type = 'patrol' OR assignment_type IS NULL)
               AND (
                    notes LIKE 'Request ID:%'
                    OR notes LIKE 'Request ID: %'
                    OR notes LIKE 'Patrol request:%'
                    OR notes LIKE 'Patrol request: %'
                    OR notes LIKE '%\\nRequest ID:%'
                    OR notes LIKE '%\\nRequest ID: %'
               )"
        );
    } catch (PDOException $e) {
        // Non-fatal: older DBs without notes/type columns skip quietly.
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
        "{$p}assignment_type",
        "{$p}patrol_request_id",
        "{$p}status",
        "{$p}created_at",
    ]);
}

function normalizePatrolAssignmentType(?string $type, ?string $notes = null): string
{
    $normalized = strtolower(trim((string) $type));
    if (in_array($normalized, ['event', 'marshal', 'patrol_request'], true)) {
        return 'event';
    }
    if ($normalized === 'patrol' || $normalized === '') {
        $notesText = (string) $notes;
        if (
            preg_match('/(^|\\n)\\s*Request ID\\s*:/i', $notesText)
            || preg_match('/(^|\\n)\\s*Patrol request\\s*:/i', $notesText)
        ) {
            return 'event';
        }
        return 'patrol';
    }

    return 'patrol';
}

function enrichPatrolScheduleRow(array $row): array
{
    $status = (string) ($row['status'] ?? 'Scheduled');
    $start = $row['patrol_start'] ?? $row['schedule_time'] ?? '';
    $end = $row['patrol_end'] ?? '';
    $row['assignment_type'] = normalizePatrolAssignmentType(
        isset($row['assignment_type']) ? (string) $row['assignment_type'] : null,
        isset($row['notes']) ? (string) $row['notes'] : null
    );

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

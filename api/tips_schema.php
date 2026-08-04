<?php

/**
 * Tips table schema and column helpers.
 */

require_once __DIR__ . '/../includes/schema_helpers.php';

function ensureTipsTable(PDO $pdo): void
{
    $columns = [];
    $tableExists = false;

    try {
        foreach ($pdo->query('SHOW COLUMNS FROM tips') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        $pdo->exec("CREATE TABLE tips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tip_id VARCHAR(255) UNIQUE NOT NULL,
            location TEXT NOT NULL,
            description TEXT NOT NULL,
            contact_number VARCHAR(50) DEFAULT NULL,
            photo_data LONGTEXT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'New',
            outcome VARCHAR(100) DEFAULT 'No Outcome Yet',
            assigned_to VARCHAR(255) DEFAULT NULL,
            assigned_patrol_id INT NULL,
            resolution_report TEXT NULL,
            assigned_at DATETIME DEFAULT NULL,
            resolved_at DATETIME DEFAULT NULL,
            police_backup_reason TEXT DEFAULT NULL,
            forwarded_at DATETIME DEFAULT NULL,
            blotter_reference_id VARCHAR(100) DEFAULT NULL,
            backup_requested_at DATETIME DEFAULT NULL,
            emergency_response_reference_id VARCHAR(100) DEFAULT NULL,
            saved_at DATETIME DEFAULT NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_assigned_patrol_id (assigned_patrol_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }

    renameTableColumnIfNeeded(
        $pdo,
        'tips',
        'group3_reference_id',
        'emergency_response_reference_id',
        'VARCHAR(100) DEFAULT NULL'
    );

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM tips') as $row) {
        $columns[$row['Field']] = true;
    }

    $alterations = [
        'tip_id' => 'ALTER TABLE tips ADD COLUMN tip_id VARCHAR(255) UNIQUE NOT NULL DEFAULT "" AFTER id',
        'location' => 'ALTER TABLE tips ADD COLUMN location TEXT NOT NULL DEFAULT "" AFTER tip_id',
        'description' => 'ALTER TABLE tips ADD COLUMN description TEXT NOT NULL DEFAULT "" AFTER location',
        'contact_number' => 'ALTER TABLE tips ADD COLUMN contact_number VARCHAR(50) DEFAULT NULL AFTER description',
        'photo_data' => 'ALTER TABLE tips ADD COLUMN photo_data LONGTEXT NULL AFTER contact_number',
        'status' => 'ALTER TABLE tips ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT "New" AFTER photo_data',
        'outcome' => 'ALTER TABLE tips ADD COLUMN outcome VARCHAR(100) DEFAULT "No Outcome Yet" AFTER status',
        'assigned_to' => 'ALTER TABLE tips ADD COLUMN assigned_to VARCHAR(255) DEFAULT NULL AFTER outcome',
        'assigned_patrol_id' => 'ALTER TABLE tips ADD COLUMN assigned_patrol_id INT NULL AFTER assigned_to',
        'resolution_report' => 'ALTER TABLE tips ADD COLUMN resolution_report TEXT NULL AFTER assigned_patrol_id',
        'assigned_at' => 'ALTER TABLE tips ADD COLUMN assigned_at DATETIME DEFAULT NULL AFTER resolution_report',
        'resolved_at' => 'ALTER TABLE tips ADD COLUMN resolved_at DATETIME DEFAULT NULL AFTER assigned_at',
        'police_backup_reason' => 'ALTER TABLE tips ADD COLUMN police_backup_reason TEXT DEFAULT NULL AFTER resolved_at',
        'forwarded_at' => 'ALTER TABLE tips ADD COLUMN forwarded_at DATETIME DEFAULT NULL AFTER police_backup_reason',
        'blotter_reference_id' => 'ALTER TABLE tips ADD COLUMN blotter_reference_id VARCHAR(100) DEFAULT NULL AFTER forwarded_at',
        'backup_requested_at' => 'ALTER TABLE tips ADD COLUMN backup_requested_at DATETIME DEFAULT NULL AFTER blotter_reference_id',
        'emergency_response_reference_id' => 'ALTER TABLE tips ADD COLUMN emergency_response_reference_id VARCHAR(100) DEFAULT NULL AFTER backup_requested_at',
        'saved_at' => 'ALTER TABLE tips ADD COLUMN saved_at DATETIME DEFAULT NULL AFTER emergency_response_reference_id',
        'submitted_at' => 'ALTER TABLE tips ADD COLUMN submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER saved_at',
        'created_at' => 'ALTER TABLE tips ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];

    foreach ($alterations as $column => $sql) {
        if (!isset($columns[$column])) {
            $pdo->exec($sql);
        }
    }

    // Migrate legacy review statuses into the new response workflow.
    try {
        $pdo->exec("UPDATE tips
            SET status = CASE
                WHEN assigned_patrol_id IS NOT NULL AND status NOT IN ('Resolved') THEN 'Assigned'
                WHEN status IN ('Under Review', 'Reviewed', '') OR status IS NULL THEN 'New'
                ELSE status
            END
            WHERE status IN ('Under Review', 'Reviewed', '') OR status IS NULL
               OR (assigned_patrol_id IS NOT NULL AND status = 'New')");
    } catch (PDOException $e) {
        // ignore migration failures on restricted hosts
    }

    // Outcome is set only by the assigned patrol's report — clear stale admin-edited values.
    try {
        $pdo->exec("UPDATE tips
            SET outcome = 'No Outcome Yet'
            WHERE (assigned_patrol_id IS NULL OR assigned_patrol_id = 0)
              AND outcome IS NOT NULL
              AND outcome <> 'No Outcome Yet'");
    } catch (PDOException $e) {
        // ignore
    }
}

function tipsSelectColumns(string $prefix = ''): string
{
    $p = $prefix !== '' ? $prefix . '.' : '';
    return implode(', ', [
        "{$p}id",
        "{$p}tip_id",
        "{$p}location",
        "{$p}description",
        "{$p}contact_number",
        "{$p}photo_data",
        "{$p}status",
        "{$p}outcome",
        "{$p}assigned_to",
        "{$p}assigned_patrol_id",
        "{$p}resolution_report",
        "{$p}assigned_at",
        "{$p}resolved_at",
        "{$p}police_backup_reason",
        "{$p}forwarded_at",
        "{$p}blotter_reference_id",
        "{$p}backup_requested_at",
        "{$p}emergency_response_reference_id",
        "{$p}saved_at",
        "{$p}submitted_at",
        "{$p}created_at",
    ]);
}

function normalizeTipStatus(?string $status): string
{
    $value = trim((string) $status);
    if (in_array($value, ['Assigned', 'Resolved', 'New'], true)) {
        return $value;
    }
    if (in_array($value, ['Under Review', 'Reviewed', 'Pending'], true)) {
        return 'New';
    }
    if ($value === 'Processing' || $value === 'In Progress') {
        return 'Assigned';
    }
    return $value !== '' ? $value : 'New';
}

function tipOutcomeOptions(): array
{
    return [
        'No Outcome Yet',
        'Under Investigation',
        'Investigation Successful',
        'Arrest Made',
        'Unfounded / No Action',
    ];
}

function fetchTipById(PDO $pdo, int $id): ?array
{
    $cols = tipsSelectColumns();
    $stmt = $pdo->prepare("SELECT {$cols} FROM tips WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

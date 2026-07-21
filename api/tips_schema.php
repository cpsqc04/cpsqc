<?php

/**
 * Tips table schema and column helpers.
 */

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
            status VARCHAR(50) NOT NULL DEFAULT 'Under Review',
            outcome VARCHAR(100) DEFAULT 'No Outcome Yet',
            police_backup_reason TEXT DEFAULT NULL,
            forwarded_at DATETIME DEFAULT NULL,
            blotter_reference_id VARCHAR(100) DEFAULT NULL,
            backup_requested_at DATETIME DEFAULT NULL,
            group3_reference_id VARCHAR(100) DEFAULT NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }

    $alterations = [
        'tip_id' => 'ALTER TABLE tips ADD COLUMN tip_id VARCHAR(255) UNIQUE NOT NULL DEFAULT "" AFTER id',
        'location' => 'ALTER TABLE tips ADD COLUMN location TEXT NOT NULL DEFAULT "" AFTER tip_id',
        'description' => 'ALTER TABLE tips ADD COLUMN description TEXT NOT NULL DEFAULT "" AFTER location',
        'contact_number' => 'ALTER TABLE tips ADD COLUMN contact_number VARCHAR(50) DEFAULT NULL AFTER description',
        'photo_data' => 'ALTER TABLE tips ADD COLUMN photo_data LONGTEXT NULL AFTER contact_number',
        'status' => 'ALTER TABLE tips ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT "Under Review" AFTER photo_data',
        'outcome' => 'ALTER TABLE tips ADD COLUMN outcome VARCHAR(100) DEFAULT "No Outcome Yet" AFTER status',
        'police_backup_reason' => 'ALTER TABLE tips ADD COLUMN police_backup_reason TEXT DEFAULT NULL AFTER outcome',
        'forwarded_at' => 'ALTER TABLE tips ADD COLUMN forwarded_at DATETIME DEFAULT NULL AFTER police_backup_reason',
        'blotter_reference_id' => 'ALTER TABLE tips ADD COLUMN blotter_reference_id VARCHAR(100) DEFAULT NULL AFTER forwarded_at',
        'backup_requested_at' => 'ALTER TABLE tips ADD COLUMN backup_requested_at DATETIME DEFAULT NULL AFTER blotter_reference_id',
        'group3_reference_id' => 'ALTER TABLE tips ADD COLUMN group3_reference_id VARCHAR(100) DEFAULT NULL AFTER backup_requested_at',
        'submitted_at' => 'ALTER TABLE tips ADD COLUMN submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER group3_reference_id',
        'created_at' => 'ALTER TABLE tips ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];

    foreach ($alterations as $column => $sql) {
        if (!isset($columns[$column])) {
            $pdo->exec($sql);
        }
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
        "{$p}police_backup_reason",
        "{$p}forwarded_at",
        "{$p}blotter_reference_id",
        "{$p}backup_requested_at",
        "{$p}group3_reference_id",
        "{$p}submitted_at",
        "{$p}created_at",
    ]);
}

function fetchTipById(PDO $pdo, int $id): ?array
{
    $cols = tipsSelectColumns();
    $stmt = $pdo->prepare("SELECT {$cols} FROM tips WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

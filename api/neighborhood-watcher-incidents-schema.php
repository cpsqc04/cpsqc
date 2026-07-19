<?php

function ensureNwIncidentReportsTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS nw_incident_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        report_id VARCHAR(50) NOT NULL,
        volunteer_id INT NOT NULL,
        member_name VARCHAR(255) NOT NULL,
        member_contact VARCHAR(50) NOT NULL DEFAULT '',
        member_email VARCHAR(255) NOT NULL DEFAULT '',
        location VARCHAR(500) NOT NULL,
        description TEXT NOT NULL,
        photo_data LONGTEXT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Under Review',
        assigned_to VARCHAR(255) NULL DEFAULT NULL,
        assigned_patrol_id INT NULL DEFAULT NULL,
        resolution_report TEXT NULL,
        assigned_at TIMESTAMP NULL DEFAULT NULL,
        resolved_at TIMESTAMP NULL DEFAULT NULL,
        admin_notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_report_id (report_id),
        INDEX idx_volunteer_id (volunteer_id),
        INDEX idx_status (status),
        INDEX idx_assigned_patrol_id (assigned_patrol_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM nw_incident_reports') as $row) {
        $columns[$row['Field']] = true;
    }

    $additions = [
        'assigned_to' => 'ALTER TABLE nw_incident_reports ADD COLUMN assigned_to VARCHAR(255) NULL DEFAULT NULL AFTER status',
        'assigned_patrol_id' => 'ALTER TABLE nw_incident_reports ADD COLUMN assigned_patrol_id INT NULL DEFAULT NULL AFTER assigned_to',
        'resolution_report' => 'ALTER TABLE nw_incident_reports ADD COLUMN resolution_report TEXT NULL AFTER assigned_patrol_id',
        'assigned_at' => 'ALTER TABLE nw_incident_reports ADD COLUMN assigned_at TIMESTAMP NULL DEFAULT NULL AFTER resolution_report',
        'resolved_at' => 'ALTER TABLE nw_incident_reports ADD COLUMN resolved_at TIMESTAMP NULL DEFAULT NULL AFTER assigned_at',
    ];

    foreach ($additions as $column => $sql) {
        if (!isset($columns[$column])) {
            $pdo->exec($sql);
        }
    }

    try {
        $indexes = [];
        foreach ($pdo->query("SHOW INDEX FROM nw_incident_reports WHERE Key_name = 'idx_assigned_patrol_id'") as $row) {
            $indexes[] = $row['Key_name'];
        }
        if (empty($indexes)) {
            $pdo->exec('ALTER TABLE nw_incident_reports ADD INDEX idx_assigned_patrol_id (assigned_patrol_id)');
        }
    } catch (PDOException $e) {
        // Index may already exist under a different name.
    }
}

function nwIncidentSelectColumns(): string
{
    return 'id, report_id, volunteer_id, member_name, member_contact, member_email, location, description, photo_data, status, assigned_to, assigned_patrol_id, resolution_report, assigned_at, resolved_at, admin_notes, created_at, updated_at';
}

function generateNwIncidentReportId(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(report_id, LOCATE('-', report_id, 5) + 1) AS UNSIGNED)) AS max_num FROM nw_incident_reports WHERE report_id LIKE 'NWI-{$year}-%'");
    $maxNum = $stmt->fetchColumn();
    $nextNum = ($maxNum === false || $maxNum === null) ? 1 : ((int) $maxNum + 1);

    return 'NWI-' . $year . '-' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);
}

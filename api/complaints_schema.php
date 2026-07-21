<?php

/**
 * Ensure complaints table exists with BPSO assignment columns.
 */
function ensureComplaintsTable(PDO $pdo): void
{
    $columns = [];
    $tableExists = false;

    try {
        foreach ($pdo->query('SHOW COLUMNS FROM complaints') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        $pdo->exec("CREATE TABLE complaints (
            id INT AUTO_INCREMENT PRIMARY KEY,
            complaint_id VARCHAR(50) NOT NULL UNIQUE,
            complainant_name VARCHAR(255) NOT NULL,
            contact_number VARCHAR(50) NOT NULL,
            address TEXT NOT NULL,
            incident_date DATE NOT NULL,
            incident_time TIME DEFAULT NULL,
            defendant_name VARCHAR(255) NOT NULL DEFAULT '',
            defendant_address TEXT NOT NULL DEFAULT '',
            defendant_contact_number VARCHAR(50) NOT NULL DEFAULT '',
            complaint_type VARCHAR(100) NOT NULL,
            complaint_type_other VARCHAR(255) DEFAULT NULL,
            location TEXT NOT NULL DEFAULT '',
            description TEXT NOT NULL,
            priority VARCHAR(20) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Pending',
            assigned_to VARCHAR(255) DEFAULT 'Pending Assignment',
            assigned_patrol_id INT NULL,
            resolution_report TEXT DEFAULT NULL,
            assigned_at DATETIME DEFAULT NULL,
            resolved_at DATETIME DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            forwarded_at DATETIME DEFAULT NULL,
            blotter_reference_id VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_assigned_patrol_id (assigned_patrol_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }

    $additions = [
        'complaint_id' => 'ALTER TABLE complaints ADD COLUMN complaint_id VARCHAR(50) NOT NULL UNIQUE AFTER id',
        'complainant_name' => 'ALTER TABLE complaints ADD COLUMN complainant_name VARCHAR(255) NOT NULL DEFAULT "" AFTER complaint_id',
        'contact_number' => 'ALTER TABLE complaints ADD COLUMN contact_number VARCHAR(50) NOT NULL DEFAULT "" AFTER complainant_name',
        'address' => 'ALTER TABLE complaints ADD COLUMN address TEXT NOT NULL DEFAULT "" AFTER contact_number',
        'incident_date' => 'ALTER TABLE complaints ADD COLUMN incident_date DATE NOT NULL DEFAULT "1970-01-01" AFTER address',
        'incident_time' => 'ALTER TABLE complaints ADD COLUMN incident_time TIME DEFAULT NULL AFTER incident_date',
        'defendant_name' => 'ALTER TABLE complaints ADD COLUMN defendant_name VARCHAR(255) NOT NULL DEFAULT "" AFTER incident_time',
        'defendant_address' => 'ALTER TABLE complaints ADD COLUMN defendant_address TEXT NOT NULL DEFAULT "" AFTER defendant_name',
        'defendant_contact_number' => 'ALTER TABLE complaints ADD COLUMN defendant_contact_number VARCHAR(50) NOT NULL DEFAULT "" AFTER defendant_address',
        'complaint_type' => 'ALTER TABLE complaints ADD COLUMN complaint_type VARCHAR(100) NOT NULL DEFAULT "" AFTER defendant_contact_number',
        'complaint_type_other' => 'ALTER TABLE complaints ADD COLUMN complaint_type_other VARCHAR(255) DEFAULT NULL AFTER complaint_type',
        'location' => 'ALTER TABLE complaints ADD COLUMN location TEXT NOT NULL DEFAULT "" AFTER complaint_type',
        'description' => 'ALTER TABLE complaints ADD COLUMN description TEXT NOT NULL DEFAULT "" AFTER location',
        'priority' => 'ALTER TABLE complaints ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT "Low" AFTER description',
        'status' => 'ALTER TABLE complaints ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT "Pending" AFTER priority',
        'assigned_to' => 'ALTER TABLE complaints ADD COLUMN assigned_to VARCHAR(255) DEFAULT "Pending Assignment" AFTER status',
        'assigned_patrol_id' => 'ALTER TABLE complaints ADD COLUMN assigned_patrol_id INT NULL AFTER assigned_to',
        'resolution_report' => 'ALTER TABLE complaints ADD COLUMN resolution_report TEXT DEFAULT NULL AFTER assigned_patrol_id',
        'assigned_at' => 'ALTER TABLE complaints ADD COLUMN assigned_at DATETIME DEFAULT NULL AFTER resolution_report',
        'resolved_at' => 'ALTER TABLE complaints ADD COLUMN resolved_at DATETIME DEFAULT NULL AFTER assigned_at',
        'notes' => 'ALTER TABLE complaints ADD COLUMN notes TEXT DEFAULT NULL AFTER resolved_at',
        'submitted_at' => 'ALTER TABLE complaints ADD COLUMN submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER notes',
        'forwarded_at' => 'ALTER TABLE complaints ADD COLUMN forwarded_at DATETIME DEFAULT NULL AFTER submitted_at',
        'blotter_reference_id' => 'ALTER TABLE complaints ADD COLUMN blotter_reference_id VARCHAR(100) DEFAULT NULL AFTER forwarded_at',
        'created_at' => 'ALTER TABLE complaints ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];

    foreach ($additions as $column => $sql) {
        if (!isset($columns[$column])) {
            $pdo->exec($sql);
        }
    }
}

function complaintsSelectColumns(string $prefix = ''): string
{
    $p = $prefix !== '' ? $prefix . '.' : '';
    return "{$p}id, {$p}complaint_id, {$p}complainant_name, {$p}contact_number, {$p}address, {$p}incident_date, {$p}incident_time, {$p}defendant_name, {$p}defendant_address, {$p}defendant_contact_number, {$p}complaint_type, {$p}complaint_type_other, {$p}location, {$p}description, {$p}priority, {$p}status, {$p}assigned_to, {$p}assigned_patrol_id, {$p}resolution_report, {$p}assigned_at, {$p}resolved_at, {$p}notes, {$p}submitted_at, {$p}forwarded_at, {$p}blotter_reference_id, {$p}created_at";
}

function formatComplaintTypeLabel(array $row): string
{
    $type = trim($row['complaint_type'] ?? '');
    $other = trim($row['complaint_type_other'] ?? '');

    if ($type === 'Other' && $other !== '') {
        return 'Other — ' . $other;
    }

    if (preg_match('/^Other:\s*(.+)$/i', $type, $matches)) {
        return 'Other — ' . trim($matches[1]);
    }

    return $type !== '' ? $type : 'Unknown';
}

function normalizeComplaintTypeInput(string $complaintType, string $complaintTypeOther): array
{
    $complaintType = trim($complaintType);
    $complaintTypeOther = trim($complaintTypeOther);

    if ($complaintType === 'Other') {
        if ($complaintTypeOther === '') {
            return ['error' => 'Please specify the complaint type when selecting Other.'];
        }

        return [
            'complaint_type' => 'Other',
            'complaint_type_other' => $complaintTypeOther,
        ];
    }

    return [
        'complaint_type' => $complaintType,
        'complaint_type_other' => null,
    ];
}

function enrichComplaintAssignment(PDO $pdo, array $row): array
{
    $patrolId = (int) ($row['assigned_patrol_id'] ?? 0);
    $assignedTo = trim($row['assigned_to'] ?? '');

    if ($patrolId <= 0 || ($assignedTo !== '' && $assignedTo !== 'Pending Assignment')) {
        return $row;
    }

    $personnelStmt = $pdo->prepare('SELECT personnel_name, bpso_personnel_id FROM patrols WHERE id = :id LIMIT 1');
    $personnelStmt->execute([':id' => $patrolId]);
    $personnel = $personnelStmt->fetch(PDO::FETCH_ASSOC);

    if (!$personnel) {
        return $row;
    }

    $label = trim($personnel['personnel_name'] . ' (' . $personnel['bpso_personnel_id'] . ')');
    $row['assigned_to'] = $label;

    $repairStmt = $pdo->prepare('UPDATE complaints SET assigned_to = :assigned_to WHERE id = :id AND (assigned_to IS NULL OR assigned_to = :pending)');
    $repairStmt->execute([
        ':assigned_to' => $label,
        ':id' => (int) $row['id'],
        ':pending' => 'Pending Assignment',
    ]);

    return $row;
}

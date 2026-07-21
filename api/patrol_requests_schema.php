<?php

function ensurePatrolRequestsTable(PDO $pdo): void
{
    $columns = [];
    $tableExists = false;

    try {
        foreach ($pdo->query('SHOW COLUMNS FROM patrol_requests') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        $pdo->exec("CREATE TABLE patrol_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id VARCHAR(50) NOT NULL UNIQUE,
            source VARCHAR(100) DEFAULT 'partner_api',
            source_group VARCHAR(50) NOT NULL,
            source_reference_id VARCHAR(100) DEFAULT NULL,
            requesting_unit VARCHAR(255) NOT NULL,
            contact_person VARCHAR(255) NOT NULL,
            contact_position VARCHAR(255) DEFAULT NULL,
            contact_number VARCHAR(50) NOT NULL,
            contact_email VARCHAR(255) DEFAULT NULL,
            event_name VARCHAR(255) NOT NULL,
            event_date DATE NOT NULL,
            event_start_time TIME NOT NULL,
            event_end_time TIME DEFAULT NULL,
            event_location TEXT NOT NULL,
            patrols_needed INT NOT NULL DEFAULT 1,
            event_description TEXT DEFAULT NULL,
            special_instructions TEXT DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Pending',
            review_notes TEXT DEFAULT NULL,
            rejection_reason TEXT DEFAULT NULL,
            patrols_assigned INT DEFAULT NULL,
            assigned_patrol_ids TEXT DEFAULT NULL,
            scheduling_notes TEXT DEFAULT NULL,
            reviewed_by VARCHAR(255) DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_request_id (request_id),
            INDEX idx_source_group (source_group),
            INDEX idx_event_date (event_date),
            INDEX idx_submitted_at (submitted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }

    $additions = [
        'source' => "ALTER TABLE patrol_requests ADD COLUMN source VARCHAR(100) DEFAULT 'partner_api' AFTER request_id",
        'source_group' => "ALTER TABLE patrol_requests ADD COLUMN source_group VARCHAR(50) NOT NULL DEFAULT 'group_6' AFTER source",
        'source_reference_id' => 'ALTER TABLE patrol_requests ADD COLUMN source_reference_id VARCHAR(100) DEFAULT NULL AFTER source_group',
        'requesting_unit' => 'ALTER TABLE patrol_requests ADD COLUMN requesting_unit VARCHAR(255) NOT NULL DEFAULT "" AFTER source_reference_id',
        'contact_person' => 'ALTER TABLE patrol_requests ADD COLUMN contact_person VARCHAR(255) NOT NULL DEFAULT "" AFTER requesting_unit',
        'contact_position' => 'ALTER TABLE patrol_requests ADD COLUMN contact_position VARCHAR(255) DEFAULT NULL AFTER contact_person',
        'contact_number' => 'ALTER TABLE patrol_requests ADD COLUMN contact_number VARCHAR(50) NOT NULL DEFAULT "" AFTER contact_position',
        'contact_email' => 'ALTER TABLE patrol_requests ADD COLUMN contact_email VARCHAR(255) DEFAULT NULL AFTER contact_number',
        'event_name' => 'ALTER TABLE patrol_requests ADD COLUMN event_name VARCHAR(255) NOT NULL DEFAULT "" AFTER contact_email',
        'event_date' => 'ALTER TABLE patrol_requests ADD COLUMN event_date DATE NOT NULL DEFAULT "1970-01-01" AFTER event_name',
        'event_start_time' => 'ALTER TABLE patrol_requests ADD COLUMN event_start_time TIME NOT NULL DEFAULT "00:00:00" AFTER event_date',
        'event_end_time' => 'ALTER TABLE patrol_requests ADD COLUMN event_end_time TIME DEFAULT NULL AFTER event_start_time',
        'event_location' => 'ALTER TABLE patrol_requests ADD COLUMN event_location TEXT NOT NULL AFTER event_end_time',
        'patrols_needed' => 'ALTER TABLE patrol_requests ADD COLUMN patrols_needed INT NOT NULL DEFAULT 1 AFTER event_location',
        'event_description' => 'ALTER TABLE patrol_requests ADD COLUMN event_description TEXT DEFAULT NULL AFTER patrols_needed',
        'special_instructions' => 'ALTER TABLE patrol_requests ADD COLUMN special_instructions TEXT DEFAULT NULL AFTER event_description',
        'status' => "ALTER TABLE patrol_requests ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Pending' AFTER special_instructions",
        'review_notes' => 'ALTER TABLE patrol_requests ADD COLUMN review_notes TEXT DEFAULT NULL AFTER status',
        'rejection_reason' => 'ALTER TABLE patrol_requests ADD COLUMN rejection_reason TEXT DEFAULT NULL AFTER review_notes',
        'patrols_assigned' => 'ALTER TABLE patrol_requests ADD COLUMN patrols_assigned INT DEFAULT NULL AFTER rejection_reason',
        'assigned_patrol_ids' => 'ALTER TABLE patrol_requests ADD COLUMN assigned_patrol_ids TEXT DEFAULT NULL AFTER patrols_assigned',
        'scheduling_notes' => 'ALTER TABLE patrol_requests ADD COLUMN scheduling_notes TEXT DEFAULT NULL AFTER patrols_assigned',
        'reviewed_by' => 'ALTER TABLE patrol_requests ADD COLUMN reviewed_by VARCHAR(255) DEFAULT NULL AFTER scheduling_notes',
        'reviewed_at' => 'ALTER TABLE patrol_requests ADD COLUMN reviewed_at DATETIME DEFAULT NULL AFTER reviewed_by',
        'submitted_at' => 'ALTER TABLE patrol_requests ADD COLUMN submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER reviewed_at',
        'updated_at' => 'ALTER TABLE patrol_requests ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER submitted_at',
    ];

    foreach ($additions as $column => $sql) {
        if (!isset($columns[$column])) {
            $pdo->exec($sql);
        }
    }
}

function patrolRequestsSelectColumns(string $prefix = ''): string
{
    $p = $prefix !== '' ? $prefix . '.' : '';
    return implode(', ', array_map(static function ($col) use ($p) {
        return $p . $col;
    }, [
        'id', 'request_id', 'source', 'source_group', 'source_reference_id', 'requesting_unit',
        'contact_person', 'contact_position', 'contact_number', 'contact_email',
        'event_name', 'event_date', 'event_start_time', 'event_end_time', 'event_location',
        'patrols_needed', 'event_description', 'special_instructions', 'status',
        'review_notes', 'rejection_reason', 'patrols_assigned', 'assigned_patrol_ids', 'scheduling_notes',
        'reviewed_by', 'reviewed_at', 'submitted_at', 'updated_at',
    ]));
}

function generatePatrolRequestId(PDO $pdo): string
{
    $year = date('Y');
    $prefix = 'PT-REQ-' . $year . '-';

    $stmt = $pdo->prepare('SELECT request_id FROM patrol_requests WHERE request_id LIKE :prefix ORDER BY id DESC LIMIT 1');
    $stmt->execute([':prefix' => $prefix . '%']);
    $last = $stmt->fetchColumn();

    $next = 1;
    if ($last && preg_match('/PT-REQ-\d{4}-(\d+)$/', $last, $matches)) {
        $next = (int) $matches[1] + 1;
    }

    return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
}

function patrolRequestAllowedSourceGroups(): array
{
    return ['group_6', 'group_8'];
}

function patrolRequestSourceGroupLabel(string $sourceGroup): string
{
    $map = [
        'group_6' => 'Group 6',
        'group_8' => 'Group 8',
    ];
    return $map[$sourceGroup] ?? $sourceGroup;
}

function validatePatrolRequestApiKey(): bool
{
    $expectedKey = trim($_ENV['PATROL_REQUEST_API_KEY'] ?? '');
    if ($expectedKey === '') {
        return true;
    }

    $providedKey = '';
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        $providedKey = $matches[1];
    }
    if ($providedKey === '') {
        $providedKey = trim($_SERVER['HTTP_X_API_KEY'] ?? '');
    }

    return $providedKey !== '' && hash_equals($expectedKey, $providedKey);
}

function requireConfiguredPatrolRequestApiKey(): bool
{
    $expectedKey = trim($_ENV['PATROL_REQUEST_API_KEY'] ?? '');
    if ($expectedKey === '') {
        return false;
    }

    return validatePatrolRequestApiKey();
}

function normalizePatrolRequestInput(array $input): array
{
    $sourceGroup = strtolower(trim($input['source_group'] ?? $input['source'] ?? ''));
    if ($sourceGroup === 'group 6') {
        $sourceGroup = 'group_6';
    }
    if ($sourceGroup === 'group 8') {
        $sourceGroup = 'group_8';
    }

    return [
        'source' => trim($input['source'] ?? 'partner_api'),
        'source_group' => $sourceGroup,
        'source_reference_id' => trim($input['source_reference_id'] ?? ''),
        'requesting_unit' => trim($input['requesting_unit'] ?? ''),
        'contact_person' => trim($input['contact_person'] ?? ''),
        'contact_position' => trim($input['contact_position'] ?? ''),
        'contact_number' => trim($input['contact_number'] ?? ''),
        'contact_email' => trim($input['contact_email'] ?? ''),
        'event_name' => trim($input['event_name'] ?? ''),
        'event_date' => trim($input['event_date'] ?? ''),
        'event_start_time' => trim($input['event_start_time'] ?? ''),
        'event_end_time' => trim($input['event_end_time'] ?? ''),
        'event_location' => trim($input['event_location'] ?? ''),
        'patrols_needed' => (int) ($input['patrols_needed'] ?? 0),
        'event_description' => trim($input['event_description'] ?? ''),
        'special_instructions' => trim($input['special_instructions'] ?? ''),
    ];
}

function validatePatrolRequestRequiredFields(array $data): ?string
{
    if (!in_array($data['source_group'], patrolRequestAllowedSourceGroups(), true)) {
        return 'Source group must be group_6 or group_8.';
    }

    $required = [
        'requesting_unit' => 'Requesting unit / organization',
        'contact_person' => 'Contact person',
        'contact_number' => 'Contact number',
        'event_name' => 'Event name',
        'event_date' => 'Event date',
        'event_start_time' => 'Event start time',
        'event_location' => 'Event location',
    ];

    foreach ($required as $field => $label) {
        if ($data[$field] === '') {
            return $label . ' is required.';
        }
    }

    if ($data['patrols_needed'] < 1) {
        return 'Number of patrols needed must be at least 1.';
    }

    if ($data['event_end_time'] !== '' && $data['event_end_time'] <= $data['event_start_time']) {
        return 'Event end time must be after start time.';
    }

    return null;
}

function parsePatrolRequestAssignedIds(?string $raw): array
{
    if ($raw === null || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $ids = [];
    foreach ($decoded as $id) {
        $intId = (int) $id;
        if ($intId > 0) {
            $ids[$intId] = $intId;
        }
    }

    return array_values($ids);
}

function encodePatrolRequestAssignedIds(array $ids): ?string
{
    $normalized = [];
    foreach ($ids as $id) {
        $intId = (int) $id;
        if ($intId > 0) {
            $normalized[$intId] = $intId;
        }
    }

    $normalized = array_values($normalized);
    if ($normalized === []) {
        return null;
    }

    return json_encode($normalized);
}

function enrichPatrolRequestAssignments(PDO $pdo, array $row): array
{
    $assignedIds = parsePatrolRequestAssignedIds($row['assigned_patrol_ids'] ?? null);
    $row['assigned_patrol_ids'] = $assignedIds;
    $row['assigned_personnel'] = [];

    if ($assignedIds === []) {
        return $row;
    }

    $placeholders = implode(',', array_fill(0, count($assignedIds), '?'));
    $stmt = $pdo->prepare("SELECT id, bpso_personnel_id, personnel_name, status FROM patrols WHERE id IN ({$placeholders}) ORDER BY personnel_name ASC");
    $stmt->execute($assignedIds);
    $row['assigned_personnel'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $row;
}

<?php

function ensureAwarenessEventsTable(PDO $pdo): void
{
    $columns = [];
    $tableExists = false;

    try {
        foreach ($pdo->query('SHOW COLUMNS FROM awareness_events') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        $pdo->exec("CREATE TABLE awareness_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id VARCHAR(50) NOT NULL UNIQUE,
            source VARCHAR(100) DEFAULT 'partner_api',
            source_group VARCHAR(50) NOT NULL DEFAULT 'campaign',
            source_reference_id VARCHAR(100) DEFAULT NULL,
            event_name VARCHAR(255) NOT NULL,
            event_date DATE NOT NULL,
            event_time TIME NOT NULL,
            organizer VARCHAR(255) NOT NULL,
            event_type VARCHAR(100) NOT NULL DEFAULT 'Awareness',
            venue TEXT NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Pending',
            description TEXT DEFAULT NULL,
            contact_person VARCHAR(255) DEFAULT NULL,
            contact_number VARCHAR(50) DEFAULT NULL,
            contact_email VARCHAR(255) DEFAULT NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_event_id (event_id),
            INDEX idx_source_group (source_group),
            INDEX idx_event_date (event_date),
            INDEX idx_submitted_at (submitted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $additions = [
            'source' => "ALTER TABLE awareness_events ADD COLUMN source VARCHAR(100) DEFAULT 'partner_api' AFTER event_id",
            'source_group' => "ALTER TABLE awareness_events ADD COLUMN source_group VARCHAR(50) NOT NULL DEFAULT 'campaign' AFTER source",
            'source_reference_id' => 'ALTER TABLE awareness_events ADD COLUMN source_reference_id VARCHAR(100) DEFAULT NULL AFTER source_group',
            'contact_person' => 'ALTER TABLE awareness_events ADD COLUMN contact_person VARCHAR(255) DEFAULT NULL AFTER description',
            'contact_number' => 'ALTER TABLE awareness_events ADD COLUMN contact_number VARCHAR(50) DEFAULT NULL AFTER contact_person',
            'contact_email' => 'ALTER TABLE awareness_events ADD COLUMN contact_email VARCHAR(255) DEFAULT NULL AFTER contact_number',
            'submitted_at' => 'ALTER TABLE awareness_events ADD COLUMN submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER contact_email',
            'updated_at' => 'ALTER TABLE awareness_events ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER submitted_at',
        ];
        foreach ($additions as $column => $sql) {
            if (!isset($columns[$column])) {
                $pdo->exec($sql);
            }
        }
    }

    try {
        $pdo->exec("UPDATE awareness_events SET source_group = 'campaign' WHERE source_group IN ('group_6', 'group 6', 'Group 6')");
    } catch (PDOException $e) {
        // ignore
    }
}

function ensureAwarenessEventReportsTable(PDO $pdo): void
{
    $columns = [];
    $tableExists = false;

    try {
        foreach ($pdo->query('SHOW COLUMNS FROM awareness_event_reports') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        $pdo->exec("CREATE TABLE awareness_event_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            report_id VARCHAR(50) NOT NULL UNIQUE,
            event_id VARCHAR(50) NOT NULL,
            source VARCHAR(100) DEFAULT 'partner_api',
            source_group VARCHAR(50) NOT NULL DEFAULT 'campaign',
            source_reference_id VARCHAR(100) DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            event_date DATE NOT NULL,
            attendance_count INT NOT NULL DEFAULT 0,
            organizer VARCHAR(255) NOT NULL,
            survey_result VARCHAR(100) DEFAULT NULL,
            evaluation_score VARCHAR(50) DEFAULT NULL,
            event_outcome TEXT DEFAULT NULL,
            location TEXT DEFAULT NULL,
            description TEXT DEFAULT NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_event_id (event_id),
            INDEX idx_report_id (report_id),
            INDEX idx_source_group (source_group),
            INDEX idx_event_date (event_date),
            INDEX idx_submitted_at (submitted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $additions = [
            'source' => "ALTER TABLE awareness_event_reports ADD COLUMN source VARCHAR(100) DEFAULT 'partner_api' AFTER event_id",
            'source_group' => "ALTER TABLE awareness_event_reports ADD COLUMN source_group VARCHAR(50) NOT NULL DEFAULT 'campaign' AFTER source",
            'source_reference_id' => 'ALTER TABLE awareness_event_reports ADD COLUMN source_reference_id VARCHAR(100) DEFAULT NULL AFTER source_group',
            'evaluation_score' => 'ALTER TABLE awareness_event_reports ADD COLUMN evaluation_score VARCHAR(50) DEFAULT NULL AFTER survey_result',
            'event_outcome' => 'ALTER TABLE awareness_event_reports ADD COLUMN event_outcome TEXT DEFAULT NULL AFTER evaluation_score',
            'submitted_at' => 'ALTER TABLE awareness_event_reports ADD COLUMN submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER description',
            'updated_at' => 'ALTER TABLE awareness_event_reports ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER submitted_at',
        ];
        foreach ($additions as $column => $sql) {
            if (!isset($columns[$column])) {
                $pdo->exec($sql);
            }
        }
    }

    try {
        $pdo->exec("UPDATE awareness_event_reports SET source_group = 'campaign' WHERE source_group IN ('group_6', 'group 6', 'Group 6')");
    } catch (PDOException $e) {
        // ignore
    }
}

function awarenessEventsSelectColumns(string $prefix = ''): string
{
    $p = $prefix !== '' ? $prefix . '.' : '';
    return implode(', ', array_map(static function ($col) use ($p) {
        return $p . $col;
    }, [
        'id', 'event_id', 'source', 'source_group', 'source_reference_id',
        'event_name', 'event_date', 'event_time', 'organizer', 'event_type', 'venue',
        'status', 'description', 'contact_person', 'contact_number', 'contact_email',
        'submitted_at', 'updated_at',
    ]));
}

function awarenessEventReportsSelectColumns(string $prefix = ''): string
{
    $p = $prefix !== '' ? $prefix . '.' : '';
    return implode(', ', array_map(static function ($col) use ($p) {
        return $p . $col;
    }, [
        'id', 'report_id', 'event_id', 'source', 'source_group', 'source_reference_id',
        'title', 'event_date', 'attendance_count', 'organizer', 'survey_result',
        'evaluation_score', 'event_outcome', 'location', 'description', 'submitted_at', 'updated_at',
    ]));
}

function awarenessEventAllowedSourceGroups(): array
{
    return ['campaign'];
}

function normalizeAwarenessSourceGroup(string $sourceGroup): string
{
    $value = strtolower(trim($sourceGroup));
    $value = str_replace([' ', '_'], '-', $value);

    return match ($value) {
        'group-6', 'group6', 'campaign' => 'campaign',
        default => $value,
    };
}

function awarenessEventSourceGroupLabel(string $sourceGroup): string
{
    $normalized = normalizeAwarenessSourceGroup($sourceGroup);
    $map = [
        'campaign' => 'Campaign',
        'group_6' => 'Campaign',
    ];
    return $map[$normalized] ?? ($map[$sourceGroup] ?? $sourceGroup);
}

function validateAwarenessEventApiKey(): bool
{
    $expectedKey = trim($_ENV['AWARENESS_EVENTS_API_KEY'] ?? '');
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

function requireConfiguredAwarenessEventApiKey(): bool
{
    $expectedKey = trim($_ENV['AWARENESS_EVENTS_API_KEY'] ?? '');
    if ($expectedKey === '') {
        return false;
    }

    return validateAwarenessEventApiKey();
}

function generateAwarenessEventId(PDO $pdo): string
{
    require_once __DIR__ . '/../includes/public_id.php';

    return generateYearlySequentialId($pdo, 'awareness_events', 'event_id', 'EVT-');
}

function generateAwarenessEventReportId(PDO $pdo): string
{
    require_once __DIR__ . '/../includes/public_id.php';

    return generateYearlySequentialId($pdo, 'awareness_event_reports', 'report_id', 'EVT-RPT-');
}

function normalizeAwarenessEventInput(array $input): array
{
    $sourceGroup = normalizeAwarenessSourceGroup((string) ($input['source_group'] ?? $input['source'] ?? 'campaign'));

    return [
        'source' => trim($input['source'] ?? 'partner_api'),
        'source_group' => $sourceGroup,
        'source_reference_id' => trim($input['source_reference_id'] ?? ''),
        'event_id' => trim($input['event_id'] ?? ''),
        'event_name' => trim($input['event_name'] ?? $input['name'] ?? $input['title'] ?? ''),
        'event_date' => trim($input['event_date'] ?? $input['date'] ?? ''),
        'event_time' => trim($input['event_time'] ?? $input['time'] ?? ''),
        'organizer' => trim($input['organizer'] ?? ''),
        'event_type' => trim($input['event_type'] ?? $input['type'] ?? 'Awareness'),
        'venue' => trim($input['venue'] ?? $input['location'] ?? ''),
        'status' => trim($input['status'] ?? 'Pending'),
        'description' => trim($input['description'] ?? ''),
        'contact_person' => trim($input['contact_person'] ?? ''),
        'contact_number' => trim($input['contact_number'] ?? ''),
        'contact_email' => trim($input['contact_email'] ?? ''),
    ];
}

function normalizeAwarenessEventReportInput(array $input): array
{
    $sourceGroup = normalizeAwarenessSourceGroup((string) ($input['source_group'] ?? 'campaign'));

    $survey = trim($input['survey_result'] ?? $input['survey_results'] ?? '');
    $evaluation = trim((string) ($input['evaluation_score'] ?? $input['evaluation'] ?? ''));
    $outcome = trim($input['event_outcome'] ?? $input['outcome'] ?? '');
    $description = trim($input['description'] ?? '');
    if ($description === '' && $outcome !== '') {
        $description = $outcome;
    }

    return [
        'source' => trim($input['source'] ?? 'partner_api'),
        'source_group' => $sourceGroup,
        'source_reference_id' => trim($input['source_reference_id'] ?? ''),
        'report_id' => trim($input['report_id'] ?? ''),
        'event_id' => trim($input['event_id'] ?? ''),
        'title' => trim($input['title'] ?? $input['event_name'] ?? $input['event_id'] ?? ''),
        'event_date' => trim($input['event_date'] ?? $input['date'] ?? ''),
        'attendance_count' => (int) ($input['attendance_count'] ?? $input['attendance'] ?? 0),
        'organizer' => trim($input['organizer'] ?? ''),
        'survey_result' => $survey,
        'evaluation_score' => $evaluation,
        'event_outcome' => $outcome,
        'location' => trim($input['location'] ?? $input['venue'] ?? ''),
        'description' => $description,
    ];
}

function validateAwarenessEventFields(array $data): ?string
{
    if (!in_array($data['source_group'], awarenessEventAllowedSourceGroups(), true)) {
        return 'Source group must be campaign.';
    }

    $required = [
        'event_name' => 'Event name',
        'event_date' => 'Event date',
        'event_time' => 'Event time',
        'organizer' => 'Organizer',
        'venue' => 'Venue',
    ];

    foreach ($required as $field => $label) {
        if ($data[$field] === '') {
            return $label . ' is required.';
        }
    }

    $allowedStatuses = ['Pending', 'Scheduled', 'Completed', 'Cancelled'];
    if ($data['status'] !== '' && !in_array($data['status'], $allowedStatuses, true)) {
        return 'Status must be Pending, Scheduled, Completed, or Cancelled.';
    }

    return null;
}

function validateAwarenessEventReportFields(array $data): ?string
{
    if (!in_array($data['source_group'], awarenessEventAllowedSourceGroups(), true)) {
        return 'Source group must be campaign.';
    }

    $required = [
        'event_id' => 'Event ID',
        'event_date' => 'Event date',
        'organizer' => 'Organizer',
    ];

    foreach ($required as $field => $label) {
        if ($data[$field] === '') {
            return $label . ' is required.';
        }
    }

    if (trim($data['title'] ?? '') === '') {
        return 'Report title (or event_name) is required.';
    }

    if ($data['attendance_count'] < 0) {
        return 'Attendance count cannot be negative.';
    }

    return null;
}

function formatAwarenessEventTime(?string $time): string
{
    if ($time === null || $time === '') {
        return '—';
    }

    $value = strlen($time) === 5 ? $time . ':00' : $time;
    $dt = DateTime::createFromFormat('H:i:s', $value);
    if (!$dt) {
        return $time;
    }

    return $dt->format('g:i A');
}

<?php

function ensureRiskAlertsTable(PDO $pdo): void
{
    $columns = [];
    $tableExists = false;

    try {
        foreach ($pdo->query('SHOW COLUMNS FROM risk_alerts') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        $pdo->exec("CREATE TABLE risk_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            alert_id VARCHAR(100) NOT NULL UNIQUE,
            source_group VARCHAR(50) NOT NULL DEFAULT 'group_5',
            source_reference_id VARCHAR(100) DEFAULT NULL,
            rule_name VARCHAR(255) NOT NULL,
            rule_type VARCHAR(50) NOT NULL DEFAULT 'Hotspot',
            severity VARCHAR(50) NOT NULL DEFAULT 'MEDIUM',
            condition_text VARCHAR(500) DEFAULT NULL,
            area_name VARCHAR(255) DEFAULT NULL,
            location TEXT NOT NULL,
            route_suggestion VARCHAR(500) DEFAULT NULL,
            incident_count INT DEFAULT NULL,
            time_window VARCHAR(100) DEFAULT NULL,
            latitude DECIMAL(10, 8) DEFAULT NULL,
            longitude DECIMAL(11, 8) DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            triggered_at DATETIME NOT NULL,
            expires_at DATETIME DEFAULT NULL,
            received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_severity (severity),
            INDEX idx_triggered_at (triggered_at),
            INDEX idx_source_group (source_group)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }

    $additions = [
        'source_group' => "ALTER TABLE risk_alerts ADD COLUMN source_group VARCHAR(50) NOT NULL DEFAULT 'group_5' AFTER alert_id",
        'source_reference_id' => 'ALTER TABLE risk_alerts ADD COLUMN source_reference_id VARCHAR(100) DEFAULT NULL AFTER source_group',
        'rule_name' => 'ALTER TABLE risk_alerts ADD COLUMN rule_name VARCHAR(255) NOT NULL DEFAULT "" AFTER source_reference_id',
        'rule_type' => "ALTER TABLE risk_alerts ADD COLUMN rule_type VARCHAR(50) NOT NULL DEFAULT 'Hotspot' AFTER rule_name",
        'severity' => "ALTER TABLE risk_alerts ADD COLUMN severity VARCHAR(50) NOT NULL DEFAULT 'MEDIUM' AFTER rule_type",
        'condition_text' => 'ALTER TABLE risk_alerts ADD COLUMN condition_text VARCHAR(500) DEFAULT NULL AFTER severity',
        'area_name' => 'ALTER TABLE risk_alerts ADD COLUMN area_name VARCHAR(255) DEFAULT NULL AFTER condition_text',
        'location' => 'ALTER TABLE risk_alerts ADD COLUMN location TEXT NOT NULL AFTER area_name',
        'route_suggestion' => 'ALTER TABLE risk_alerts ADD COLUMN route_suggestion VARCHAR(500) DEFAULT NULL AFTER location',
        'incident_count' => 'ALTER TABLE risk_alerts ADD COLUMN incident_count INT DEFAULT NULL AFTER route_suggestion',
        'time_window' => 'ALTER TABLE risk_alerts ADD COLUMN time_window VARCHAR(100) DEFAULT NULL AFTER incident_count',
        'latitude' => 'ALTER TABLE risk_alerts ADD COLUMN latitude DECIMAL(10, 8) DEFAULT NULL AFTER time_window',
        'longitude' => 'ALTER TABLE risk_alerts ADD COLUMN longitude DECIMAL(11, 8) DEFAULT NULL AFTER latitude',
        'status' => "ALTER TABLE risk_alerts ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'active' AFTER longitude",
        'triggered_at' => 'ALTER TABLE risk_alerts ADD COLUMN triggered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status',
        'expires_at' => 'ALTER TABLE risk_alerts ADD COLUMN expires_at DATETIME DEFAULT NULL AFTER triggered_at',
        'received_at' => 'ALTER TABLE risk_alerts ADD COLUMN received_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER expires_at',
        'updated_at' => 'ALTER TABLE risk_alerts ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER received_at',
    ];

    foreach ($additions as $column => $sql) {
        if (!isset($columns[$column])) {
            $pdo->exec($sql);
        }
    }
}

function validateGroup5AlertApiKey(bool $allowQueryString = false): bool
{
    $expectedKey = trim($_ENV['GROUP5_API_KEY'] ?? '');
    if ($expectedKey === '') {
        return false;
    }

    $providedKey = '';
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        $providedKey = $matches[1];
    }
    if ($providedKey === '') {
        $providedKey = trim($_SERVER['HTTP_X_API_KEY'] ?? '');
    }
    if ($providedKey === '' && $allowQueryString && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        $providedKey = trim($_GET['api_key'] ?? '');
    }

    return $providedKey !== '' && hash_equals($expectedKey, $providedKey);
}

function requireConfiguredGroup5AlertApiKey(): bool
{
    $expectedKey = trim($_ENV['GROUP5_API_KEY'] ?? '');
    if ($expectedKey === '') {
        return false;
    }

    return validateGroup5AlertApiKey();
}

function normalizeRiskAlertInput(array $input): array
{
    $sourceGroup = strtolower(trim($input['source_group'] ?? $input['source'] ?? 'group_5'));
    if ($sourceGroup === 'group 5' || $sourceGroup === 'group5') {
        $sourceGroup = 'group_5';
    }

    $severity = strtoupper(trim($input['severity'] ?? 'MEDIUM'));
    $ruleType = trim($input['rule_type'] ?? $input['type'] ?? 'Hotspot');
    $location = trim($input['location'] ?? $input['area_location'] ?? '');
    $areaName = trim($input['area_name'] ?? $input['area'] ?? '');

    if ($location === '' && $areaName !== '') {
        $location = $areaName;
    }

    $route = trim($input['route_suggestion'] ?? $input['route'] ?? '');
    if ($route === '' && $areaName !== '') {
        $route = $areaName;
    }

    $status = strtolower(trim($input['status'] ?? 'active'));
    if (in_array($status, ['disabled', 'inactive', 'resolved', 'cleared', 'expired'], true)) {
        $status = in_array($status, ['disabled', 'inactive'], true) ? 'inactive' : $status;
    } else {
        $status = 'active';
    }

    $triggeredAt = trim($input['triggered_at'] ?? $input['alert_time'] ?? '');
    if ($triggeredAt === '') {
        $triggeredAt = date('Y-m-d H:i:s');
    } else {
        $parsed = strtotime($triggeredAt);
        $triggeredAt = $parsed !== false ? date('Y-m-d H:i:s', $parsed) : date('Y-m-d H:i:s');
    }

    $expiresAt = trim($input['expires_at'] ?? '');
    if ($expiresAt !== '') {
        $parsed = strtotime($expiresAt);
        $expiresAt = $parsed !== false ? date('Y-m-d H:i:s', $parsed) : '';
    }

    return [
        'alert_id' => trim($input['alert_id'] ?? $input['id'] ?? ''),
        'source_group' => $sourceGroup,
        'source_reference_id' => trim($input['source_reference_id'] ?? ''),
        'rule_name' => trim($input['rule_name'] ?? $input['name'] ?? ''),
        'rule_type' => $ruleType,
        'severity' => $severity,
        'condition_text' => trim($input['condition_text'] ?? $input['condition'] ?? ''),
        'area_name' => $areaName,
        'location' => $location,
        'route_suggestion' => $route,
        'incident_count' => isset($input['incident_count']) ? (int) $input['incident_count'] : null,
        'time_window' => trim($input['time_window'] ?? ''),
        'latitude' => isset($input['latitude']) && $input['latitude'] !== '' ? (float) $input['latitude'] : null,
        'longitude' => isset($input['longitude']) && $input['longitude'] !== '' ? (float) $input['longitude'] : null,
        'status' => $status,
        'triggered_at' => $triggeredAt,
        'expires_at' => $expiresAt,
    ];
}

function validateRiskAlertRequiredFields(array $data): ?string
{
    if ($data['rule_name'] === '') {
        return 'rule_name is required.';
    }
    if ($data['location'] === '') {
        return 'location (or area_name) is required.';
    }
    if (!in_array($data['severity'], ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'], true)) {
        return 'severity must be CRITICAL, HIGH, MEDIUM, or LOW.';
    }

    return null;
}

function generateRiskAlertId(PDO $pdo): string
{
    $year = date('Y');
    $prefix = 'RISK-' . $year . '-';

    $stmt = $pdo->prepare('SELECT alert_id FROM risk_alerts WHERE alert_id LIKE :prefix ORDER BY id DESC LIMIT 1');
    $stmt->execute([':prefix' => $prefix . '%']);
    $last = $stmt->fetchColumn();

    $next = 1;
    if ($last && preg_match('/RISK-\d{4}-(\d+)$/', $last, $matches)) {
        $next = (int) $matches[1] + 1;
    }

    return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
}

function riskAlertsSelectColumns(string $prefix = ''): string
{
    $p = $prefix !== '' ? $prefix . '.' : '';

    return implode(', ', array_map(static function ($col) use ($p) {
        return $p . $col;
    }, [
        'id', 'alert_id', 'source_group', 'source_reference_id', 'rule_name', 'rule_type',
        'severity', 'condition_text', 'area_name', 'location', 'route_suggestion',
        'incident_count', 'time_window', 'latitude', 'longitude', 'status',
        'triggered_at', 'expires_at', 'received_at', 'updated_at',
    ]));
}

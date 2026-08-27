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
            source_group VARCHAR(50) NOT NULL DEFAULT 'crime_analytics',
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
        'source_group' => "ALTER TABLE risk_alerts ADD COLUMN source_group VARCHAR(50) NOT NULL DEFAULT 'crime_analytics' AFTER alert_id",
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

    // Normalize legacy Crime Analytics source labels.
    try {
        $pdo->exec("UPDATE risk_alerts SET source_group = 'crime_analytics'
            WHERE LOWER(source_group) IN ('group_5', 'group5', 'group 5')");
    } catch (PDOException $e) {
        // ignore if table/column not ready
    }
}

function validateCrimeAnalyticsAlertApiKey(bool $allowQueryString = false): bool
{
    require_once __DIR__ . '/../includes/api_key_auth.php';
    return validatePartnerApiKey(partnerEnvKeyCandidates('crime-analytics'), $allowQueryString);
}

/** @deprecated Use validateCrimeAnalyticsAlertApiKey() */
function validateGroup5AlertApiKey(bool $allowQueryString = false): bool
{
    return validateCrimeAnalyticsAlertApiKey($allowQueryString);
}

function requireConfiguredCrimeAnalyticsAlertApiKey(): bool
{
    require_once __DIR__ . '/../includes/api_key_auth.php';
    $expectedKey = envFirst(...partnerEnvKeyCandidates('crime-analytics'));
    if ($expectedKey === '') {
        return false;
    }

    return validateCrimeAnalyticsAlertApiKey();
}

/** @deprecated Use requireConfiguredCrimeAnalyticsAlertApiKey() */
function requireConfiguredGroup5AlertApiKey(): bool
{
    return requireConfiguredCrimeAnalyticsAlertApiKey();
}

function normalizeRiskAlertInput(array $input): array
{
    $sourceGroup = strtolower(trim($input['source_group'] ?? $input['source'] ?? 'crime_analytics'));
    if (in_array($sourceGroup, ['group 5', 'group5', 'group_5', 'crime-analytics', 'crime analytics'], true)) {
        $sourceGroup = 'crime_analytics';
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
    require_once __DIR__ . '/../includes/public_id.php';

    return generateYearlySequentialId($pdo, 'risk_alerts', 'alert_id', 'RISK-');
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

/**
 * Insert or update one risk alert row from normalized data.
 */
function upsertRiskAlert(PDO $pdo, array $data): string
{
    $alertId = $data['alert_id'] !== '' ? $data['alert_id'] : generateRiskAlertId($pdo);
    $existing = $pdo->prepare('SELECT id FROM risk_alerts WHERE alert_id = :alert_id LIMIT 1');
    $existing->execute([':alert_id' => $alertId]);
    $isUpdate = (bool) $existing->fetch();

    if ($isUpdate) {
        $stmt = $pdo->prepare('UPDATE risk_alerts SET
            source_group = :source_group,
            source_reference_id = :source_reference_id,
            rule_name = :rule_name,
            rule_type = :rule_type,
            severity = :severity,
            condition_text = :condition_text,
            area_name = :area_name,
            location = :location,
            route_suggestion = :route_suggestion,
            incident_count = :incident_count,
            time_window = :time_window,
            latitude = :latitude,
            longitude = :longitude,
            status = :status,
            triggered_at = :triggered_at,
            expires_at = :expires_at
            WHERE alert_id = :alert_id');
    } else {
        $stmt = $pdo->prepare('INSERT INTO risk_alerts (
            alert_id, source_group, source_reference_id, rule_name, rule_type, severity,
            condition_text, area_name, location, route_suggestion, incident_count, time_window,
            latitude, longitude, status, triggered_at, expires_at
        ) VALUES (
            :alert_id, :source_group, :source_reference_id, :rule_name, :rule_type, :severity,
            :condition_text, :area_name, :location, :route_suggestion, :incident_count, :time_window,
            :latitude, :longitude, :status, :triggered_at, :expires_at
        )');
    }

    $stmt->execute([
        ':alert_id' => $alertId,
        ':source_group' => $data['source_group'],
        ':source_reference_id' => $data['source_reference_id'] !== '' ? $data['source_reference_id'] : null,
        ':rule_name' => $data['rule_name'],
        ':rule_type' => $data['rule_type'],
        ':severity' => $data['severity'],
        ':condition_text' => $data['condition_text'] !== '' ? $data['condition_text'] : null,
        ':area_name' => $data['area_name'] !== '' ? $data['area_name'] : null,
        ':location' => $data['location'],
        ':route_suggestion' => $data['route_suggestion'] !== '' ? $data['route_suggestion'] : null,
        ':incident_count' => $data['incident_count'],
        ':time_window' => $data['time_window'] !== '' ? $data['time_window'] : null,
        ':latitude' => $data['latitude'],
        ':longitude' => $data['longitude'],
        ':status' => $data['status'],
        ':triggered_at' => $data['triggered_at'],
        ':expires_at' => $data['expires_at'] !== '' ? $data['expires_at'] : null,
    ]);

    return $alertId;
}

/**
 * Pull active alerts from Crime Analytics public feed and upsert into risk_alerts.
 *
 * @return array{success:bool,message:string,synced?:int,resolved?:int,http_code?:int}
 */
function syncCrimeAnalyticsActiveAlerts(PDO $pdo): array
{
    require_once __DIR__ . '/../includes/api_key_auth.php';

    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL extension is required to sync Crime Analytics alerts.'];
    }

    $config = getCrimeAnalyticsApiConfig();
    $url = trim((string) ($config['active_data_url'] ?? ''));
    if ($url === '') {
        return ['success' => false, 'message' => 'Crime Analytics active-data URL is not configured.'];
    }

    $headers = ['Accept: application/json'];
    if (!empty($config['api_key'])) {
        $headers[] = 'X-API-Key: ' . $config['api_key'];
        $headers[] = 'Authorization: Bearer ' . $config['api_key'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => (int) ($config['timeout'] ?? 20),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        return [
            'success' => false,
            'message' => 'Failed to reach Crime Analytics API: ' . ($curlError ?: 'Unknown error'),
            'http_code' => $httpCode,
        ];
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'message' => 'Crime Analytics API returned invalid JSON (HTTP ' . $httpCode . ').',
            'http_code' => $httpCode,
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = trim((string) ($decoded['message'] ?? $decoded['error'] ?? 'Crime Analytics API request failed.'));
        return [
            'success' => false,
            'message' => $message . ' (HTTP ' . $httpCode . ')',
            'http_code' => $httpCode,
        ];
    }

    $alerts = $decoded['alerts'] ?? $decoded['data'] ?? $decoded['items'] ?? null;
    if (!is_array($alerts)) {
        return [
            'success' => false,
            'message' => 'Crime Analytics response missing alerts list.',
            'http_code' => $httpCode,
        ];
    }

    $syncedIds = [];
    $synced = 0;
    $skipped = 0;

    foreach ($alerts as $alert) {
        if (!is_array($alert)) {
            $skipped++;
            continue;
        }

        $data = normalizeRiskAlertInput($alert);
        if ($data['status'] !== 'active') {
            // Active feed should only contain active items, but keep non-active in sync if present.
        }

        $error = validateRiskAlertRequiredFields($data);
        if ($error !== null) {
            $skipped++;
            continue;
        }

        $alertId = upsertRiskAlert($pdo, $data);
        $syncedIds[] = $alertId;
        $synced++;
    }

    $resolved = 0;
    if ($syncedIds !== []) {
        $placeholders = implode(',', array_fill(0, count($syncedIds), '?'));
        $params = $syncedIds;
        $sql = "UPDATE risk_alerts
                SET status = 'resolved'
                WHERE status = 'active'
                  AND LOWER(source_group) IN ('crime_analytics', 'group_5', 'group5', 'group 5')
                  AND alert_id NOT IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $resolved = $stmt->rowCount();
    } elseif (count($alerts) === 0) {
        // Empty active feed: resolve all Crime Analytics actives.
        $stmt = $pdo->prepare("UPDATE risk_alerts
            SET status = 'resolved'
            WHERE status = 'active'
              AND LOWER(source_group) IN ('crime_analytics', 'group_5', 'group5', 'group 5')");
        $stmt->execute();
        $resolved = $stmt->rowCount();
    }

    return [
        'success' => true,
        'message' => 'Crime Analytics alerts synced.',
        'synced' => $synced,
        'skipped' => $skipped,
        'resolved' => $resolved,
        'http_code' => $httpCode,
        'stats' => is_array($decoded['stats'] ?? null) ? $decoded['stats'] : null,
    ];
}

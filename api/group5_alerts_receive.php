<?php

/**
 * Inbound API for Group 5 — Alert Management (high-risk areas / hotspots).
 *
 * POST JSON with alert fields from Group 5 when a rule triggers.
 * Headers: X-API-Key or Authorization: Bearer {GROUP5_API_KEY}
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/risk_alerts_schema.php';

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit;
}

if (!requireConfiguredGroup5AlertApiKey()) {
    $expectedKey = trim($_ENV['GROUP5_API_KEY'] ?? '');
    http_response_code($expectedKey === '' ? 503 : 401);
    echo json_encode([
        'success' => false,
        'message' => $expectedKey === ''
            ? 'Group 5 alert API is not configured. Set GROUP5_API_KEY in .env.'
            : 'Invalid or missing API key.',
    ]);
    exit;
}

try {
    ensureRiskAlertsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare risk alerts table: ' . $e->getMessage()]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$data = normalizeRiskAlertInput($input);
$error = validateRiskAlertRequiredFields($data);
if ($error !== null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error]);
    exit;
}

$alertId = $data['alert_id'] !== '' ? $data['alert_id'] : generateRiskAlertId($pdo);
$isUpdate = false;

try {
    if ($data['alert_id'] !== '') {
        $existing = $pdo->prepare('SELECT id FROM risk_alerts WHERE alert_id = :alert_id LIMIT 1');
        $existing->execute([':alert_id' => $alertId]);
        $isUpdate = (bool) $existing->fetch();
    }

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

    echo json_encode([
        'success' => true,
        'message' => $isUpdate ? 'Risk alert updated.' : 'Risk alert received.',
        'data' => [
            'alert_id' => $alertId,
            'status' => $data['status'],
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save risk alert: ' . $e->getMessage()]);
}

<?php

/**
 * List high-risk alerts from Crime Analytics for admin patrol scheduling.
 *
 * GET — public (no API key). Query:
 *   status=active (default) | all
 *   severity=CRITICAL|HIGH|MEDIUM|LOW
 *   sync=1 (default) | 0 — pull latest from Crime Analytics active-data feed first
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/risk_alerts_schema.php';
require_once __DIR__ . '/../includes/api_key_auth.php';

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit;
}

try {
    ensureRiskAlertsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare risk alerts table: ' . $e->getMessage()]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

$syncFlag = strtolower(trim((string) ($_GET['sync'] ?? '1')));
$shouldSync = !in_array($syncFlag, ['0', 'false', 'no', 'off'], true);
$syncResult = null;

if ($shouldSync) {
    try {
        $syncResult = syncCrimeAnalyticsActiveAlerts($pdo);
    } catch (Throwable $e) {
        $syncResult = [
            'success' => false,
            'message' => 'Crime Analytics sync failed: ' . $e->getMessage(),
        ];
    }
}

$statusFilter = strtolower(trim($_GET['status'] ?? 'active'));
$severityFilter = strtoupper(trim($_GET['severity'] ?? ''));

$where = [];
$params = [];

if ($statusFilter !== 'all') {
    $where[] = 'status = :status';
    $params[':status'] = $statusFilter === '' ? 'active' : $statusFilter;
}

if ($severityFilter !== '' && in_array($severityFilter, ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'], true)) {
    $where[] = 'severity = :severity';
    $params[':severity'] = $severityFilter;
}

$sql = 'SELECT ' . riskAlertsSelectColumns() . ' FROM risk_alerts';
if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY FIELD(severity, 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW'), triggered_at DESC, id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $payload = [
        'success' => true,
        'count' => count($rows),
        'data' => $rows,
    ];
    if (is_array($syncResult)) {
        $payload['sync'] = $syncResult;
    }

    echo json_encode($payload);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load risk alerts: ' . $e->getMessage()]);
}

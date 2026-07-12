<?php

/**
 * List high-risk alerts from Group 5 for admin patrol scheduling.
 *
 * GET — admin session or GROUP5_API_KEY
 * Query: status=active (default) | all, severity=CRITICAL|HIGH|...
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/risk_alerts_schema.php';
require_once __DIR__ . '/../includes/bpso_auth.php';

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

$isAdmin = isAdminLoggedIn();
$hasApiKey = validateGroup5AlertApiKey(true);

if (!$isAdmin && !$hasApiKey) {
    $expectedKey = trim($_ENV['GROUP5_API_KEY'] ?? '');
    http_response_code($expectedKey === '' ? 503 : 401);
    echo json_encode([
        'success' => false,
        'message' => $expectedKey === ''
            ? 'Group 5 alert API is not configured. Set GROUP5_API_KEY in .env.'
            : 'Unauthorized.',
    ]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
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

    echo json_encode([
        'success' => true,
        'count' => count($rows),
        'data' => $rows,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load risk alerts: ' . $e->getMessage()]);
}

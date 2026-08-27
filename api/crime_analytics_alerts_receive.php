<?php

/**
 * Inbound API for Crime Analytics — high-risk areas / hotspots.
 *
 * POST JSON with alert fields when a rule triggers.
 * Public — no API key required.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

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

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$data = normalizeRiskAlertInput($input);
$error = validateRiskAlertRequiredFields($data);
if ($error !== null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error]);
    exit;
}

try {
    $existing = null;
    if ($data['alert_id'] !== '') {
        $check = $pdo->prepare('SELECT id FROM risk_alerts WHERE alert_id = :alert_id LIMIT 1');
        $check->execute([':alert_id' => $data['alert_id']]);
        $existing = $check->fetch();
    }

    $alertId = upsertRiskAlert($pdo, $data);

    echo json_encode([
        'success' => true,
        'message' => $existing ? 'Risk alert updated.' : 'Risk alert received.',
        'data' => [
            'alert_id' => $alertId,
            'status' => $data['status'],
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save risk alert: ' . $e->getMessage()]);
}

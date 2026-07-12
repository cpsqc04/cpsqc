<?php

/**
 * Inbound API for Group 6 and Group 8 event patrol requests.
 *
 * POST JSON with patrol request fields.
 * Headers: X-API-Key or Authorization: Bearer {PATROL_REQUEST_API_KEY}
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/patrol_requests_schema.php';
require_once __DIR__ . '/notifications_schema.php';

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit;
}

if (!requireConfiguredPatrolRequestApiKey()) {
    $expectedKey = trim($_ENV['PATROL_REQUEST_API_KEY'] ?? '');
    http_response_code($expectedKey === '' ? 503 : 401);
    echo json_encode([
        'success' => false,
        'message' => $expectedKey === ''
            ? 'Patrol request API is not configured. Set PATROL_REQUEST_API_KEY in .env.'
            : 'Invalid or missing API key.',
    ]);
    exit;
}

try {
    ensurePatrolRequestsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare patrol requests table: ' . $e->getMessage()]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$data = normalizePatrolRequestInput($input);
$error = validatePatrolRequestRequiredFields($data);
if ($error !== null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error]);
    exit;
}

try {
    $requestId = generatePatrolRequestId($pdo);
    $stmt = $pdo->prepare('INSERT INTO patrol_requests (
        request_id, source, source_group, source_reference_id, requesting_unit,
        contact_person, contact_position, contact_number, contact_email,
        event_name, event_date, event_start_time, event_end_time, event_location,
        patrols_needed, event_description, special_instructions, status, submitted_at
    ) VALUES (
        :request_id, :source, :source_group, :source_reference_id, :requesting_unit,
        :contact_person, :contact_position, :contact_number, :contact_email,
        :event_name, :event_date, :event_start_time, :event_end_time, :event_location,
        :patrols_needed, :event_description, :special_instructions, :status, NOW()
    )');

    $stmt->execute([
        ':request_id' => $requestId,
        ':source' => $data['source'] !== '' ? $data['source'] : 'partner_api',
        ':source_group' => $data['source_group'],
        ':source_reference_id' => $data['source_reference_id'] !== '' ? $data['source_reference_id'] : null,
        ':requesting_unit' => $data['requesting_unit'],
        ':contact_person' => $data['contact_person'],
        ':contact_position' => $data['contact_position'] !== '' ? $data['contact_position'] : null,
        ':contact_number' => $data['contact_number'],
        ':contact_email' => $data['contact_email'] !== '' ? $data['contact_email'] : null,
        ':event_name' => $data['event_name'],
        ':event_date' => $data['event_date'],
        ':event_start_time' => $data['event_start_time'],
        ':event_end_time' => $data['event_end_time'] !== '' ? $data['event_end_time'] : null,
        ':event_location' => $data['event_location'],
        ':patrols_needed' => $data['patrols_needed'],
        ':event_description' => $data['event_description'] !== '' ? $data['event_description'] : null,
        ':special_instructions' => $data['special_instructions'] !== '' ? $data['special_instructions'] : null,
        ':status' => 'Pending',
    ]);

    $groupLabel = patrolRequestSourceGroupLabel($data['source_group']);
    createAdminNotification(
        $pdo,
        'patrol_request',
        'New Patrol Request',
        'Request #' . $requestId . ' from ' . $groupLabel . ' — ' . $data['event_name'],
        'patrol-request.php?id=' . urlencode($requestId)
    );

    echo json_encode([
        'success' => true,
        'message' => 'Patrol request received.',
        'data' => [
            'request_id' => $requestId,
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save patrol request: ' . $e->getMessage()]);
}

<?php

/**
 * Inbound API for partner groups submitting CCTV footage requests.
 *
 * POST JSON — same fields as api/cctv_requests.php create action.
 * Headers: X-API-Key or Authorization: Bearer {CCTV_REQUEST_API_KEY}
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/cctv_requests_schema.php';
require_once __DIR__ . '/notifications_schema.php';

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit;
}

if (!requireConfiguredCctvRequestApiKey()) {
    $expectedKey = trim($_ENV['CCTV_REQUEST_API_KEY'] ?? '');
    http_response_code($expectedKey === '' ? 503 : 401);
    echo json_encode([
        'success' => false,
        'message' => $expectedKey === ''
            ? 'CCTV request API is not configured. Set CCTV_REQUEST_API_KEY in .env.'
            : 'Invalid or missing API key.',
    ]);
    exit;
}

try {
    ensureCctvRequestsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare CCTV requests table: ' . $e->getMessage()]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (!isset($input['source']) || trim($input['source']) === '') {
    $input['source'] = 'partner_api';
}

$data = normalizeCctvRequestInput($input);
$error = validateCctvRequestRequiredFields($data);
if ($error !== null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error]);
    exit;
}

try {
    $requestId = generateCctvRequestId($pdo);
    $stmt = $pdo->prepare('INSERT INTO cctv_requests (
        request_id, source, source_reference_id, requesting_agency, contact_person, contact_position,
        contact_number, contact_email, office_unit, case_reference, related_complaint_id,
        purpose, purpose_details, legal_basis, incident_location, camera_id, location_description,
        incident_date, footage_start_time, footage_end_time, incident_type, incident_description,
        delivery_method, supporting_document, status, submitted_at
    ) VALUES (
        :request_id, :source, :source_reference_id, :requesting_agency, :contact_person, :contact_position,
        :contact_number, :contact_email, :office_unit, :case_reference, :related_complaint_id,
        :purpose, :purpose_details, :legal_basis, :incident_location, :camera_id, :location_description,
        :incident_date, :footage_start_time, :footage_end_time, :incident_type, :incident_description,
        :delivery_method, :supporting_document, :status, NOW()
    )');

    $stmt->execute([
        ':request_id' => $requestId,
        ':source' => $data['source'],
        ':source_reference_id' => $data['source_reference_id'] !== '' ? $data['source_reference_id'] : null,
        ':requesting_agency' => $data['requesting_agency'],
        ':contact_person' => $data['contact_person'],
        ':contact_position' => $data['contact_position'] !== '' ? $data['contact_position'] : null,
        ':contact_number' => $data['contact_number'],
        ':contact_email' => $data['contact_email'] !== '' ? $data['contact_email'] : null,
        ':office_unit' => $data['office_unit'] !== '' ? $data['office_unit'] : null,
        ':case_reference' => $data['case_reference'] !== '' ? $data['case_reference'] : null,
        ':related_complaint_id' => $data['related_complaint_id'] !== '' ? $data['related_complaint_id'] : null,
        ':purpose' => $data['purpose'] !== '' ? $data['purpose'] : 'General request',
        ':purpose_details' => $data['purpose_details'],
        ':legal_basis' => $data['legal_basis'],
        ':incident_location' => $data['incident_location'],
        ':camera_id' => $data['camera_id'] !== '' ? $data['camera_id'] : null,
        ':location_description' => $data['location_description'] !== '' ? $data['location_description'] : null,
        ':incident_date' => $data['incident_date'],
        ':footage_start_time' => $data['footage_start_time'],
        ':footage_end_time' => $data['footage_end_time'],
        ':incident_type' => $data['incident_type'] !== '' ? $data['incident_type'] : null,
        ':incident_description' => $data['incident_description'],
        ':delivery_method' => $data['delivery_method'] !== '' ? $data['delivery_method'] : 'secure_download',
        ':supporting_document' => $data['supporting_document'] !== '' ? $data['supporting_document'] : null,
        ':status' => 'Pending',
    ]);

    createAdminNotification(
        $pdo,
        'cctv_request',
        'New CCTV Footage Request',
        'Request #' . $requestId . ' from ' . $data['requesting_agency'],
        'cctv-request.php?id=' . urlencode($requestId)
    );

    echo json_encode([
        'success' => true,
        'message' => 'CCTV footage request received.',
        'data' => [
            'request_id' => $requestId,
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save CCTV request: ' . $e->getMessage()]);
}

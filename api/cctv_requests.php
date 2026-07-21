<?php
session_start();

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/cctv_requests_schema.php';
require_once __DIR__ . '/notifications_schema.php';
require_once __DIR__ . '/../includes/json_response.php';
require_once __DIR__ . '/../includes/api_key_auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!$pdo instanceof PDO) {
    jsonResponse(['success' => false, 'message' => 'Database connection unavailable.'], 500, false);
}

try {
    ensureCctvRequestsTable($pdo);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Failed to prepare CCTV requests table: ' . $e->getMessage()], 500, false);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? ($_GET['action'] ?? '');

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if ($method === 'GET') {
    if (!canAccessPartnerList($isAdmin, 'CCTV_REQUEST_API_KEY')) {
        denyPartnerListAccess($isAdmin, 'CCTV_REQUEST_API_KEY', 'CCTV request');
    }

    try {
        $cols = cctvRequestsSelectColumns();
        $sql = "SELECT {$cols} FROM cctv_requests WHERE 1=1";
        $params = [];

        $requestId = trim($_GET['request_id'] ?? '');
        if ($requestId !== '') {
            $sql .= ' AND request_id = :request_id';
            $params[':request_id'] = $requestId;
        }

        $status = trim($_GET['status'] ?? '');
        if ($status !== '') {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }

        $sourceReferenceId = trim($_GET['source_reference_id'] ?? '');
        if ($sourceReferenceId !== '') {
            $sql .= ' AND source_reference_id = :source_reference_id';
            $params[':source_reference_id'] = $sourceReferenceId;
        }

        $requestingAgency = trim($_GET['requesting_agency'] ?? '');
        if ($requestingAgency !== '') {
            $sql .= ' AND requesting_agency LIKE :requesting_agency';
            $params[':requesting_agency'] = '%' . $requestingAgency . '%';
        }

        $sql .= ' ORDER BY submitted_at DESC, id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $index => $row) {
            $rows[$index]['has_supporting_document'] = !empty($row['supporting_document']);
            unset($rows[$index]['supporting_document']);
        }

        jsonResponse([
            'success' => true,
            'count' => count($rows),
            'data' => $rows,
        ]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to load CCTV requests: ' . $e->getMessage()], 500);
    }
}

if ($method === 'POST' && $action === 'create') {
    if (!canCreateCctvRequest($isAdmin)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing API key.']);
        exit;
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
            ':source' => $data['source'] !== '' ? $data['source'] : 'web_form',
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
            'message' => 'CCTV footage request submitted successfully.',
            'data' => [
                'id' => (int) $pdo->lastInsertId(),
                'request_id' => $requestId,
            ],
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save CCTV request: ' . $e->getMessage()]);
    }
    exit;
}

if (!$isAdmin) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($method === 'POST' && $action === 'manage') {
    $id = (int) ($input['id'] ?? 0);
    $status = trim($input['status'] ?? '');
    $allowedStatuses = ['Pending', 'Under Review', 'Approved', 'Fulfilled', 'Rejected', 'Cancelled'];

    if ($id <= 0 || $status === '' || !in_array($status, $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid request ID and status are required.']);
        exit;
    }

    if ($status === 'Rejected' && trim($input['rejection_reason'] ?? '') === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Rejection reason is required when rejecting a request.']);
        exit;
    }

    try {
        $currentStmt = $pdo->prepare('SELECT request_id, status FROM cctv_requests WHERE id = :id');
        $currentStmt->execute([':id' => $id]);
        $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$current) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'CCTV request not found.']);
            exit;
        }

        $fulfilledAt = null;
        if ($status === 'Fulfilled') {
            $fulfilledAt = date('Y-m-d H:i:s');
        }

        $stmt = $pdo->prepare('UPDATE cctv_requests SET
            status = :status,
            review_notes = :review_notes,
            rejection_reason = :rejection_reason,
            approved_camera_id = :approved_camera_id,
            actual_footage_start = :actual_footage_start,
            actual_footage_end = :actual_footage_end,
            fulfillment_notes = :fulfillment_notes,
            reviewed_by = :reviewed_by,
            fulfilled_at = :fulfilled_at
            WHERE id = :id');

        $stmt->execute([
            ':status' => $status,
            ':review_notes' => trim($input['review_notes'] ?? '') !== '' ? trim($input['review_notes']) : null,
            ':rejection_reason' => trim($input['rejection_reason'] ?? '') !== '' ? trim($input['rejection_reason']) : null,
            ':approved_camera_id' => trim($input['approved_camera_id'] ?? '') !== '' ? trim($input['approved_camera_id']) : null,
            ':actual_footage_start' => trim($input['actual_footage_start'] ?? '') !== '' ? trim($input['actual_footage_start']) : null,
            ':actual_footage_end' => trim($input['actual_footage_end'] ?? '') !== '' ? trim($input['actual_footage_end']) : null,
            ':fulfillment_notes' => trim($input['fulfillment_notes'] ?? '') !== '' ? trim($input['fulfillment_notes']) : null,
            ':reviewed_by' => $_SESSION['username'] ?? 'Admin',
            ':fulfilled_at' => $fulfilledAt,
            ':id' => $id,
        ]);

        echo json_encode(['success' => true, 'message' => 'CCTV request updated successfully.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update CCTV request: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST' && $action === 'get_document') {
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT request_id, supporting_document FROM cctv_requests WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['supporting_document'])) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Supporting document not found.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'request_id' => $row['request_id'],
                'supporting_document' => $row['supporting_document'],
            ],
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load supporting document.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

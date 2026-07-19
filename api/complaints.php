<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/complaints_schema.php';
require_once __DIR__ . '/bpso_attendance_schema.php';
require_once __DIR__ . '/notifications_schema.php';
require_once __DIR__ . '/../includes/blotter_forward.php';
require_once __DIR__ . '/../includes/contact_validation.php';

try {
    ensureComplaintsTable($pdo);
    ensureBpsoAttendanceTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare complaints table: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

// Public tip/create-style: allow complaint creation without admin session.
$isPublicCreate = ($method === 'POST' && $action === 'create');
if (!$isPublicCreate && (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$cols = complaintsSelectColumns();

if ($method === 'GET') {
    try {
        $stmt = $pdo->query('SELECT ' . complaintsSelectColumns() . ' FROM complaints ORDER BY id DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $index => $row) {
            $rows[$index] = enrichComplaintAssignment($pdo, $row);
        }
        echo json_encode(['success' => true, 'data' => $rows]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load complaints: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    if ($action === 'create') {
        $complaintId = trim($input['complaint_id'] ?? '');
        $complainantName = trim($input['complainant_name'] ?? '');
        $contactNumber = trim($input['contact_number'] ?? '');
        $address = trim($input['address'] ?? '');
        $incidentDate = trim($input['incident_date'] ?? '');
        $incidentTime = trim($input['incident_time'] ?? '');
        $defendantName = trim($input['defendant_name'] ?? '');
        $defendantAddress = trim($input['defendant_address'] ?? '');
        $defendantContactNumber = trim($input['defendant_contact_number'] ?? '');
        $complaintType = trim($input['complaint_type'] ?? '');
        $complaintTypeOther = trim($input['complaint_type_other'] ?? '');
        $location = trim($input['location'] ?? '');
        $description = trim($input['description'] ?? '');
        $priority = trim($input['priority'] ?? 'Low');
        $status = trim($input['status'] ?? 'Pending');
        $assignedTo = trim($input['assigned_to'] ?? 'Pending Assignment');
        $notes = trim($input['notes'] ?? 'Complaint submitted and awaiting review.');

        if ($complaintId === '' || $complainantName === '' || $contactNumber === '' || $address === '' || $incidentDate === '' || $incidentTime === '' || $defendantName === '' || $defendantAddress === '' || $complaintType === '' || $description === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        $contactNumber = normalizeContactDigits($contactNumber);
        $defendantContactNumber = normalizeContactDigits($defendantContactNumber);
        $contactError = validateContactNumber($contactNumber, "Complainant's contact number");
        if ($contactError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $contactError]);
            exit;
        }
        $defendantContactError = validateContactNumberOptional($defendantContactNumber, "Defendant's contact number");
        if ($defendantContactError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $defendantContactError]);
            exit;
        }

        $typeFields = normalizeComplaintTypeInput($complaintType, $complaintTypeOther);
        if (isset($typeFields['error'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $typeFields['error']]);
            exit;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO complaints (complaint_id, complainant_name, contact_number, address, incident_date, incident_time, defendant_name, defendant_address, defendant_contact_number, complaint_type, complaint_type_other, location, description, priority, status, assigned_to, notes, submitted_at) VALUES (:complaint_id, :complainant_name, :contact_number, :address, :incident_date, :incident_time, :defendant_name, :defendant_address, :defendant_contact_number, :complaint_type, :complaint_type_other, :location, :description, :priority, :status, :assigned_to, :notes, NOW())');
            $stmt->execute([
                ':complaint_id' => $complaintId,
                ':complainant_name' => $complainantName,
                ':contact_number' => $contactNumber,
                ':address' => $address,
                ':incident_date' => $incidentDate,
                ':incident_time' => $incidentTime,
                ':defendant_name' => $defendantName,
                ':defendant_address' => $defendantAddress,
                ':defendant_contact_number' => $defendantContactNumber,
                ':complaint_type' => $typeFields['complaint_type'],
                ':complaint_type_other' => $typeFields['complaint_type_other'],
                ':location' => $location,
                ':description' => $description,
                ':priority' => $priority,
                ':status' => $status,
                ':assigned_to' => $assignedTo,
                ':notes' => $notes,
            ]);

            createAdminNotification(
                $pdo,
                'complaint',
                'New Complaint Submitted',
                'Complaint #' . $complaintId . ' - ' . $typeFields['complaint_type'] . ' from ' . $complainantName,
                'track-complaint.php?id=' . $complaintId
            );

            echo json_encode(['success' => true, 'data' => ['id' => (int) $pdo->lastInsertId()]]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save complaint: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'assign') {
        $id = (int) ($input['id'] ?? 0);
        $patrolId = (int) ($input['assigned_patrol_id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid complaint ID.']);
            exit;
        }

        try {
            if ($patrolId <= 0) {
                $stmt = $pdo->prepare('UPDATE complaints SET assigned_patrol_id = NULL, assigned_to = :assigned_to, assigned_at = NULL, status = :status WHERE id = :id');
                $stmt->execute([
                    ':assigned_to' => 'Pending Assignment',
                    ':status' => 'Pending',
                    ':id' => $id,
                ]);
                echo json_encode(['success' => true, 'message' => 'Assignment cleared.']);
                exit;
            }

            $personnelStmt = $pdo->prepare('SELECT id, personnel_name, bpso_personnel_id, status FROM patrols WHERE id = :id');
            $personnelStmt->execute([':id' => $patrolId]);
            $personnel = $personnelStmt->fetch(PDO::FETCH_ASSOC);

            if (!$personnel) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'BPSO personnel not found.']);
                exit;
            }

            if (($personnel['status'] ?? '') !== 'Available') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Selected personnel is not available. Please choose an available BPSO personnel.']);
                exit;
            }

            if (!isPatrolAtHall($pdo, $patrolId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Selected personnel is not at the barangay hall. Only personnel who have timed in today can be assigned.']);
                exit;
            }

            $assignedLabel = $personnel['personnel_name'] . ' (' . $personnel['bpso_personnel_id'] . ')';
            $timestamp = date('Y-m-d H:i:s');

            $complaintStmt = $pdo->prepare('SELECT notes FROM complaints WHERE id = :id');
            $complaintStmt->execute([':id' => $id]);
            $complaint = $complaintStmt->fetch(PDO::FETCH_ASSOC);
            if (!$complaint) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Complaint not found.']);
                exit;
            }

            $noteEntry = "[{$timestamp}] Assigned to {$assignedLabel} for investigation.";
            $updatedNotes = trim(($complaint['notes'] ?? '') . "\n\n" . $noteEntry);

            $stmt = $pdo->prepare('UPDATE complaints SET assigned_patrol_id = :assigned_patrol_id, assigned_to = :assigned_to, assigned_at = :assigned_at, status = :status, notes = :notes, resolution_report = NULL, resolved_at = NULL WHERE id = :id');
            $stmt->execute([
                ':assigned_patrol_id' => $patrolId,
                ':assigned_to' => $assignedLabel,
                ':assigned_at' => $timestamp,
                ':status' => 'Processing',
                ':notes' => $updatedNotes,
                ':id' => $id,
            ]);

            $pdo->prepare('UPDATE patrols SET status = :status WHERE id = :id')->execute([
                ':status' => 'Assigned',
                ':id' => $patrolId,
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Complaint assigned successfully.',
                'data' => ['assigned_to' => $assignedLabel, 'status' => 'Processing'],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to assign complaint: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'forward') {
        $id = (int) ($input['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid complaint ID.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('SELECT ' . complaintsSelectColumns() . ' FROM complaints WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $complaint = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$complaint) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Complaint not found.']);
                exit;
            }

            $complaint = enrichComplaintAssignment($pdo, $complaint);

            if (!empty($complaint['forwarded_at'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'This complaint was already forwarded to the Digital Blotter System.',
                    'data' => [
                        'forwarded_at' => $complaint['forwarded_at'],
                        'blotter_reference_id' => $complaint['blotter_reference_id'] ?? '',
                    ],
                ]);
                exit;
            }

            $forwardResult = forwardComplaintToBlotter($complaint);
            if (!$forwardResult['success']) {
                http_response_code(502);
                echo json_encode([
                    'success' => false,
                    'message' => $forwardResult['message'] ?? 'Failed to forward complaint.',
                ]);
                exit;
            }

            $timestamp = date('Y-m-d H:i:s');
            $referenceId = trim($forwardResult['blotter_reference_id'] ?? '');
            $referenceNote = $referenceId !== '' ? " (Ref: {$referenceId})" : '';
            $noteEntry = "[{$timestamp}] Forwarded to Digital Blotter System{$referenceNote}.";
            $updatedNotes = trim(($complaint['notes'] ?? '') . "\n\n" . $noteEntry);

            $updateStmt = $pdo->prepare('UPDATE complaints SET status = :status, forwarded_at = :forwarded_at, blotter_reference_id = :blotter_reference_id, notes = :notes WHERE id = :id');
            $updateStmt->execute([
                ':status' => 'Forwarded to Digital Blotter',
                ':forwarded_at' => $timestamp,
                ':blotter_reference_id' => $referenceId !== '' ? $referenceId : null,
                ':notes' => $updatedNotes,
                ':id' => $id,
            ]);

            echo json_encode([
                'success' => true,
                'message' => $forwardResult['message'] ?? 'Complaint forwarded to Digital Blotter System.',
                'data' => [
                    'status' => 'Forwarded to Digital Blotter',
                    'forwarded_at' => $timestamp,
                    'blotter_reference_id' => $referenceId,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to forward complaint: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Complaint content cannot be modified after submission. Use manage, assign, or forward actions.',
        ]);
        exit;
    }

    if ($action === 'manage') {
        $id = (int) ($input['id'] ?? 0);
        $status = trim($input['status'] ?? '');
        $notesProvided = array_key_exists('notes', $input);
        $notes = $notesProvided ? trim($input['notes']) : null;

        if ($id <= 0 || $status === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Complaint ID and status are required.']);
            exit;
        }

        $allowedStatuses = ['Pending', 'Processing', 'Resolved', 'Rejected', 'Forwarded to Digital Blotter'];
        if (!in_array($status, $allowedStatuses, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
            exit;
        }

        try {
            $currentStmt = $pdo->prepare('SELECT assigned_patrol_id, notes, status, forwarded_at FROM complaints WHERE id = :id');
            $currentStmt->execute([':id' => $id]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Complaint not found.']);
                exit;
            }

            if (!empty($current['forwarded_at']) && $status !== 'Forwarded to Digital Blotter') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Status cannot be changed after forwarding to the Digital Blotter.']);
                exit;
            }

            if ($notes === null) {
                $notes = $current['notes'] ?? '';
            }

            $stmt = $pdo->prepare('UPDATE complaints SET status = :status, notes = :notes WHERE id = :id');
            $stmt->execute([
                ':status' => $status,
                ':notes' => $notes,
                ':id' => $id,
            ]);

            if ($status === 'Resolved' && !empty($current['assigned_patrol_id'])) {
                $pdo->prepare('UPDATE patrols SET status = :status WHERE id = :id')->execute([
                    ':status' => 'Available',
                    ':id' => (int) $current['assigned_patrol_id'],
                ]);
            }

            echo json_encode(['success' => true, 'message' => 'Complaint updated successfully.']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update complaint: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid complaint ID.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM complaints WHERE id = :id');
            $stmt->execute([':id' => $id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete complaint: ' . $e->getMessage()]);
        }
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

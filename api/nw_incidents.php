<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/nw_incidents_schema.php';
require_once __DIR__ . '/nw_members_schema.php';
require_once __DIR__ . '/bpso_attendance_schema.php';
require_once __DIR__ . '/notifications_schema.php';
require_once __DIR__ . '/../includes/nw_member_auth.php';

try {
    ensureNwMembersTable($pdo);
    ensureNwIncidentReportsTable($pdo);
    ensureBpsoAttendanceTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare incident reports table: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

$isAdmin = !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isMember = isNwMemberLoggedIn();

if ($method === 'GET') {
    try {
        if ($isAdmin) {
            $stmt = $pdo->query('SELECT ' . nwIncidentSelectColumns() . ' FROM nw_incident_reports ORDER BY id DESC');
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($isMember) {
            $stmt = $pdo->prepare('SELECT ' . nwIncidentSelectColumns() . ' FROM nw_incident_reports WHERE volunteer_id = :volunteer_id ORDER BY id DESC');
            $stmt->execute([':volunteer_id' => getNwMemberId()]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        echo json_encode(['success' => true, 'data' => $reports]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load incident reports: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    if ($action === 'create') {
        if (!$isMember) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $location = trim($input['location'] ?? '');
        $description = trim($input['description'] ?? '');
        $photoData = $input['photo'] ?? null;

        if ($location === '' || $description === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Location and description are required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('SELECT name, contact, email FROM nw_members WHERE id = :id AND status = :status LIMIT 1');
            $stmt->execute([':id' => getNwMemberId(), ':status' => 'Active']);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$member) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Active member account required.']);
                exit;
            }

            $reportId = generateNwIncidentReportId($pdo);

            $stmt = $pdo->prepare('INSERT INTO nw_incident_reports (report_id, volunteer_id, member_name, member_contact, member_email, location, description, photo_data, status) VALUES (:report_id, :volunteer_id, :member_name, :member_contact, :member_email, :location, :description, :photo_data, :status)');
            $stmt->execute([
                ':report_id' => $reportId,
                ':volunteer_id' => getNwMemberId(),
                ':member_name' => $member['name'],
                ':member_contact' => $member['contact'],
                ':member_email' => $member['email'],
                ':location' => $location,
                ':description' => $description,
                ':photo_data' => $photoData ?: null,
                ':status' => 'Under Review',
            ]);

            $id = (int) $pdo->lastInsertId();

            createAdminNotification(
                $pdo,
                'nw_incident',
                'Neighborhood Watch Incident Report',
                $reportId . ' - ' . $location . ' (' . $member['name'] . ')',
                'review-nw-incidents.php?id=' . urlencode($reportId)
            );

            echo json_encode([
                'success' => true,
                'message' => 'Incident report submitted successfully.',
                'data' => [
                    'id' => $id,
                    'report_id' => $reportId,
                    'status' => 'Under Review',
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to submit incident report: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'assign') {
        if (!$isAdmin) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $id = (int) ($input['id'] ?? 0);
        $patrolId = (int) ($input['assigned_patrol_id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid incident report ID.']);
            exit;
        }

        try {
            $reportStmt = $pdo->prepare('SELECT report_id, location, assigned_patrol_id, assigned_to, status FROM nw_incident_reports WHERE id = :id');
            $reportStmt->execute([':id' => $id]);
            $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
            if (!$report) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Incident report not found.']);
                exit;
            }

            $previousPatrolId = (int) ($report['assigned_patrol_id'] ?? 0);

            if ($patrolId <= 0) {
                $stmt = $pdo->prepare('UPDATE nw_incident_reports SET assigned_patrol_id = NULL, assigned_to = NULL, assigned_at = NULL, resolution_report = NULL, resolved_at = NULL, status = :status WHERE id = :id');
                $stmt->execute([
                    ':status' => 'Under Review',
                    ':id' => $id,
                ]);
                if ($previousPatrolId > 0) {
                    $pdo->prepare('UPDATE patrols SET status = :status WHERE id = :id')->execute([
                        ':status' => 'Available',
                        ':id' => $previousPatrolId,
                    ]);
                }
                echo json_encode(['success' => true, 'message' => 'Assignment cleared.']);
                exit;
            }

            if ($patrolId === $previousPatrolId) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Assignment saved.',
                    'data' => [
                        'assigned_to' => $report['assigned_to'] ?? null,
                        'status' => $report['status'] ?? 'In Progress',
                    ],
                ]);
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

            $stmt = $pdo->prepare('UPDATE nw_incident_reports SET assigned_patrol_id = :assigned_patrol_id, assigned_to = :assigned_to, assigned_at = :assigned_at, status = :status, resolution_report = NULL, resolved_at = NULL WHERE id = :id');
            $stmt->execute([
                ':assigned_patrol_id' => $patrolId,
                ':assigned_to' => $assignedLabel,
                ':assigned_at' => $timestamp,
                ':status' => 'In Progress',
                ':id' => $id,
            ]);

            $pdo->prepare('UPDATE patrols SET status = :status WHERE id = :id')->execute([
                ':status' => 'Assigned',
                ':id' => $patrolId,
            ]);

            if ($previousPatrolId > 0 && $previousPatrolId !== $patrolId) {
                $pdo->prepare('UPDATE patrols SET status = :status WHERE id = :id')->execute([
                    ':status' => 'Available',
                    ':id' => $previousPatrolId,
                ]);
            }

            createPatrolNotification(
                $pdo,
                $patrolId,
                'nw_incident_assignment',
                'Neighborhood Watch Incident Assigned',
                $report['report_id'] . ' — ' . $report['location'],
                'tab:nw-incidents:' . $id,
                $timestamp
            );

            echo json_encode([
                'success' => true,
                'message' => 'Incident report assigned successfully.',
                'data' => ['assigned_to' => $assignedLabel, 'status' => 'In Progress'],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to assign incident report: ' . $e->getMessage()]);
        }
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

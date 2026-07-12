<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/patrol_schedules_schema.php';
require_once __DIR__ . '/bpso_attendance_schema.php';
require_once __DIR__ . '/../includes/bpso_auth.php';

try {
    ensurePatrolSchedulesTable($pdo);
    ensureBpsoAttendanceTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare patrol schedules table: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

if ($method === 'GET') {
    if (!isAdminLoggedIn() && !isBpsoLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    try {
        if (isBpsoLoggedIn()) {
            $patrolId = getBpsoPatrolId();
            $stmt = $pdo->prepare('SELECT id, patrol_id, personnel_name, route, location, schedule_date, schedule_time, notes, status, created_at FROM patrol_schedules WHERE patrol_id = :patrol_id ORDER BY schedule_date DESC, id DESC');
            $stmt->execute([':patrol_id' => $patrolId]);
        } else {
            $stmt = $pdo->query('SELECT id, patrol_id, personnel_name, route, location, schedule_date, schedule_time, notes, status, created_at FROM patrol_schedules ORDER BY schedule_date DESC, id DESC');
        }

        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load patrol schedules: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    if ($action === 'create') {
        if (!isAdminLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $patrolId = (int)($input['patrol_id'] ?? 0);
        $route = trim($input['route'] ?? '');
        $location = trim($input['location'] ?? '');
        $scheduleDate = trim($input['schedule_date'] ?? '');
        $scheduleTime = trim($input['schedule_time'] ?? '');
        $notes = trim($input['notes'] ?? '');

        if ($patrolId <= 0 || $route === '' || $scheduleDate === '' || $scheduleTime === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        try {
            $personnelStmt = $pdo->prepare('SELECT personnel_name FROM patrols WHERE id = :id');
            $personnelStmt->execute([':id' => $patrolId]);
            $personnel = $personnelStmt->fetch(PDO::FETCH_ASSOC);
            if (!$personnel) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'BPSO personnel not found.']);
                exit;
            }

            if (!isPatrolAtHall($pdo, $patrolId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Selected personnel is not at the barangay hall. Only personnel who have timed in today can be assigned to patrol.']);
                exit;
            }

            $stmt = $pdo->prepare('INSERT INTO patrol_schedules (patrol_id, personnel_name, route, location, schedule_date, schedule_time, notes, status) VALUES (:patrol_id, :personnel_name, :route, :location, :schedule_date, :schedule_time, :notes, :status)');
            $stmt->execute([
                ':patrol_id' => $patrolId,
                ':personnel_name' => $personnel['personnel_name'],
                ':route' => $route,
                ':location' => $location,
                ':schedule_date' => $scheduleDate,
                ':schedule_time' => $scheduleTime,
                ':notes' => $notes,
                ':status' => 'Scheduled',
            ]);

            $id = (int)$pdo->lastInsertId();

            require_once __DIR__ . '/patrol_logs_schema.php';
            ensurePatrolLogsTable($pdo);
            $logStmt = $pdo->prepare('INSERT INTO patrol_logs (patrol_id, schedule_id, personnel_name, route, date, time, status, location, details) VALUES (:patrol_id, :schedule_id, :personnel_name, :route, :date, :time, :status, :location, :details)');
            $logStmt->execute([
                ':patrol_id' => $patrolId,
                ':schedule_id' => $id,
                ':personnel_name' => $personnel['personnel_name'],
                ':route' => $route,
                ':date' => $scheduleDate,
                ':time' => $scheduleTime,
                ':status' => 'Scheduled',
                ':location' => $location,
                ':details' => $notes !== '' ? $notes : 'Patrol assignment scheduled by admin.',
            ]);

            echo json_encode([
                'success' => true,
                'data' => ['id' => $id],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create patrol schedule: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update_status') {
        if (!isBpsoLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $scheduleId = (int)($input['schedule_id'] ?? 0);
        $status = trim($input['status'] ?? '');

        if ($scheduleId <= 0 || $status === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('UPDATE patrol_schedules SET status = :status WHERE id = :id AND patrol_id = :patrol_id');
            $stmt->execute([
                ':status' => $status,
                ':id' => $scheduleId,
                ':patrol_id' => getBpsoPatrolId(),
            ]);

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update schedule status: ' . $e->getMessage()]);
        }
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

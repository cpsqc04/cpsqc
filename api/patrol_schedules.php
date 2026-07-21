<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/patrol_schedules_schema.php';
require_once __DIR__ . '/bpso_attendance_schema.php';
require_once __DIR__ . '/../includes/bpso_auth.php';
<<<<<<< HEAD
require_once __DIR__ . '/../includes/patrol_shifts.php';
=======
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69

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
<<<<<<< HEAD
        $columns = patrolSchedulesSelectColumns();
        if (isBpsoLoggedIn()) {
            $patrolId = getBpsoPatrolId();
            $stmt = $pdo->prepare("SELECT {$columns} FROM patrol_schedules WHERE patrol_id = :patrol_id ORDER BY schedule_date DESC, id DESC");
            $stmt->execute([':patrol_id' => $patrolId]);
        } else {
            $stmt = $pdo->query("SELECT {$columns} FROM patrol_schedules ORDER BY schedule_date DESC, id DESC");
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $index => $row) {
            $rows[$index] = enrichPatrolScheduleRow($row);
        }

        echo json_encode(['success' => true, 'data' => $rows]);
=======
        if (isBpsoLoggedIn()) {
            $patrolId = getBpsoPatrolId();
            $stmt = $pdo->prepare('SELECT id, patrol_id, personnel_name, route, location, schedule_date, schedule_time, notes, status, created_at FROM patrol_schedules WHERE patrol_id = :patrol_id ORDER BY schedule_date DESC, id DESC');
            $stmt->execute([':patrol_id' => $patrolId]);
        } else {
            $stmt = $pdo->query('SELECT id, patrol_id, personnel_name, route, location, schedule_date, schedule_time, notes, status, created_at FROM patrol_schedules ORDER BY schedule_date DESC, id DESC');
        }

        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
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

<<<<<<< HEAD
        $patrolId = (int) ($input['patrol_id'] ?? 0);
        $patrolZone = trim($input['patrol_zone'] ?? '');
        $route = trim($input['route'] ?? $patrolZone);
        $location = trim($input['location'] ?? '');
        $scheduleDate = trim($input['schedule_date'] ?? '');
        $shift = trim($input['shift'] ?? '');
        $notes = trim($input['notes'] ?? '');

        if ($patrolId <= 0 || $patrolZone === '' || $scheduleDate === '' || !isValidPatrolShift($shift)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields. Select personnel, patrol zone, date, and shift.']);
=======
        $patrolId = (int)($input['patrol_id'] ?? 0);
        $route = trim($input['route'] ?? '');
        $location = trim($input['location'] ?? '');
        $scheduleDate = trim($input['schedule_date'] ?? '');
        $scheduleTime = trim($input['schedule_time'] ?? '');
        $notes = trim($input['notes'] ?? '');

        if ($patrolId <= 0 || $route === '' || $scheduleDate === '' || $scheduleTime === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
            exit;
        }

        try {
<<<<<<< HEAD
            $personnelStmt = $pdo->prepare('SELECT personnel_name, duty_shift FROM patrols WHERE id = :id');
=======
            $personnelStmt = $pdo->prepare('SELECT personnel_name FROM patrols WHERE id = :id');
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
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

<<<<<<< HEAD
            $personnelShift = trim((string) ($personnel['duty_shift'] ?? ''));
            if ($personnelShift !== '' && $personnelShift !== $shift) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Selected personnel is assigned to ' . $personnelShift . '. Assign patrol only during their duty shift.']);
                exit;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO patrol_schedules
                (patrol_id, personnel_name, patrol_zone, route, location, schedule_date, schedule_time, shift, notes, status)
                VALUES
                (:patrol_id, :personnel_name, :patrol_zone, :route, :location, :schedule_date, :schedule_time, :shift, :notes, :status)'
            );
            $stmt->execute([
                ':patrol_id' => $patrolId,
                ':personnel_name' => $personnel['personnel_name'],
                ':patrol_zone' => $patrolZone,
                ':route' => $route !== '' ? $route : $patrolZone,
                ':location' => $location !== '' ? $location : $patrolZone,
                ':schedule_date' => $scheduleDate,
                ':schedule_time' => '',
                ':shift' => $shift,
                ':notes' => $notes !== '' ? $notes : null,
                ':status' => 'Scheduled',
            ]);

            $id = (int) $pdo->lastInsertId();

            require_once __DIR__ . '/patrol_logs_schema.php';
            ensurePatrolLogsTable($pdo);
            $logStmt = $pdo->prepare(
                'INSERT INTO patrol_logs (patrol_id, schedule_id, personnel_name, route, date, time, status, location, details)
                 VALUES (:patrol_id, :schedule_id, :personnel_name, :route, :date, :time, :status, :location, :details)'
            );
=======
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
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
            $logStmt->execute([
                ':patrol_id' => $patrolId,
                ':schedule_id' => $id,
                ':personnel_name' => $personnel['personnel_name'],
<<<<<<< HEAD
                ':route' => $route !== '' ? $route : $patrolZone,
                ':date' => $scheduleDate,
                ':time' => '',
                ':status' => 'Scheduled',
                ':location' => $location !== '' ? $location : $patrolZone,
=======
                ':route' => $route,
                ':date' => $scheduleDate,
                ':time' => $scheduleTime,
                ':status' => 'Scheduled',
                ':location' => $location,
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
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

<<<<<<< HEAD
    if ($action === 'update_status' || $action === 'start_patrol') {
=======
    if ($action === 'update_status') {
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
        if (!isBpsoLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

<<<<<<< HEAD
        $scheduleId = (int) ($input['schedule_id'] ?? 0);
        $status = trim($input['status'] ?? 'In Progress');

        if ($scheduleId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing schedule ID.']);
=======
        $scheduleId = (int)($input['schedule_id'] ?? 0);
        $status = trim($input['status'] ?? '');

        if ($scheduleId <= 0 || $status === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
            exit;
        }

        try {
<<<<<<< HEAD
            $patrolId = getBpsoPatrolId();
            $check = $pdo->prepare('SELECT id, schedule_date, patrol_start, status FROM patrol_schedules WHERE id = :id AND patrol_id = :patrol_id LIMIT 1');
            $check->execute([':id' => $scheduleId, ':patrol_id' => $patrolId]);
            $schedule = $check->fetch(PDO::FETCH_ASSOC);
            if (!$schedule) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Patrol schedule not found.']);
                exit;
            }

            if ($schedule['status'] !== 'Scheduled') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'This patrol has already been started or completed.']);
                exit;
            }

            $now = new DateTime();
            $patrolStart = $now->format('H:i:s');

            $stmt = $pdo->prepare(
                'UPDATE patrol_schedules
                 SET status = :status,
                     patrol_start = :patrol_start,
                     schedule_time = :schedule_time
                 WHERE id = :id AND patrol_id = :patrol_id'
            );
            $stmt->execute([
                ':status' => $status !== '' ? $status : 'In Progress',
                ':patrol_start' => $patrolStart,
                ':schedule_time' => $patrolStart,
                ':id' => $scheduleId,
                ':patrol_id' => $patrolId,
            ]);

            require_once __DIR__ . '/patrol_logs_schema.php';
            ensurePatrolLogsTable($pdo);
            $logUpdate = $pdo->prepare(
                'UPDATE patrol_logs SET status = :status, time = :time WHERE schedule_id = :schedule_id AND patrol_id = :patrol_id'
            );
            $logUpdate->execute([
                ':status' => 'In Progress',
                ':time' => $patrolStart,
                ':schedule_id' => $scheduleId,
                ':patrol_id' => $patrolId,
            ]);

            echo json_encode([
                'success' => true,
                'data' => [
                    'patrol_start' => $patrolStart,
                    'patrol_start_display' => formatPatrolTimeDisplay($patrolStart),
                ],
            ]);
=======
            $stmt = $pdo->prepare('UPDATE patrol_schedules SET status = :status WHERE id = :id AND patrol_id = :patrol_id');
            $stmt->execute([
                ':status' => $status,
                ':id' => $scheduleId,
                ':patrol_id' => getBpsoPatrolId(),
            ]);

            echo json_encode(['success' => true]);
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update schedule status: ' . $e->getMessage()]);
        }
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/patrol_logs_schema.php';
require_once __DIR__ . '/../includes/bpso_auth.php';
<<<<<<< HEAD
require_once __DIR__ . '/../includes/patrol_shifts.php';
=======
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69

try {
    ensurePatrolLogsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare patrol logs table: ' . $e->getMessage()]);
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
            $stmt = $pdo->prepare('SELECT id, patrol_id, schedule_id, personnel_name, route, date, time, status, incidents, details, location, created_at FROM patrol_logs WHERE patrol_id = :patrol_id ORDER BY date DESC, id DESC');
            $stmt->execute([':patrol_id' => getBpsoPatrolId()]);
        } else {
            $stmt = $pdo->query('SELECT id, patrol_id, schedule_id, personnel_name, route, date, time, status, incidents, details, location, created_at FROM patrol_logs ORDER BY date DESC, id DESC');
        }

        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load patrol logs: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST' && $action === 'submit_report') {
    if (!isBpsoLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $patrolId = getBpsoPatrolId();
    $personnelName = getBpsoPersonnelName();
    $scheduleId = (int)($input['schedule_id'] ?? 0);
    $route = trim($input['route'] ?? '');
    $date = trim($input['date'] ?? '');
    $time = trim($input['time'] ?? '');
    $location = trim($input['location'] ?? '');
    $incidents = trim($input['incidents'] ?? 'None');
    $details = trim($input['details'] ?? '');
    $status = trim($input['status'] ?? 'Completed');

    if ($route === '' || $date === '' || $time === '' || $details === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    try {
        if ($scheduleId > 0) {
            require_once __DIR__ . '/patrol_schedules_schema.php';
            ensurePatrolSchedulesTable($pdo);

            $check = $pdo->prepare('SELECT id FROM patrol_schedules WHERE id = :id AND patrol_id = :patrol_id');
            $check->execute([':id' => $scheduleId, ':patrol_id' => $patrolId]);
            if (!$check->fetch()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Invalid patrol schedule selected.']);
                exit;
            }

            $updateLog = $pdo->prepare('UPDATE patrol_logs SET status = :status, incidents = :incidents, details = :details, location = :location, time = :time WHERE schedule_id = :schedule_id AND patrol_id = :patrol_id');
            $updateLog->execute([
                ':status' => $status,
                ':incidents' => $incidents,
                ':details' => $details,
                ':location' => $location,
                ':time' => $time,
                ':schedule_id' => $scheduleId,
                ':patrol_id' => $patrolId,
            ]);

            if ($updateLog->rowCount() === 0) {
                $insert = $pdo->prepare('INSERT INTO patrol_logs (patrol_id, schedule_id, personnel_name, route, date, time, status, incidents, details, location) VALUES (:patrol_id, :schedule_id, :personnel_name, :route, :date, :time, :status, :incidents, :details, :location)');
                $insert->execute([
                    ':patrol_id' => $patrolId,
                    ':schedule_id' => $scheduleId,
                    ':personnel_name' => $personnelName,
                    ':route' => $route,
                    ':date' => $date,
                    ':time' => $time,
                    ':status' => $status,
                    ':incidents' => $incidents,
                    ':details' => $details,
                    ':location' => $location,
                ]);
            }

<<<<<<< HEAD
            $scheduleUpdate = $pdo->prepare(
                'UPDATE patrol_schedules
                 SET status = :status,
                     patrol_end = :patrol_end,
                     duration_minutes = :duration_minutes
                 WHERE id = :id AND patrol_id = :patrol_id'
            );

            $patrolEnd = normalizePatrolTime($time);
            $durationMinutes = 0;

            $scheduleRow = $pdo->prepare('SELECT schedule_date, patrol_start FROM patrol_schedules WHERE id = :id AND patrol_id = :patrol_id LIMIT 1');
            $scheduleRow->execute([':id' => $scheduleId, ':patrol_id' => $patrolId]);
            $scheduleData = $scheduleRow->fetch(PDO::FETCH_ASSOC);
            if ($scheduleData && !empty($scheduleData['patrol_start']) && $patrolEnd !== '') {
                $durationMinutes = calculatePatrolDurationMinutes(
                    (string) $scheduleData['schedule_date'],
                    (string) $scheduleData['patrol_start'],
                    $patrolEnd
                );
            }

            $scheduleUpdate->execute([
                ':status' => $status === 'Completed' ? 'Completed' : 'In Progress',
                ':patrol_end' => $patrolEnd !== '' ? $patrolEnd : null,
                ':duration_minutes' => $durationMinutes,
=======
            $scheduleUpdate = $pdo->prepare('UPDATE patrol_schedules SET status = :status WHERE id = :id AND patrol_id = :patrol_id');
            $scheduleUpdate->execute([
                ':status' => $status === 'Completed' ? 'Completed' : 'In Progress',
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                ':id' => $scheduleId,
                ':patrol_id' => $patrolId,
            ]);
        } else {
            $insert = $pdo->prepare('INSERT INTO patrol_logs (patrol_id, schedule_id, personnel_name, route, date, time, status, incidents, details, location) VALUES (:patrol_id, NULL, :personnel_name, :route, :date, :time, :status, :incidents, :details, :location)');
            $insert->execute([
                ':patrol_id' => $patrolId,
                ':personnel_name' => $personnelName,
                ':route' => $route,
                ':date' => $date,
                ':time' => $time,
                ':status' => $status,
                ':incidents' => $incidents,
                ':details' => $details,
                ':location' => $location,
            ]);
        }

        echo json_encode(['success' => true, 'message' => 'Patrol report submitted successfully.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to submit patrol report: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

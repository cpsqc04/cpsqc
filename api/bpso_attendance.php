<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/bpso_attendance_schema.php';
require_once __DIR__ . '/../includes/bpso_auth.php';

try {
    ensureBpsoAttendanceTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare attendance table: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? ($_GET['action'] ?? '');

if ($method === 'GET') {
    if (!isAdminLoggedIn() && !isBpsoLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $view = trim($_GET['view'] ?? 'today');
    $date = trim($_GET['date'] ?? date('Y-m-d'));
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo = trim($_GET['date_to'] ?? '');

    if ($view === 'export') {
        if (!isAdminLoggedIn()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        try {
            $exportDate = $date !== '' ? $date : date('Y-m-d');
            $sql = 'SELECT a.id, a.patrol_id, a.personnel_name, a.bpso_personnel_id, a.attendance_date, a.time_in, a.time_out, a.notes, p.duty_shift
                    FROM bpso_attendance a
                    LEFT JOIN patrols p ON p.id = a.patrol_id
                    WHERE a.attendance_date = :attendance_date
                    ORDER BY a.time_in ASC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':attendance_date' => $exportDate]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $filename = 'bpso-attendance-' . $exportDate . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Patrol ID', 'Name', 'Duty', 'Duration', 'Overtime', 'Clock On Date', 'Clock On Time', 'Clock Out', 'Status', 'Date']);

            foreach ($rows as $row) {
                $enriched = enrichAttendanceRow($row, $pdo);
                $timeIn = (string) ($enriched['time_in'] ?? '');
                $clockOnDate = '';
                $clockOnTime = '';
                if ($timeIn !== '' && preg_match('/(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}(?::\d{2})?)/', $timeIn, $m)) {
                    $clockOnDate = $m[1];
                    $clockOnTime = $m[2];
                } elseif ($timeIn !== '') {
                    $clockOnDate = $timeIn;
                }

                fputcsv($out, [
                    $enriched['bpso_personnel_id'] ?? '',
                    $enriched['personnel_name'] ?? '',
                    $enriched['duty'] ?? '',
                    $enriched['patrol_duration_label'] ?? '',
                    $enriched['overtime_label'] ?? '',
                    $clockOnDate,
                    $clockOnTime,
                    $enriched['time_out'] ?? '',
                    $enriched['status_label'] ?? '',
                    $enriched['attendance_date'] ?? '',
                ]);
            }

            fclose($out);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to export attendance: ' . $e->getMessage()]);
        }
        exit;
    }

    try {
        if ($view === 'my_status' && isBpsoLoggedIn()) {
            $patrolId = getBpsoPatrolId();
            $stmt = $pdo->prepare(
                'SELECT ' . bpsoAttendanceSelectColumns() . ' FROM bpso_attendance
                 WHERE patrol_id = :patrol_id AND time_out IS NULL
                 ORDER BY time_in DESC LIMIT 1'
            );
            $stmt->execute([':patrol_id' => $patrolId]);
            $open = $stmt->fetch(PDO::FETCH_ASSOC);

            $today = date('Y-m-d');
            $activitySql = 'SELECT ' . bpsoAttendanceSelectColumns() . ' FROM bpso_attendance
                WHERE patrol_id = :patrol_id
                  AND (attendance_date = :today OR time_out IS NULL)
                ORDER BY time_in DESC';
            $activityStmt = $pdo->prepare($activitySql);
            $activityStmt->execute([
                ':patrol_id' => $patrolId,
                ':today' => $today,
            ]);
            $sessions = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($sessions as $index => $row) {
                $sessions[$index] = enrichAttendanceRow($row, $pdo);
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'is_at_hall' => (bool) $open,
                    'is_clocked_on' => (bool) $open,
                    'open_session' => $open ? enrichAttendanceRow($open, $pdo) : null,
                    'sessions' => $sessions,
                ],
            ]);
            exit;
        }

        $sql = 'SELECT ' . bpsoAttendanceSelectColumns('a') . ', p.duty_shift
                FROM bpso_attendance a
                LEFT JOIN patrols p ON p.id = a.patrol_id
                WHERE 1=1';
        $params = [];

        if (isBpsoLoggedIn()) {
            $sql .= ' AND a.patrol_id = :patrol_id';
            $params[':patrol_id'] = getBpsoPatrolId();
        } elseif (isAdminLoggedIn()) {
            $filterPatrolId = (int) ($_GET['patrol_id'] ?? 0);
            if ($filterPatrolId > 0) {
                $sql .= ' AND a.patrol_id = :filter_patrol_id';
                $params[':filter_patrol_id'] = $filterPatrolId;
            }
        }

        if ($view === 'at_hall') {
            $sql .= ' AND a.attendance_date = :attendance_date AND a.time_out IS NULL';
            $params[':attendance_date'] = date('Y-m-d');
        } elseif ($view === 'today') {
            $sql .= ' AND a.attendance_date = :attendance_date';
            $params[':attendance_date'] = $date;
        } elseif ($view === 'history') {
            if ($dateFrom !== '' && $dateTo !== '') {
                try {
                    $fromDt = new DateTime($dateFrom);
                    $toDt = new DateTime($dateTo);
                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid date range.']);
                    exit;
                }

                if ($fromDt > $toDt) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Date From cannot be later than Date To.']);
                    exit;
                }

                $daySpan = (int) $fromDt->diff($toDt)->days + 1;
                if ($daySpan > 10) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Date range is limited to 10 days.']);
                    exit;
                }

                $sql .= ' AND a.attendance_date BETWEEN :date_from AND :date_to';
                $params[':date_from'] = $fromDt->format('Y-m-d');
                $params[':date_to'] = $toDt->format('Y-m-d');
            } elseif ($date !== '') {
                $sql .= ' AND a.attendance_date = :attendance_date';
                $params[':attendance_date'] = $date;
            } else {
                $sql .= ' AND a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 9 DAY)';
            }
        }

        $sql .= ' ORDER BY a.time_in DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $index => $row) {
            $rows[$index] = enrichAttendanceRow($row, $pdo);
        }

        echo json_encode(['success' => true, 'data' => $rows]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load attendance: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    if ($action === 'time_in') {
        if (!isBpsoLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $patrolId = getBpsoPatrolId();
        $notes = trim($input['notes'] ?? '');

        try {
            $openStmt = $pdo->prepare(
                'SELECT id FROM bpso_attendance WHERE patrol_id = :patrol_id AND time_out IS NULL LIMIT 1'
            );
            $openStmt->execute([':patrol_id' => $patrolId]);
            if ($openStmt->fetch(PDO::FETCH_ASSOC)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'You are already clocked on. Please clock out first.']);
                exit;
            }

            $personnelStmt = $pdo->prepare('SELECT personnel_name, bpso_personnel_id FROM patrols WHERE id = :id LIMIT 1');
            $personnelStmt->execute([':id' => $patrolId]);
            $personnel = $personnelStmt->fetch(PDO::FETCH_ASSOC);
            if (!$personnel) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'BPSO personnel record not found.']);
                exit;
            }

            $timestamp = date('Y-m-d H:i:s');
            $insert = $pdo->prepare(
                'INSERT INTO bpso_attendance (patrol_id, personnel_name, bpso_personnel_id, attendance_date, time_in, notes)
                 VALUES (:patrol_id, :personnel_name, :bpso_personnel_id, :attendance_date, :time_in, :notes)'
            );
            $insert->execute([
                ':patrol_id' => $patrolId,
                ':personnel_name' => $personnel['personnel_name'],
                ':bpso_personnel_id' => $personnel['bpso_personnel_id'] ?? null,
                ':attendance_date' => date('Y-m-d'),
                ':time_in' => $timestamp,
                ':notes' => $notes !== '' ? $notes : null,
            ]);

            require_once __DIR__ . '/notifications_schema.php';
            $attendanceId = (int) $pdo->lastInsertId();
            notifyAdminActorActivity(
                $pdo,
                'patrol',
                (string) $personnel['personnel_name'],
                'clocked on.',
                'bpso-attendance.php?activity=clock_in_' . $attendanceId
            );

            echo json_encode([
                'success' => true,
                'message' => 'Clocked on successfully.',
                'data' => [
                    'id' => (int) $pdo->lastInsertId(),
                    'time_in' => $timestamp,
                    'is_at_hall' => true,
                    'is_clocked_on' => true,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to clock on: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'time_out') {
        if (!isBpsoLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $patrolId = getBpsoPatrolId();

        try {
            $openStmt = $pdo->prepare(
                'SELECT a.id, a.time_in, p.duty_shift, p.personnel_name
                 FROM bpso_attendance a
                 LEFT JOIN patrols p ON p.id = a.patrol_id
                 WHERE a.patrol_id = :patrol_id AND a.time_out IS NULL
                 ORDER BY a.time_in DESC
                 LIMIT 1'
            );
            $openStmt->execute([':patrol_id' => $patrolId]);
            $open = $openStmt->fetch(PDO::FETCH_ASSOC);
            if (!$open) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No active clock-on session found. Please clock on first.']);
                exit;
            }

            $timestamp = date('Y-m-d H:i:s');
            $update = $pdo->prepare('UPDATE bpso_attendance SET time_out = :time_out WHERE id = :id AND patrol_id = :patrol_id');
            $update->execute([
                ':time_out' => $timestamp,
                ':id' => (int) $open['id'],
                ':patrol_id' => $patrolId,
            ]);

            require_once __DIR__ . '/notifications_schema.php';
            notifyAdminActorActivity(
                $pdo,
                'patrol',
                (string) ($open['personnel_name'] ?? getBpsoPersonnelName()),
                'clocked out.',
                'bpso-attendance.php?activity=clock_out_' . (int) $open['id']
            );

            echo json_encode([
                'success' => true,
                'message' => 'Clocked out successfully.',
                'data' => [
                    'id' => (int) $open['id'],
                    'time_in' => $open['time_in'],
                    'time_out' => $timestamp,
                    'is_at_hall' => false,
                    'is_clocked_on' => false,
                    'duration_label' => formatHallDurationLabel($open['time_in'], $timestamp),
                    'overtime_label' => formatOvertimeLabel($open['time_in'], $timestamp, $open['duty_shift'] ?? null),
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to clock out: ' . $e->getMessage()]);
        }
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

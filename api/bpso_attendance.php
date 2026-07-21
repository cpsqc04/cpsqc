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

<<<<<<< HEAD
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
            fputcsv($out, ['Personnel ID', 'Name', 'Duty', 'Patrol Duration', 'Time In', 'Time Out', 'Status', 'Date']);

            foreach ($rows as $row) {
                $enriched = enrichAttendanceRow($row, $pdo);
                fputcsv($out, [
                    $enriched['bpso_personnel_id'] ?? '',
                    $enriched['personnel_name'] ?? '',
                    $enriched['duty'] ?? '',
                    $enriched['patrol_duration_label'] ?? '',
                    $enriched['time_in'] ?? '',
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

=======
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
    try {
        if ($view === 'my_status' && isBpsoLoggedIn()) {
            $stmt = $pdo->prepare(
                'SELECT ' . bpsoAttendanceSelectColumns() . ' FROM bpso_attendance
                 WHERE patrol_id = :patrol_id AND attendance_date = :attendance_date AND time_out IS NULL
                 ORDER BY time_in DESC LIMIT 1'
            );
            $stmt->execute([
                ':patrol_id' => getBpsoPatrolId(),
                ':attendance_date' => date('Y-m-d'),
            ]);
            $open = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'data' => [
                    'is_at_hall' => (bool) $open,
<<<<<<< HEAD
                    'open_session' => $open ? enrichAttendanceRow($open, $pdo) : null,
=======
                    'open_session' => $open ? enrichAttendanceRow($open) : null,
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                ],
            ]);
            exit;
        }

<<<<<<< HEAD
        $sql = 'SELECT ' . bpsoAttendanceSelectColumns('a') . ', p.duty_shift
                FROM bpso_attendance a
                LEFT JOIN patrols p ON p.id = a.patrol_id
                WHERE 1=1';
        $params = [];

        if (isBpsoLoggedIn()) {
            $sql .= ' AND a.patrol_id = :patrol_id';
=======
        $sql = 'SELECT ' . bpsoAttendanceSelectColumns() . ' FROM bpso_attendance WHERE 1=1';
        $params = [];

        if (isBpsoLoggedIn()) {
            $sql .= ' AND patrol_id = :patrol_id';
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
            $params[':patrol_id'] = getBpsoPatrolId();
        }

        if ($view === 'at_hall') {
<<<<<<< HEAD
            $sql .= ' AND a.attendance_date = :attendance_date AND a.time_out IS NULL';
            $params[':attendance_date'] = date('Y-m-d');
        } elseif ($view === 'today') {
            $sql .= ' AND a.attendance_date = :attendance_date';
            $params[':attendance_date'] = $date;
        } elseif ($view === 'history') {
            if ($date !== '') {
                $sql .= ' AND a.attendance_date = :attendance_date';
=======
            $sql .= ' AND attendance_date = :attendance_date AND time_out IS NULL';
            $params[':attendance_date'] = date('Y-m-d');
        } elseif ($view === 'today') {
            $sql .= ' AND attendance_date = :attendance_date';
            $params[':attendance_date'] = $date;
        } elseif ($view === 'history') {
            if ($date !== '') {
                $sql .= ' AND attendance_date = :attendance_date';
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                $params[':attendance_date'] = $date;
            }
        }

<<<<<<< HEAD
        $sql .= ' ORDER BY a.time_in DESC';
=======
        $sql .= ' ORDER BY time_in DESC';
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $index => $row) {
<<<<<<< HEAD
            $rows[$index] = enrichAttendanceRow($row, $pdo);
=======
            $rows[$index] = enrichAttendanceRow($row);
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
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
                'SELECT id FROM bpso_attendance WHERE patrol_id = :patrol_id AND attendance_date = :attendance_date AND time_out IS NULL LIMIT 1'
            );
            $openStmt->execute([
                ':patrol_id' => $patrolId,
                ':attendance_date' => date('Y-m-d'),
            ]);
            if ($openStmt->fetch(PDO::FETCH_ASSOC)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'You are already timed in at the barangay hall.']);
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

            echo json_encode([
                'success' => true,
                'message' => 'Timed in at barangay hall.',
                'data' => [
                    'id' => (int) $pdo->lastInsertId(),
                    'time_in' => $timestamp,
                    'is_at_hall' => true,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to time in: ' . $e->getMessage()]);
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
                'SELECT id, time_in FROM bpso_attendance WHERE patrol_id = :patrol_id AND attendance_date = :attendance_date AND time_out IS NULL ORDER BY time_in DESC LIMIT 1'
            );
            $openStmt->execute([
                ':patrol_id' => $patrolId,
                ':attendance_date' => date('Y-m-d'),
            ]);
            $open = $openStmt->fetch(PDO::FETCH_ASSOC);
            if (!$open) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No active hall attendance session found. Please time in first.']);
                exit;
            }

            $timestamp = date('Y-m-d H:i:s');
            $update = $pdo->prepare('UPDATE bpso_attendance SET time_out = :time_out WHERE id = :id AND patrol_id = :patrol_id');
            $update->execute([
                ':time_out' => $timestamp,
                ':id' => (int) $open['id'],
                ':patrol_id' => $patrolId,
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Timed out from barangay hall.',
                'data' => [
                    'id' => (int) $open['id'],
                    'time_in' => $open['time_in'],
                    'time_out' => $timestamp,
                    'is_at_hall' => false,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to time out: ' . $e->getMessage()]);
        }
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

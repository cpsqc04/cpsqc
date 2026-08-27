<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/patrol_logs_schema.php';
require_once __DIR__ . '/../includes/bpso_auth.php';
require_once __DIR__ . '/../includes/patrol_shifts.php';
require_once __DIR__ . '/../includes/patrol_availability.php';

function validatePatrolDocumentationPhoto(?string $photoData, bool $required = true): ?string
{
    $photoData = trim((string) $photoData);
    if ($photoData === '') {
        return $required ? 'Documentation photo is required as evidence.' : null;
    }

    if (!preg_match('/^data:image\/(jpeg|jpg|png|gif|webp);base64,/', $photoData)) {
        return 'Invalid photo format. Use JPG, PNG, GIF, or WebP.';
    }

    if (strlen($photoData) > 3.5 * 1024 * 1024) {
        return 'Photo is too large. Please upload an image under 2MB.';
    }

    return null;
}

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
        $singleId = (int) ($_GET['id'] ?? 0);
        $listColumns = 'id, patrol_id, schedule_id, personnel_name, route, date, time, status, incidents, details, location, campaign_forwarded_at, campaign_reference_id, created_at, CASE WHEN documentation_photo IS NOT NULL AND documentation_photo != \'\' THEN 1 ELSE 0 END AS has_documentation_photo';
        $detailColumns = 'id, patrol_id, schedule_id, personnel_name, route, date, time, status, incidents, details, location, documentation_photo, campaign_forwarded_at, campaign_reference_id, created_at, CASE WHEN documentation_photo IS NOT NULL AND documentation_photo != \'\' THEN 1 ELSE 0 END AS has_documentation_photo';

        if ($singleId > 0) {
            if (isBpsoLoggedIn()) {
                $stmt = $pdo->prepare("SELECT {$detailColumns} FROM patrol_logs WHERE id = :id AND patrol_id = :patrol_id LIMIT 1");
                $stmt->execute([':id' => $singleId, ':patrol_id' => getBpsoPatrolId()]);
            } else {
                $stmt = $pdo->prepare("SELECT {$detailColumns} FROM patrol_logs WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $singleId]);
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Patrol log not found.']);
                exit;
            }
            $row['has_documentation_photo'] = (int) ($row['has_documentation_photo'] ?? 0) === 1;
            if (isBpsoLoggedIn()) {
                require_once __DIR__ . '/bpso_attendance_schema.php';
                ensureBpsoAttendanceTable($pdo);
                $row['can_edit'] = isPatrolClockedOn($pdo, getBpsoPatrolId()) && (($row['status'] ?? '') !== 'Scheduled');
            }
            echo json_encode(['success' => true, 'data' => $row]);
            exit;
        }

        // List omits base64 documentation_photo (multi-MB payloads). Use ?id= for photo.
        if (isBpsoLoggedIn()) {
            $stmt = $pdo->prepare("SELECT {$listColumns} FROM patrol_logs WHERE patrol_id = :patrol_id ORDER BY date DESC, id DESC");
            $stmt->execute([':patrol_id' => getBpsoPatrolId()]);
        } else {
            $stmt = $pdo->query("SELECT {$listColumns} FROM patrol_logs ORDER BY date DESC, id DESC");
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (isBpsoLoggedIn()) {
            require_once __DIR__ . '/bpso_attendance_schema.php';
            ensureBpsoAttendanceTable($pdo);
            $canEdit = isPatrolClockedOn($pdo, getBpsoPatrolId());
            foreach ($rows as $index => $row) {
                $rows[$index]['can_edit'] = $canEdit && (($row['status'] ?? '') !== 'Scheduled');
                $rows[$index]['has_documentation_photo'] = (int) ($row['has_documentation_photo'] ?? 0) === 1;
                $rows[$index]['documentation_photo'] = null;
            }
        } else {
            foreach ($rows as $index => $row) {
                $rows[$index]['has_documentation_photo'] = (int) ($row['has_documentation_photo'] ?? 0) === 1;
                $rows[$index]['documentation_photo'] = null;
            }
        }

        echo json_encode(['success' => true, 'data' => $rows]);
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
    $documentationPhoto = trim((string) ($input['documentation_photo'] ?? ''));

    if ($route === '' || $date === '' || $time === '' || $details === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    $photoError = validatePatrolDocumentationPhoto($documentationPhoto, false);
    if ($photoError !== null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $photoError]);
        exit;
    }
    if ($documentationPhoto === '') {
        $documentationPhoto = null;
    }

    try {
        if ($scheduleId > 0) {
            require_once __DIR__ . '/patrol_schedules_schema.php';
            ensurePatrolSchedulesTable($pdo);

            $check = $pdo->prepare('SELECT id, schedule_date, patrol_start, shift FROM patrol_schedules WHERE id = :id AND patrol_id = :patrol_id LIMIT 1');
            $check->execute([':id' => $scheduleId, ':patrol_id' => $patrolId]);
            $scheduleData = $check->fetch(PDO::FETCH_ASSOC);
            if (!$scheduleData) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Invalid patrol schedule selected.']);
                exit;
            }

            $countStmt = $pdo->prepare("
                SELECT COUNT(*) FROM patrol_logs
                WHERE schedule_id = :schedule_id
                  AND patrol_id = :patrol_id
                  AND status <> 'Scheduled'
            ");
            $countStmt->execute([
                ':schedule_id' => $scheduleId,
                ':patrol_id' => $patrolId,
            ]);
            $existingReportCount = (int) $countStmt->fetchColumn();

            $placeholderStmt = $pdo->prepare("
                SELECT id FROM patrol_logs
                WHERE schedule_id = :schedule_id
                  AND patrol_id = :patrol_id
                  AND status = 'Scheduled'
                ORDER BY id ASC
                LIMIT 1
            ");
            $placeholderStmt->execute([
                ':schedule_id' => $scheduleId,
                ':patrol_id' => $patrolId,
            ]);
            $placeholderId = (int) ($placeholderStmt->fetchColumn() ?: 0);

            if ($placeholderId > 0) {
                $updateLog = $pdo->prepare('UPDATE patrol_logs SET status = :status, route = :route, date = :date, time = :time, incidents = :incidents, details = :details, location = :location, documentation_photo = :documentation_photo WHERE id = :id AND patrol_id = :patrol_id');
                $updateLog->execute([
                    ':status' => $status,
                    ':route' => $route,
                    ':date' => $date,
                    ':time' => $time,
                    ':incidents' => $incidents,
                    ':details' => $details,
                    ':location' => $location,
                    ':documentation_photo' => $documentationPhoto,
                    ':id' => $placeholderId,
                    ':patrol_id' => $patrolId,
                ]);
            } else {
                $insert = $pdo->prepare('INSERT INTO patrol_logs (patrol_id, schedule_id, personnel_name, route, date, time, status, incidents, details, location, documentation_photo) VALUES (:patrol_id, :schedule_id, :personnel_name, :route, :date, :time, :status, :incidents, :details, :location, :documentation_photo)');
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
                    ':documentation_photo' => $documentationPhoto,
                ]);
            }

            $reportCount = $existingReportCount + 1;

            $patrolEnd = normalizePatrolTime($time);
            $durationMinutes = 0;
            $patrolStart = normalizePatrolTime((string) ($scheduleData['patrol_start'] ?? ''));
            if ($patrolStart === '') {
                $shift = trim((string) ($scheduleData['shift'] ?? ''));
                if ($shift === PATROL_SHIFT_DAY) {
                    $patrolStart = normalizePatrolTime(PATROL_SHIFT_DAY_START);
                } elseif ($shift === PATROL_SHIFT_NIGHT) {
                    $patrolStart = normalizePatrolTime(PATROL_SHIFT_NIGHT_START);
                } else {
                    $patrolStart = $patrolEnd;
                }
            }

            if ($patrolStart !== '' && $patrolEnd !== '') {
                $durationMinutes = calculatePatrolDurationMinutes(
                    (string) $scheduleData['schedule_date'],
                    $patrolStart,
                    $patrolEnd
                );
            }

            $scheduleUpdate = $pdo->prepare(
                'UPDATE patrol_schedules
                 SET status = :status,
                     patrol_start = COALESCE(NULLIF(patrol_start, ""), :patrol_start),
                     patrol_end = :patrol_end,
                     schedule_time = COALESCE(NULLIF(schedule_time, ""), :schedule_time),
                     duration_minutes = :duration_minutes
                 WHERE id = :id AND patrol_id = :patrol_id'
            );

            $scheduleUpdate->execute([
                ':status' => $status === 'Completed' ? 'Completed' : 'In Progress',
                ':patrol_start' => $patrolStart !== '' ? $patrolStart : null,
                ':patrol_end' => $patrolEnd !== '' ? $patrolEnd : null,
                ':schedule_time' => $patrolStart !== '' ? $patrolStart : null,
                ':duration_minutes' => $durationMinutes,
                ':id' => $scheduleId,
                ':patrol_id' => $patrolId,
            ]);

            clearOnReportingAfterPatrolReport($pdo, $patrolId);

            $reportLogId = $placeholderId > 0 ? $placeholderId : (int) $pdo->lastInsertId();
            require_once __DIR__ . '/notifications_schema.php';
            notifyAdminActorActivity(
                $pdo,
                'patrol',
                $personnelName !== '' ? $personnelName : 'Patrol personnel',
                'submitted a patrol report for route ' . $route . '.',
                'patrol-logs.php?activity=report_' . $reportLogId
            );

            echo json_encode([
                'success' => true,
                'message' => 'Patrol report submitted successfully.',
                'data' => [
                    'schedule_id' => $scheduleId,
                    'report_count' => $reportCount,
                ],
            ]);
            exit;
        }

        $insert = $pdo->prepare('INSERT INTO patrol_logs (patrol_id, schedule_id, personnel_name, route, date, time, status, incidents, details, location, documentation_photo) VALUES (:patrol_id, NULL, :personnel_name, :route, :date, :time, :status, :incidents, :details, :location, :documentation_photo)');
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
            ':documentation_photo' => $documentationPhoto,
        ]);

        require_once __DIR__ . '/notifications_schema.php';
        $newLogId = (int) $pdo->lastInsertId();
        clearOnReportingAfterPatrolReport($pdo, $patrolId);
        notifyAdminActorActivity(
            $pdo,
            'patrol',
            $personnelName !== '' ? $personnelName : 'Patrol personnel',
            'submitted a patrol report for route ' . $route . '.',
            'patrol-logs.php?activity=report_' . $newLogId
        );

        echo json_encode(['success' => true, 'message' => 'Patrol report submitted successfully.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to submit patrol report: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST' && $action === 'update_report') {
    if (!isBpsoLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $patrolId = getBpsoPatrolId();
    $reportId = (int) ($input['id'] ?? 0);
    $route = trim($input['route'] ?? '');
    $date = trim($input['date'] ?? '');
    $time = trim($input['time'] ?? '');
    $location = trim($input['location'] ?? '');
    $incidents = trim($input['incidents'] ?? 'None');
    $details = trim($input['details'] ?? '');
    $documentationPhoto = trim((string) ($input['documentation_photo'] ?? ''));

    if ($reportId <= 0 || $route === '' || $date === '' || $time === '' || $details === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    if ($documentationPhoto !== '') {
        $photoError = validatePatrolDocumentationPhoto($documentationPhoto, true);
        if ($photoError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $photoError]);
            exit;
        }
    }

    try {
        require_once __DIR__ . '/bpso_attendance_schema.php';
        ensureBpsoAttendanceTable($pdo);

        $reportStmt = $pdo->prepare('SELECT id, schedule_id, status, documentation_photo FROM patrol_logs WHERE id = :id AND patrol_id = :patrol_id LIMIT 1');
        $reportStmt->execute([':id' => $reportId, ':patrol_id' => $patrolId]);
        $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
        if (!$report || ($report['status'] ?? '') === 'Scheduled') {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Report not found.']);
            exit;
        }

        if (!isPatrolClockedOn($pdo, $patrolId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Editing is not allowed after you have clocked out.']);
            exit;
        }

        $photoToSave = $documentationPhoto !== '' ? $documentationPhoto : ($report['documentation_photo'] ?? null);
        if ($photoToSave === '') {
            $photoToSave = null;
        }

        $update = $pdo->prepare('UPDATE patrol_logs SET route = :route, date = :date, time = :time, location = :location, incidents = :incidents, details = :details, documentation_photo = :documentation_photo WHERE id = :id AND patrol_id = :patrol_id');
        $update->execute([
            ':route' => $route,
            ':date' => $date,
            ':time' => $time,
            ':location' => $location,
            ':incidents' => $incidents,
            ':details' => $details,
            ':documentation_photo' => $photoToSave,
            ':id' => $reportId,
            ':patrol_id' => $patrolId,
        ]);

        require_once __DIR__ . '/notifications_schema.php';
        notifyAdminActorActivity(
            $pdo,
            'patrol',
            getBpsoPersonnelName() !== '' ? getBpsoPersonnelName() : 'Patrol personnel',
            'updated a patrol report for route ' . $route . '.',
            'patrol-logs.php?activity=update_' . $reportId . '_' . time()
        );

        echo json_encode(['success' => true, 'message' => 'Patrol report updated successfully.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update patrol report: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST' && $action === 'upload_documentation') {
    if (!isBpsoLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $patrolId = getBpsoPatrolId();
    $reportId = (int) ($input['id'] ?? 0);
    $photoData = trim((string) ($input['documentation_photo'] ?? ''));

    if ($reportId <= 0 || $photoData === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Report ID and photo are required.']);
        exit;
    }

    if (!preg_match('/^data:image\/(jpeg|jpg|png|gif|webp);base64,/', $photoData)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid photo format. Use JPG, PNG, GIF, or WebP.']);
        exit;
    }

    if (strlen($photoData) > 3.5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Photo is too large. Please upload an image under 2MB.']);
        exit;
    }

    try {
        require_once __DIR__ . '/bpso_attendance_schema.php';
        ensureBpsoAttendanceTable($pdo);

        $reportStmt = $pdo->prepare('SELECT id, status FROM patrol_logs WHERE id = :id AND patrol_id = :patrol_id LIMIT 1');
        $reportStmt->execute([':id' => $reportId, ':patrol_id' => $patrolId]);
        $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
        if (!$report || ($report['status'] ?? '') === 'Scheduled') {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Report not found.']);
            exit;
        }

        if (!isPatrolClockedOn($pdo, $patrolId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Photo upload is not allowed after you have clocked out.']);
            exit;
        }

        $update = $pdo->prepare('UPDATE patrol_logs SET documentation_photo = :documentation_photo WHERE id = :id AND patrol_id = :patrol_id');
        $update->execute([
            ':documentation_photo' => $photoData,
            ':id' => $reportId,
            ':patrol_id' => $patrolId,
        ]);

        echo json_encode(['success' => true, 'message' => 'Documentation photo uploaded successfully.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to upload documentation photo: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

<?php
session_start();

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/awareness_events_schema.php';
require_once __DIR__ . '/../includes/json_response.php';
require_once __DIR__ . '/../includes/api_key_auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!$pdo instanceof PDO) {
    jsonResponse(['success' => false, 'message' => 'Database connection unavailable.'], 500, false);
}

try {
    ensureAwarenessEventsTable($pdo);
    ensureAwarenessEventReportsTable($pdo);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Failed to prepare awareness tables: ' . $e->getMessage()], 500, false);
}

$method = $_SERVER['REQUEST_METHOD'];
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if ($method === 'GET') {
    if (!canAccessPartnerList($isAdmin, 'AWARENESS_EVENTS_API_KEY')) {
        denyPartnerListAccess($isAdmin, 'AWARENESS_EVENTS_API_KEY', 'Awareness events');
    }

    $recordType = strtolower(trim($_GET['record_type'] ?? $_GET['type'] ?? 'event'));
    if ($recordType === 'events') {
        $recordType = 'event';
    }
    if ($recordType === 'reports') {
        $recordType = 'report';
    }

    try {
        if ($recordType === 'report') {
            $cols = awarenessEventReportsSelectColumns();
            $sql = "SELECT {$cols} FROM awareness_event_reports WHERE 1=1";
            $params = [];

            $reportId = trim($_GET['report_id'] ?? '');
            if ($reportId !== '') {
                $sql .= ' AND report_id = :report_id';
                $params[':report_id'] = $reportId;
            }

            $eventId = trim($_GET['event_id'] ?? '');
            if ($eventId !== '') {
                $sql .= ' AND event_id = :event_id';
                $params[':event_id'] = $eventId;
            }

            $sql .= ' ORDER BY event_date DESC, id DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            jsonResponse([
                'success' => true,
                'record_type' => 'report',
                'count' => count($rows),
                'data' => $rows,
            ]);
        }

        $cols = awarenessEventsSelectColumns();
        $sql = "SELECT {$cols} FROM awareness_events WHERE 1=1";
        $params = [];

        $eventId = trim($_GET['event_id'] ?? $_GET['id'] ?? '');
        if ($eventId !== '') {
            $sql .= ' AND event_id = :event_id';
            $params[':event_id'] = $eventId;
        }

        $status = trim($_GET['status'] ?? '');
        if ($status !== '') {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }

        $eventType = trim($_GET['event_type'] ?? '');
        if ($eventType !== '') {
            $sql .= ' AND event_type = :event_type';
            $params[':event_type'] = $eventType;
        }

        $sql .= ' ORDER BY event_date DESC, id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $index => $row) {
            $rows[$index]['source_group_label'] = awarenessEventSourceGroupLabel((string) $row['source_group']);
            $rows[$index]['event_time_display'] = formatAwarenessEventTime($row['event_time'] ?? '');
        }

        jsonResponse([
            'success' => true,
            'record_type' => 'event',
            'count' => count($rows),
            'data' => $rows,
        ]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to load awareness records: ' . $e->getMessage()], 500);
    }
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';

    if ($action === 'create') {
        if (!$isAdmin && !validateAwarenessEventApiKey()) {
            jsonResponse(['success' => false, 'message' => 'Invalid or missing API key.'], 401, false);
        }

        $recordType = strtolower(trim($input['record_type'] ?? 'event'));
        if ($recordType === 'report') {
            $data = normalizeAwarenessEventReportInput($input);
            $error = validateAwarenessEventReportFields($data);
            if ($error !== null) {
                jsonResponse(['success' => false, 'message' => $error], 400, false);
            }

            $reportId = $data['report_id'] !== '' ? $data['report_id'] : generateAwarenessEventReportId($pdo);
            $stmt = $pdo->prepare('INSERT INTO awareness_event_reports (
                report_id, event_id, source, source_group, source_reference_id,
                title, event_date, attendance_count, organizer, survey_result, location, description, submitted_at
            ) VALUES (
                :report_id, :event_id, :source, :source_group, :source_reference_id,
                :title, :event_date, :attendance_count, :organizer, :survey_result, :location, :description, NOW()
            )');
            $stmt->execute([
                ':report_id' => $reportId,
                ':event_id' => $data['event_id'],
                ':source' => $data['source'] !== '' ? $data['source'] : ($isAdmin ? 'admin_form' : 'partner_api'),
                ':source_group' => $data['source_group'],
                ':source_reference_id' => $data['source_reference_id'] !== '' ? $data['source_reference_id'] : null,
                ':title' => $data['title'],
                ':event_date' => $data['event_date'],
                ':attendance_count' => $data['attendance_count'],
                ':organizer' => $data['organizer'],
                ':survey_result' => $data['survey_result'] !== '' ? $data['survey_result'] : null,
                ':location' => $data['location'] !== '' ? $data['location'] : null,
                ':description' => $data['description'] !== '' ? $data['description'] : null,
            ]);

            jsonResponse([
                'success' => true,
                'message' => 'Event report saved.',
                'data' => ['report_id' => $reportId, 'record_type' => 'report'],
            ], 201, false);
        }

        $data = normalizeAwarenessEventInput($input);
        $error = validateAwarenessEventFields($data);
        if ($error !== null) {
            jsonResponse(['success' => false, 'message' => $error], 400, false);
        }

        $eventId = $data['event_id'] !== '' ? $data['event_id'] : generateAwarenessEventId($pdo);
        $stmt = $pdo->prepare('INSERT INTO awareness_events (
            event_id, source, source_group, source_reference_id,
            event_name, event_date, event_time, organizer, event_type, venue,
            status, description, contact_person, contact_number, contact_email, submitted_at
        ) VALUES (
            :event_id, :source, :source_group, :source_reference_id,
            :event_name, :event_date, :event_time, :organizer, :event_type, :venue,
            :status, :description, :contact_person, :contact_number, :contact_email, NOW()
        )');
        $stmt->execute([
            ':event_id' => $eventId,
            ':source' => $data['source'] !== '' ? $data['source'] : ($isAdmin ? 'admin_form' : 'partner_api'),
            ':source_group' => $data['source_group'],
            ':source_reference_id' => $data['source_reference_id'] !== '' ? $data['source_reference_id'] : null,
            ':event_name' => $data['event_name'],
            ':event_date' => $data['event_date'],
            ':event_time' => strlen($data['event_time']) === 5 ? $data['event_time'] . ':00' : $data['event_time'],
            ':organizer' => $data['organizer'],
            ':event_type' => $data['event_type'] !== '' ? $data['event_type'] : 'Awareness',
            ':venue' => $data['venue'],
            ':status' => $data['status'] !== '' ? $data['status'] : 'Pending',
            ':description' => $data['description'] !== '' ? $data['description'] : null,
            ':contact_person' => $data['contact_person'] !== '' ? $data['contact_person'] : null,
            ':contact_number' => $data['contact_number'] !== '' ? $data['contact_number'] : null,
            ':contact_email' => $data['contact_email'] !== '' ? $data['contact_email'] : null,
        ]);

        jsonResponse([
            'success' => true,
            'message' => 'Awareness event saved.',
            'data' => ['event_id' => $eventId, 'record_type' => 'event'],
        ], 201, false);
    }
}

jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405, false);

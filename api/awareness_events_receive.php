<?php

/**
 * Inbound API for Group 6 — Awareness and Outreach Event Tracking.
 *
 * POST JSON with record_type: "event" or "report"
 * Headers: X-API-Key or Authorization: Bearer {AWARENESS_EVENTS_API_KEY}
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/awareness_events_schema.php';
require_once __DIR__ . '/notifications_schema.php';

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit;
}

if (!requireConfiguredAwarenessEventApiKey()) {
    $expectedKey = trim($_ENV['AWARENESS_EVENTS_API_KEY'] ?? '');
    http_response_code($expectedKey === '' ? 503 : 401);
    echo json_encode([
        'success' => false,
        'message' => $expectedKey === ''
            ? 'Awareness events API is not configured. Set AWARENESS_EVENTS_API_KEY in .env.'
            : 'Invalid or missing API key.',
    ]);
    exit;
}

try {
    ensureAwarenessEventsTable($pdo);
    ensureAwarenessEventReportsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare awareness tables: ' . $e->getMessage()]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$recordType = strtolower(trim($input['record_type'] ?? $input['type'] ?? 'event'));

if ($recordType === 'events') {
    $recordType = 'event';
}
if ($recordType === 'reports') {
    $recordType = 'report';
}

if (!in_array($recordType, ['event', 'report'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'record_type must be "event" or "report".']);
    exit;
}

try {
    if ($recordType === 'event') {
        $data = normalizeAwarenessEventInput($input);
        $error = validateAwarenessEventFields($data);
        if ($error !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }

        $eventId = $data['event_id'] !== '' ? $data['event_id'] : generateAwarenessEventId($pdo);
        if ($data['event_id'] !== '') {
            $dup = $pdo->prepare('SELECT id FROM awareness_events WHERE event_id = :event_id LIMIT 1');
            $dup->execute([':event_id' => $eventId]);
            if ($dup->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Event ID already exists.']);
                exit;
            }
        }

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
            ':source' => $data['source'] !== '' ? $data['source'] : 'partner_api',
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

        createAdminNotification(
            $pdo,
            'awareness_event',
            'New Awareness Event',
            'Event ' . $eventId . ' from Group 6 — ' . $data['event_name'],
            'event-list.php?id=' . urlencode($eventId)
        );

        echo json_encode([
            'success' => true,
            'message' => 'Awareness event received.',
            'data' => [
                'record_type' => 'event',
                'event_id' => $eventId,
                'id' => (int) $pdo->lastInsertId(),
            ],
        ]);
        exit;
    }

    $data = normalizeAwarenessEventReportInput($input);
    $error = validateAwarenessEventReportFields($data);
    if ($error !== null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    }

    $reportId = $data['report_id'] !== '' ? $data['report_id'] : generateAwarenessEventReportId($pdo);
    if ($data['report_id'] !== '') {
        $dup = $pdo->prepare('SELECT id FROM awareness_event_reports WHERE report_id = :report_id LIMIT 1');
        $dup->execute([':report_id' => $reportId]);
        if ($dup->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Report ID already exists.']);
            exit;
        }
    }

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
        ':source' => $data['source'] !== '' ? $data['source'] : 'partner_api',
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

    createAdminNotification(
        $pdo,
        'awareness_event',
        'New Event Report',
        'Report ' . $reportId . ' for ' . $data['event_id'] . ' — ' . $data['title'],
        'event-reports.php?id=' . urlencode($reportId)
    );

    echo json_encode([
        'success' => true,
        'message' => 'Awareness event report received.',
        'data' => [
            'record_type' => 'report',
            'report_id' => $reportId,
            'event_id' => $data['event_id'],
            'id' => (int) $pdo->lastInsertId(),
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save awareness record: ' . $e->getMessage()]);
}

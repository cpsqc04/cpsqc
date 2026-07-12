<?php
/**
 * One-time sample data for awareness events (Group 6).
 * Run: php api/seed_awareness_events_sample.php
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/awareness_events_schema.php';

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Database connection unavailable.\n");
    exit(1);
}

ensureAwarenessEventsTable($pdo);
ensureAwarenessEventReportsTable($pdo);

$events = [
    [
        'event_id' => 'EVT-2025-001',
        'source' => 'partner_api',
        'source_group' => 'group_6',
        'source_reference_id' => 'G6-EVT-2025-001',
        'event_name' => 'Community Safety Awareness',
        'event_date' => '2025-01-25',
        'event_time' => '09:00:00',
        'organizer' => 'Maria Santos',
        'event_type' => 'Awareness',
        'venue' => 'Barangay San Agustin Hall',
        'status' => 'Scheduled',
        'description' => 'Community safety awareness session for residents.',
        'submitted_at' => '2025-01-10 08:00:00',
    ],
    [
        'event_id' => 'EVT-2025-002',
        'source' => 'partner_api',
        'source_group' => 'group_6',
        'source_reference_id' => 'G6-EVT-2025-002',
        'event_name' => 'Neighborhood Meeting',
        'event_date' => '2025-01-28',
        'event_time' => '14:00:00',
        'organizer' => 'Juan Dela Cruz',
        'event_type' => 'Meeting',
        'venue' => 'Barangay San Agustin Multi-Purpose Hall',
        'status' => 'Scheduled',
        'description' => 'Monthly neighborhood coordination meeting.',
        'submitted_at' => '2025-01-12 10:30:00',
    ],
    [
        'event_id' => 'EVT-2025-003',
        'source' => 'partner_api',
        'source_group' => 'group_6',
        'source_reference_id' => 'G6-EVT-2025-003',
        'event_name' => 'Safety Training Workshop',
        'event_date' => '2025-02-01',
        'event_time' => '10:00:00',
        'organizer' => 'Roberto Reyes',
        'event_type' => 'Training',
        'venue' => 'Barangay San Agustin Community Center',
        'status' => 'Pending',
        'description' => 'Workshop on emergency response and first aid.',
        'submitted_at' => '2025-01-15 09:00:00',
    ],
];

$reports = [
    [
        'report_id' => 'EVT-RPT-2025-001',
        'event_id' => 'EVT-2025-001',
        'source' => 'partner_api',
        'source_group' => 'group_6',
        'source_reference_id' => 'G6-RPT-2025-001',
        'title' => 'Community Safety Awareness',
        'event_date' => '2025-01-15',
        'attendance_count' => 150,
        'organizer' => 'Maria Santos',
        'survey_result' => '85% Positive',
        'location' => 'Barangay San Agustin Hall, Quezon City',
        'description' => 'Community safety awareness event conducted to educate residents about safety measures and emergency procedures.',
        'submitted_at' => '2025-01-16 11:00:00',
    ],
    [
        'report_id' => 'EVT-RPT-2025-002',
        'event_id' => 'EVT-2025-002',
        'source' => 'partner_api',
        'source_group' => 'group_6',
        'source_reference_id' => 'G6-RPT-2025-002',
        'title' => 'Neighborhood Meeting',
        'event_date' => '2025-01-10',
        'attendance_count' => 85,
        'organizer' => 'Juan Dela Cruz',
        'survey_result' => '92% Positive',
        'location' => 'Barangay San Agustin Multi-Purpose Hall, Quezon City',
        'description' => 'Monthly neighborhood meeting to discuss community concerns and safety initiatives.',
        'submitted_at' => '2025-01-11 16:00:00',
    ],
    [
        'report_id' => 'EVT-RPT-2025-003',
        'event_id' => 'EVT-2025-003',
        'source' => 'partner_api',
        'source_group' => 'group_6',
        'source_reference_id' => 'G6-RPT-2025-003',
        'title' => 'Safety Training Workshop',
        'event_date' => '2025-01-05',
        'attendance_count' => 120,
        'organizer' => 'Roberto Reyes',
        'survey_result' => '78% Positive',
        'location' => 'Barangay San Agustin Community Center, Quezon City',
        'description' => 'Safety training workshop covering first aid and community safety protocols.',
        'submitted_at' => '2025-01-06 14:30:00',
    ],
];

foreach ($events as $event) {
    $check = $pdo->prepare('SELECT id FROM awareness_events WHERE event_id = :event_id');
    $check->execute([':event_id' => $event['event_id']]);
    if ($check->fetch()) {
        echo "Skip existing event {$event['event_id']}\n";
        continue;
    }

    $stmt = $pdo->prepare('INSERT INTO awareness_events (
        event_id, source, source_group, source_reference_id, event_name, event_date, event_time,
        organizer, event_type, venue, status, description, submitted_at
    ) VALUES (
        :event_id, :source, :source_group, :source_reference_id, :event_name, :event_date, :event_time,
        :organizer, :event_type, :venue, :status, :description, :submitted_at
    )');
    $stmt->execute($event);
    echo "Inserted event {$event['event_id']}\n";
}

foreach ($reports as $report) {
    $check = $pdo->prepare('SELECT id FROM awareness_event_reports WHERE report_id = :report_id');
    $check->execute([':report_id' => $report['report_id']]);
    if ($check->fetch()) {
        echo "Skip existing report {$report['report_id']}\n";
        continue;
    }

    $stmt = $pdo->prepare('INSERT INTO awareness_event_reports (
        report_id, event_id, source, source_group, source_reference_id, title, event_date,
        attendance_count, organizer, survey_result, location, description, submitted_at
    ) VALUES (
        :report_id, :event_id, :source, :source_group, :source_reference_id, :title, :event_date,
        :attendance_count, :organizer, :survey_result, :location, :description, :submitted_at
    )');
    $stmt->execute($report);
    echo "Inserted report {$report['report_id']}\n";
}

echo "Done.\n";

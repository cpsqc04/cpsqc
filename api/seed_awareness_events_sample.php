<?php
/**
 * Sample data for awareness events (Event List) and event reports.
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
        'event_id' => 'EVT-2026-001',
        'source' => 'partner_api',
        'source_group' => 'campaign',
        'source_reference_id' => 'G6-EVT-2026-001',
        'event_name' => 'Community Safety Awareness',
        'event_date' => '2026-07-15',
        'event_time' => '09:00:00',
        'organizer' => 'Maria Santos',
        'event_type' => 'Awareness',
        'venue' => 'Barangay San Agustin Hall',
        'status' => 'Completed',
        'description' => 'Community safety awareness session for residents covering emergency hotlines and reporting channels.',
        'submitted_at' => '2026-07-01 08:00:00',
    ],
    [
        'event_id' => 'EVT-2026-002',
        'source' => 'partner_api',
        'source_group' => 'campaign',
        'source_reference_id' => 'G6-EVT-2026-002',
        'event_name' => 'Neighborhood Watch Orientation',
        'event_date' => '2026-07-22',
        'event_time' => '14:00:00',
        'organizer' => 'Juan Dela Cruz',
        'event_type' => 'Orientation',
        'venue' => 'Barangay San Agustin Multi-Purpose Hall',
        'status' => 'Completed',
        'description' => 'Orientation for new neighborhood watch volunteers on patrol routes and reporting flow.',
        'submitted_at' => '2026-07-08 10:30:00',
    ],
    [
        'event_id' => 'EVT-2026-003',
        'source' => 'partner_api',
        'source_group' => 'campaign',
        'source_reference_id' => 'G6-EVT-2026-003',
        'event_name' => 'Youth Anti-Drug Symposium',
        'event_date' => '2026-08-02',
        'event_time' => '10:00:00',
        'organizer' => 'Roberto Reyes',
        'event_type' => 'Symposium',
        'venue' => 'Barangay San Agustin Community Center',
        'status' => 'Completed',
        'description' => 'Symposium for students and parents on drug awareness and community support programs.',
        'submitted_at' => '2026-07-20 09:00:00',
    ],
    [
        'event_id' => 'EVT-2026-004',
        'source' => 'partner_api',
        'source_group' => 'campaign',
        'source_reference_id' => 'G6-EVT-2026-004',
        'event_name' => 'Emergency Response Drill',
        'event_date' => '2026-08-10',
        'event_time' => '08:30:00',
        'organizer' => 'Ana Villanueva',
        'event_type' => 'Drill',
        'venue' => 'Barangay San Agustin Covered Court',
        'status' => 'Scheduled',
        'description' => 'Earthquake and fire evacuation drill with BPSO and barangay responders.',
        'submitted_at' => '2026-07-28 13:15:00',
    ],
    [
        'event_id' => 'EVT-2026-005',
        'source' => 'partner_api',
        'source_group' => 'campaign',
        'source_reference_id' => 'G6-EVT-2026-005',
        'event_name' => 'Senior Citizens Safety Forum',
        'event_date' => '2026-08-18',
        'event_time' => '09:30:00',
        'organizer' => 'Liza Navarro',
        'event_type' => 'Forum',
        'venue' => 'Barangay San Agustin Hall',
        'status' => 'Pending',
        'description' => 'Forum on scam prevention, emergency contacts, and home safety tips for seniors.',
        'submitted_at' => '2026-08-01 11:45:00',
    ],
];

$reports = [
    [
        'report_id' => 'EVT-RPT-2026-001',
        'event_id' => 'EVT-2026-001',
        'source' => 'partner_api',
        'source_group' => 'campaign',
        'source_reference_id' => 'G6-RPT-2026-001',
        'title' => 'Community Safety Awareness',
        'event_date' => '2026-07-15',
        'attendance_count' => 150,
        'organizer' => 'Maria Santos',
        'survey_result' => '85% Positive',
        'location' => 'Barangay San Agustin Hall, Quezon City',
        'description' => 'Community safety awareness event conducted to educate residents about safety measures and emergency procedures. Participants received hotline cards and reporting guides.',
        'submitted_at' => '2026-07-16 11:00:00',
    ],
    [
        'report_id' => 'EVT-RPT-2026-002',
        'event_id' => 'EVT-2026-002',
        'source' => 'partner_api',
        'source_group' => 'campaign',
        'source_reference_id' => 'G6-RPT-2026-002',
        'title' => 'Neighborhood Watch Orientation',
        'event_date' => '2026-07-22',
        'attendance_count' => 68,
        'organizer' => 'Juan Dela Cruz',
        'survey_result' => '91% Positive',
        'location' => 'Barangay San Agustin Multi-Purpose Hall, Quezon City',
        'description' => 'Orientation completed for new neighborhood watch members. Route assignments and incident reporting steps were reviewed.',
        'submitted_at' => '2026-07-23 16:20:00',
    ],
    [
        'report_id' => 'EVT-RPT-2026-003',
        'event_id' => 'EVT-2026-003',
        'source' => 'partner_api',
        'source_group' => 'campaign',
        'source_reference_id' => 'G6-RPT-2026-003',
        'title' => 'Youth Anti-Drug Symposium',
        'event_date' => '2026-08-02',
        'attendance_count' => 210,
        'organizer' => 'Roberto Reyes',
        'survey_result' => '88% Positive',
        'location' => 'Barangay San Agustin Community Center, Quezon City',
        'description' => 'Youth symposium with guest speakers from the barangay and partner agencies. Q&A focused on peer support and reporting channels.',
        'submitted_at' => '2026-08-03 10:45:00',
    ],
    [
        'report_id' => 'EVT-RPT-2026-004',
        'event_id' => 'EVT-2026-004',
        'source' => 'partner_api',
        'source_group' => 'campaign',
        'source_reference_id' => 'G6-RPT-2026-004',
        'title' => 'Emergency Response Drill',
        'event_date' => '2026-08-10',
        'attendance_count' => 95,
        'organizer' => 'Ana Villanueva',
        'survey_result' => '80% Positive',
        'location' => 'Barangay San Agustin Covered Court, Quezon City',
        'description' => 'Evacuation drill completed within the target time. Follow-up notes include clearer assembly-point signage and radio protocol refreshers.',
        'submitted_at' => '2026-08-10 15:30:00',
    ],
    [
        'report_id' => 'EVT-RPT-2026-005',
        'event_id' => 'EVT-2026-005',
        'source' => 'partner_api',
        'source_group' => 'campaign',
        'source_reference_id' => 'G6-RPT-2026-005',
        'title' => 'Senior Citizens Safety Forum',
        'event_date' => '2026-08-18',
        'attendance_count' => 74,
        'organizer' => 'Liza Navarro',
        'survey_result' => '93% Positive',
        'location' => 'Barangay San Agustin Hall, Quezon City',
        'description' => 'Forum for seniors on scam prevention and emergency contacts. Printed guides were distributed and assistance desks were available.',
        'submitted_at' => '2026-08-18 14:10:00',
    ],
];

$eventInserted = 0;
$eventSkipped = 0;
foreach ($events as $event) {
    $check = $pdo->prepare('SELECT id FROM awareness_events WHERE event_id = :event_id');
    $check->execute([':event_id' => $event['event_id']]);
    if ($check->fetch()) {
        echo "Skip existing event {$event['event_id']}\n";
        $eventSkipped++;
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
    $eventInserted++;
}

$reportInserted = 0;
$reportSkipped = 0;
foreach ($reports as $report) {
    $check = $pdo->prepare('SELECT id FROM awareness_event_reports WHERE report_id = :report_id');
    $check->execute([':report_id' => $report['report_id']]);
    if ($check->fetch()) {
        echo "Skip existing report {$report['report_id']}\n";
        $reportSkipped++;
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
    $reportInserted++;
}

echo "\nDone. Events inserted {$eventInserted}, skipped {$eventSkipped}. Reports inserted {$reportInserted}, skipped {$reportSkipped}.\n";

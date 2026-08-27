<?php
/**
 * Sample data for awareness events (Event List) and event reports.
 *
 * CLI:  php api/seed_awareness_events_sample.php
 * Web:  auto-runs from Event List / Event Reports when admin opens the page
 */

if (PHP_SAPI !== 'cli') {
    session_start();
    header('Content-Type: application/json');
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Admin login required.']);
        exit;
    }
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/awareness_events_schema.php';

if (!$pdo instanceof PDO) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Database connection unavailable.\n");
        exit(1);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit;
}

ensureAwarenessEventsTable($pdo);
ensureAwarenessEventReportsTable($pdo);

$events = [
    [
        'event_id' => 'EVT-2026-001',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-EVT-001',
        'event_name' => 'Barangay Peace and Order Forum',
        'event_date' => '2026-07-12',
        'event_time' => '09:00:00',
        'organizer' => 'BPSO Desk — Brgy. San Agustin',
        'event_type' => 'Forum',
        'venue' => 'Barangay San Agustin Hall, Novaliches, Quezon City',
        'status' => 'Completed',
        'description' => 'Open forum with residents on peace and order concerns, patrol schedules, and how to report incidents through AlertaraQC and the barangay desk.',
        'contact_person' => 'Kagawad Miguel Santos',
        'contact_number' => '09171234501',
        'contact_email' => 'peaceorder@brgysanagustin.gov.ph',
        'submitted_at' => '2026-07-01 08:15:00',
    ],
    [
        'event_id' => 'EVT-2026-002',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-EVT-002',
        'event_name' => 'Neighborhood Watch Volunteer Orientation',
        'event_date' => '2026-07-20',
        'event_time' => '14:00:00',
        'organizer' => 'Neighborhood Watch Coordination',
        'event_type' => 'Orientation',
        'venue' => 'Multi-Purpose Hall, Brgy. San Agustin',
        'status' => 'Completed',
        'description' => 'Orientation for new neighborhood watch applicants covering duties, route familiarity, ID verification, and incident reporting to BPSO.',
        'contact_person' => 'Coordinator Ana Reyes',
        'contact_number' => '09181234502',
        'contact_email' => 'nw@brgysanagustin.gov.ph',
        'submitted_at' => '2026-07-08 10:30:00',
    ],
    [
        'event_id' => 'EVT-2026-003',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-EVT-003',
        'event_name' => 'Youth Anti-Drug Awareness Symposium',
        'event_date' => '2026-08-02',
        'event_time' => '10:00:00',
        'organizer' => 'Barangay Council for the Protection of Children',
        'event_type' => 'Symposium',
        'venue' => 'Covered Court, Brgy. San Agustin',
        'status' => 'Completed',
        'description' => 'Symposium for students and parents on drug awareness, peer pressure, and available barangay support services. Coordinated with local school representatives.',
        'contact_person' => 'Kagawad Liza Navarro',
        'contact_number' => '09191234503',
        'contact_email' => 'bcpc@brgysanagustin.gov.ph',
        'submitted_at' => '2026-07-20 09:00:00',
    ],
    [
        'event_id' => 'EVT-2026-004',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-EVT-004',
        'event_name' => 'Earthquake and Fire Evacuation Drill',
        'event_date' => '2026-08-09',
        'event_time' => '08:30:00',
        'organizer' => 'Barangay Disaster Risk Reduction and Management',
        'event_type' => 'Drill',
        'venue' => 'Barangay San Agustin Covered Court & Assembly Area',
        'status' => 'Completed',
        'description' => 'Joint evacuation drill with BPSO, barangay responders, and residents. Focused on assembly points, radio protocol, and family reunification.',
        'contact_person' => 'BDRRMC Lead Roberto Reyes',
        'contact_number' => '09201234504',
        'contact_email' => 'bdrrrm@brgysanagustin.gov.ph',
        'submitted_at' => '2026-07-28 13:15:00',
    ],
    [
        'event_id' => 'EVT-2026-005',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-EVT-005',
        'event_name' => 'Senior Citizens Scam Prevention Seminar',
        'event_date' => '2026-08-16',
        'event_time' => '09:30:00',
        'organizer' => 'Office of Senior Citizens Affairs — Brgy. San Agustin',
        'event_type' => 'Seminar',
        'venue' => 'Barangay San Agustin Hall',
        'status' => 'Completed',
        'description' => 'Seminar for senior citizens on phone and online scam prevention, emergency contacts, and how to seek help from BPSO and the barangay hall.',
        'contact_person' => 'OSCA Desk Officer Carmen Dela Peña',
        'contact_number' => '09211234505',
        'contact_email' => 'osca@brgysanagustin.gov.ph',
        'submitted_at' => '2026-08-01 11:45:00',
    ],
    [
        'event_id' => 'EVT-2026-006',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-EVT-006',
        'event_name' => 'CCTV and Community Safety Walkthrough',
        'event_date' => '2026-08-23',
        'event_time' => '15:00:00',
        'organizer' => 'CCTV Monitoring Desk — AlertaraQC',
        'event_type' => 'Awareness',
        'venue' => 'Susano Road Corridor, Brgy. San Agustin',
        'status' => 'Completed',
        'description' => 'Community walkthrough explaining CCTV coverage areas, privacy reminders, and how footage requests are processed for investigations.',
        'contact_person' => 'Admin Surveillance Desk',
        'contact_number' => '09221234506',
        'contact_email' => 'cctv@alertaraqc.com',
        'submitted_at' => '2026-08-10 16:00:00',
    ],
    [
        'event_id' => 'EVT-2026-007',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-EVT-007',
        'event_name' => 'Women and Children Protection Desk Orientation',
        'event_date' => '2026-08-28',
        'event_time' => '13:00:00',
        'organizer' => 'VAWC Desk — Brgy. San Agustin',
        'event_type' => 'Orientation',
        'venue' => 'Barangay San Agustin Conference Room',
        'status' => 'Completed',
        'description' => 'Orientation on reporting channels for VAWC-related concerns, confidential handling, and coordination with city social services.',
        'contact_person' => 'VAWC Desk Officer Marites Gomez',
        'contact_number' => '09231234507',
        'contact_email' => 'vawc@brgysanagustin.gov.ph',
        'submitted_at' => '2026-08-15 09:20:00',
    ],
    [
        'event_id' => 'EVT-2026-008',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-EVT-008',
        'event_name' => 'Traffic and Road Safety Awareness Day',
        'event_date' => '2026-09-05',
        'event_time' => '07:30:00',
        'organizer' => 'Barangay Traffic Aide Unit',
        'event_type' => 'Awareness',
        'venue' => 'Quezon Avenue Extension / Susano Road junction',
        'status' => 'Scheduled',
        'description' => 'Road safety campaign for motorists and pedestrians near school zones, including helmet reminders and designated drop-off points.',
        'contact_person' => 'Traffic Aide Lead Paolo Cruz',
        'contact_number' => '09241234508',
        'contact_email' => 'traffic@brgysanagustin.gov.ph',
        'submitted_at' => '2026-08-20 14:05:00',
    ],
    [
        'event_id' => 'EVT-2026-010',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-EVT-010',
        'event_name' => 'Health and Sanitation Community Fair',
        'event_date' => '2026-09-20',
        'event_time' => '08:00:00',
        'organizer' => 'Barangay Health Station — San Agustin',
        'event_type' => 'Medical Mission',
        'venue' => 'Barangay Plaza / Covered Court, Brgy. San Agustin',
        'status' => 'Pending',
        'description' => 'Community health fair with BP checks, dengue prevention tips, and sanitation reminders for households near drainage canals.',
        'contact_person' => 'BHW Supervisor Elena Mendoza',
        'contact_number' => '09261234510',
        'contact_email' => 'bhs@brgysanagustin.gov.ph',
        'submitted_at' => '2026-08-25 08:40:00',
    ],
    [
        'event_id' => 'EVT-2026-011',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-EVT-011',
        'event_name' => 'First Aid and Basic Life Support Training',
        'event_date' => '2026-08-26',
        'event_time' => '09:00:00',
        'organizer' => 'Barangay Health Station — San Agustin',
        'event_type' => 'Training',
        'venue' => 'Barangay San Agustin Multi-Purpose Hall',
        'status' => 'Completed',
        'description' => 'Hands-on first aid and CPR training for BPSO, neighborhood watch volunteers, and barangay health workers.',
        'contact_person' => 'Nurse-in-Charge Sofia Ramirez',
        'contact_number' => '09271234511',
        'contact_email' => 'bhs-training@brgysanagustin.gov.ph',
        'submitted_at' => '2026-08-12 10:00:00',
    ],
    [
        'event_id' => 'EVT-2026-012',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-EVT-012',
        'event_name' => 'Barangay Clean-Up and Street Lighting Inspection',
        'event_date' => '2026-08-30',
        'event_time' => '06:00:00',
        'organizer' => 'Barangay Environment Committee',
        'event_type' => 'Community Drive',
        'venue' => 'Mabini Street to Susano Road, Brgy. San Agustin',
        'status' => 'Scheduled',
        'description' => 'Morning clean-up drive with street-light inspection to reduce dark spots reported by residents and night patrols.',
        'contact_person' => 'Kagawad Diego Villanueva',
        'contact_number' => '09281234512',
        'contact_email' => 'environment@brgysanagustin.gov.ph',
        'submitted_at' => '2026-08-26 07:30:00',
    ],
];

$reports = [
    [
        'report_id' => 'EVT-RPT-2026-001',
        'event_id' => 'EVT-2026-001',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-RPT-001',
        'title' => 'Barangay Peace and Order Forum — Post-Event Report',
        'event_date' => '2026-07-12',
        'attendance_count' => 142,
        'organizer' => 'BPSO Desk — Brgy. San Agustin',
        'survey_result' => '87% Positive',
        'evaluation_score' => '4.3/5',
        'event_outcome' => 'Forum completed; 18 walk-in concerns logged for follow-up patrol.',
        'location' => 'Barangay San Agustin Hall, Novaliches, Quezon City',
        'description' => 'Residents raised concerns on nighttime loitering along Mabini and Acacia streets. BPSO shared current patrol shifts and demonstrated complaint submission via the resident portal.',
        'submitted_at' => '2026-07-13 10:20:00',
    ],
    [
        'report_id' => 'EVT-RPT-2026-002',
        'event_id' => 'EVT-2026-002',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-RPT-002',
        'title' => 'Neighborhood Watch Orientation — Post-Event Report',
        'event_date' => '2026-07-20',
        'attendance_count' => 56,
        'organizer' => 'Neighborhood Watch Coordination',
        'survey_result' => '92% Positive',
        'evaluation_score' => '4.6/5',
        'event_outcome' => '56 applicants oriented; 41 endorsed for ID processing.',
        'location' => 'Multi-Purpose Hall, Brgy. San Agustin',
        'description' => 'Applicants completed duty briefing, route familiarity, and ID photo requirements. Next batch scheduled after barangay endorsement.',
        'submitted_at' => '2026-07-21 15:45:00',
    ],
    [
        'report_id' => 'EVT-RPT-2026-003',
        'event_id' => 'EVT-2026-003',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-RPT-003',
        'title' => 'Youth Anti-Drug Awareness Symposium — Post-Event Report',
        'event_date' => '2026-08-02',
        'attendance_count' => 218,
        'organizer' => 'Barangay Council for the Protection of Children',
        'survey_result' => '89% Positive',
        'evaluation_score' => '4.4/5',
        'event_outcome' => 'Hotline cards distributed to 180 households and 3 partner schools.',
        'location' => 'Covered Court, Brgy. San Agustin',
        'description' => 'Students and parents attended talks from barangay officers and partner speakers. Anonymous tip-line reminders and peer-support referrals were emphasized.',
        'submitted_at' => '2026-08-03 11:05:00',
    ],
    [
        'report_id' => 'EVT-RPT-2026-004',
        'event_id' => 'EVT-2026-004',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-RPT-004',
        'title' => 'Earthquake and Fire Evacuation Drill — Post-Event Report',
        'event_date' => '2026-08-09',
        'attendance_count' => 103,
        'organizer' => 'Barangay Disaster Risk Reduction and Management',
        'survey_result' => '81% Positive',
        'evaluation_score' => '4.0/5',
        'event_outcome' => 'Assembly completed in 6 minutes 40 seconds; signage gaps noted.',
        'location' => 'Barangay San Agustin Covered Court & Assembly Area',
        'description' => 'Joint drill with BPSO and barangay responders. Follow-ups: clearer assembly signage near Purok 3 and refresher on radio channel handoff.',
        'submitted_at' => '2026-08-09 16:10:00',
    ],
    [
        'report_id' => 'EVT-RPT-2026-005',
        'event_id' => 'EVT-2026-005',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-RPT-005',
        'title' => 'Senior Citizens Scam Prevention Seminar — Post-Event Report',
        'event_date' => '2026-08-16',
        'attendance_count' => 79,
        'organizer' => 'Office of Senior Citizens Affairs — Brgy. San Agustin',
        'survey_result' => '94% Positive',
        'evaluation_score' => '4.7/5',
        'event_outcome' => 'Printed scam-alert guides issued; 12 seniors practiced reporting flow.',
        'location' => 'Barangay San Agustin Hall',
        'description' => 'Seniors received guides on phone and online scams and practiced reporting suspicious calls to the barangay desk and AlertaraQC tip line.',
        'submitted_at' => '2026-08-16 14:30:00',
    ],
    [
        'report_id' => 'EVT-RPT-2026-006',
        'event_id' => 'EVT-2026-006',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-RPT-006',
        'title' => 'CCTV and Community Safety Walkthrough — Post-Event Report',
        'event_date' => '2026-08-23',
        'attendance_count' => 44,
        'organizer' => 'CCTV Monitoring Desk — AlertaraQC',
        'survey_result' => '90% Positive',
        'evaluation_score' => '4.5/5',
        'event_outcome' => 'Coverage map shared with 8 purok leaders; 2 blind spots flagged.',
        'location' => 'Susano Road Corridor, Brgy. San Agustin',
        'description' => 'Residents and purok leaders walked camera coverage points along Susano Road. Footage request process and privacy rules for investigations were explained.',
        'submitted_at' => '2026-08-24 09:15:00',
    ],
    [
        'report_id' => 'EVT-RPT-2026-007',
        'event_id' => 'EVT-2026-007',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-RPT-007',
        'title' => 'VAWC Desk Orientation — Post-Event Report',
        'event_date' => '2026-08-28',
        'attendance_count' => 38,
        'organizer' => 'VAWC Desk — Brgy. San Agustin',
        'survey_result' => '91% Positive',
        'evaluation_score' => '4.5/5',
        'event_outcome' => 'Referral pathway cards issued; confidential intake checklist refreshed.',
        'location' => 'Barangay San Agustin Conference Room',
        'description' => 'Desk officers and partner NGOs reviewed confidential intake, city social service referrals, and coordination with BPSO for urgent cases.',
        'submitted_at' => '2026-08-28 17:20:00',
    ],
    [
        'report_id' => 'EVT-RPT-2026-008',
        'event_id' => 'EVT-2026-011',
        'source' => 'sample_seed',
        'source_group' => 'campaign',
        'source_reference_id' => 'SEED-RPT-008',
        'title' => 'First Aid and Basic Life Support Training — Post-Event Report',
        'event_date' => '2026-08-26',
        'attendance_count' => 62,
        'organizer' => 'Barangay Health Station — San Agustin',
        'survey_result' => '96% Positive',
        'evaluation_score' => '4.8/5',
        'event_outcome' => '62 responders certified for basic first aid; kits restocked at 4 outposts.',
        'location' => 'Barangay San Agustin Multi-Purpose Hall',
        'description' => 'Hands-on CPR and bleeding-control stations for BPSO, neighborhood watch, and health volunteers. Certificates issued after skills check.',
        'submitted_at' => '2026-08-26 18:05:00',
    ],
];

// Drop retired sample (Anonymous Tip Line Community Briefing) if it was seeded earlier.
$removed = 0;
try {
    $delReports = $pdo->prepare(
        "DELETE FROM awareness_event_reports
         WHERE event_id = 'EVT-2026-009'
            OR source_reference_id = 'SEED-EVT-009'
            OR title LIKE '%Anonymous Tip Line%'"
    );
    $delReports->execute();
    $removed += $delReports->rowCount();

    $delEvents = $pdo->prepare(
        "DELETE FROM awareness_events
         WHERE event_id = 'EVT-2026-009'
            OR source_reference_id = 'SEED-EVT-009'
            OR event_name LIKE '%Anonymous Tip Line%'"
    );
    $delEvents->execute();
    $removed += $delEvents->rowCount();
} catch (PDOException $e) {
    // ignore cleanup failures
}

$eventInserted = 0;
$eventSkipped = 0;
foreach ($events as $event) {
    $check = $pdo->prepare('SELECT id FROM awareness_events WHERE event_id = :event_id');
    $check->execute([':event_id' => $event['event_id']]);
    if ($check->fetch()) {
        if (PHP_SAPI === 'cli') {
            echo "Skip existing event {$event['event_id']}\n";
        }
        $eventSkipped++;
        continue;
    }

    $stmt = $pdo->prepare('INSERT INTO awareness_events (
        event_id, source, source_group, source_reference_id, event_name, event_date, event_time,
        organizer, event_type, venue, status, description, contact_person, contact_number, contact_email, submitted_at
    ) VALUES (
        :event_id, :source, :source_group, :source_reference_id, :event_name, :event_date, :event_time,
        :organizer, :event_type, :venue, :status, :description, :contact_person, :contact_number, :contact_email, :submitted_at
    )');
    $stmt->execute($event);
    if (PHP_SAPI === 'cli') {
        echo "Inserted event {$event['event_id']}\n";
    }
    $eventInserted++;
}

$reportInserted = 0;
$reportSkipped = 0;
foreach ($reports as $report) {
    $check = $pdo->prepare('SELECT id FROM awareness_event_reports WHERE report_id = :report_id');
    $check->execute([':report_id' => $report['report_id']]);
    if ($check->fetch()) {
        if (PHP_SAPI === 'cli') {
            echo "Skip existing report {$report['report_id']}\n";
        }
        $reportSkipped++;
        continue;
    }

    $stmt = $pdo->prepare('INSERT INTO awareness_event_reports (
        report_id, event_id, source, source_group, source_reference_id, title, event_date,
        attendance_count, organizer, survey_result, evaluation_score, event_outcome, location, description, submitted_at
    ) VALUES (
        :report_id, :event_id, :source, :source_group, :source_reference_id, :title, :event_date,
        :attendance_count, :organizer, :survey_result, :evaluation_score, :event_outcome, :location, :description, :submitted_at
    )');
    $stmt->execute($report);
    if (PHP_SAPI === 'cli') {
        echo "Inserted report {$report['report_id']}\n";
    }
    $reportInserted++;
}

$summary = [
    'success' => true,
    'message' => "Events inserted {$eventInserted}, skipped {$eventSkipped}. Reports inserted {$reportInserted}, skipped {$reportSkipped}. Removed {$removed} retired sample row(s).",
    'events_inserted' => $eventInserted,
    'events_skipped' => $eventSkipped,
    'reports_inserted' => $reportInserted,
    'reports_skipped' => $reportSkipped,
    'removed' => $removed,
];

if (PHP_SAPI === 'cli') {
    echo "\nDone. {$summary['message']}\n";
    exit(0);
}

echo json_encode($summary);

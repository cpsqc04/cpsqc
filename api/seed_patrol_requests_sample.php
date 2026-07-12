<?php
/**
 * One-time sample data for patrol_requests (Group 6 & Group 8).
 * Run: php api/seed_patrol_requests_sample.php
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/patrol_requests_schema.php';

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Database connection unavailable.\n");
    exit(1);
}

ensurePatrolRequestsTable($pdo);

$samples = [
    [
        'request_id' => 'PT-REQ-2026-001',
        'source' => 'partner_api',
        'source_group' => 'group_6',
        'source_reference_id' => 'EVT-G6-2026-014',
        'requesting_unit' => 'Awareness and Outreach Event Tracking',
        'contact_person' => 'Maria Clara Santos',
        'contact_position' => 'Event Coordinator',
        'contact_number' => '09171234567',
        'contact_email' => 'm.santos@barangay-sanagustin.gov.ph',
        'event_name' => 'Barangay Safety & Disaster Preparedness Seminar',
        'event_date' => '2026-07-18',
        'event_start_time' => '08:00:00',
        'event_end_time' => '12:00:00',
        'event_location' => 'Barangay San Agustin Covered Court, Quezon City',
        'patrols_needed' => 3,
        'event_description' => 'Half-day seminar for residents on fire safety, earthquake drills, and community watch protocols. Expected attendance: 120 persons.',
        'special_instructions' => 'Patrol needed at main entrance, parking area, and perimeter during registration (7:30–8:30 AM).',
        'status' => 'Pending',
        'review_notes' => null,
        'rejection_reason' => null,
        'patrols_assigned' => null,
        'scheduling_notes' => null,
        'reviewed_by' => null,
        'submitted_at' => '2026-07-08 09:15:00',
    ],
    [
        'request_id' => 'PT-REQ-2026-002',
        'source' => 'partner_api',
        'source_group' => 'group_8',
        'source_reference_id' => 'EVT-G8-2026-007',
        'requesting_unit' => 'Community Events Office',
        'contact_person' => 'Juan Miguel Reyes',
        'contact_position' => 'Program Lead',
        'contact_number' => '09189876543',
        'contact_email' => 'jm.reyes@events.qc.gov.ph',
        'event_name' => 'Neighborhood Clean-Up & Tree Planting Drive',
        'event_date' => '2026-07-20',
        'event_start_time' => '06:00:00',
        'event_end_time' => '10:00:00',
        'event_location' => 'Heavenly Drive & San Agustin Street, Brgy. San Agustin QC',
        'patrols_needed' => 2,
        'event_description' => 'Morning community clean-up along Heavenly Drive with volunteers from local schools and homeowners associations.',
        'special_instructions' => 'Traffic assistance needed at Heavenly Drive intersection from 5:45 AM.',
        'status' => 'Under Review',
        'review_notes' => 'Checking personnel availability for early-morning shift.',
        'rejection_reason' => null,
        'patrols_assigned' => null,
        'scheduling_notes' => null,
        'reviewed_by' => 'BPSO Admin',
        'submitted_at' => '2026-07-09 14:22:00',
    ],
    [
        'request_id' => 'PT-REQ-2026-003',
        'source' => 'partner_api',
        'source_group' => 'group_6',
        'source_reference_id' => 'EVT-G6-2026-021',
        'requesting_unit' => 'Impact Monitoring and Evaluation System',
        'contact_person' => 'Ana Patricia Lopez',
        'contact_position' => 'Outreach Manager',
        'contact_number' => '09201234567',
        'contact_email' => 'a.lopez@barangay-sanagustin.gov.ph',
        'event_name' => 'Youth Leadership & Anti-Bullying Workshop',
        'event_date' => '2026-07-25',
        'event_start_time' => '13:00:00',
        'event_end_time' => '17:00:00',
        'event_location' => 'Barangay Hall Multipurpose Room, San Agustin QC',
        'patrols_needed' => 2,
        'event_description' => 'Afternoon workshop for barangay youth leaders. Partner schools sending 80 participants.',
        'special_instructions' => 'Crowd control at hall entrance; coordinate with barangay tanod desk.',
        'status' => 'Approved',
        'review_notes' => 'Approved for 2 patrol personnel. Hall security briefing at 12:30 PM.',
        'rejection_reason' => null,
        'patrols_assigned' => 2,
        'scheduling_notes' => 'Assign available hall-duty personnel from afternoon shift.',
        'reviewed_by' => 'BPSO Admin',
        'submitted_at' => '2026-07-07 11:40:00',
    ],
    [
        'request_id' => 'PT-REQ-2026-004',
        'source' => 'partner_api',
        'source_group' => 'group_8',
        'source_reference_id' => 'EVT-G8-2026-011',
        'requesting_unit' => 'Community Events Office',
        'contact_person' => 'Roberto Dela Cruz',
        'contact_position' => 'Events Supervisor',
        'contact_number' => '09351239876',
        'contact_email' => 'r.delacruz@events.qc.gov.ph',
        'event_name' => 'Barangay Health Fair & Free Medical Check-Up',
        'event_date' => '2026-08-02',
        'event_start_time' => '07:00:00',
        'event_end_time' => '15:00:00',
        'event_location' => 'San Agustin Elementary School Grounds, Quezon City',
        'patrols_needed' => 4,
        'event_description' => 'Full-day health fair with DOH partners, free BP/sugar screening, and dental check-ups. High public turnout expected.',
        'special_instructions' => '4 patrols: 2 at school gate, 1 at queue line, 1 roving perimeter. Medical tent area is restricted.',
        'status' => 'Scheduled',
        'review_notes' => 'Confirmed with school administration. Route plan attached in scheduling notes.',
        'rejection_reason' => null,
        'patrols_assigned' => 4,
        'scheduling_notes' => 'Patrol A & B: 07:00–11:00 gate duty. Patrol C: queue management. Patrol D: roving 07:00–15:00.',
        'reviewed_by' => 'BPSO Admin',
        'submitted_at' => '2026-07-05 16:05:00',
    ],
];

$check = $pdo->prepare('SELECT id FROM patrol_requests WHERE request_id = :request_id LIMIT 1');
$insert = $pdo->prepare('INSERT INTO patrol_requests (
    request_id, source, source_group, source_reference_id, requesting_unit,
    contact_person, contact_position, contact_number, contact_email,
    event_name, event_date, event_start_time, event_end_time, event_location,
    patrols_needed, event_description, special_instructions, status,
    review_notes, rejection_reason, patrols_assigned, scheduling_notes,
    reviewed_by, reviewed_at, submitted_at
) VALUES (
    :request_id, :source, :source_group, :source_reference_id, :requesting_unit,
    :contact_person, :contact_position, :contact_number, :contact_email,
    :event_name, :event_date, :event_start_time, :event_end_time, :event_location,
    :patrols_needed, :event_description, :special_instructions, :status,
    :review_notes, :rejection_reason, :patrols_assigned, :scheduling_notes,
    :reviewed_by, :reviewed_at, :submitted_at
)');

$inserted = 0;
$skipped = 0;

foreach ($samples as $row) {
    $check->execute([':request_id' => $row['request_id']]);
    if ($check->fetch()) {
        $skipped++;
        continue;
    }

    $reviewedAt = in_array($row['status'], ['Under Review', 'Approved', 'Scheduled'], true)
        ? date('Y-m-d H:i:s', strtotime($row['submitted_at'] . ' +2 hours'))
        : null;

    $insert->execute([
        ':request_id' => $row['request_id'],
        ':source' => $row['source'],
        ':source_group' => $row['source_group'],
        ':source_reference_id' => $row['source_reference_id'],
        ':requesting_unit' => $row['requesting_unit'],
        ':contact_person' => $row['contact_person'],
        ':contact_position' => $row['contact_position'],
        ':contact_number' => $row['contact_number'],
        ':contact_email' => $row['contact_email'],
        ':event_name' => $row['event_name'],
        ':event_date' => $row['event_date'],
        ':event_start_time' => $row['event_start_time'],
        ':event_end_time' => $row['event_end_time'],
        ':event_location' => $row['event_location'],
        ':patrols_needed' => $row['patrols_needed'],
        ':event_description' => $row['event_description'],
        ':special_instructions' => $row['special_instructions'],
        ':status' => $row['status'],
        ':review_notes' => $row['review_notes'],
        ':rejection_reason' => $row['rejection_reason'],
        ':patrols_assigned' => $row['patrols_assigned'],
        ':scheduling_notes' => $row['scheduling_notes'],
        ':reviewed_by' => $row['reviewed_by'],
        ':reviewed_at' => $reviewedAt,
        ':submitted_at' => $row['submitted_at'],
    ]);
    $inserted++;
}

echo "Patrol request samples: {$inserted} inserted, {$skipped} skipped (already exist).\n";

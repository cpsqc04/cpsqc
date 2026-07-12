<?php
/**
 * Sample CCTV requests for testing Open Playback against local recordings.
 * Run: php api/seed_cctv_requests_sample.php
 *
 * Today's sample uses times that overlap existing recordings/ folders.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/cctv_requests_schema.php';

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Database connection unavailable.\n");
    exit(1);
}

ensureCctvRequestsTable($pdo);

$today = date('Y-m-d');

$samples = [
    [
        'request_id' => 'CCTV-RFQ-' . date('Y') . '-TODAY-001',
        'source' => 'sample_seed',
        'source_reference_id' => 'SEED-TODAY-001',
        'requesting_agency' => 'Quezon City Police District',
        'contact_person' => 'PO2 Ana Reyes',
        'contact_position' => 'Investigator',
        'contact_number' => '09171234567',
        'contact_email' => 'ana.reyes@qcpd.gov.ph',
        'office_unit' => 'Station 5 Investigation',
        'case_reference' => 'INV-2026-SAMPLE-01',
        'related_complaint_id' => null,
        'purpose' => 'Investigation',
        'purpose_details' => 'Sample request for today so Open Playback can find matching recordings.',
        'legal_basis' => 'Law enforcement request',
        'incident_location' => 'Susano Road, Barangay San Agustin, Quezon City',
        'camera_id' => 'CAM-001',
        'location_description' => 'Main Entrance Camera — Susano Road',
        'incident_date' => $today,
        'footage_start_time' => '16:30:00',
        'footage_end_time' => '17:00:00',
        'incident_type' => 'Suspicious activity',
        'incident_description' => 'Sample incident for playback testing. Covers afternoon footage recorded today.',
        'delivery_method' => 'secure_download',
        'supporting_document' => null,
        'status' => 'Under Review',
        'review_notes' => 'Sample data — use Open Playback to review today\'s recordings.',
        'rejection_reason' => null,
        'approved_camera_id' => 'CAM-001',
        'actual_footage_start' => '16:30:00',
        'actual_footage_end' => '17:00:00',
        'fulfillment_notes' => null,
        'reviewed_by' => 'BPSO Admin',
        'fulfilled_at' => null,
        'submitted_at' => $today . ' 15:00:00',
    ],
    [
        'request_id' => 'CCTV-RFQ-' . date('Y') . '-TODAY-002',
        'source' => 'sample_seed',
        'source_reference_id' => 'SEED-TODAY-002',
        'requesting_agency' => 'Barangay San Agustin Hall',
        'contact_person' => 'Kagawad Miguel Santos',
        'contact_position' => 'Peace and Order Committee',
        'contact_number' => '09189876543',
        'contact_email' => 'm.santos@barangay-sanagustin.gov.ph',
        'office_unit' => 'BPSO Desk',
        'case_reference' => 'BRGY-2026-SAMPLE-02',
        'related_complaint_id' => null,
        'purpose' => 'Incident verification',
        'purpose_details' => 'Second sample request overlapping earlier afternoon recordings.',
        'legal_basis' => 'Barangay request',
        'incident_location' => 'Main entrance area, Brgy. San Agustin',
        'camera_id' => 'CAM-001',
        'location_description' => 'Main Entrance Camera',
        'incident_date' => $today,
        'footage_start_time' => '15:40:00',
        'footage_end_time' => '16:20:00',
        'incident_type' => 'Traffic / pedestrian incident',
        'incident_description' => 'Sample request covering mid-afternoon recordings for playback demo.',
        'delivery_method' => 'secure_download',
        'supporting_document' => null,
        'status' => 'Approved',
        'review_notes' => 'Approved for demo. Open Playback should find overlapping segments.',
        'rejection_reason' => null,
        'approved_camera_id' => 'CAM-001',
        'actual_footage_start' => '15:40:00',
        'actual_footage_end' => '16:20:00',
        'fulfillment_notes' => null,
        'reviewed_by' => 'BPSO Admin',
        'fulfilled_at' => null,
        'submitted_at' => $today . ' 14:30:00',
    ],
];

$inserted = 0;
$skipped = 0;

foreach ($samples as $row) {
    $check = $pdo->prepare('SELECT id FROM cctv_requests WHERE request_id = :request_id LIMIT 1');
    $check->execute([':request_id' => $row['request_id']]);
    if ($check->fetch()) {
        echo "Skip existing: {$row['request_id']}\n";
        $skipped++;
        continue;
    }

    $stmt = $pdo->prepare('INSERT INTO cctv_requests (
        request_id, source, source_reference_id, requesting_agency, contact_person, contact_position,
        contact_number, contact_email, office_unit, case_reference, related_complaint_id,
        purpose, purpose_details, legal_basis, incident_location, camera_id, location_description,
        incident_date, footage_start_time, footage_end_time, incident_type, incident_description,
        delivery_method, supporting_document, status, review_notes, rejection_reason,
        approved_camera_id, actual_footage_start, actual_footage_end, fulfillment_notes,
        reviewed_by, fulfilled_at, submitted_at
    ) VALUES (
        :request_id, :source, :source_reference_id, :requesting_agency, :contact_person, :contact_position,
        :contact_number, :contact_email, :office_unit, :case_reference, :related_complaint_id,
        :purpose, :purpose_details, :legal_basis, :incident_location, :camera_id, :location_description,
        :incident_date, :footage_start_time, :footage_end_time, :incident_type, :incident_description,
        :delivery_method, :supporting_document, :status, :review_notes, :rejection_reason,
        :approved_camera_id, :actual_footage_start, :actual_footage_end, :fulfillment_notes,
        :reviewed_by, :fulfilled_at, :submitted_at
    )');

    $stmt->execute([
        ':request_id' => $row['request_id'],
        ':source' => $row['source'],
        ':source_reference_id' => $row['source_reference_id'],
        ':requesting_agency' => $row['requesting_agency'],
        ':contact_person' => $row['contact_person'],
        ':contact_position' => $row['contact_position'],
        ':contact_number' => $row['contact_number'],
        ':contact_email' => $row['contact_email'],
        ':office_unit' => $row['office_unit'],
        ':case_reference' => $row['case_reference'],
        ':related_complaint_id' => $row['related_complaint_id'],
        ':purpose' => $row['purpose'],
        ':purpose_details' => $row['purpose_details'],
        ':legal_basis' => $row['legal_basis'],
        ':incident_location' => $row['incident_location'],
        ':camera_id' => $row['camera_id'],
        ':location_description' => $row['location_description'],
        ':incident_date' => $row['incident_date'],
        ':footage_start_time' => $row['footage_start_time'],
        ':footage_end_time' => $row['footage_end_time'],
        ':incident_type' => $row['incident_type'],
        ':incident_description' => $row['incident_description'],
        ':delivery_method' => $row['delivery_method'],
        ':supporting_document' => $row['supporting_document'],
        ':status' => $row['status'],
        ':review_notes' => $row['review_notes'],
        ':rejection_reason' => $row['rejection_reason'],
        ':approved_camera_id' => $row['approved_camera_id'],
        ':actual_footage_start' => $row['actual_footage_start'],
        ':actual_footage_end' => $row['actual_footage_end'],
        ':fulfillment_notes' => $row['fulfillment_notes'],
        ':reviewed_by' => $row['reviewed_by'],
        ':fulfilled_at' => $row['fulfilled_at'],
        ':submitted_at' => $row['submitted_at'],
    ]);

    echo "Inserted: {$row['request_id']} ({$row['incident_date']} {$row['footage_start_time']}–{$row['footage_end_time']})\n";
    $inserted++;
}

echo "\nDone. Inserted {$inserted}, skipped {$skipped}.\n";
echo "Open CCTV Request → Manage a TODAY sample → Open Playback.\n";

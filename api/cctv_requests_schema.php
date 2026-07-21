<?php

function ensureCctvRequestsTable(PDO $pdo): void
{
    $columns = [];
    $tableExists = false;

    try {
        foreach ($pdo->query('SHOW COLUMNS FROM cctv_requests') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        $pdo->exec("CREATE TABLE cctv_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id VARCHAR(50) NOT NULL UNIQUE,
            source VARCHAR(100) DEFAULT 'web_form',
            source_reference_id VARCHAR(100) DEFAULT NULL,
            requesting_agency VARCHAR(255) NOT NULL,
            contact_person VARCHAR(255) NOT NULL,
            contact_position VARCHAR(255) DEFAULT NULL,
            contact_number VARCHAR(50) NOT NULL,
            contact_email VARCHAR(255) DEFAULT NULL,
            office_unit VARCHAR(255) DEFAULT NULL,
            case_reference VARCHAR(100) DEFAULT NULL,
            related_complaint_id VARCHAR(50) DEFAULT NULL,
            purpose VARCHAR(100) NOT NULL,
            purpose_details TEXT NOT NULL,
            legal_basis VARCHAR(100) NOT NULL,
            incident_location TEXT NOT NULL,
            camera_id VARCHAR(50) DEFAULT NULL,
            location_description TEXT DEFAULT NULL,
            incident_date DATE NOT NULL,
            footage_start_time TIME NOT NULL,
            footage_end_time TIME NOT NULL,
            incident_type VARCHAR(100) DEFAULT NULL,
            incident_description TEXT NOT NULL,
            delivery_method VARCHAR(100) NOT NULL DEFAULT 'secure_download',
            supporting_document LONGTEXT DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Pending',
            review_notes TEXT DEFAULT NULL,
            rejection_reason TEXT DEFAULT NULL,
            approved_camera_id VARCHAR(50) DEFAULT NULL,
            actual_footage_start TIME DEFAULT NULL,
            actual_footage_end TIME DEFAULT NULL,
            fulfillment_notes TEXT DEFAULT NULL,
            reviewed_by VARCHAR(255) DEFAULT NULL,
            fulfilled_at DATETIME DEFAULT NULL,
            forwarded_to_group1_at DATETIME DEFAULT NULL,
            group1_evidence_reference_id VARCHAR(100) DEFAULT NULL,
            forwarded_recording_files TEXT DEFAULT NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_request_id (request_id),
            INDEX idx_submitted_at (submitted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }

    $additions = [
        'source' => "ALTER TABLE cctv_requests ADD COLUMN source VARCHAR(100) DEFAULT 'web_form' AFTER request_id",
        'source_reference_id' => 'ALTER TABLE cctv_requests ADD COLUMN source_reference_id VARCHAR(100) DEFAULT NULL AFTER source',
        'requesting_agency' => 'ALTER TABLE cctv_requests ADD COLUMN requesting_agency VARCHAR(255) NOT NULL DEFAULT "" AFTER source_reference_id',
        'contact_person' => 'ALTER TABLE cctv_requests ADD COLUMN contact_person VARCHAR(255) NOT NULL DEFAULT "" AFTER requesting_agency',
        'contact_position' => 'ALTER TABLE cctv_requests ADD COLUMN contact_position VARCHAR(255) DEFAULT NULL AFTER contact_person',
        'contact_number' => 'ALTER TABLE cctv_requests ADD COLUMN contact_number VARCHAR(50) NOT NULL DEFAULT "" AFTER contact_position',
        'contact_email' => 'ALTER TABLE cctv_requests ADD COLUMN contact_email VARCHAR(255) DEFAULT NULL AFTER contact_number',
        'office_unit' => 'ALTER TABLE cctv_requests ADD COLUMN office_unit VARCHAR(255) DEFAULT NULL AFTER contact_email',
        'case_reference' => 'ALTER TABLE cctv_requests ADD COLUMN case_reference VARCHAR(100) DEFAULT NULL AFTER office_unit',
        'related_complaint_id' => 'ALTER TABLE cctv_requests ADD COLUMN related_complaint_id VARCHAR(50) DEFAULT NULL AFTER case_reference',
        'purpose' => 'ALTER TABLE cctv_requests ADD COLUMN purpose VARCHAR(100) NOT NULL DEFAULT "" AFTER related_complaint_id',
        'purpose_details' => 'ALTER TABLE cctv_requests ADD COLUMN purpose_details TEXT NOT NULL AFTER purpose',
        'legal_basis' => 'ALTER TABLE cctv_requests ADD COLUMN legal_basis VARCHAR(100) NOT NULL DEFAULT "" AFTER purpose_details',
        'incident_location' => 'ALTER TABLE cctv_requests ADD COLUMN incident_location TEXT NOT NULL AFTER legal_basis',
        'camera_id' => 'ALTER TABLE cctv_requests ADD COLUMN camera_id VARCHAR(50) DEFAULT NULL AFTER incident_location',
        'location_description' => 'ALTER TABLE cctv_requests ADD COLUMN location_description TEXT DEFAULT NULL AFTER camera_id',
        'incident_date' => 'ALTER TABLE cctv_requests ADD COLUMN incident_date DATE NOT NULL DEFAULT "1970-01-01" AFTER location_description',
        'footage_start_time' => 'ALTER TABLE cctv_requests ADD COLUMN footage_start_time TIME NOT NULL DEFAULT "00:00:00" AFTER incident_date',
        'footage_end_time' => 'ALTER TABLE cctv_requests ADD COLUMN footage_end_time TIME NOT NULL DEFAULT "00:00:00" AFTER footage_start_time',
        'incident_type' => 'ALTER TABLE cctv_requests ADD COLUMN incident_type VARCHAR(100) DEFAULT NULL AFTER footage_end_time',
        'incident_description' => 'ALTER TABLE cctv_requests ADD COLUMN incident_description TEXT NOT NULL AFTER incident_type',
        'delivery_method' => "ALTER TABLE cctv_requests ADD COLUMN delivery_method VARCHAR(100) NOT NULL DEFAULT 'secure_download' AFTER incident_description",
        'supporting_document' => 'ALTER TABLE cctv_requests ADD COLUMN supporting_document LONGTEXT DEFAULT NULL AFTER delivery_method',
        'status' => "ALTER TABLE cctv_requests ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Pending' AFTER supporting_document",
        'review_notes' => 'ALTER TABLE cctv_requests ADD COLUMN review_notes TEXT DEFAULT NULL AFTER status',
        'rejection_reason' => 'ALTER TABLE cctv_requests ADD COLUMN rejection_reason TEXT DEFAULT NULL AFTER review_notes',
        'approved_camera_id' => 'ALTER TABLE cctv_requests ADD COLUMN approved_camera_id VARCHAR(50) DEFAULT NULL AFTER rejection_reason',
        'actual_footage_start' => 'ALTER TABLE cctv_requests ADD COLUMN actual_footage_start TIME DEFAULT NULL AFTER approved_camera_id',
        'actual_footage_end' => 'ALTER TABLE cctv_requests ADD COLUMN actual_footage_end TIME DEFAULT NULL AFTER actual_footage_start',
        'fulfillment_notes' => 'ALTER TABLE cctv_requests ADD COLUMN fulfillment_notes TEXT DEFAULT NULL AFTER actual_footage_end',
        'reviewed_by' => 'ALTER TABLE cctv_requests ADD COLUMN reviewed_by VARCHAR(255) DEFAULT NULL AFTER fulfillment_notes',
        'fulfilled_at' => 'ALTER TABLE cctv_requests ADD COLUMN fulfilled_at DATETIME DEFAULT NULL AFTER reviewed_by',
        'forwarded_to_group1_at' => 'ALTER TABLE cctv_requests ADD COLUMN forwarded_to_group1_at DATETIME DEFAULT NULL AFTER fulfilled_at',
        'group1_evidence_reference_id' => 'ALTER TABLE cctv_requests ADD COLUMN group1_evidence_reference_id VARCHAR(100) DEFAULT NULL AFTER forwarded_to_group1_at',
        'forwarded_recording_files' => 'ALTER TABLE cctv_requests ADD COLUMN forwarded_recording_files TEXT DEFAULT NULL AFTER group1_evidence_reference_id',
        'submitted_at' => 'ALTER TABLE cctv_requests ADD COLUMN submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER forwarded_recording_files',
        'updated_at' => 'ALTER TABLE cctv_requests ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER submitted_at',
    ];

    foreach ($additions as $column => $sql) {
        if (!isset($columns[$column])) {
            $pdo->exec($sql);
        }
    }
}

function cctvRequestsSelectColumns(string $prefix = ''): string
{
    $p = $prefix !== '' ? $prefix . '.' : '';
    return implode(', ', array_map(static function ($col) use ($p) {
        return $p . $col;
    }, [
        'id', 'request_id', 'source', 'source_reference_id', 'requesting_agency',
        'contact_person', 'contact_position', 'contact_number', 'contact_email', 'office_unit',
        'case_reference', 'related_complaint_id', 'purpose', 'purpose_details', 'legal_basis',
        'incident_location', 'camera_id', 'location_description', 'incident_date',
        'footage_start_time', 'footage_end_time', 'incident_type', 'incident_description',
        'delivery_method', 'status', 'review_notes', 'rejection_reason', 'approved_camera_id',
        'actual_footage_start', 'actual_footage_end', 'fulfillment_notes', 'reviewed_by',
        'fulfilled_at', 'forwarded_to_group1_at', 'group1_evidence_reference_id',
        'forwarded_recording_files', 'submitted_at', 'updated_at',
    ])) . ', ' . $p . 'supporting_document';
}

function generateCctvRequestId(PDO $pdo): string
{
    $year = date('Y');
    $prefix = 'CCTV-REQ-' . $year . '-';

    $stmt = $pdo->prepare('SELECT request_id FROM cctv_requests WHERE request_id LIKE :prefix ORDER BY id DESC LIMIT 1');
    $stmt->execute([':prefix' => $prefix . '%']);
    $last = $stmt->fetchColumn();

    $next = 1;
    if ($last && preg_match('/CCTV-REQ-\d{4}-(\d+)$/', $last, $matches)) {
        $next = (int) $matches[1] + 1;
    }

    return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
}

function validateCctvRequestApiKey(): bool
{
    $expectedKey = trim($_ENV['CCTV_REQUEST_API_KEY'] ?? '');
    if ($expectedKey === '') {
        return true;
    }

    $providedKey = '';
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        $providedKey = $matches[1];
    }
    if ($providedKey === '') {
        $providedKey = trim($_SERVER['HTTP_X_API_KEY'] ?? '');
    }

    return $providedKey !== '' && hash_equals($expectedKey, $providedKey);
}

function requireConfiguredCctvRequestApiKey(): bool
{
    $expectedKey = trim($_ENV['CCTV_REQUEST_API_KEY'] ?? '');
    if ($expectedKey === '') {
        return false;
    }

    return validateCctvRequestApiKey();
}

function canCreateCctvRequest(bool $isAdmin): bool
{
    if ($isAdmin) {
        return true;
    }
    if (validateCctvRequestApiKey()) {
        return true;
    }
    $allowPublic = filter_var($_ENV['CCTV_REQUEST_ALLOW_PUBLIC_FORM'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    return $allowPublic;
}

function normalizeCctvRequestInput(array $input): array
{
    return [
        'source' => trim($input['source'] ?? 'web_form'),
        'source_reference_id' => trim($input['source_reference_id'] ?? ''),
        'requesting_agency' => trim($input['requesting_agency'] ?? ''),
        'contact_person' => trim($input['contact_person'] ?? ''),
        'contact_position' => trim($input['contact_position'] ?? ''),
        'contact_number' => trim($input['contact_number'] ?? ''),
        'contact_email' => trim($input['contact_email'] ?? ''),
        'office_unit' => trim($input['office_unit'] ?? ''),
        'case_reference' => trim($input['case_reference'] ?? ''),
        'related_complaint_id' => trim($input['related_complaint_id'] ?? ''),
        'purpose' => trim($input['purpose'] ?? ''),
        'purpose_details' => trim($input['purpose_details'] ?? ''),
        'legal_basis' => trim($input['legal_basis'] ?? ''),
        'incident_location' => trim($input['incident_location'] ?? ''),
        'camera_id' => trim($input['camera_id'] ?? ''),
        'location_description' => trim($input['location_description'] ?? ''),
        'incident_date' => trim($input['incident_date'] ?? ''),
        'footage_start_time' => trim($input['footage_start_time'] ?? ''),
        'footage_end_time' => trim($input['footage_end_time'] ?? ''),
        'incident_type' => trim($input['incident_type'] ?? ''),
        'incident_description' => trim($input['incident_description'] ?? ''),
        'delivery_method' => trim($input['delivery_method'] ?? 'secure_download'),
        'supporting_document' => trim($input['supporting_document'] ?? ''),
    ];
}

function validateCctvRequestRequiredFields(array $data): ?string
{
    $required = [
        'requesting_agency' => 'Requesting agency',
        'contact_person' => 'Contact person',
        'contact_number' => 'Contact number',
        'purpose_details' => 'Purpose / reason for request',
        'legal_basis' => 'Legal basis',
        'incident_location' => 'Incident location',
        'incident_date' => 'Incident date',
        'footage_start_time' => 'Footage start time',
        'footage_end_time' => 'Footage end time',
        'incident_description' => 'Incident description',
    ];

    foreach ($required as $field => $label) {
        if ($data[$field] === '') {
            return $label . ' is required.';
        }
    }

    if ($data['camera_id'] === '' && $data['location_description'] === '') {
        return 'Please select a camera or provide a location description.';
    }

    if ($data['footage_end_time'] <= $data['footage_start_time']) {
        return 'Footage end time must be after start time.';
    }

    return null;
}

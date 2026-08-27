<?php

/**
 * Forward complaints from AlertaraQC to Incident Reporting (Digital Blotter) via HTTP API.
 *
 * Configure in .env (preferred + legacy):
 *   INCIDENT_REPORTING_API_URL=https://report.alertaraqc.com/api/api.php?action=create_blotter
 *   INCIDENT_REPORTING_API_KEY=shared-secret-key (optional if their endpoint is open)
 *   INCIDENT_REPORTING_API_TIMEOUT=30
 *   Legacy: BLOTTER_API_URL, BLOTTER_API_KEY, BLOTTER_API_TIMEOUT
 *
 * Their create_blotter requires: complainant_name, incident_type
 * Accepted response shapes:
 *   { "success": true, "blotter_reference_id": "DB-2026-001", "message": "..." }
 *   { "status": "success", "data": { "blotter_no": "BLT-..." }, "message": "..." }
 */

require_once __DIR__ . '/../api/complaints_schema.php';
require_once __DIR__ . '/api_key_auth.php';

function getBlotterApiConfig(): array
{
    $cfg = getIncidentReportingApiConfig();

    return [
        'url' => $cfg['url'],
        'api_key' => $cfg['api_key'],
        'timeout' => $cfg['timeout'],
    ];
}

function buildBlotterForwardPayload(array $complaint): array
{
    $notes = trim($complaint['notes'] ?? '');
    if ($notes !== '') {
        $lines = preg_split('/\r\n|\r|\n/', $notes);
        $lines = array_filter($lines, static function ($line) {
            $line = trim($line);
            if ($line === '') {
                return false;
            }
            return !preg_match('/Assigned to/i', $line)
                && !preg_match('/Marked as resolved/i', $line)
                && !preg_match('/Updated progress/i', $line);
        });
        $notes = trim(implode("\n", $lines));
    }

    $complaintType = formatComplaintTypeLabel($complaint);
    $typeOther = (($complaint['complaint_type'] ?? '') === 'Other') ? trim($complaint['complaint_type_other'] ?? '') : null;
    $date = $complaint['incident_date'] ?? '';
    $time = $complaint['incident_time'] ?? '';
    $statusDescription = trim($complaint['description'] ?? '');

    return [
        'source' => 'alertaraqc',
        'source_complaint_id' => $complaint['complaint_id'] ?? '',
        // Nested structure (existing contract)
        'complainant' => [
            'name' => $complaint['complainant_name'] ?? '',
            'contact_number' => $complaint['contact_number'] ?? '',
            'address' => $complaint['address'] ?? '',
        ],
        'defendant' => [
            'name' => $complaint['defendant_name'] ?? '',
            'address' => $complaint['defendant_address'] ?? '',
            'contact_number' => $complaint['defendant_contact_number'] ?? '',
        ],
        'incident' => [
            'date' => $date,
            'time' => $time,
            'location' => $complaint['location'] ?? '',
            'type' => $complaintType,
            'type_other' => $typeOther,
            'description' => $statusDescription,
        ],
        'priority' => $complaint['priority'] ?? 'Low',
        'notes' => $notes,
        'submitted_at' => $complaint['submitted_at'] ?? null,
        // Flat fields for report.alertaraqc.com action=create_blotter
        'complainant_name' => $complaint['complainant_name'] ?? '',
        'complainant_address' => $complaint['address'] ?? '',
        'complainant_contact_number' => $complaint['contact_number'] ?? '',
        'contact_number' => $complaint['contact_number'] ?? '',
        'address' => $complaint['address'] ?? '',
        'location' => $complaint['location'] ?? '',
        'incident_type' => $complaintType !== '' ? $complaintType : 'Other',
        'incident_date' => $date,
        'incident_time' => $time,
        'date_time' => trim($date . ' ' . $time),
        'description' => $statusDescription,
        // IR blotter records use narrative as the primary text field (see action=all dump).
        // create_blotter currently INSERTS narrative even when omitted — if IR's DB lacks the
        // column, their API returns HTTP 500 until they add it or fix the INSERT.
        'narrative' => $statusDescription,
        'defendant_name' => $complaint['defendant_name'] ?? '',
        'defendant_address' => $complaint['defendant_address'] ?? '',
        'defendant_contact_number' => $complaint['defendant_contact_number'] ?? '',
        'complaint_type' => $complaintType,
        'specify_complaint_type' => $typeOther,
        'status_description' => $statusDescription,
        'metadata' => [
            'internal_id' => (int) ($complaint['id'] ?? 0),
            'forwarded_by' => 'alertaraqc_admin',
            'forwarded_at' => date('c'),
        ],
    ];
}

function forwardComplaintToBlotter(array $complaint): array
{
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL extension is required to forward complaints.'];
    }

    $config = getBlotterApiConfig();
    if ($config['url'] === '') {
        return [
            'success' => false,
            'message' => 'Incident Reporting API is not configured. Set INCIDENT_REPORTING_API_URL in .env.',
        ];
    }

    $payload = json_encode(buildBlotterForwardPayload($complaint), JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return ['success' => false, 'message' => 'Failed to encode complaint payload.'];
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($config['api_key'] !== '') {
        $headers[] = 'X-API-Key: ' . $config['api_key'];
        $headers[] = 'Authorization: Bearer ' . $config['api_key'];
    }

    $ch = curl_init($config['url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $config['timeout'],
        CURLOPT_HTTPHEADER => $headers,
    ]);

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        return [
            'success' => false,
            'message' => 'Failed to reach Digital Blotter API: ' . ($curlError ?: 'Unknown error'),
        ];
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'message' => 'Digital Blotter API returned an invalid response (HTTP ' . $httpCode . ').',
            'http_code' => $httpCode,
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = trim($decoded['message'] ?? $decoded['error'] ?? 'Digital Blotter API request failed.');
        if (stripos($message, 'narrative') !== false && stripos($message, 'Unknown column') !== false) {
            $message = 'Incident Reporting Digital Blotter API is broken on their server (missing DB column `narrative` in create_blotter). AlertaraQC already omits that field — IR must add the column or stop inserting it.';
        }
        return [
            'success' => false,
            'message' => $message . ' (HTTP ' . $httpCode . ')',
            'http_code' => $httpCode,
        ];
    }

    if (!partnerApiResponseSucceeded($decoded)) {
        return [
            'success' => false,
            'message' => trim($decoded['message'] ?? $decoded['error'] ?? 'Digital Blotter API rejected the complaint.'),
            'http_code' => $httpCode,
        ];
    }

    $referenceId = partnerApiReferenceId($decoded);

    return [
        'success' => true,
        'message' => trim($decoded['message'] ?? 'Complaint forwarded successfully.'),
        'blotter_reference_id' => $referenceId,
        'http_code' => $httpCode,
    ];
}

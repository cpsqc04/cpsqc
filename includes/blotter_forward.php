<?php

/**
 * Forward complaints from AlertaraQC to Group 1 Digital Blotter System via HTTP API.
 *
 * Configure in .env:
 *   BLOTTER_API_URL=https://group1.example.com/api/blotter/receive
 *   BLOTTER_API_KEY=shared-secret-key
 *   BLOTTER_API_TIMEOUT=30
 *
 * Expected Group 1 response (JSON):
 *   { "success": true, "blotter_reference_id": "DB-2026-001", "message": "..." }
 */

require_once __DIR__ . '/../api/complaints_schema.php';

function getBlotterApiConfig(): array
{
    return [
        'url' => trim($_ENV['BLOTTER_API_URL'] ?? ''),
        'api_key' => trim($_ENV['BLOTTER_API_KEY'] ?? ''),
        'timeout' => max(5, (int) ($_ENV['BLOTTER_API_TIMEOUT'] ?? 30)),
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

    return [
        'source' => 'alertaraqc',
        'source_complaint_id' => $complaint['complaint_id'] ?? '',
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
            'date' => $complaint['incident_date'] ?? '',
            'time' => $complaint['incident_time'] ?? '',
            'location' => $complaint['location'] ?? '',
            'type' => formatComplaintTypeLabel($complaint),
            'type_other' => (($complaint['complaint_type'] ?? '') === 'Other') ? trim($complaint['complaint_type_other'] ?? '') : null,
            'description' => $complaint['description'] ?? '',
        ],
        'priority' => $complaint['priority'] ?? 'Low',
        'notes' => $notes,
        'submitted_at' => $complaint['submitted_at'] ?? null,
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
            'message' => 'Digital Blotter API is not configured. Set BLOTTER_API_URL in .env.',
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
        return [
            'success' => false,
            'message' => $message . ' (HTTP ' . $httpCode . ')',
            'http_code' => $httpCode,
        ];
    }

    $success = !empty($decoded['success']);
    if (!$success) {
        return [
            'success' => false,
            'message' => trim($decoded['message'] ?? $decoded['error'] ?? 'Digital Blotter API rejected the complaint.'),
            'http_code' => $httpCode,
        ];
    }

    $referenceId = trim(
        (string) ($decoded['blotter_reference_id'] ?? $decoded['reference_id'] ?? $decoded['id'] ?? '')
    );

    return [
        'success' => true,
        'message' => trim($decoded['message'] ?? 'Complaint forwarded successfully.'),
        'blotter_reference_id' => $referenceId,
        'http_code' => $httpCode,
    ];
}

<?php

/**
 * Forward BPSO tips to Incident Reporting (tips / incident logging).
 *
 * Configure in .env (preferred + legacy):
 *   INCIDENT_REPORTING_TIP_API_URL=   (optional; falls back to INCIDENT_REPORTING_API_URL / TIP_BLOTTER_API_URL / BLOTTER_API_URL)
 *   INCIDENT_REPORTING_API_KEY=       (legacy: BLOTTER_API_KEY)
 *   INCIDENT_REPORTING_API_TIMEOUT=30
 */

require_once __DIR__ . '/api_key_auth.php';
require_once __DIR__ . '/tip_outbound_photo.php';

function getTipBlotterApiConfig(): array
{
    $cfg = getIncidentReportingApiConfig();

    return [
        'url' => $cfg['tip_url'],
        'api_key' => $cfg['api_key'],
        'timeout' => $cfg['timeout'],
    ];
}

function buildTipIncidentPayload(array $tip): array
{
    $submittedAt = $tip['submitted_at'] ?? null;
    if ($submittedAt) {
        try {
            $submittedAt = (new DateTime($submittedAt))->format('c');
        } catch (Exception $e) {
            $submittedAt = (string) $submittedAt;
        }
    }

    $photo = prepareTipOutboundPhoto($tip['photo_data'] ?? null);
    $hasPhoto = !empty($photo['has_photo']);
    $photoData = $hasPhoto ? (string) $photo['photo_data'] : null;

    return [
        'source' => 'alertaraqc',
        'record_type' => 'tip',
        'source_tip_id' => $tip['tip_id'] ?? '',
        // Nested structure (existing contract)
        'incident' => [
            'location' => $tip['location'] ?? '',
            'description' => $tip['description'] ?? '',
            'submitted_at' => $submittedAt,
            'classification' => 'community_tip',
        ],
        'reporter' => [
            'contact_number' => $tip['contact_number'] ?? null,
            'anonymous' => empty($tip['contact_number']),
        ],
        'review' => [
            'status' => $tip['status'] ?? 'New',
            'outcome' => $tip['outcome'] ?? 'No Outcome Yet',
            'assigned_to' => $tip['assigned_to'] ?? null,
        ],
        'has_photo' => $hasPhoto,
        // Flat photo fields for partner mapping (Incident Reporting + Emergency Response)
        'photo_data' => $photoData,
        'photo_of_evidence' => $photoData ?? '',
        'attached_evidence' => [
            'type' => $hasPhoto ? 'photo' : null,
            'photo_data' => $photoData,
            'available' => $hasPhoto,
        ],
        // Flat module labels for partner mapping (Review Tip outbound)
        'tip_id' => $tip['tip_id'] ?? '',
        'date_time' => $submittedAt,
        'location' => $tip['location'] ?? '',
        'tip_description' => $tip['description'] ?? '',
        'status' => $tip['status'] ?? 'New',
        'outcome' => $tip['outcome'] ?? 'No Outcome Yet',
        'assigned_to' => $tip['assigned_to'] ?? null,
        'metadata' => [
            'internal_id' => (int) ($tip['id'] ?? 0),
            'forwarded_by' => 'alertaraqc_bpso_admin',
            'forwarded_at' => date('c'),
            'has_photo' => $hasPhoto,
        ],
    ];
}

function forwardTipToIncidentReporting(array $tip): array
{
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL extension is required to forward tips.'];
    }

    $config = getTipBlotterApiConfig();
    if ($config['url'] === '') {
        return [
            'success' => false,
            'message' => 'Incident Reporting tip API is not configured. Set INCIDENT_REPORTING_TIP_API_URL or INCIDENT_REPORTING_API_URL in .env.',
        ];
    }

    $payload = json_encode(buildTipIncidentPayload($tip), JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return ['success' => false, 'message' => 'Failed to encode tip payload.'];
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($config['api_key'] !== '') {
        $headers[] = 'X-API-Key: ' . $config['api_key'];
        $headers[] = 'Authorization: Bearer ' . $config['api_key'];
    }

    // Photos enlarge the JSON body — allow more time than the default tip timeout.
    $timeout = max((int) $config['timeout'], !empty($tip['photo_data']) ? 90 : 30);

    $ch = curl_init($config['url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        return [
            'success' => false,
            'message' => 'Failed to reach Incident Reporting API: ' . ($curlError ?: 'Unknown error'),
        ];
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'message' => 'Incident Reporting API returned an invalid response (HTTP ' . $httpCode . ').',
            'http_code' => $httpCode,
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = trim($decoded['message'] ?? $decoded['error'] ?? 'Incident Reporting API request failed.');
        return [
            'success' => false,
            'message' => $message . ' (HTTP ' . $httpCode . ')',
            'http_code' => $httpCode,
        ];
    }

    if (empty($decoded['success'])) {
        return [
            'success' => false,
            'message' => trim($decoded['message'] ?? $decoded['error'] ?? 'Incident Reporting API rejected the tip.'),
            'http_code' => $httpCode,
        ];
    }

    $referenceId = trim(
        (string) ($decoded['blotter_reference_id'] ?? $decoded['incident_reference_id'] ?? $decoded['reference_id'] ?? $decoded['id'] ?? '')
    );

    return [
        'success' => true,
        'message' => trim($decoded['message'] ?? 'Tip forwarded to Incident Reporting.'),
        'blotter_reference_id' => $referenceId,
        'http_code' => $httpCode,
    ];
}

/** @deprecated Use forwardTipToIncidentReporting() */
function forwardTipToGroup1(array $tip): array
{
    return forwardTipToIncidentReporting($tip);
}

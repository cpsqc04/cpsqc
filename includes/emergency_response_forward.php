<?php

/**
 * Forward BPSO tips to Emergency Response (police backup / coordination).
 *
 * Configure in .env (preferred + legacy):
 *   EMERGENCY_RESPONSE_API_URL=
 *   EMERGENCY_RESPONSE_API_KEY=
 *   EMERGENCY_RESPONSE_API_TIMEOUT=30
 *   Legacy: GROUP3_API_URL, GROUP3_API_KEY, GROUP3_API_TIMEOUT
 */

require_once __DIR__ . '/api_key_auth.php';

function buildEmergencyResponseBackupPayload(array $tip, string $backupReason = ''): array
{
    $reason = trim($backupReason);
    if ($reason === '') {
        $reason = trim($tip['police_backup_reason'] ?? '');
    }
    if ($reason === '') {
        $reason = trim($tip['description'] ?? '');
    }
    if ($reason === '') {
        $reason = 'Police backup requested from BPSO admin review of community tip.';
    }

    $submittedAt = $tip['submitted_at'] ?? null;
    if ($submittedAt) {
        try {
            $submittedAt = (new DateTime($submittedAt))->format('c');
        } catch (Exception $e) {
            $submittedAt = (string) $submittedAt;
        }
    }

    return [
        'source' => 'alertaraqc',
        'request_type' => 'police_backup',
        'source_tip_id' => $tip['tip_id'] ?? '',
        'requesting_agency' => 'BPSO - Quezon City',
        'incident' => [
            'location' => $tip['location'] ?? '',
            'description' => $tip['description'] ?? '',
            'submitted_at' => $submittedAt,
        ],
        'backup' => [
            'reason' => $reason,
            'priority' => 'high',
            'units_requested' => 'patrol',
        ],
        'review' => [
            'status' => $tip['status'] ?? 'Under Review',
        ],
        'contact' => [
            'contact_number' => $tip['contact_number'] ?? null,
        ],
        'has_photo' => !empty($tip['photo_data']),
        'metadata' => [
            'internal_id' => (int) ($tip['id'] ?? 0),
            'forwarded_by' => 'alertaraqc_bpso_admin',
            'forwarded_at' => date('c'),
        ],
    ];
}

function forwardTipToEmergencyResponse(array $tip, string $backupReason = ''): array
{
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL extension is required to request police backup.'];
    }

    $config = getEmergencyResponseApiConfig();
    if ($config['url'] === '') {
        return [
            'success' => false,
            'message' => 'Emergency Response API is not configured. Set EMERGENCY_RESPONSE_API_URL in .env.',
        ];
    }

    $payload = json_encode(buildEmergencyResponseBackupPayload($tip, $backupReason), JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return ['success' => false, 'message' => 'Failed to encode coordination payload.'];
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
            'message' => 'Failed to reach Emergency Response API: ' . ($curlError ?: 'Unknown error'),
        ];
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'message' => 'Emergency Response API returned an invalid response (HTTP ' . $httpCode . ').',
            'http_code' => $httpCode,
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = trim($decoded['message'] ?? $decoded['error'] ?? 'Emergency Response API request failed.');
        return [
            'success' => false,
            'message' => $message . ' (HTTP ' . $httpCode . ')',
            'http_code' => $httpCode,
        ];
    }

    if (empty($decoded['success'])) {
        return [
            'success' => false,
            'message' => trim($decoded['message'] ?? $decoded['error'] ?? 'Emergency Response API rejected the request.'),
            'http_code' => $httpCode,
        ];
    }

    $referenceId = trim(
        (string) ($decoded['coordination_reference_id'] ?? $decoded['reference_id'] ?? $decoded['id'] ?? '')
    );

    return [
        'success' => true,
        'message' => trim($decoded['message'] ?? 'Police backup request sent to Emergency Response.'),
        'emergency_response_reference_id' => $referenceId,
        'http_code' => $httpCode,
    ];
}

/** @deprecated Use getEmergencyResponseApiConfig() */
function getGroup3ApiConfig(): array
{
    return getEmergencyResponseApiConfig();
}

/** @deprecated Use buildEmergencyResponseBackupPayload() */
function buildGroup3BackupPayload(array $tip, string $backupReason = ''): array
{
    return buildEmergencyResponseBackupPayload($tip, $backupReason);
}

/** @deprecated Use forwardTipToEmergencyResponse() */
function forwardTipToGroup3(array $tip, string $backupReason = ''): array
{
    $result = forwardTipToEmergencyResponse($tip, $backupReason);
    if (isset($result['emergency_response_reference_id'])) {
        $result['group3_reference_id'] = $result['emergency_response_reference_id'];
    }
    return $result;
}

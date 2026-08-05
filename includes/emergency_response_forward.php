<?php

/**
 * Forward BPSO tips to Emergency Response (anonymous tip / police backup).
 *
 * Their receive API (anonymous_tip.php) expects tip fields including tip_id.
 *
 * Configure in .env (preferred + legacy):
 *   EMERGENCY_RESPONSE_API_URL=
 *   EMERGENCY_RESPONSE_API_KEY=
 *   EMERGENCY_RESPONSE_API_TIMEOUT=30
 *   Legacy: GROUP3_API_URL, GROUP3_API_KEY, GROUP3_API_TIMEOUT
 */

require_once __DIR__ . '/api_key_auth.php';
require_once __DIR__ . '/tip_outbound_photo.php';

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

    $description = trim($tip['description'] ?? '');
    if ($description === '') {
        $description = $reason;
    } elseif ($reason !== '' && strcasecmp($reason, $description) !== 0) {
        $description = $description . "\n\n[Police backup] " . $reason;
    }

    $submittedAt = $tip['submitted_at'] ?? null;
    $tipDatetime = '';
    if ($submittedAt) {
        try {
            $dt = new DateTime($submittedAt);
            $submittedAt = $dt->format('c');
            $tipDatetime = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $submittedAt = (string) $submittedAt;
            $tipDatetime = $submittedAt;
        }
    }
    if ($tipDatetime === '') {
        $tipDatetime = date('Y-m-d H:i:s');
    }

    $photo = prepareTipOutboundPhoto($tip['photo_data'] ?? null);
    $hasPhoto = !empty($photo['has_photo']);
    $photoData = $hasPhoto ? (string) $photo['photo_data'] : '';

    $tipId = trim((string) ($tip['tip_id'] ?? ''));
    $location = trim((string) ($tip['location'] ?? ''));
    $status = trim((string) ($tip['status'] ?? 'new'));
    if ($status === '' || strcasecmp($status, 'Under Review') === 0) {
        $status = 'new';
    }
    $outcome = trim((string) ($tip['outcome'] ?? ''));

    // Flat fields match Emergency Response anonymous_tip.php contract.
    // Nested/legacy fields kept for compatibility with older coordination endpoints.
    return [
        'tip_id' => $tipId,
        'tip_datetime' => $tipDatetime,
        'location' => $location,
        'tip_description' => $description,
        'photo_of_evidence' => $photoData,
        'photo_data' => $photoData !== '' ? $photoData : null,
        'status' => $status,
        'outcome' => $outcome,
        'source_system' => 'alertaraqc',
        'source' => 'alertaraqc',
        'record_type' => 'tip',
        'request_type' => 'police_backup',
        'source_tip_id' => $tipId,
        'requesting_agency' => 'BPSO - Quezon City',
        'date_time' => $submittedAt,
        'incident' => [
            'location' => $location,
            'description' => $description,
            'submitted_at' => $submittedAt,
        ],
        'backup' => [
            'reason' => $reason,
            'priority' => 'high',
            'units_requested' => 'patrol',
        ],
        'review' => [
            'status' => $tip['status'] ?? 'Under Review',
            'outcome' => $tip['outcome'] ?? 'No Outcome Yet',
        ],
        'contact' => [
            'contact_number' => $tip['contact_number'] ?? null,
        ],
        'has_photo' => $hasPhoto,
        'attached_evidence' => [
            'type' => $hasPhoto ? 'photo' : null,
            'photo_data' => $photoData !== '' ? $photoData : null,
            'available' => $hasPhoto,
        ],
        'metadata' => [
            'internal_id' => (int) ($tip['id'] ?? 0),
            'forwarded_by' => 'alertaraqc_bpso_admin',
            'forwarded_at' => date('c'),
            'police_backup' => true,
            'has_photo' => $hasPhoto,
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

    $tipId = trim((string) ($tip['tip_id'] ?? ''));
    if ($tipId === '') {
        return ['success' => false, 'message' => 'tip_id is missing on this tip record.'];
    }

    $payload = json_encode(buildEmergencyResponseBackupPayload($tip, $backupReason), JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return ['success' => false, 'message' => 'Failed to encode tip payload.'];
    }

    $url = $config['url'];
    // Some Emergency Response endpoints accept api_key in the query string.
    if ($config['api_key'] !== '' && stripos($url, 'api_key=') === false) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'api_key=' . rawurlencode($config['api_key']);
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($config['api_key'] !== '') {
        $headers[] = 'X-API-Key: ' . $config['api_key'];
        $headers[] = 'Authorization: Bearer ' . $config['api_key'];
    }

    $timeout = max((int) $config['timeout'], !empty($tip['photo_data']) ? 90 : 30);

    $ch = curl_init($url);
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

    if (array_key_exists('success', $decoded) && empty($decoded['success'])) {
        return [
            'success' => false,
            'message' => trim($decoded['message'] ?? $decoded['error'] ?? 'Emergency Response API rejected the request.'),
            'http_code' => $httpCode,
        ];
    }

    $referenceId = trim(
        (string) (
            $decoded['coordination_reference_id']
            ?? $decoded['tip_id']
            ?? $decoded['reference_id']
            ?? $decoded['id']
            ?? ''
        )
    );
    if ($referenceId === '') {
        $referenceId = $tipId;
    }

    return [
        'success' => true,
        'message' => trim($decoded['message'] ?? 'Tip sent to Emergency Response for police backup.'),
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

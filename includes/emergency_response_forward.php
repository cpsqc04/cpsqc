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
require_once __DIR__ . '/../api/tips_schema.php';

function buildEmergencyResponseBackupPayload(array $tip, string $backupReason = '', bool $includePhoto = true): array
{
    $reason = trim($backupReason);
    if ($reason === '') {
        $reason = trim($tip['police_backup_reason'] ?? '');
    }
    if ($reason === '') {
        $reason = trim($tip['description'] ?? '');
    }
    if ($reason === '') {
        $reason = 'Inter-Agency assistance requested from BPSO admin review of community tip.';
    }

    $description = trim($tip['description'] ?? '');
    if ($description === '') {
        $description = $reason;
    } elseif ($reason !== '' && strcasecmp($reason, $description) !== 0) {
        $description = $description . "\n\n[Police backup] " . $reason;
    }

    $eventAtRaw = tipPrimaryEventAt($tip) ?? ($tip['submitted_at'] ?? null);
    $submittedAtRaw = $tip['submitted_at'] ?? null;
    $submittedAt = $submittedAtRaw;
    $tipDatetime = '';
    if ($eventAtRaw) {
        try {
            $dt = new DateTime($eventAtRaw);
            $tipDatetime = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $tipDatetime = (string) $eventAtRaw;
        }
    }
    if ($submittedAt) {
        try {
            $submittedAt = (new DateTime($submittedAt))->format('c');
        } catch (Exception $e) {
            $submittedAt = (string) $submittedAt;
        }
    }
    $dateTimeIso = $submittedAt;
    if ($eventAtRaw) {
        try {
            $dateTimeIso = (new DateTime($eventAtRaw))->format('c');
        } catch (Exception $e) {
            $dateTimeIso = (string) $eventAtRaw;
        }
    }
    if ($tipDatetime === '') {
        $tipDatetime = date('Y-m-d H:i:s');
    }
    if (!$submittedAt) {
        $submittedAt = date('c');
    }
    if (!$dateTimeIso) {
        $dateTimeIso = $submittedAt;
    }

    $photoDataUrl = '';
    $photoBase64 = '';
    $hasPhoto = false;
    if ($includePhoto) {
        $photo = prepareTipOutboundPhoto($tip['photo_data'] ?? null);
        $hasPhoto = !empty($photo['has_photo']);
        $photoDataUrl = $hasPhoto ? (string) ($photo['photo_data'] ?? '') : '';
        $photoBase64 = $hasPhoto ? (string) ($photo['photo_base64'] ?? '') : '';
        // Emergency Response PHP often base64_decodes photo_of_evidence directly.
        // Sending a data-URL prefix causes decode failures → HTTP 500 "Unable to process anonymous tip".
        if ($photoBase64 === '' && $photoDataUrl !== '' && preg_match('#base64,(.+)$#is', $photoDataUrl, $m)) {
            $photoBase64 = preg_replace('/\s+/', '', $m[1]);
        }
    }

    $tipId = trim((string) ($tip['tip_id'] ?? ''));
    $location = trim((string) ($tip['location'] ?? ''));
    if ($location === '') {
        $location = 'Barangay San Agustin, Quezon City';
    }

    $statusRaw = trim((string) ($tip['status'] ?? 'New'));
    if (function_exists('normalizeTipStatus')) {
        $statusRaw = normalizeTipStatus($statusRaw);
    }
    // Partner APIs commonly expect lowercase workflow values.
    $status = strtolower($statusRaw);
    if ($status === '' || $status === 'under review' || $status === 'reviewed') {
        $status = 'new';
    }

    $outcome = trim((string) ($tip['outcome'] ?? 'No Outcome Yet'));
    if ($outcome === '') {
        $outcome = 'No Outcome Yet';
    }

    return [
        'tip_id' => $tipId,
        'tip_datetime' => $tipDatetime,
        'location' => $location,
        'tip_description' => $description,
        // Primary field for Emergency Response anonymous_tip.php (raw base64, no data: prefix).
        'photo_of_evidence' => $hasPhoto ? $photoBase64 : '',
        // Compatibility aliases
        'photo_data' => $hasPhoto ? $photoDataUrl : null,
        'photo_base64' => $hasPhoto ? $photoBase64 : null,
        'status' => $status,
        'outcome' => $outcome,
        'source_system' => 'alertaraqc',
        'source' => 'alertaraqc',
        'record_type' => 'tip',
        'request_type' => 'police_backup',
        'source_tip_id' => $tipId,
        'requesting_agency' => 'BPSO - Quezon City',
        'date_time' => $dateTimeIso,
        'incident' => [
            'location' => $location,
            'description' => $description,
            'incident_at' => $dateTimeIso,
            'submitted_at' => $submittedAt,
        ],
        'backup' => [
            'reason' => $reason,
            'priority' => 'high',
            'units_requested' => 'patrol',
        ],
        'review' => [
            'status' => $statusRaw !== '' ? $statusRaw : 'New',
            'outcome' => $outcome,
        ],
        'contact' => [
            'contact_number' => $tip['contact_number'] ?? null,
        ],
        'has_photo' => $hasPhoto,
        'attached_evidence' => [
            'type' => $hasPhoto ? 'photo' : null,
            'photo_data' => $hasPhoto ? $photoDataUrl : null,
            'photo_base64' => $hasPhoto ? $photoBase64 : null,
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

function postEmergencyResponsePayload(string $url, string $apiKey, string $payload, int $timeout): array
{
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($apiKey !== '') {
        $headers[] = 'X-API-Key: ' . $apiKey;
        $headers[] = 'Authorization: Bearer ' . $apiKey;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'body' => $responseBody,
        'curl_error' => $curlError,
        'http_code' => $httpCode,
    ];
}

function parseEmergencyResponseResult(array $transport, string $tipId): array
{
    $responseBody = $transport['body'];
    $curlError = (string) ($transport['curl_error'] ?? '');
    $httpCode = (int) ($transport['http_code'] ?? 0);

    if ($responseBody === false || $responseBody === null) {
        return [
            'success' => false,
            'message' => 'Failed to reach Emergency Response API: ' . ($curlError !== '' ? $curlError : 'Unknown error'),
            'http_code' => $httpCode,
            'retryable_without_photo' => false,
        ];
    }

    $decoded = json_decode((string) $responseBody, true);
    if (!is_array($decoded)) {
        $snippet = trim(substr(strip_tags((string) $responseBody), 0, 160));
        return [
            'success' => false,
            'message' => 'Emergency Response API returned an invalid response (HTTP ' . $httpCode . ')'
                . ($snippet !== '' ? ': ' . $snippet : '.'),
            'http_code' => $httpCode,
            'retryable_without_photo' => $httpCode >= 500 || $httpCode === 413,
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = trim((string) ($decoded['message'] ?? $decoded['error'] ?? 'Emergency Response API request failed.'));
        $lower = strtolower($message);
        $retryWithoutPhoto = $httpCode >= 500
            || $httpCode === 413
            || str_contains($lower, 'unable to process anonymous tip')
            || str_contains($lower, 'memory')
            || str_contains($lower, 'photo');

        return [
            'success' => false,
            'message' => $message . ' (HTTP ' . $httpCode . ')',
            'http_code' => $httpCode,
            'retryable_without_photo' => $retryWithoutPhoto,
        ];
    }

    if (array_key_exists('success', $decoded) && empty($decoded['success'])) {
        $message = trim((string) ($decoded['message'] ?? $decoded['error'] ?? 'Emergency Response API rejected the request.'));
        return [
            'success' => false,
            'message' => $message,
            'http_code' => $httpCode,
            'retryable_without_photo' => str_contains(strtolower($message), 'photo')
                || str_contains(strtolower($message), 'unable to process'),
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
        'message' => trim((string) ($decoded['message'] ?? 'Tip sent to Inter-Agency for assistance.')),
        'emergency_response_reference_id' => $referenceId,
        'http_code' => $httpCode,
        'retryable_without_photo' => false,
    ];
}

function forwardTipToEmergencyResponse(array $tip, string $backupReason = ''): array
{
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL extension is required to request Inter-Agency assistance.'];
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

    $url = $config['url'];
    // Some Emergency Response endpoints accept api_key in the query string.
    if ($config['api_key'] !== '' && stripos($url, 'api_key=') === false) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'api_key=' . rawurlencode($config['api_key']);
    }

    $timeout = max((int) $config['timeout'], !empty($tip['photo_data']) ? 90 : 30);
    $attempts = [
        ['include_photo' => true, 'label' => 'with photo'],
        ['include_photo' => false, 'label' => 'without photo'],
    ];

    $lastFailure = null;
    foreach ($attempts as $index => $attempt) {
        // Skip photo-less retry unless the first attempt looks photo/size related.
        if ($index > 0 && empty($lastFailure['retryable_without_photo'])) {
            break;
        }
        if ($index > 0 && empty($tip['photo_data'])) {
            break;
        }

        $payloadArr = buildEmergencyResponseBackupPayload($tip, $backupReason, (bool) $attempt['include_photo']);
        $payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return ['success' => false, 'message' => 'Failed to encode tip payload.'];
        }

        $transport = postEmergencyResponsePayload($url, $config['api_key'], $payload, $timeout);
        $parsed = parseEmergencyResponseResult($transport, $tipId);
        if (!empty($parsed['success'])) {
            if ($index > 0) {
                $parsed['message'] = trim(($parsed['message'] ?? 'Sent to Inter-Agency.') . ' (Sent without tip photo after partner API error.)');
            }
            return $parsed;
        }

        $lastFailure = $parsed;
    }

    return $lastFailure ?: ['success' => false, 'message' => 'Failed to request Inter-Agency assistance.'];
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

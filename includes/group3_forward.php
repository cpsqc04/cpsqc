<?php

/**
 * Forward BPSO tips to Group 3 Inter-agency Coordination Portal (police backup requests).
 *
 * Configure in .env:
 *   GROUP3_API_URL=
 *   GROUP3_API_KEY=
 *   GROUP3_API_TIMEOUT=30
 *
 * Outbound payload (JSON):
 *   {
 *     "source": "alertaraqc",
 *     "request_type": "police_backup",
 *     "source_tip_id": "TIP-2026-002",
 *     "requesting_agency": "BPSO - Quezon City",
 *     "incident": { "location", "description", "submitted_at" },
 *     "review": { "status" },
 *     "backup": { "reason", "priority": "high", "units_requested": "patrol" },
 *     "metadata": { "internal_id", "forwarded_by", "forwarded_at" }
 *   }
 *
 * Expected response:
 *   { "success": true, "coordination_reference_id": "COORD-2026-000001", "message": "..." }
 */

function getGroup3ApiConfig(): array
{
    return [
        'url' => trim($_ENV['GROUP3_API_URL'] ?? ''),
        'api_key' => trim($_ENV['GROUP3_API_KEY'] ?? ''),
        'timeout' => max(5, (int) ($_ENV['GROUP3_API_TIMEOUT'] ?? 30)),
    ];
}

function buildGroup3BackupPayload(array $tip, string $backupReason = ''): array
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

function forwardTipToGroup3(array $tip, string $backupReason = ''): array
{
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL extension is required to request police backup.'];
    }

    $config = getGroup3ApiConfig();
    if ($config['url'] === '') {
        return [
            'success' => false,
            'message' => 'Inter-agency Coordination API is not configured. Set GROUP3_API_URL in .env.',
        ];
    }

    $payload = json_encode(buildGroup3BackupPayload($tip, $backupReason), JSON_UNESCAPED_UNICODE);
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
            'message' => 'Failed to reach Inter-agency Coordination API: ' . ($curlError ?: 'Unknown error'),
        ];
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'message' => 'Inter-agency Coordination API returned an invalid response (HTTP ' . $httpCode . ').',
            'http_code' => $httpCode,
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = trim($decoded['message'] ?? $decoded['error'] ?? 'Inter-agency Coordination API request failed.');
        return [
            'success' => false,
            'message' => $message . ' (HTTP ' . $httpCode . ')',
            'http_code' => $httpCode,
        ];
    }

    if (empty($decoded['success'])) {
        return [
            'success' => false,
            'message' => trim($decoded['message'] ?? $decoded['error'] ?? 'Inter-agency Coordination API rejected the request.'),
            'http_code' => $httpCode,
        ];
    }

    $referenceId = trim(
        (string) ($decoded['coordination_reference_id'] ?? $decoded['reference_id'] ?? $decoded['id'] ?? '')
    );

    return [
        'success' => true,
        'message' => trim($decoded['message'] ?? 'Police backup request sent to Inter-agency Coordination Portal.'),
        'group3_reference_id' => $referenceId,
        'http_code' => $httpCode,
    ];
}

<?php

/**
 * Forward BPSO tips to Group 1 Incident Logging and Classification module.
 *
 * Configure in .env:
 *   TIP_BLOTTER_API_URL=   (optional; falls back to BLOTTER_API_URL)
 *   BLOTTER_API_URL=
 *   BLOTTER_API_KEY=
 *   BLOTTER_API_TIMEOUT=30
 *
 * Outbound payload (JSON):
 *   {
 *     "source": "alertaraqc",
 *     "record_type": "tip",
 *     "source_tip_id": "TIP-2026-002",
 *     "incident": { "location", "description", "submitted_at" },
 *     "review": { "status", "outcome" },
 *     "reporter": { "contact_number" },
 *     "metadata": { "internal_id", "forwarded_by", "forwarded_at" },
 *     "has_photo": true
 *   }
 *
 * Expected response:
 *   { "success": true, "blotter_reference_id": "INC-2026-000001", "message": "..." }
 */

function getTipBlotterApiConfig(): array
{
    $url = trim($_ENV['TIP_BLOTTER_API_URL'] ?? '');
    if ($url === '') {
        $url = trim($_ENV['BLOTTER_API_URL'] ?? '');
    }

    return [
        'url' => $url,
        'api_key' => trim($_ENV['BLOTTER_API_KEY'] ?? ''),
        'timeout' => max(5, (int) ($_ENV['BLOTTER_API_TIMEOUT'] ?? 30)),
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

    return [
        'source' => 'alertaraqc',
        'record_type' => 'tip',
        'source_tip_id' => $tip['tip_id'] ?? '',
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
            'status' => $tip['status'] ?? 'Under Review',
            'outcome' => $tip['outcome'] ?? 'No Outcome Yet',
        ],
        'has_photo' => !empty($tip['photo_data']),
        'metadata' => [
            'internal_id' => (int) ($tip['id'] ?? 0),
            'forwarded_by' => 'alertaraqc_bpso_admin',
            'forwarded_at' => date('c'),
        ],
    ];
}

function forwardTipToGroup1(array $tip): array
{
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL extension is required to forward tips.'];
    }

    $config = getTipBlotterApiConfig();
    if ($config['url'] === '') {
        return [
            'success' => false,
            'message' => 'Incident Logging API is not configured. Set TIP_BLOTTER_API_URL or BLOTTER_API_URL in .env.',
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
            'message' => 'Failed to reach Incident Logging API: ' . ($curlError ?: 'Unknown error'),
        ];
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'message' => 'Incident Logging API returned an invalid response (HTTP ' . $httpCode . ').',
            'http_code' => $httpCode,
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = trim($decoded['message'] ?? $decoded['error'] ?? 'Incident Logging API request failed.');
        return [
            'success' => false,
            'message' => $message . ' (HTTP ' . $httpCode . ')',
            'http_code' => $httpCode,
        ];
    }

    if (empty($decoded['success'])) {
        return [
            'success' => false,
            'message' => trim($decoded['message'] ?? $decoded['error'] ?? 'Incident Logging API rejected the tip.'),
            'http_code' => $httpCode,
        ];
    }

    $referenceId = trim(
        (string) ($decoded['blotter_reference_id'] ?? $decoded['incident_reference_id'] ?? $decoded['reference_id'] ?? $decoded['id'] ?? '')
    );

    return [
        'success' => true,
        'message' => trim($decoded['message'] ?? 'Tip forwarded to Incident Logging and Classification.'),
        'blotter_reference_id' => $referenceId,
        'http_code' => $httpCode,
    ];
}

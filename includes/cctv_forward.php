<?php

/**
 * Forward CCTV evidence packages to Group 1 Incident Logging and Classification.
 *
 * Configure in .env:
 *   CCTV_EVIDENCE_API_URL=   (optional; falls back to BLOTTER_API_URL)
 *   BLOTTER_API_URL=
 *   BLOTTER_API_KEY=
 *   BLOTTER_API_TIMEOUT=30
 *
 * Outbound payload (JSON):
 *   {
 *     "source": "alertaraqc",
 *     "record_type": "cctv_evidence",
 *     "source_request_id": "CCTV-REQ-2026-001",
 *     "request": { ... request metadata ... },
 *     "footage": { "segment_count", "total_size_bytes", "segments": [ ... ] },
 *     "metadata": { "internal_id", "forwarded_by", "forwarded_at" }
 *   }
 *
 * Expected response:
 *   { "success": true, "evidence_reference_id": "EVD-2026-000001", "message": "..." }
 */

require_once __DIR__ . '/../api/recordings_helpers.php';

function alertaraBaseUrl(): string
{
    $env = trim($_ENV['APP_BASE_URL'] ?? '');
    if ($env !== '') {
        return rtrim($env, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api'));
    $projectRoot = dirname($scriptDir);

    return rtrim($scheme . '://' . $host . ($projectRoot === '/' ? '' : $projectRoot), '/');
}

function getCctvEvidenceApiConfig(): array
{
    $url = trim($_ENV['CCTV_EVIDENCE_API_URL'] ?? '');
    if ($url === '') {
        $url = trim($_ENV['BLOTTER_API_URL'] ?? '');
    }

    return [
        'url' => $url,
        'api_key' => trim($_ENV['BLOTTER_API_KEY'] ?? ''),
        'timeout' => max(5, (int) ($_ENV['BLOTTER_API_TIMEOUT'] ?? 30)),
    ];
}

function normalizeCctvTimeValue(?string $time): string
{
    $time = trim((string) $time);
    if ($time === '') {
        return '';
    }

    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        return $time . ':00';
    }

    return substr($time, 0, 8);
}

function getCctvRequestFootageWindow(array $request): array
{
    $date = trim((string) ($request['incident_date'] ?? ''));
    $requestedStart = normalizeCctvTimeValue($request['footage_start_time'] ?? '');
    $requestedEnd = normalizeCctvTimeValue($request['footage_end_time'] ?? '');
    $actualStart = normalizeCctvTimeValue($request['actual_footage_start'] ?? '');
    $actualEnd = normalizeCctvTimeValue($request['actual_footage_end'] ?? '');

    $searchStart = $actualStart !== '' ? $actualStart : $requestedStart;
    $searchEnd = $actualEnd !== '' ? $actualEnd : $requestedEnd;

    return [
        'date' => $date,
        'requested_start' => $requestedStart,
        'requested_end' => $requestedEnd,
        'actual_start' => $actualStart !== '' ? $actualStart : null,
        'actual_end' => $actualEnd !== '' ? $actualEnd : null,
        'search_start' => $searchStart,
        'search_end' => $searchEnd,
    ];
}

function findRecordingsForCctvRequest(array $request): array
{
    $window = getCctvRequestFootageWindow($request);
    if ($window['date'] === '' || $window['search_start'] === '' || $window['search_end'] === '') {
        return [];
    }

    return findRecordingSegmentsForWindow($window['date'], $window['search_start'], $window['search_end']);
}

function buildCctvEvidenceDownloadUrl(string $requestId, string $filename, string $apiKey): string
{
    $params = [
        'request_id' => $requestId,
        'file' => $filename,
    ];
    if ($apiKey !== '') {
        $params['api_key'] = $apiKey;
    }

    return alertaraBaseUrl() . '/api/cctv_evidence_download.php?' . http_build_query($params);
}

function buildCctvEvidencePayload(array $request, array $segments): array
{
    $config = getCctvEvidenceApiConfig();
    $window = getCctvRequestFootageWindow($request);
    $requestId = trim((string) ($request['request_id'] ?? ''));
    $totalSize = 0;
    $segmentPayload = [];

    foreach ($segments as $segment) {
        $sizeBytes = (int) ($segment['size_bytes'] ?? 0);
        $totalSize += $sizeBytes;
        $segmentPayload[] = [
            'filename' => $segment['filename'],
            'start_at' => $segment['start_at'],
            'end_at' => $segment['end_at'],
            'size_bytes' => $sizeBytes,
            'size_label' => $segment['size_label'] ?? formatBytes($sizeBytes),
            'playable' => (bool) ($segment['playable'] ?? false),
            'status' => $segment['status'] ?? 'ready',
            'download_url' => buildCctvEvidenceDownloadUrl($requestId, $segment['filename'], $config['api_key']),
        ];
    }

    return [
        'source' => 'alertaraqc',
        'record_type' => 'cctv_evidence',
        'source_request_id' => $requestId,
        'request' => [
            'requesting_agency' => $request['requesting_agency'] ?? '',
            'contact_person' => $request['contact_person'] ?? '',
            'contact_number' => $request['contact_number'] ?? '',
            'case_reference' => $request['case_reference'] ?? null,
            'related_complaint_id' => $request['related_complaint_id'] ?? null,
            'purpose' => $request['purpose'] ?? '',
            'purpose_details' => $request['purpose_details'] ?? '',
            'legal_basis' => $request['legal_basis'] ?? '',
            'incident_location' => $request['incident_location'] ?? '',
            'camera_id' => $request['approved_camera_id'] ?? $request['camera_id'] ?? null,
            'incident_date' => $window['date'],
            'incident_type' => $request['incident_type'] ?? null,
            'incident_description' => $request['incident_description'] ?? '',
            'delivery_method' => $request['delivery_method'] ?? 'secure_download',
            'footage_window' => [
                'requested_start' => $window['requested_start'],
                'requested_end' => $window['requested_end'],
                'actual_start' => $window['actual_start'],
                'actual_end' => $window['actual_end'],
            ],
        ],
        'footage' => [
            'segment_count' => count($segmentPayload),
            'total_size_bytes' => $totalSize,
            'total_size_label' => formatBytes($totalSize),
            'segments' => $segmentPayload,
        ],
        'metadata' => [
            'internal_id' => (int) ($request['id'] ?? 0),
            'forwarded_by' => 'alertaraqc_admin',
            'forwarded_at' => date('c'),
        ],
    ];
}

function forwardCctvEvidenceToGroup1(array $request): array
{
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL extension is required to forward CCTV evidence.'];
    }

    $config = getCctvEvidenceApiConfig();
    if ($config['url'] === '') {
        return [
            'success' => false,
            'message' => 'CCTV Evidence API is not configured. Set CCTV_EVIDENCE_API_URL or BLOTTER_API_URL in .env.',
        ];
    }

    $segments = findRecordingsForCctvRequest($request);
    if ($segments === []) {
        return [
            'success' => false,
            'message' => 'No matching CCTV recordings were found for this request time window.',
        ];
    }

    $payload = json_encode(buildCctvEvidencePayload($request, $segments), JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return ['success' => false, 'message' => 'Failed to encode CCTV evidence payload.'];
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
            'message' => 'Failed to reach CCTV Evidence API: ' . ($curlError ?: 'Unknown error'),
        ];
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'message' => 'CCTV Evidence API returned an invalid response (HTTP ' . $httpCode . ').',
            'http_code' => $httpCode,
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = trim($decoded['message'] ?? $decoded['error'] ?? 'CCTV Evidence API request failed.');
        return [
            'success' => false,
            'message' => $message . ' (HTTP ' . $httpCode . ')',
            'http_code' => $httpCode,
        ];
    }

    if (empty($decoded['success'])) {
        return [
            'success' => false,
            'message' => trim($decoded['message'] ?? $decoded['error'] ?? 'CCTV Evidence API rejected the package.'),
            'http_code' => $httpCode,
        ];
    }

    $referenceId = trim(
        (string) ($decoded['evidence_reference_id'] ?? $decoded['blotter_reference_id'] ?? $decoded['reference_id'] ?? $decoded['id'] ?? '')
    );

    return [
        'success' => true,
        'message' => trim($decoded['message'] ?? 'CCTV evidence forwarded to Group 1.'),
        'evidence_reference_id' => $referenceId,
        'segments' => $segments,
        'http_code' => $httpCode,
    ];
}

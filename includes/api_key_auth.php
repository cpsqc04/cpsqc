<?php

/**
 * Validate inbound partner API keys from .env.
 * Preferred partner names:
 *   incident-reporting  (legacy: Group 1 / BLOTTER_*)
 *   emergency-response  (legacy: Group 3 / GROUP3_*)
 *   crime-analytics     (legacy: Group 5 / GROUP5_*)
 */

function getPartnerApiKeyFromRequest(bool $allowQueryString = false): string
{
    $providedKey = '';
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        $providedKey = $matches[1];
    }
    if ($providedKey === '') {
        $providedKey = trim($_SERVER['HTTP_X_API_KEY'] ?? '');
    }
    if ($providedKey === '' && $allowQueryString && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        $providedKey = trim($_GET['api_key'] ?? '');
    }

    return $providedKey;
}

/**
 * Return the first non-empty env value from a list of candidate key names.
 */
function envFirst(string ...$keys): string
{
    foreach ($keys as $key) {
        $value = trim($_ENV[$key] ?? '');
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

/**
 * First configured env key name from candidates (for error messages).
 */
function envFirstKeyName(string ...$keys): string
{
    foreach ($keys as $key) {
        if (trim($_ENV[$key] ?? '') !== '') {
            return $key;
        }
    }

    return $keys[0] ?? '';
}

function partnerEnvKeyCandidates(string $partner): array
{
    $partner = strtolower(trim($partner));
    return match ($partner) {
        'incident_reporting', 'incident-reporting', 'group1', 'group_1', 'blotter' => [
            'INCIDENT_REPORTING_API_KEY',
            'BLOTTER_API_KEY',
        ],
        'emergency_response', 'emergency-response', 'group3', 'group_3' => [
            'EMERGENCY_RESPONSE_API_KEY',
            'GROUP3_API_KEY',
        ],
        'crime_analytics', 'crime-analytics', 'group5', 'group_5' => [
            'CRIME_ANALYTICS_API_KEY',
            'GROUP5_API_KEY',
        ],
        default => [$partner],
    };
}

function getIncidentReportingApiConfig(): array
{
    return [
        'url' => envFirst('INCIDENT_REPORTING_API_URL', 'BLOTTER_API_URL'),
        'tip_url' => envFirst(
            'INCIDENT_REPORTING_TIP_API_URL',
            'TIP_BLOTTER_API_URL',
            'INCIDENT_REPORTING_API_URL',
            'BLOTTER_API_URL'
        ),
        'cctv_evidence_url' => envFirst(
            'CCTV_EVIDENCE_API_URL',
            'INCIDENT_REPORTING_API_URL',
            'BLOTTER_API_URL'
        ),
        'api_key' => envFirst('INCIDENT_REPORTING_API_KEY', 'BLOTTER_API_KEY'),
        'timeout' => max(5, (int) envFirst('INCIDENT_REPORTING_API_TIMEOUT', 'BLOTTER_API_TIMEOUT', '30')),
    ];
}

function getEmergencyResponseApiConfig(): array
{
    return [
        'url' => envFirst('EMERGENCY_RESPONSE_API_URL', 'GROUP3_API_URL'),
        'api_key' => envFirst('EMERGENCY_RESPONSE_API_KEY', 'GROUP3_API_KEY'),
        'timeout' => max(5, (int) envFirst('EMERGENCY_RESPONSE_API_TIMEOUT', 'GROUP3_API_TIMEOUT', '30')),
    ];
}

function getCrimeAnalyticsApiConfig(): array
{
    return [
        'api_key' => envFirst('CRIME_ANALYTICS_API_KEY', 'GROUP5_API_KEY'),
    ];
}

function validatePartnerApiKey(string|array $envKeyName, bool $allowQueryString = false): bool
{
    $candidates = is_array($envKeyName) ? $envKeyName : [$envKeyName];
    $expectedKey = envFirst(...$candidates);
    if ($expectedKey === '') {
        return false;
    }

    $providedKey = getPartnerApiKeyFromRequest($allowQueryString);

    return $providedKey !== '' && hash_equals($expectedKey, $providedKey);
}

function requirePartnerApiKey(string|array $envKeyName, string $serviceLabel, bool $allowQueryString = false): void
{
    $candidates = is_array($envKeyName) ? $envKeyName : [$envKeyName];
    $expectedKey = envFirst(...$candidates);
    $preferredName = $candidates[0] ?? 'API_KEY';

    if ($expectedKey === '') {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => "{$serviceLabel} API is not configured. Set {$preferredName} in .env.",
        ]);
        exit;
    }

    if (!validatePartnerApiKey($candidates, $allowQueryString)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing API key.']);
        exit;
    }
}

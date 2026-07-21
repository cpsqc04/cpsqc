<?php

/**
 * Validate inbound partner API keys from .env.
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

function validatePartnerApiKey(string $envKeyName, bool $allowQueryString = false): bool
{
    $expectedKey = trim($_ENV[$envKeyName] ?? '');
    if ($expectedKey === '') {
        return false;
    }

    $providedKey = getPartnerApiKeyFromRequest($allowQueryString);

    return $providedKey !== '' && hash_equals($expectedKey, $providedKey);
}

function requirePartnerApiKey(string $envKeyName, string $serviceLabel): void
{
    $expectedKey = trim($_ENV[$envKeyName] ?? '');
    if ($expectedKey === '') {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => "{$serviceLabel} API is not configured. Set {$envKeyName} in .env.",
        ]);
        exit;
    }

    if (!validatePartnerApiKey($envKeyName)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing API key.']);
        exit;
    }
}

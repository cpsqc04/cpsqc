<?php

/**
 * Send a JSON response and exit.
 */
function jsonResponse(array $data, int $statusCode = 200, ?bool $pretty = null): void
{
    http_response_code($statusCode);

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if ($pretty ?? wantsPrettyJson()) {
        $flags |= JSON_PRETTY_PRINT;
    }

    echo json_encode($data, $flags);
    exit;
}

/**
 * Pretty-print JSON only when explicitly requested.
 * Pass ?pretty=1 for server-side formatting; otherwise use the browser Pretty-print checkbox.
 */
function wantsPrettyJson(): bool
{
    if (!isset($_GET['pretty'])) {
        return false;
    }

    return $_GET['pretty'] === '1' || $_GET['pretty'] === 'true';
}

/**
 * Allow admin session or a configured partner API key.
 */
function canAccessPartnerList(bool $isAdmin, string $envKeyName): bool
{
    if ($isAdmin) {
        return true;
    }

    return validatePartnerApiKey($envKeyName, true);
}

/**
 * Deny list access with the correct HTTP status for admin vs partner callers.
 */
function denyPartnerListAccess(bool $isAdmin, string $envKeyName, string $serviceLabel): void
{
    if ($isAdmin) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $expectedKey = trim($_ENV[$envKeyName] ?? '');
    if ($expectedKey === '') {
        jsonResponse([
            'success' => false,
            'message' => "{$serviceLabel} API is not configured. Set {$envKeyName} in .env.",
        ], 503);
    }

    jsonResponse(['success' => false, 'message' => 'Invalid or missing API key.'], 401);
}

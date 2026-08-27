<?php

/**
 * Forward youth / sports / cultural campaign recommendations to Campaign partner.
 *
 * Configure in .env:
 *   CAMPAIGN_RECOMMENDATION_API_URL=
 *   CAMPAIGN_API_KEY=              (optional)
 *   CAMPAIGN_API_TIMEOUT=30
 *
 * Legacy aliases: GROUP6_API_URL, GROUP6_API_KEY, GROUP6_API_TIMEOUT
 */

require_once __DIR__ . '/api_key_auth.php';

function getCampaignApiConfig(): array
{
    return [
        'recommendation_url' => envFirst(
            'CAMPAIGN_RECOMMENDATION_API_URL',
            'CAMPAIGN_API_URL',
            'GROUP6_API_URL'
        ),
        'api_key' => envFirst('CAMPAIGN_API_KEY', 'GROUP6_API_KEY'),
        'timeout' => max(5, (int) (envFirst('CAMPAIGN_API_TIMEOUT', 'GROUP6_API_TIMEOUT') ?: '30')),
    ];
}

/**
 * Heuristic: text suggests youth / curfew / loitering.
 */
function patrolLogLooksYouthRelated(array $log): bool
{
    $haystack = strtolower(trim(implode(' ', [
        (string) ($log['incidents'] ?? ''),
        (string) ($log['details'] ?? ''),
        (string) ($log['location'] ?? ''),
        (string) ($log['route'] ?? ''),
    ])));

    if ($haystack === '') {
        return false;
    }

    $keywords = [
        'youth', 'minor', 'minors', 'loiter', 'loitering', 'curfew',
        '17 below', 'under 18', 'underage', 'bata', 'kabataan', 'estudyante',
        'student', 'teenager', 'teen', 'sk ', 'sangguniang kabataan',
    ];

    foreach ($keywords as $keyword) {
        if (str_contains($haystack, $keyword)) {
            return true;
        }
    }

    return false;
}

/**
 * True when patrol time falls in ordinance window 10:00 PM – 8:00 AM.
 */
function patrolLogInCurfewWindow(array $log): bool
{
    $timeRaw = trim((string) ($log['time'] ?? ''));
    if ($timeRaw === '') {
        return false;
    }

    $normalized = preg_replace('/\s+/', ' ', $timeRaw);
    $formats = ['H:i:s', 'H:i', 'g:i A', 'g:iA', 'h:i A', 'h:iA'];
    $dt = false;
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $normalized);
        if ($dt instanceof DateTime) {
            break;
        }
    }
    if (!$dt instanceof DateTime) {
        return false;
    }

    $minutes = ((int) $dt->format('H')) * 60 + (int) $dt->format('i');
    // 22:00 (1320) through 23:59, or 00:00 through 07:59 (479)
    return $minutes >= (22 * 60) || $minutes < (8 * 60);
}

function buildCampaignRecommendationPayload(array $options): array
{
    $themes = $options['themes'] ?? ['youth', 'sports', 'cultural'];
    $themes = array_values(array_unique(array_filter(array_map(static function ($t) {
        return strtolower(trim((string) $t));
    }, is_array($themes) ? $themes : []))));

    $allowedThemes = ['youth', 'sports', 'cultural'];
    $themes = array_values(array_intersect($themes, $allowedThemes));
    if ($themes === []) {
        $themes = ['youth', 'sports', 'cultural'];
    }

    $logs = $options['patrol_logs'] ?? [];
    $bulletin = $options['bulletin'] ?? null;
    $title = trim((string) ($options['title'] ?? ''));
    $rationale = trim((string) ($options['rationale'] ?? ''));
    $priority = strtolower(trim((string) ($options['priority'] ?? 'medium')));
    if (!in_array($priority, ['low', 'medium', 'high'], true)) {
        $priority = 'medium';
    }

    $reportPayloads = [];
    $zones = [];
    $youthCount = 0;
    $curfewCount = 0;

    foreach ($logs as $log) {
        $youthRelated = patrolLogLooksYouthRelated($log);
        $inCurfew = patrolLogInCurfewWindow($log);
        if ($youthRelated) {
            $youthCount++;
        }
        if ($inCurfew) {
            $curfewCount++;
        }

        $location = trim((string) ($log['location'] ?? ''));
        $route = trim((string) ($log['route'] ?? ''));
        if ($location !== '') {
            $zones[$location] = true;
        } elseif ($route !== '') {
            $zones[$route] = true;
        }

        $hasPhoto = !empty($log['documentation_photo']);
        $reportPayloads[] = [
            'patrol_log_id' => (int) ($log['id'] ?? 0),
            'date' => $log['date'] ?? null,
            'time' => $log['time'] ?? null,
            'personnel_name' => $log['personnel_name'] ?? '',
            'route' => $route,
            'location' => $location !== '' ? $location : $route,
            'incidents' => $log['incidents'] ?? '',
            'details' => $log['details'] ?? '',
            'status' => $log['status'] ?? '',
            'youth_related' => $youthRelated,
            'in_curfew_window' => $inCurfew,
            'has_photo' => $hasPhoto,
        ];
    }

    $targetZones = array_keys($zones);
    if ($title === '') {
        $title = 'Youth Sports & Cultural Development Recommendation';
    }

    if ($rationale === '') {
        $parts = [];
        $parts[] = 'Barangay San Agustin enforces a youth curfew ordinance: no youth aged 17 and below should be loitering outside from 10:00 PM to 8:00 AM.';
        $parts[] = 'Night-shift patrol submitted ' . count($reportPayloads) . ' linked report(s)';
        if ($youthCount > 0) {
            $parts[count($parts) - 1] .= ' (' . $youthCount . ' youth/loitering-related)';
        }
        $parts[count($parts) - 1] .= '.';
        if ($curfewCount > 0) {
            $parts[] = $curfewCount . ' report(s) fall within the curfew window.';
        }
        $parts[] = 'Request a positive engagement campaign focused on youth, sports, and cultural development for the listed target zones.';
        $rationale = implode(' ', $parts);
    }

    $policyBasis = null;
    if (is_array($bulletin) && !empty($bulletin)) {
        $policyBasis = [
            'type' => 'bulletin_ordinance',
            'bulletin_post_id' => (int) ($bulletin['id'] ?? 0),
            'title' => $bulletin['title'] ?? '',
            'body' => $bulletin['body'] ?? '',
            'summary' => 'No youth aged 17 and below should be loitering outside from 10:00 PM to 8:00 AM.',
            'curfew_start' => '22:00',
            'curfew_end' => '08:00',
            'max_age' => 17,
        ];
    } else {
        $policyBasis = [
            'type' => 'ordinance_summary',
            'summary' => 'No youth aged 17 and below should be loitering outside from 10:00 PM to 8:00 AM.',
            'curfew_start' => '22:00',
            'curfew_end' => '08:00',
            'max_age' => 17,
            'source' => 'digital_bulletin',
        ];
    }

    return [
        'request_type' => 'campaign_recommendation',
        'source' => 'alertaraqc',
        'source_group' => 'campaign',
        'themes' => $themes,
        'title' => $title,
        'barangay' => 'San Agustin',
        'target_zones' => $targetZones,
        'priority' => $priority,
        'rationale' => $rationale,
        'suggested_objectives' => [
            'Organize weekend sports leagues and recreation for ages 13–17',
            'Host cultural / talent showcases with Sangguniang Kabataan partners',
            'Pair youth engagement events with curfew and street-safety awareness',
        ],
        'policy_basis' => $policyBasis,
        'patrol_reports' => $reportPayloads,
        'stats' => [
            'patrol_report_count' => count($reportPayloads),
            'youth_related_count' => $youthCount,
            'curfew_window_count' => $curfewCount,
        ],
        'contact' => [
            'office' => 'BPSO / AlertaraQC Admin',
            'system' => 'AlertaraQC Community Policing',
        ],
        'metadata' => [
            'forwarded_by' => 'alertaraqc_bpso_admin',
            'forwarded_at' => date('c'),
        ],
    ];
}

function forwardCampaignRecommendation(array $payload): array
{
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL extension is required to forward campaign recommendations.'];
    }

    $config = getCampaignApiConfig();
    if ($config['recommendation_url'] === '') {
        return [
            'success' => false,
            'message' => 'Campaign recommendation API is not configured. Set CAMPAIGN_RECOMMENDATION_API_URL in .env.',
        ];
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return ['success' => false, 'message' => 'Failed to encode campaign recommendation payload.'];
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($config['api_key'] !== '') {
        $headers[] = 'X-API-Key: ' . $config['api_key'];
        $headers[] = 'Authorization: Bearer ' . $config['api_key'];
    }

    $ch = curl_init($config['recommendation_url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
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
            'message' => 'Failed to reach Campaign API: ' . ($curlError ?: 'Unknown error'),
        ];
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'message' => 'Campaign API returned an invalid response (HTTP ' . $httpCode . ').',
            'http_code' => $httpCode,
            'raw' => substr((string) $responseBody, 0, 500),
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = trim($decoded['message'] ?? $decoded['error'] ?? 'Campaign API request failed.');
        return [
            'success' => false,
            'message' => $message . ' (HTTP ' . $httpCode . ')',
            'http_code' => $httpCode,
        ];
    }

    if (array_key_exists('success', $decoded) && empty($decoded['success'])) {
        return [
            'success' => false,
            'message' => trim($decoded['message'] ?? $decoded['error'] ?? 'Campaign API rejected the recommendation.'),
            'http_code' => $httpCode,
        ];
    }

    $referenceId = trim((string) (
        $decoded['campaign_reference_id']
        ?? $decoded['reference_id']
        ?? $decoded['recommendation_id']
        ?? $decoded['id']
        ?? ''
    ));

    return [
        'success' => true,
        'message' => trim($decoded['message'] ?? 'Campaign recommendation forwarded.'),
        'campaign_reference_id' => $referenceId,
        'http_code' => $httpCode,
        'partner_response' => $decoded,
    ];
}

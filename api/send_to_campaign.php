<?php

/**
 * Admin endpoint: forward selected night-shift / youth patrol reports to Campaign
 * as a youth–sports–cultural campaign recommendation.
 *
 * POST JSON:
 * {
 *   "patrol_log_ids": [1, 2, 3],
 *   "themes": ["youth", "sports", "cultural"],
 *   "bulletin_post_id": 12,
 *   "title": optional,
 *   "rationale": optional,
 *   "priority": "medium"
 * }
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/patrol_logs_schema.php';
require_once __DIR__ . '/bulletin_board_schema.php';
require_once __DIR__ . '/../includes/campaign_forward.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$rawIds = $input['patrol_log_ids'] ?? $input['ids'] ?? [];
if (!is_array($rawIds)) {
    $rawIds = [];
}
$ids = array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn ($id) => $id > 0)));

if ($ids === []) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Select at least one patrol report to forward.']);
    exit;
}

$themes = $input['themes'] ?? ['youth', 'sports', 'cultural'];
$bulletinPostId = (int) ($input['bulletin_post_id'] ?? 0);
$title = trim((string) ($input['title'] ?? ''));
$rationale = trim((string) ($input['rationale'] ?? ''));
$priority = trim((string) ($input['priority'] ?? 'medium'));

try {
    ensurePatrolLogsTable($pdo);
    ensureBulletinBoardTable($pdo);

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, patrol_id, schedule_id, personnel_name, route, date, time, status, incidents, details, location,
                documentation_photo, campaign_forwarded_at, campaign_reference_id, created_at
         FROM patrol_logs
         WHERE id IN ({$placeholders})
         ORDER BY date DESC, id DESC"
    );
    $stmt->execute($ids);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($logs) !== count($ids)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'One or more selected patrol reports were not found.']);
        exit;
    }

    $alreadyForwarded = [];
    foreach ($logs as $log) {
        if (!empty($log['campaign_forwarded_at'])) {
            $alreadyForwarded[] = (int) $log['id'];
        }
    }
    if ($alreadyForwarded !== []) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Some selected reports were already forwarded to Campaign: #' . implode(', #', $alreadyForwarded),
            'data' => ['already_forwarded_ids' => $alreadyForwarded],
        ]);
        exit;
    }

    $bulletin = null;
    if ($bulletinPostId > 0) {
        $bStmt = $pdo->prepare('SELECT id, title, body, status FROM bulletin_posts WHERE id = :id LIMIT 1');
        $bStmt->execute([':id' => $bulletinPostId]);
        $bulletin = $bStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$bulletin) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Selected bulletin ordinance post was not found.']);
            exit;
        }
    }

    $payload = buildCampaignRecommendationPayload([
        'patrol_logs' => $logs,
        'bulletin' => $bulletin,
        'themes' => $themes,
        'title' => $title,
        'rationale' => $rationale,
        'priority' => $priority,
    ]);

    $result = forwardCampaignRecommendation($payload);
    if (!$result['success']) {
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to forward recommendation to Campaign.',
        ]);
        exit;
    }

    $timestamp = date('Y-m-d H:i:s');
    $referenceId = trim((string) ($result['campaign_reference_id'] ?? ''));
    $update = $pdo->prepare(
        'UPDATE patrol_logs
         SET campaign_forwarded_at = :forwarded_at, campaign_reference_id = :reference_id
         WHERE id = :id'
    );
    foreach ($logs as $log) {
        $update->execute([
            ':forwarded_at' => $timestamp,
            ':reference_id' => $referenceId !== '' ? $referenceId : null,
            ':id' => (int) $log['id'],
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => $result['message'] ?? 'Campaign recommendation forwarded.',
        'data' => [
            'campaign_reference_id' => $referenceId,
            'forwarded_at' => $timestamp,
            'patrol_log_ids' => array_map(static fn ($log) => (int) $log['id'], $logs),
            'themes' => $payload['themes'],
            'target_zones' => $payload['target_zones'],
            'stats' => $payload['stats'],
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to forward to Campaign: ' . $e->getMessage()]);
}

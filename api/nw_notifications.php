<?php
/**
 * Neighborhood Watch member notifications API.
 * GET  ?action=list|sync
 * POST ?action=mark_read  (id optional = mark all)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/neighborhood-watcher-member-auth.php';
require_once __DIR__ . '/notifications_schema.php';

nwMemberSessionStart();

if (!($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database unavailable']);
    exit;
}

if (!isNwMemberLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$nwMemberId = getNwMemberId();
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function nwNotifTimeAgo($datetime): string
{
    if (empty($datetime)) {
        return '';
    }
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '';
    }
    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $mins = (int) floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    }
    if ($diff < 604800) {
        $days = (int) floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
    return date('M j, Y', $timestamp);
}

try {
    ensureNotificationsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare notifications.']);
    exit;
}

try {
    if ($action === 'sync' && $method === 'GET') {
        $synced = syncBulletinNotificationsForNwMember($pdo, (int) $nwMemberId);
        echo json_encode(['success' => true, 'synced' => $synced]);
        exit;
    }

    if ($action === 'list' && $method === 'GET') {
        $limit = (int) ($_GET['limit'] ?? 20);
        $offset = (int) ($_GET['offset'] ?? 0);
        if ($limit < 1) {
            $limit = 20;
        }
        if ($limit > 50) {
            $limit = 50;
        }
        if ($offset < 0) {
            $offset = 0;
        }
        $fetchLimit = $limit + 1;

        $stmt = $pdo->prepare("SELECT id, type, title, message, link, is_read, created_at
            FROM notifications
            WHERE nw_member_id = :nw_member_id
            ORDER BY created_at DESC, id DESC
            LIMIT {$fetchLimit} OFFSET {$offset}");
        $stmt->execute([':nw_member_id' => $nwMemberId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasMore = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);

        $notifications = [];
        foreach ($rows as $row) {
            $notifications[] = [
                'id' => (int) $row['id'],
                'type' => $row['type'],
                'title' => $row['title'],
                'message' => $row['message'],
                'link' => $row['link'],
                'is_read' => !empty($row['is_read']),
                'time_ago' => nwNotifTimeAgo($row['created_at']),
                'created_at' => $row['created_at'],
            ];
        }

        $unreadStmt = $pdo->prepare('SELECT COUNT(*) AS count FROM notifications WHERE nw_member_id = :nw_member_id AND is_read = 0');
        $unreadStmt->execute([':nw_member_id' => $nwMemberId]);
        $unread = (int) ($unreadStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

        echo json_encode([
            'success' => true,
            'unread_count' => $unread,
            'notifications' => $notifications,
            'has_more' => $hasMore,
            'offset' => $offset,
            'limit' => $limit,
        ]);
        exit;
    }

    if ($action === 'mark_read' && $method === 'POST') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND nw_member_id = :nw_member_id');
            $stmt->execute([':id' => $id, ':nw_member_id' => $nwMemberId]);
        } else {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE nw_member_id = :nw_member_id AND is_read = 0');
            $stmt->execute([':nw_member_id' => $nwMemberId]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

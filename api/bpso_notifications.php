<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bpso_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/patrol_schedules_schema.php';
require_once __DIR__ . '/complaints_schema.php';
require_once __DIR__ . '/nw_incidents_schema.php';
require_once __DIR__ . '/notifications_schema.php';

if (!function_exists('getTimeAgo')) {
    function getTimeAgo($datetime)
    {
        if (empty($datetime)) {
            return 'Unknown';
        }
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return 'Invalid date';
        }
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        }
        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        }
        if ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }

        return date('M j, Y', $timestamp);
    }
}

bpsoSessionStart();

if (!($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database unavailable']);
    exit;
}

if (!isBpsoLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$patrolId = getBpsoPatrolId();
$action = $_GET['action'] ?? 'list';

try {
    ensurePatrolSchedulesTable($pdo);
    ensureComplaintsTable($pdo);
    ensureNwIncidentReportsTable($pdo);
    ensureNotificationsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare notifications.']);
    exit;
}

if ($action === 'sync') {
    $synced = 0;

    try {
        $stmt = $pdo->prepare("
            SELECT id, route, schedule_date, schedule_time, created_at
            FROM patrol_schedules
            WHERE patrol_id = :patrol_id
              AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC
        ");
        $stmt->execute([':patrol_id' => $patrolId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $link = 'tab:schedule:' . $row['id'];
            if (createPatrolNotification(
                $pdo,
                $patrolId,
                'patrol_schedule',
                'New Patrol Assignment',
                'Patrol on ' . $row['schedule_date'] . ' at ' . $row['schedule_time'] . ' — ' . $row['route'],
                $link,
                $row['created_at']
            )) {
                $synced++;
            }
        }
    } catch (PDOException $e) {
        // continue
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, complaint_id, complaint_type, priority, assigned_at, created_at
            FROM complaints
            WHERE assigned_patrol_id = :patrol_id
              AND assigned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY assigned_at DESC
        ");
        $stmt->execute([':patrol_id' => $patrolId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $link = 'tab:complaints:' . $row['id'];
            $assignedAt = $row['assigned_at'] ?: $row['created_at'];
            if (createPatrolNotification(
                $pdo,
                $patrolId,
                'complaint_assignment',
                'Complaint Assigned',
                'Complaint #' . $row['complaint_id'] . ' (' . $row['complaint_type'] . ')',
                $link,
                $assignedAt
            )) {
                $synced++;
            }
        }
    } catch (PDOException $e) {
        // continue
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, report_id, location, assigned_at, created_at
            FROM nw_incident_reports
            WHERE assigned_patrol_id = :patrol_id
              AND assigned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY assigned_at DESC
        ");
        $stmt->execute([':patrol_id' => $patrolId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $link = 'tab:nw-incidents:' . $row['id'];
            $assignedAt = $row['assigned_at'] ?: $row['created_at'];
            if (createPatrolNotification(
                $pdo,
                $patrolId,
                'nw_incident_assignment',
                'Neighborhood Watch Incident Assigned',
                $row['report_id'] . ' — ' . $row['location'],
                $link,
                $assignedAt
            )) {
                $synced++;
            }
        }
    } catch (PDOException $e) {
        // continue
    }

    echo json_encode(['success' => true, 'synced' => $synced]);
    exit;
}

if ($action === 'list') {
    try {
        $stmt = $pdo->prepare("
            SELECT id, type, title, message, link, is_read, created_at
            FROM notifications
            WHERE patrol_id = :patrol_id
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt->execute([':patrol_id' => $patrolId]);

        $notifications = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $notifications[] = [
                'id' => (int) $row['id'],
                'type' => $row['type'],
                'title' => $row['title'],
                'message' => $row['message'],
                'link' => $row['link'],
                'is_read' => (bool) $row['is_read'],
                'created_at' => $row['created_at'],
                'time_ago' => getTimeAgo($row['created_at']),
            ];
        }

        $unreadStmt = $pdo->prepare('SELECT COUNT(*) AS count FROM notifications WHERE patrol_id = :patrol_id AND is_read = 0');
        $unreadStmt->execute([':patrol_id' => $patrolId]);
        $unreadCount = (int) ($unreadStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load notifications.']);
    }
    exit;
}

if ($action === 'mark_read') {
    $notificationId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    try {
        if ($notificationId > 0) {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND patrol_id = :patrol_id');
            $stmt->execute([':id' => $notificationId, ':patrol_id' => $patrolId]);
        } else {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE patrol_id = :patrol_id AND is_read = 0');
            $stmt->execute([':patrol_id' => $patrolId]);
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update notifications.']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action.']);

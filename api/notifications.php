<?php
// Notifications API - Provides recent activities and unread count
header('Content-Type: application/json');
ob_start();

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../db.php';

// Ensure notifications table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        link VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Table might already exist, continue
}

$action = $_GET['action'] ?? 'list';
$userId = $_SESSION['user_id'] ?? null;

try {
    if ($action === 'list') {
        // Get user role
        $userRole = $_SESSION['user_role'] ?? 'User';
        $isAdmin = ($userRole === 'Admin');
        
        // Build query based on user role
        // Admins see: all notifications (user_id IS NULL) + their own notifications (user_id = userId)
        // Users see: only their own notifications (user_id = userId)
        if ($isAdmin) {
            $stmt = $pdo->prepare("
                SELECT id, type, title, message, link, is_read, created_at 
                FROM notifications 
                WHERE user_id IS NULL OR user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            $stmt->execute([':user_id' => $userId]);
            
            $unreadStmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE (user_id IS NULL OR user_id = :user_id) AND is_read = 0
            ");
            $unreadStmt->execute([':user_id' => $userId]);
        } else {
            // Regular users only see their own notifications
            $stmt = $pdo->prepare("
                SELECT id, type, title, message, link, is_read, created_at 
                FROM notifications 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            $stmt->execute([':user_id' => $userId]);
            
            $unreadStmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE user_id = :user_id AND is_read = 0
            ");
            $unreadStmt->execute([':user_id' => $userId]);
        }
        
        $notifications = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $notifications[] = [
                'id' => (int)$row['id'],
                'type' => $row['type'],
                'title' => $row['title'],
                'message' => $row['message'],
                'link' => $row['link'],
                'is_read' => (bool)$row['is_read'],
                'created_at' => $row['created_at'],
                'time_ago' => getTimeAgo($row['created_at'])
            ];
        }
        
        $unreadCount = (int)$unreadStmt->fetch()['count'];
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
        
    } elseif ($action === 'mark_read') {
        $notificationId = $_POST['id'] ?? null;
        $userRole = $_SESSION['user_role'] ?? 'User';
        $isAdmin = ($userRole === 'Admin');
        
        if ($notificationId) {
            // Users can only mark their own notifications as read
            // Admins can mark any notification as read
            if ($isAdmin) {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id");
                $stmt->execute([':id' => $notificationId]);
            } else {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
                $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);
            }
        } else {
            // Mark all as read - based on user role
            if ($isAdmin) {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE (user_id IS NULL OR user_id = :user_id) AND is_read = 0");
                $stmt->execute([':user_id' => $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0");
                $stmt->execute([':user_id' => $userId]);
            }
        }
        
        ob_clean();
        echo json_encode(['success' => true]);
        
    } elseif ($action === 'sync') {
        // Sync activities from various tables to notifications
        $synced = 0;
        
        // Sync recent complaints (last 7 days)
        $stmt = $pdo->query("
            SELECT complaint_id, complainant_name, complaint_type, location, submitted_at 
            FROM complaints 
            WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY submitted_at DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Check if notification already exists
            $checkStmt = $pdo->prepare("SELECT id FROM notifications WHERE type = 'complaint' AND link = :link LIMIT 1");
            $checkStmt->execute([':link' => 'track-complaint.php?id=' . $row['complaint_id']]);
            if (!$checkStmt->fetch()) {
                $insertStmt = $pdo->prepare("
                    INSERT INTO notifications (type, title, message, link, created_at) 
                    VALUES ('complaint', 'New Complaint Submitted', :message, :link, :created_at)
                ");
                $insertStmt->execute([
                    ':message' => 'Complaint #' . $row['complaint_id'] . ' - ' . ($row['complaint_type'] ?? 'Unknown') . ' at ' . ($row['location'] ?? 'Unknown location'),
                    ':link' => 'track-complaint.php?id=' . $row['complaint_id'],
                    ':created_at' => $row['submitted_at']
                ]);
                $synced++;
            }
        }
        
        // Sync recent tips (last 7 days)
        $stmt = $pdo->query("
            SELECT tip_id, location, description, submitted_at 
            FROM tips 
            WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY submitted_at DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $checkStmt = $pdo->prepare("SELECT id FROM notifications WHERE type = 'tip' AND link = :link LIMIT 1");
            $checkStmt->execute([':link' => 'review-tip.php?id=' . $row['tip_id']]);
            if (!$checkStmt->fetch()) {
                $insertStmt = $pdo->prepare("
                    INSERT INTO notifications (type, title, message, link, created_at) 
                    VALUES ('tip', 'New Tip Received', :message, :link, :created_at)
                ");
                $insertStmt->execute([
                    ':message' => 'Tip #' . $row['tip_id'] . ' - ' . ($row['location'] ?? 'Unknown location'),
                    ':link' => 'review-tip.php?id=' . $row['tip_id'],
                    ':created_at' => $row['submitted_at']
                ]);
                $synced++;
            }
        }
        
        // Sync recent volunteers (last 7 days)
        $stmt = $pdo->query("
            SELECT id, name, status, created_at 
            FROM volunteers 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $checkStmt = $pdo->prepare("SELECT id FROM notifications WHERE type = 'volunteer' AND link = :link LIMIT 1");
            $checkStmt->execute([':link' => 'volunteer-list.php?id=' . $row['id']]);
            if (!$checkStmt->fetch()) {
                $insertStmt = $pdo->prepare("
                    INSERT INTO notifications (type, title, message, link, created_at) 
                    VALUES ('volunteer', 'New Volunteer Registration', :message, :link, :created_at)
                ");
                $insertStmt->execute([
                    ':message' => $row['name'] . ' registered as volunteer (Status: ' . $row['status'] . ')',
                    ':link' => 'volunteer-list.php?id=' . $row['id'],
                    ':created_at' => $row['created_at']
                ]);
                $synced++;
            }
        }
        
        // Sync recent events (last 7 days)
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'events'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->query("
                    SELECT id, event_name, event_date, location, created_at 
                    FROM events 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ORDER BY created_at DESC
                ");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $checkStmt = $pdo->prepare("SELECT id FROM notifications WHERE type = 'event' AND link = :link LIMIT 1");
                    $checkStmt->execute([':link' => 'event-list.php?id=' . $row['id']]);
                    if (!$checkStmt->fetch()) {
                        $eventDate = $row['event_date'] ? date('M j, Y', strtotime($row['event_date'])) : '';
                        $insertStmt = $pdo->prepare("
                            INSERT INTO notifications (type, title, message, link, created_at) 
                            VALUES ('event', 'New Event Created', :message, :link, :created_at)
                        ");
                        $insertStmt->execute([
                            ':message' => $row['event_name'] . ($eventDate ? ' scheduled for ' . $eventDate : '') . ($row['location'] ? ' at ' . $row['location'] : ''),
                            ':link' => 'event-list.php?id=' . $row['id'],
                            ':created_at' => $row['created_at']
                        ]);
                        $synced++;
                    }
                }
            }
        } catch (PDOException $e) {
            // Events table doesn't exist, skip
        }
        
        // Sync recent event reports (last 7 days) - check if event_reports table exists
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'event_reports'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->query("
                    SELECT id, event_name, report_date, created_at 
                    FROM event_reports 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ORDER BY created_at DESC
                ");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $checkStmt = $pdo->prepare("SELECT id FROM notifications WHERE type = 'event_report' AND link = :link LIMIT 1");
                    $checkStmt->execute([':link' => 'event-reports.php?id=' . $row['id']]);
                    if (!$checkStmt->fetch()) {
                        $insertStmt = $pdo->prepare("
                            INSERT INTO notifications (type, title, message, link, created_at) 
                            VALUES ('event_report', 'New Event Report Generated', :message, :link, :created_at)
                        ");
                        $insertStmt->execute([
                            ':message' => 'Event report for ' . ($row['event_name'] ?? 'Event') . ' generated',
                            ':link' => 'event-reports.php?id=' . $row['id'],
                            ':created_at' => $row['created_at']
                        ]);
                        $synced++;
                    }
                }
            }
        } catch (PDOException $e) {
            // event_reports table doesn't exist, skip
        }
        
        // Sync recent incident reports for patrol assignment (last 7 days)
        // Check for incidents table or use complaints with specific status
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'incidents'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->query("
                    SELECT id, incident_id, location, incident_type, created_at 
                    FROM incidents 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ORDER BY created_at DESC
                ");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $checkStmt = $pdo->prepare("SELECT id FROM notifications WHERE type = 'incident' AND link = :link LIMIT 1");
                    $checkStmt->execute([':link' => 'incident-feed.php?id=' . ($row['incident_id'] ?? $row['id'])]);
                    if (!$checkStmt->fetch()) {
                        $insertStmt = $pdo->prepare("
                            INSERT INTO notifications (type, title, message, link, created_at) 
                            VALUES ('incident', 'New Incident Report', :message, :link, :created_at)
                        ");
                        $insertStmt->execute([
                            ':message' => 'Incident #' . ($row['incident_id'] ?? $row['id']) . ' - ' . ($row['incident_type'] ?? 'Unknown') . ' at ' . ($row['location'] ?? 'Unknown location') . ' (Requires patrol assignment)',
                            ':link' => 'patrol-schedule.php?id=' . ($row['incident_id'] ?? $row['id']),
                            ':created_at' => $row['created_at']
                        ]);
                        $synced++;
                    }
                }
            } else {
                // If no incidents table, check for complaints that need patrol assignment
                $stmt = $pdo->query("
                    SELECT complaint_id, complaint_type, location, submitted_at 
                    FROM complaints 
                    WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    AND (assigned_to = 'Pending Assignment' OR assigned_to = '' OR assigned_to IS NULL)
                    ORDER BY submitted_at DESC
                ");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $checkStmt = $pdo->prepare("SELECT id FROM notifications WHERE type = 'incident' AND link = :link LIMIT 1");
                    $checkStmt->execute([':link' => 'patrol-schedule.php?id=' . $row['complaint_id']]);
                    if (!$checkStmt->fetch()) {
                        $insertStmt = $pdo->prepare("
                            INSERT INTO notifications (type, title, message, link, created_at) 
                            VALUES ('incident', 'New Incident Report - Patrol Assignment Needed', :message, :link, :created_at)
                        ");
                        $insertStmt->execute([
                            ':message' => 'Incident #' . $row['complaint_id'] . ' - ' . ($row['complaint_type'] ?? 'Unknown') . ' at ' . ($row['location'] ?? 'Unknown location') . ' requires patrol assignment',
                            ':link' => 'patrol-schedule.php?id=' . $row['complaint_id'],
                            ':created_at' => $row['submitted_at']
                        ]);
                        $synced++;
                    }
                }
            }
        } catch (PDOException $e) {
            // Table doesn't exist or error, skip
        }
        
        // Sync volunteer requests for events (last 7 days)
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'volunteer_requests'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->query("
                    SELECT id, request_id, event_name, volunteers_needed, request_date, created_at 
                    FROM volunteer_requests 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ORDER BY created_at DESC
                ");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $checkStmt = $pdo->prepare("SELECT id FROM notifications WHERE type = 'volunteer_request' AND link = :link LIMIT 1");
                    $checkStmt->execute([':link' => 'schedule-management.php?id=' . ($row['request_id'] ?? $row['id'])]);
                    if (!$checkStmt->fetch()) {
                        $insertStmt = $pdo->prepare("
                            INSERT INTO notifications (type, title, message, link, created_at) 
                            VALUES ('volunteer_request', 'Volunteer Request for Event', :message, :link, :created_at)
                        ");
                        $insertStmt->execute([
                            ':message' => 'Volunteer request for ' . ($row['event_name'] ?? 'Event') . ' - ' . ($row['volunteers_needed'] ?? 0) . ' volunteers needed',
                            ':link' => 'schedule-management.php?id=' . ($row['request_id'] ?? $row['id']),
                            ':created_at' => $row['created_at']
                        ]);
                        $synced++;
                    }
                }
            }
        } catch (PDOException $e) {
            // volunteer_requests table doesn't exist, skip
        }
        
        // Sync recent patrol logs (last 7 days) - for completed patrols or new assignments
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'patrol_logs'");
            if ($stmt->rowCount() > 0) {
                // New patrol assignments
                $stmt = $pdo->query("
                    SELECT id, officer_name, route, date, time, status, created_at 
                    FROM patrol_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    AND status != 'Completed'
                    ORDER BY created_at DESC
                ");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $checkStmt = $pdo->prepare("SELECT id FROM notifications WHERE type = 'patrol' AND link = :link LIMIT 1");
                    $checkStmt->execute([':link' => 'patrol-logs.php?id=' . $row['id']]);
                    if (!$checkStmt->fetch()) {
                        $insertStmt = $pdo->prepare("
                            INSERT INTO notifications (type, title, message, link, created_at) 
                            VALUES ('patrol', 'New Patrol Assignment', :message, :link, :created_at)
                        ");
                        $insertStmt->execute([
                            ':message' => ($row['officer_name'] ?? 'Officer') . ' assigned to patrol route: ' . ($row['route'] ?? 'Unknown route'),
                            ':link' => 'patrol-logs.php?id=' . $row['id'],
                            ':created_at' => $row['created_at']
                        ]);
                        $synced++;
                    }
                }
            }
        } catch (PDOException $e) {
            // patrol_logs table doesn't exist, skip
        }
        
        // Note: Login/logout notifications are created directly in login.php and logout.php
        // This sync function only handles other activities
        
        ob_clean();
        echo json_encode(['success' => true, 'synced' => $synced]);
    }
    
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function getTimeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}


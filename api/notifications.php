<?php
// Notifications API - Provides recent activities and unread count
header('Content-Type: application/json');
ob_start();

// Helper function - define early to avoid issues
// Check if function already exists (in case it's defined elsewhere)
if (!function_exists('getTimeAgo')) {
    function getTimeAgo($datetime) {
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
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized', 'message' => 'Please log in to view notifications']);
    exit;
}

// Load database connection with error handling
try {
    require_once __DIR__ . '/../db.php';
    require_once __DIR__ . '/patrol_logs_schema.php';
    require_once __DIR__ . '/notifications_schema.php';
    if (!($pdo instanceof PDO)) {
        throw new Exception('Database connection not available');
    }
    ensureNotificationsTable($pdo);
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    error_log('Notifications API - Database connection failed: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database connection failed',
        'message' => 'Unable to connect to database. Please contact administrator.'
    ]);
    exit;
}

// Ensure notifications table exists
try {
    ensureNotificationsTable($pdo);
} catch (PDOException $e) {
    // Table might already exist, continue
}

$action = $_GET['action'] ?? 'list';
$userId = $_SESSION['user_id'] ?? null;

function notificationsIsAdmin(): bool
{
    $userRole = trim((string) ($_SESSION['user_role'] ?? ''));
    return $userRole === '' || strcasecmp($userRole, 'Admin') === 0;
}

// Debug mode - only log in development
$debugMode = isset($_ENV['ENVIRONMENT']) && $_ENV['ENVIRONMENT'] !== 'production';
if ($debugMode) {
    error_log('Notifications API - Action: ' . $action . ', User ID: ' . ($userId ?? 'null') . ', User Role: ' . ($_SESSION['user_role'] ?? 'not set'));
}

try {
    if ($action === 'list') {
        // Get user role
        $isAdmin = notificationsIsAdmin();
        
        // Initialize variables
        $notifications = [];
        $unreadCount = 0;
        
        // Build query based on user role
        // Admins see: all notifications (user_id IS NULL) + their own notifications (user_id = userId)
        // Users see: only their own notifications (user_id = userId)
        if ($isAdmin) {
            try {
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
            } catch (PDOException $e) {
                error_log('Notifications API - Query error: ' . $e->getMessage());
                // Return empty result instead of failing
                $notifications = [];
                $unreadCount = 0;
            }
            
            if (isset($stmt) && $stmt) {
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
                
                if (isset($unreadStmt) && $unreadStmt) {
                    $unreadResult = $unreadStmt->fetch(PDO::FETCH_ASSOC);
                    $unreadCount = $unreadResult ? (int)$unreadResult['count'] : 0;
                }
            }
        } else {
            // Regular users only see their own notifications
            if ($userId === null) {
                // If no user_id, return empty notifications
                $notifications = [];
                $unreadCount = 0;
            } else {
                try {
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
                    
                    $unreadResult = $unreadStmt->fetch(PDO::FETCH_ASSOC);
                    $unreadCount = $unreadResult ? (int)$unreadResult['count'] : 0;
                } catch (PDOException $e) {
                    error_log('Notifications API - Query error (user): ' . $e->getMessage());
                    // Return empty result instead of failing
                    $notifications = [];
                    $unreadCount = 0;
                }
            }
        }
        
        // Ensure notifications is always an array
        if (!isset($notifications) || !is_array($notifications)) {
            $notifications = [];
        }
        if (!isset($unreadCount)) {
            $unreadCount = 0;
        }
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'count' => count($notifications)
        ]);
        
    } elseif ($action === 'mark_read') {
        $notificationId = $_POST['id'] ?? null;
        $isAdmin = notificationsIsAdmin();
        
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
        try {
            $stmt = $pdo->query("
                SELECT complaint_id, complainant_name, complaint_type, location, submitted_at 
                FROM complaints 
                WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY submitted_at DESC
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (createAdminNotification(
                    $pdo,
                    'complaint',
                    'New Complaint Submitted',
                    'Complaint #' . $row['complaint_id'] . ' - ' . ($row['complaint_type'] ?? 'Unknown') . ' from ' . ($row['complainant_name'] ?? 'Unknown'),
                    'track-complaint.php?id=' . $row['complaint_id'],
                    null,
                    $row['submitted_at']
                )) {
                    $synced++;
                }
            }
        } catch (PDOException $e) {
            // Complaints table doesn't exist, skip
        }
        
        // Sync recent tips (last 7 days)
        try {
            $stmt = $pdo->query("
                SELECT tip_id, location, description, submitted_at 
                FROM tips 
                WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY submitted_at DESC
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (createAdminNotification(
                    $pdo,
                    'tip',
                    'New Tip Received',
                    'Tip #' . $row['tip_id'] . ' - ' . ($row['location'] ?? 'Unknown location'),
                    'review-tip.php?id=' . $row['tip_id'],
                    null,
                    $row['submitted_at']
                )) {
                    $synced++;
                }
            }
        } catch (PDOException $e) {
            // Tips table doesn't exist, skip
        }
        
        // Sync recent neighborhood watch applications (last 7 days)
        try {
            $stmt = $pdo->query("
                SELECT id, name, status, created_at 
                FROM nw_members 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY created_at DESC
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $checkStmt = $pdo->prepare("SELECT id FROM notifications WHERE type = 'volunteer' AND link = :link LIMIT 1");
                $checkStmt->execute([':link' => 'neighborhood-watch-application.php?id=' . $row['id']]);
                if (!$checkStmt->fetch()) {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO notifications (type, title, message, link, created_at) 
                        VALUES ('volunteer', 'New Neighborhood Watch Application', :message, :link, :created_at)
                    ");
                    $insertStmt->execute([
                        ':message' => $row['name'] . ' submitted a neighborhood watch membership application (Status: ' . $row['status'] . ')',
                        ':link' => 'neighborhood-watch-application.php?id=' . $row['id'],
                        ':created_at' => $row['created_at']
                    ]);
                    $synced++;
                }
            }
        } catch (PDOException $e) {
            // Volunteers table doesn't exist, skip
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
                try {
                    $stmt = $pdo->query("
                        SELECT id, incident_id, location, incident_type, created_at 
                        FROM incidents 
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        ORDER BY created_at DESC
                    ");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $checkStmt = $pdo->prepare("SELECT id FROM notifications WHERE type = 'incident' AND link = :link LIMIT 1");
                        $checkStmt->execute([':link' => 'patrol-schedule.php?id=' . ($row['incident_id'] ?? $row['id'])]);
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
                } catch (PDOException $e) {
                    // Incidents table structure issue, skip
                }
            } else {
                // If no incidents table, check for complaints that need patrol assignment
                try {
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
                } catch (PDOException $e) {
                    // Complaints table doesn't exist or query failed, skip
                }
            }
        } catch (PDOException $e) {
            // Table check failed, skip
        }
        
        // Sync recent patrol logs (last 7 days) - for completed patrols or new assignments
        try {
            ensurePatrolLogsTable($pdo);
            $stmt = $pdo->query("SHOW TABLES LIKE 'patrol_logs'");
            if ($stmt->rowCount() > 0) {
                // New patrol assignments
                $stmt = $pdo->query("
                    SELECT id, personnel_name, route, date, time, status, created_at 
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
                            ':message' => ($row['personnel_name'] ?? 'BPSO Personnel') . ' assigned to patrol route: ' . ($row['route'] ?? 'Unknown route'),
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
        echo json_encode([
            'success' => true, 
            'synced' => $synced,
            'message' => "Synced {$synced} notification(s)",
            'debug' => [
                'complaints_checked' => isset($activities['complaints']),
                'tips_checked' => isset($activities['tips']),
                'volunteers_checked' => isset($activities['volunteers'])
            ]
        ]);
    } else {
        // Unknown action
        ob_clean();
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid action',
            'message' => 'Valid actions are: list, mark_read, sync'
        ]);
    }
    
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    // Log error for debugging (don't expose sensitive info to client)
    error_log('Notifications API Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database error occurred. Please check server logs for details.'
    ]);
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    error_log('Notifications API Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'An error occurred. Please check server logs for details.'
    ]);
}

// getTimeAgo function is now defined at the top of the file


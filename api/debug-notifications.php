<?php
/**
 * Debug Notifications - Check why notifications aren't appearing
 * Access: https://surveillance.alertaraqc.com/api/debug-notifications.php
 */

session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in first']);
    exit;
}

require_once __DIR__ . '/../db.php';

$debug = [
    'success' => true,
    'checks' => [],
    'notifications' => [],
    'activities' => [],
    'recommendations' => []
];

try {
    // Check notifications table
    $debug['checks']['notifications_table'] = 'exists';
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM notifications");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $debug['checks']['total_notifications'] = (int)$result['count'];
    
    // Check unread notifications
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $debug['checks']['unread_notifications'] = (int)$result['count'];
    
    // Get recent notifications
    $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10");
    $debug['notifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check for activities in last 7 days
    $activities = [];
    
    // Check complaints
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM complaints WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $activities['complaints'] = (int)$result['count'];
    } catch (PDOException $e) {
        $activities['complaints'] = 'table_not_found';
    }
    
    // Check tips
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM tips WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $activities['tips'] = (int)$result['count'];
    } catch (PDOException $e) {
        $activities['tips'] = 'table_not_found';
    }
    
    // Check volunteers
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM volunteers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $activities['volunteers'] = (int)$result['count'];
    } catch (PDOException $e) {
        $activities['volunteers'] = 'table_not_found';
    }
    
    $debug['activities'] = $activities;
    
    // Recommendations
    if ($debug['checks']['total_notifications'] == 0) {
        $debug['recommendations'][] = 'No notifications exist. The sync action should create notifications from recent activities.';
    }
    
    if ($debug['checks']['unread_notifications'] == 0 && $debug['checks']['total_notifications'] > 0) {
        $debug['recommendations'][] = 'All notifications are marked as read.';
    }
    
    $hasActivities = false;
    foreach ($activities as $count) {
        if (is_numeric($count) && $count > 0) {
            $hasActivities = true;
            break;
        }
    }
    
    if (!$hasActivities) {
        $debug['recommendations'][] = 'No recent activities found in the last 7 days. Notifications are only created for activities in the last 7 days.';
    }
    
    if ($hasActivities && $debug['checks']['total_notifications'] == 0) {
        $debug['recommendations'][] = 'Activities exist but no notifications were created. Try manually calling the sync action: api/notifications.php?action=sync';
    }
    
    // Check user-specific notifications
    $userId = $_SESSION['user_id'] ?? null;
    if ($userId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $debug['checks']['user_notifications'] = (int)$result['count'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE (user_id IS NULL OR user_id = :user_id) AND is_read = 0");
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $debug['checks']['user_unread_notifications'] = (int)$result['count'];
    }
    
    // Check admin notifications (user_id IS NULL)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM notifications WHERE user_id IS NULL");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $debug['checks']['admin_notifications'] = (int)$result['count'];
    
} catch (PDOException $e) {
    $debug['success'] = false;
    $debug['error'] = $e->getMessage();
    $debug['checks']['database_error'] = true;
}

echo json_encode($debug, JSON_PRETTY_PRINT);



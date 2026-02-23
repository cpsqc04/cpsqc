<?php
/**
 * Test Login Notification Creation
 * Access: https://surveillance.alertaraqc.com/api/test-login-notification.php
 * This will create a test login notification to verify the system works
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

$result = [
    'success' => false,
    'steps' => [],
    'notifications_created' => 0,
    'notifications_found' => 0,
    'error' => null
];

try {
    // Step 1: Ensure notifications table exists
    $result['steps'][] = 'Checking notifications table...';
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
    $result['steps'][] = 'Notifications table exists';
    
    // Step 2: Get current user info
    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'Test User';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    $result['user_info'] = [
        'user_id' => $userId,
        'username' => $username,
        'ip_address' => $ipAddress
    ];
    
    // Step 3: Create test login notification for user
    $result['steps'][] = 'Creating user notification...';
    $loginTime = date('M j, Y g:i:s A');
    $message = "User {$username} logged in from IP {$ipAddress} at {$loginTime} (TEST)";
    
    if ($userId) {
        $notifStmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (:user_id, :type, :title, :message, :link, NOW())');
        $notifStmt->execute([
            ':user_id' => $userId,
            ':type' => 'login',
            ':title' => 'Login Successful (TEST)',
            ':message' => $message,
            ':link' => 'login-history.php'
        ]);
        $result['notifications_created']++;
        $result['steps'][] = 'User notification created successfully';
    } else {
        $result['steps'][] = 'Skipped user notification (no user_id)';
    }
    
    // Step 4: Create admin notification
    $result['steps'][] = 'Creating admin notification...';
    $adminNotifStmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (NULL, :type, :title, :message, :link, NOW())');
    $adminNotifStmt->execute([
        ':type' => 'login',
        ':title' => 'User Login (TEST)',
        ':message' => $message,
        ':link' => 'login-history.php'
    ]);
    $result['notifications_created']++;
    $result['steps'][] = 'Admin notification created successfully';
    
    // Step 5: Verify notifications were created
    $result['steps'][] = 'Verifying notifications...';
    
    // Count total notifications
    $countStmt = $pdo->query("SELECT COUNT(*) as count FROM notifications");
    $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $result['total_notifications'] = (int)$countResult['count'];
    
    // Count user notifications
    if ($userId) {
        $userCountStmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id");
        $userCountStmt->execute([':user_id' => $userId]);
        $userCountResult = $userCountStmt->fetch(PDO::FETCH_ASSOC);
        $result['user_notifications'] = (int)$userCountResult['count'];
    }
    
    // Count admin notifications
    $adminCountStmt = $pdo->query("SELECT COUNT(*) as count FROM notifications WHERE user_id IS NULL");
    $adminCountResult = $adminCountStmt->fetch(PDO::FETCH_ASSOC);
    $result['admin_notifications'] = (int)$adminCountResult['count'];
    
    // Get recent notifications
    $recentStmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
    $result['recent_notifications'] = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result['success'] = true;
    $result['steps'][] = 'Test completed successfully';
    
} catch (PDOException $e) {
    $result['error'] = $e->getMessage();
    $result['error_info'] = $e->errorInfo ?? null;
    $result['steps'][] = 'Error: ' . $e->getMessage();
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
    $result['steps'][] = 'Error: ' . $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);


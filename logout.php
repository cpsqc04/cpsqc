<?php
session_start();

// Record logout time in login_history if user was logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    require_once __DIR__ . '/db.php';
    
    try {
        // Ensure login_history table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            username VARCHAR(100) NOT NULL,
            login_time DATETIME NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'Success',
            logout_time DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_username (username),
            INDEX idx_login_time (login_time),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Update the most recent login record for this user to set logout_time
        $stmt = $pdo->prepare('UPDATE login_history SET logout_time = NOW() WHERE user_id = :user_id AND logout_time IS NULL ORDER BY login_time DESC LIMIT 1');
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        
        // Create logout notification for the user
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
        
        $logoutTime = date('M j, Y g:i:s A');
        $message = "User {$_SESSION['username']} logged out at {$logoutTime}";
        
        // Create notification for the user who logged out
        $notifStmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (:user_id, :type, :title, :message, :link, NOW())');
        $notifStmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':type' => 'logout',
            ':title' => 'Logout Successful',
            ':message' => $message,
            ':link' => 'login-history.php'
        ]);
        
        // Also create a notification for admins
        $adminNotifStmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (NULL, :type, :title, :message, :link, NOW())');
        $adminNotifStmt->execute([
            ':type' => 'logout',
            ':title' => 'User Logout',
            ':message' => $message,
            ':link' => 'login-history.php'
        ]);
        
    } catch (PDOException $e) {
        // Log error but don't break logout flow
        error_log('Failed to record logout: ' . $e->getMessage());
    }
}

session_unset();
session_destroy();
header('Location: login.php');
exit;
?>


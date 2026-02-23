<?php
// Login History API
header('Content-Type: application/json');
ob_start();

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check if user is admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../db.php';

// Ensure login_history table exists
try {
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
} catch (PDOException $e) {
    // Table might already exist, continue
}

try {
    // Get login history (last 100 entries)
    $stmt = $pdo->query("
        SELECT id, user_id, username, login_time, ip_address, status, logout_time 
        FROM login_history 
        ORDER BY login_time DESC 
        LIMIT 100
    ");
    
    $history = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Format login date and time
        $loginDate = $row['login_time'] ? date('M j, Y', strtotime($row['login_time'])) : '-';
        $loginTime = $row['login_time'] ? date('g:i:s A', strtotime($row['login_time'])) : '-';
        $loginDateTime = $row['login_time'] ? date('M j, Y g:i:s A', strtotime($row['login_time'])) : '-';
        
        // Format logout date and time
        $logoutDate = $row['logout_time'] ? date('M j, Y', strtotime($row['logout_time'])) : '-';
        $logoutTime = $row['logout_time'] ? date('g:i:s A', strtotime($row['logout_time'])) : '-';
        $logoutDateTime = $row['logout_time'] ? date('M j, Y g:i:s A', strtotime($row['logout_time'])) : '-';
        
        $history[] = [
            'id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'username' => $row['username'],
            'login_date' => $loginDate,
            'login_time' => $loginTime,
            'login_datetime' => $loginDateTime,
            'ip_address' => $row['ip_address'] ?? '-',
            'status' => $row['status'],
            'logout_date' => $logoutDate,
            'logout_time' => $logoutTime,
            'logout_datetime' => $logoutDateTime
        ];
    }
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
    
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}


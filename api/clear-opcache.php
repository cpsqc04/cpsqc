<?php
/**
 * Clear PHP Opcache
 * Access: https://surveillance.alertaraqc.com/api/clear-opcache.php
 * This will force PHP to reload all PHP files
 */

session_start();

// Only allow admins or localhost
$isLocalhost = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost']);
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!$isLocalhost && !$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

$result = [
    'success' => false,
    'opcache_enabled' => false,
    'opcache_cleared' => false,
    'message' => ''
];

// Check if opcache is enabled
if (function_exists('opcache_get_status')) {
    $result['opcache_enabled'] = true;
    $status = opcache_get_status(false);
    
    if ($status && isset($status['opcache_enabled']) && $status['opcache_enabled']) {
        // Try to clear opcache
        if (function_exists('opcache_reset')) {
            $cleared = opcache_reset();
            $result['opcache_cleared'] = $cleared;
            $result['success'] = $cleared;
            $result['message'] = $cleared ? 'Opcache cleared successfully' : 'Failed to clear opcache';
        } else {
            $result['message'] = 'opcache_reset() function not available';
        }
    } else {
        $result['message'] = 'Opcache is not enabled';
    }
} else {
    $result['message'] = 'Opcache extension not installed or not available';
    $result['success'] = true; // Not an error if opcache isn't enabled
}

// Also touch the notifications.php file to force reload
$notificationsFile = __DIR__ . '/notifications.php';
if (file_exists($notificationsFile)) {
    touch($notificationsFile);
    $result['file_touched'] = true;
    $result['message'] .= ' | notifications.php file touched to force reload';
}

echo json_encode($result, JSON_PRETTY_PRINT);



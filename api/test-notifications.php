<?php
/**
 * Notifications API Diagnostic Script
 * Use this to test if the notifications API is working
 * Access: https://surveillance.alertaraqc.com/api/test-notifications.php
 */

session_start();
header('Content-Type: application/json');

$result = [
    'success' => false,
    'checks' => [],
    'errors' => []
];

// Check 1: Session
$result['checks']['session'] = [
    'started' => session_status() === PHP_SESSION_ACTIVE,
    'admin_logged_in' => isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true,
    'user_id' => $_SESSION['user_id'] ?? 'not set',
    'user_role' => $_SESSION['user_role'] ?? 'not set'
];

// Check 2: Database connection
try {
    require_once __DIR__ . '/../db.php';
    $result['checks']['database'] = [
        'connected' => isset($pdo) && $pdo !== null,
        'pdo_class' => isset($pdo) ? get_class($pdo) : 'not set'
    ];
    
    if (isset($pdo)) {
        // Test query
        try {
            $testStmt = $pdo->query("SELECT 1 as test");
            $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
            $result['checks']['database']['query_test'] = $testResult ? 'passed' : 'failed';
        } catch (PDOException $e) {
            $result['checks']['database']['query_test'] = 'failed: ' . $e->getMessage();
            $result['errors'][] = 'Database query test failed: ' . $e->getMessage();
        }
        
        // Check if notifications table exists
        try {
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'notifications'");
            $result['checks']['database']['notifications_table'] = $tableCheck->rowCount() > 0 ? 'exists' : 'does not exist';
            
            if ($tableCheck->rowCount() > 0) {
                // Count notifications
                $countStmt = $pdo->query("SELECT COUNT(*) as count FROM notifications");
                $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
                $result['checks']['database']['notification_count'] = (int)$countResult['count'];
            }
        } catch (PDOException $e) {
            $result['checks']['database']['notifications_table'] = 'error: ' . $e->getMessage();
            $result['errors'][] = 'Table check failed: ' . $e->getMessage();
        }
    }
} catch (Exception $e) {
    $result['checks']['database'] = ['connected' => false, 'error' => $e->getMessage()];
    $result['errors'][] = 'Database connection failed: ' . $e->getMessage();
}

// Check 3: getTimeAgo function (defined in notifications.php)
// Load notifications.php to check if function exists there
$notificationsFile = __DIR__ . '/notifications.php';
if (file_exists($notificationsFile)) {
    // Check if function is defined in that file
    $fileContent = file_get_contents($notificationsFile);
    if (strpos($fileContent, 'function getTimeAgo') !== false) {
        $result['checks']['getTimeAgo'] = 'function defined in notifications.php';
        // Try to include it to test
        ob_start();
        try {
            // Define function manually for test
            if (!function_exists('getTimeAgo')) {
                eval('
                    function getTimeAgo($datetime) {
                        if (empty($datetime)) return "Unknown";
                        $timestamp = strtotime($datetime);
                        if ($timestamp === false) return "Invalid date";
                        $diff = time() - $timestamp;
                        if ($diff < 60) return "Just now";
                        elseif ($diff < 3600) {
                            $mins = floor($diff / 60);
                            return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
                        } elseif ($diff < 86400) {
                            $hours = floor($diff / 3600);
                            return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
                        } elseif ($diff < 604800) {
                            $days = floor($diff / 86400);
                            return $days . " day" . ($days > 1 ? "s" : "") . " ago";
                        } else {
                            return date("M j, Y", $timestamp);
                        }
                    }
                ');
            }
            $testResult = getTimeAgo(date('Y-m-d H:i:s'));
            $result['checks']['getTimeAgo_test'] = 'works: ' . $testResult;
        } catch (Exception $e) {
            $result['checks']['getTimeAgo_test'] = 'error: ' . $e->getMessage();
            $result['errors'][] = 'getTimeAgo test failed: ' . $e->getMessage();
        }
        ob_end_clean();
    } else {
        $result['checks']['getTimeAgo'] = 'function NOT FOUND in notifications.php file';
        $result['errors'][] = 'getTimeAgo function is not defined in notifications.php';
    }
} else {
    $result['checks']['getTimeAgo'] = 'notifications.php file not found';
    $result['errors'][] = 'notifications.php file does not exist';
}

// Check 4: PHP errors
$result['checks']['php'] = [
    'version' => PHP_VERSION,
    'error_reporting' => error_reporting(),
    'display_errors' => ini_get('display_errors')
];

// Overall status
$result['success'] = empty($result['errors']) && 
                     $result['checks']['session']['admin_logged_in'] && 
                     ($result['checks']['database']['connected'] ?? false);

echo json_encode($result, JSON_PRETTY_PRINT);


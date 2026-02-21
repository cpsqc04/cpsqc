<?php
// Dashboard API - Provides statistics and chart data
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

/**
 * Check if a table exists
 */
function tableExists(PDO $pdo, $tableName): bool
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

try {
    $response = [
        'success' => true,
        'statistics' => [],
        'trends' => [],
        'charts' => []
    ];
    
    // Statistics
    $stats = [];
    
    // Total Members
    if (tableExists($pdo, 'members')) {
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM members');
        $stats['totalMembers'] = (int)$stmt->fetch()['count'];
        
        // Members this month
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM members WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
        $stats['membersThisMonth'] = (int)$stmt->fetch()['count'];
    } else {
        $stats['totalMembers'] = 0;
        $stats['membersThisMonth'] = 0;
    }
    
    // Active Complaints
    if (tableExists($pdo, 'complaints')) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM complaints WHERE status IN ('Pending', 'In Progress', 'Under Review')");
        $stats['activeComplaints'] = (int)$stmt->fetch()['count'];
        
        // Complaints resolved this week
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'Resolved' AND WEEK(submitted_at) = WEEK(CURRENT_DATE()) AND YEAR(submitted_at) = YEAR(CURRENT_DATE())");
        $stats['complaintsResolvedThisWeek'] = (int)$stmt->fetch()['count'];
    } else {
        $stats['activeComplaints'] = 0;
        $stats['complaintsResolvedThisWeek'] = 0;
    }
    
    // Active Volunteers
    if (tableExists($pdo, 'volunteers')) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM volunteers WHERE status = 'Active'");
        $stats['activeVolunteers'] = (int)$stmt->fetch()['count'];
        
        // Volunteers this month
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM volunteers WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
        $stats['volunteersThisMonth'] = (int)$stmt->fetch()['count'];
    } else {
        $stats['activeVolunteers'] = 0;
        $stats['volunteersThisMonth'] = 0;
    }
    
    // Upcoming Events (assuming events table exists)
    if (tableExists($pdo, 'events')) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM events WHERE event_date >= CURRENT_DATE()");
        $stats['upcomingEvents'] = (int)$stmt->fetch()['count'];
        
        // Events this week
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM events WHERE WEEK(event_date) = WEEK(CURRENT_DATE()) AND YEAR(event_date) = YEAR(CURRENT_DATE())");
        $stats['eventsThisWeek'] = (int)$stmt->fetch()['count'];
    } else {
        $stats['upcomingEvents'] = 0;
        $stats['eventsThisWeek'] = 0;
    }
    
    // Pending Tips
    if (tableExists($pdo, 'tips')) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM tips WHERE status = 'Pending'");
        $stats['pendingTips'] = (int)$stmt->fetch()['count'];
        
        // New tips this week
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM tips WHERE WEEK(submitted_at) = WEEK(CURRENT_DATE()) AND YEAR(submitted_at) = YEAR(CURRENT_DATE())");
        $stats['newTipsThisWeek'] = (int)$stmt->fetch()['count'];
    } else {
        $stats['pendingTips'] = 0;
        $stats['newTipsThisWeek'] = 0;
    }
    
    // Total Patrol Officers
    if (tableExists($pdo, 'patrols')) {
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM patrols');
        $stats['totalPatrolOfficers'] = (int)$stmt->fetch()['count'];
    } else {
        $stats['totalPatrolOfficers'] = 0;
    }
    
    $response['statistics'] = $stats;
    
    // Trends (for display in stat cards)
    $trends = [
        'membersTrend' => $stats['membersThisMonth'] > 0 ? '+' . $stats['membersThisMonth'] . ' this month' : '0 this month',
        'complaintsTrend' => $stats['complaintsResolvedThisWeek'] > 0 ? $stats['complaintsResolvedThisWeek'] . ' resolved this week' : '0 resolved this week',
        'volunteersTrend' => $stats['volunteersThisMonth'] > 0 ? '+' . $stats['volunteersThisMonth'] . ' this month' : '0 this month',
        'eventsTrend' => $stats['eventsThisWeek'] > 0 ? $stats['eventsThisWeek'] . ' this week' : '0 this week',
        'tipsTrend' => $stats['newTipsThisWeek'] > 0 ? '+' . $stats['newTipsThisWeek'] . ' this week' : '0 this week'
    ];
    
    $response['trends'] = $trends;
    
    // Charts Data
    $charts = [];
    
    // Complaints by Status
    if (tableExists($pdo, 'complaints')) {
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM complaints GROUP BY status");
        $complaintsByStatus = [];
        while ($row = $stmt->fetch()) {
            $complaintsByStatus[$row['status']] = (int)$row['count'];
        }
        $charts['complaintsByStatus'] = $complaintsByStatus;
        
        // Complaints Over Time (last 7 days)
        $complaintsOverTime = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM complaints WHERE DATE(submitted_at) = :date");
            $stmt->execute([':date' => $date]);
            $complaintsOverTime[$date] = (int)$stmt->fetch()['count'];
        }
        $charts['complaintsOverTime'] = $complaintsOverTime;
    } else {
        $charts['complaintsByStatus'] = [];
        $charts['complaintsOverTime'] = [];
    }
    
    // Tips Over Time (last 7 days)
    if (tableExists($pdo, 'tips')) {
        $tipsOverTime = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tips WHERE DATE(submitted_at) = :date");
            $stmt->execute([':date' => $date]);
            $tipsOverTime[$date] = (int)$stmt->fetch()['count'];
        }
        $charts['tipsOverTime'] = $tipsOverTime;
        
        // Tips by Status
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM tips GROUP BY status");
        $tipsByStatus = [];
        while ($row = $stmt->fetch()) {
            $tipsByStatus[$row['status']] = (int)$row['count'];
        }
        $charts['tipsByStatus'] = $tipsByStatus;
    } else {
        $charts['tipsOverTime'] = [];
        $charts['tipsByStatus'] = [];
    }
    
    // Volunteers by Category
    if (tableExists($pdo, 'volunteers')) {
        $stmt = $pdo->query("SELECT category, COUNT(*) as count FROM volunteers GROUP BY category");
        $volunteersByCategory = [];
        while ($row = $stmt->fetch()) {
            $volunteersByCategory[$row['category']] = (int)$row['count'];
        }
        $charts['volunteersByCategory'] = $volunteersByCategory;
    } else {
        $charts['volunteersByCategory'] = [];
    }
    
    $response['charts'] = $charts;
    
    // Recent Activity
    $recentActivity = [];
    
    // Recent Complaints
    if (tableExists($pdo, 'complaints')) {
        $stmt = $pdo->query("SELECT complaint_id, complainant_name, complaint_type, location, submitted_at FROM complaints ORDER BY submitted_at DESC LIMIT 10");
        while ($row = $stmt->fetch()) {
            $recentActivity[] = [
                'type' => 'complaint',
                'title' => 'New Complaint Submitted',
                'details' => 'Complaint #' . $row['complaint_id'] . ' - ' . ($row['complaint_type'] ?? 'Unknown') . ($row['location'] ? ' reported at ' . $row['location'] : ''),
                'time' => $row['submitted_at']
            ];
        }
    }
    
    // Recent Tips
    if (tableExists($pdo, 'tips')) {
        $stmt = $pdo->query("SELECT tip_id, location, description, submitted_at FROM tips ORDER BY submitted_at DESC LIMIT 10");
        while ($row = $stmt->fetch()) {
            $location = $row['location'] ?? 'Unknown location';
            $recentActivity[] = [
                'type' => 'tip',
                'title' => 'New Tip Received',
                'details' => 'Tip #' . $row['tip_id'] . ' - ' . $location,
                'time' => $row['submitted_at']
            ];
        }
    }
    
    // Recent Events
    if (tableExists($pdo, 'events')) {
        $stmt = $pdo->query("SELECT event_name, event_date, event_time, location, created_at FROM events ORDER BY created_at DESC LIMIT 10");
        while ($row = $stmt->fetch()) {
            $eventDate = $row['event_date'] ? date('F j, Y', strtotime($row['event_date'])) : '';
            $location = $row['location'] ?? 'Community Center';
            $recentActivity[] = [
                'type' => 'event',
                'title' => 'Community Meeting Scheduled',
                'details' => $row['event_name'] . ($eventDate ? ' - ' . $eventDate : '') . ' at ' . $location,
                'time' => $row['created_at']
            ];
        }
    }
    
    // Recent Members
    if (tableExists($pdo, 'members')) {
        $stmt = $pdo->query("SELECT name, created_at FROM members ORDER BY created_at DESC LIMIT 10");
        while ($row = $stmt->fetch()) {
            $recentActivity[] = [
                'type' => 'member',
                'title' => 'New Member Registered',
                'details' => $row['name'] . ' joined the Neighborhood Watch program',
                'time' => $row['created_at']
            ];
        }
    }
    
    // Recent Volunteers
    if (tableExists($pdo, 'volunteers')) {
        $stmt = $pdo->query("SELECT name, status, created_at FROM volunteers ORDER BY created_at DESC LIMIT 10");
        while ($row = $stmt->fetch()) {
            $recentActivity[] = [
                'type' => 'volunteer',
                'title' => 'New Volunteer Registration',
                'details' => $row['name'] . ' registered as volunteer (Status: ' . ($row['status'] ?? 'Pending') . ')',
                'time' => $row['created_at']
            ];
        }
    }
    
    // Check for patrol_logs table (if it exists)
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'patrol_logs'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT officer_name, route, date, time, status, created_at FROM patrol_logs WHERE status = 'Completed' ORDER BY created_at DESC LIMIT 10");
            while ($row = $stmt->fetch()) {
                $route = $row['route'] ?? 'Unknown route';
                $recentActivity[] = [
                    'type' => 'patrol',
                    'title' => 'Patrol Completed',
                    'details' => ($row['officer_name'] ?? 'Officer') . ' completed patrol route - ' . $route,
                    'time' => $row['created_at'] ?? $row['date'] . ' ' . ($row['time'] ?? '')
                ];
            }
        }
    } catch (PDOException $e) {
        // Table doesn't exist, skip
    }
    
    // Sort by time (most recent first) and limit to 5
    usort($recentActivity, function($a, $b) {
        $timeA = strtotime($a['time'] ?? '1970-01-01');
        $timeB = strtotime($b['time'] ?? '1970-01-01');
        return $timeB - $timeA;
    });
    $recentActivity = array_slice($recentActivity, 0, 5);
    
    // Format time for each activity
    foreach ($recentActivity as &$activity) {
        $activity['time_ago'] = getTimeAgo($activity['time']);
    }
    unset($activity);
    
    $response['recentActivity'] = $recentActivity;
    
    ob_clean();
    echo json_encode($response);
    
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

/**
 * Get time ago string (e.g., "2 hours ago", "1 day ago")
 */
function getTimeAgo($datetime) {
    if (empty($datetime)) {
        return 'Unknown time';
    }
    
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return 'Unknown time';
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
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}


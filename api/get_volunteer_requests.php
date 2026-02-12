<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';

// Basic auth check – only allow logged-in admins
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// For now, return empty array since this is a stub for external system integration
// In the future, this can be connected to a volunteer_requests table or external API
echo json_encode([
    'success' => true,
    'requests' => []
]);


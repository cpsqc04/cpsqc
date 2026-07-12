<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/bpso_auth.php';

if (!isBpsoLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, personnel_name, bpso_personnel_id, status, schedule FROM patrols WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => getBpsoPatrolId()]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Profile not found.']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $profile]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load profile.']);
}

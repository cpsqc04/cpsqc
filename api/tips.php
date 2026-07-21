<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/tips_schema.php';
require_once __DIR__ . '/notifications_schema.php';

try {
    ensureTipsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare tips table: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

if ($action !== 'create' && (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($method === 'GET') {
    try {
        $cols = tipsSelectColumns();
        $stmt = $pdo->query("SELECT {$cols} FROM tips ORDER BY id DESC");
        $tips = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $tips,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load tips: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    if ($action === 'create') {
        $location = trim($input['location'] ?? '');
        $description = trim($input['description'] ?? '');
        $photoData = $input['photo'] ?? null;

        if ($location === '' || $description === '' || empty($photoData)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Location, description, and photo are required.']);
            exit;
        }

        $year = date('Y');
        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(tip_id, LOCATE('-', tip_id, 5) + 1) AS UNSIGNED)) AS max_num FROM tips WHERE tip_id LIKE 'TIP-{$year}-%'");
        $maxNum = $stmt->fetchColumn();
        $nextNum = ($maxNum === false || $maxNum === null) ? 1 : $maxNum + 1;
        $tipId = "TIP-{$year}-" . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        try {
            $stmt = $pdo->prepare('INSERT INTO tips (tip_id, location, description, photo_data, status, outcome) VALUES (:tip_id, :location, :description, :photo_data, :status, :outcome)');
            $stmt->execute([
                ':tip_id' => $tipId,
                ':location' => $location,
                ':description' => $description,
                ':photo_data' => $photoData,
                ':status' => 'Under Review',
                ':outcome' => 'No Outcome Yet',
            ]);

            $id = (int) $pdo->lastInsertId();

            createAdminNotification(
                $pdo,
                'tip',
                'New Tip Received',
                'Tip #' . $tipId . ' - ' . $location,
                'review-tip.php?id=' . $tipId
            );

            echo json_encode([
                'success' => true,
                'message' => 'Tip submitted successfully!',
                'data' => [
                    'id' => $id,
                    'tip_id' => $tipId,
                    'location' => $location,
                    'description' => $description,
                    'status' => 'Under Review',
                    'outcome' => 'No Outcome Yet',
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to submit tip: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update') {
        $id = (int) ($input['id'] ?? 0);
        $status = trim($input['status'] ?? '');
        $outcome = trim($input['outcome'] ?? 'No Outcome Yet');

        if ($id <= 0 || $status === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('UPDATE tips SET status = :status, outcome = :outcome WHERE id = :id');
            $stmt->execute([
                ':status' => $status,
                ':outcome' => $outcome,
                ':id' => $id,
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Tip updated successfully!',
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update tip: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid tip ID.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM tips WHERE id = :id');
            $stmt->execute([':id' => $id]);

            echo json_encode(['success' => true, 'message' => 'Tip deleted successfully!']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete tip: ' . $e->getMessage()]);
        }
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

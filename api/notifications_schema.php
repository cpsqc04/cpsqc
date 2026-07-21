<?php

/**
 * Ensure the shared notifications table exists with all required columns.
 */
function ensureNotificationsTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        patrol_id INT DEFAULT NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL DEFAULT '',
        message TEXT NOT NULL,
        link VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_patrol_id (patrol_id),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM notifications') as $row) {
        $columns[$row['Field']] = true;
    }

    if (!isset($columns['user_id'])) {
        $pdo->exec('ALTER TABLE notifications ADD COLUMN user_id INT DEFAULT NULL AFTER id');
    } else {
        $pdo->exec('ALTER TABLE notifications MODIFY COLUMN user_id INT DEFAULT NULL');
    }
    if (!isset($columns['patrol_id'])) {
        $pdo->exec('ALTER TABLE notifications ADD COLUMN patrol_id INT DEFAULT NULL AFTER user_id');
        $pdo->exec('ALTER TABLE notifications ADD INDEX idx_patrol_id (patrol_id)');
    }
    if (!isset($columns['title'])) {
        $pdo->exec("ALTER TABLE notifications ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT '' AFTER type");
    }
    if (!isset($columns['message'])) {
        $pdo->exec('ALTER TABLE notifications ADD COLUMN message TEXT NOT NULL AFTER title');
    }
    if (!isset($columns['link'])) {
        $pdo->exec('ALTER TABLE notifications ADD COLUMN link VARCHAR(255) DEFAULT NULL AFTER message');
    }
    if (!isset($columns['is_read'])) {
        $pdo->exec('ALTER TABLE notifications ADD COLUMN is_read TINYINT(1) DEFAULT 0 AFTER link');
    }
    if (!isset($columns['created_at'])) {
        $pdo->exec('ALTER TABLE notifications ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER is_read');
    }
}

function createAdminNotification(PDO $pdo, string $type, string $title, string $message, ?string $link = null, ?int $userId = null, ?string $createdAt = null): bool
{
    ensureNotificationsTable($pdo);

    if ($link !== null && $link !== '') {
        $checkStmt = $pdo->prepare('SELECT id FROM notifications WHERE type = :type AND link = :link LIMIT 1');
        $checkStmt->execute([':type' => $type, ':link' => $link]);
        if ($checkStmt->fetch()) {
            return false;
        }
    }

    $sql = 'INSERT INTO notifications (type, title, message, link, created_at' . ($userId !== null ? ', user_id' : '') . ') VALUES (:type, :title, :message, :link, ' . ($createdAt ? ':created_at' : 'NOW()') . ($userId !== null ? ', :user_id' : '') . ')';
    $stmt = $pdo->prepare($sql);
    $params = [
        ':type' => $type,
        ':title' => $title,
        ':message' => $message,
        ':link' => $link,
    ];
    if ($userId !== null) {
        $params[':user_id'] = $userId;
    }
    if ($createdAt) {
        $params[':created_at'] = $createdAt;
    }
    $stmt->execute($params);

    return true;
}

function createPatrolNotification(PDO $pdo, int $patrolId, string $type, string $title, string $message, ?string $link = null, ?string $createdAt = null): bool
{
    ensureNotificationsTable($pdo);

    if ($link !== null && $link !== '') {
        $checkStmt = $pdo->prepare('SELECT id FROM notifications WHERE patrol_id = :patrol_id AND type = :type AND link = :link LIMIT 1');
        $checkStmt->execute([':patrol_id' => $patrolId, ':type' => $type, ':link' => $link]);
        if ($checkStmt->fetch()) {
            return false;
        }
    }

    $sql = 'INSERT INTO notifications (patrol_id, type, title, message, link, created_at) VALUES (:patrol_id, :type, :title, :message, :link, ' . ($createdAt ? ':created_at' : 'NOW()') . ')';
    $stmt = $pdo->prepare($sql);
    $params = [
        ':patrol_id' => $patrolId,
        ':type' => $type,
        ':title' => $title,
        ':message' => $message,
        ':link' => $link,
    ];
    if ($createdAt) {
        $params[':created_at'] = $createdAt;
    }
    $stmt->execute($params);

    return true;
}

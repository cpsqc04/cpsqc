<?php

function ensureBulletinBoardTable(PDO $pdo): void
{
    $columns = [];
    $tableExists = false;

    try {
        foreach ($pdo->query('SHOW COLUMNS FROM bulletin_posts') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        $pdo->exec("CREATE TABLE bulletin_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            body TEXT DEFAULT NULL,
            target_audience VARCHAR(50) NOT NULL DEFAULT 'all',
            media_json LONGTEXT DEFAULT NULL,
            attachments_json LONGTEXT DEFAULT NULL,
            publish_at DATETIME DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            is_pinned TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_by INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_audience (target_audience),
            INDEX idx_pinned (is_pinned),
            INDEX idx_publish_at (publish_at),
            INDEX idx_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }

    $additions = [
        'title' => "ALTER TABLE bulletin_posts ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT '' AFTER id",
        'body' => 'ALTER TABLE bulletin_posts ADD COLUMN body TEXT DEFAULT NULL AFTER title',
        'target_audience' => "ALTER TABLE bulletin_posts ADD COLUMN target_audience VARCHAR(50) NOT NULL DEFAULT 'all' AFTER body",
        'media_json' => 'ALTER TABLE bulletin_posts ADD COLUMN media_json LONGTEXT DEFAULT NULL AFTER target_audience',
        'attachments_json' => 'ALTER TABLE bulletin_posts ADD COLUMN attachments_json LONGTEXT DEFAULT NULL AFTER media_json',
        'publish_at' => 'ALTER TABLE bulletin_posts ADD COLUMN publish_at DATETIME DEFAULT NULL AFTER attachments_json',
        'expires_at' => 'ALTER TABLE bulletin_posts ADD COLUMN expires_at DATETIME DEFAULT NULL AFTER publish_at',
        'is_pinned' => 'ALTER TABLE bulletin_posts ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER expires_at',
        'status' => "ALTER TABLE bulletin_posts ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'active' AFTER is_pinned",
        'created_by' => 'ALTER TABLE bulletin_posts ADD COLUMN created_by INT DEFAULT NULL AFTER status',
        'created_at' => 'ALTER TABLE bulletin_posts ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER created_by',
        'updated_at' => 'ALTER TABLE bulletin_posts ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    ];

    foreach ($additions as $column => $sql) {
        if (!isset($columns[$column])) {
            $pdo->exec($sql);
        }
    }
}

function archiveExpiredBulletinPosts(PDO $pdo): void
{
    $pdo->exec("UPDATE bulletin_posts
        SET status = 'archived'
        WHERE status = 'active'
          AND expires_at IS NOT NULL
          AND expires_at < NOW()");
}

function bulletinDecodeJsonList(?string $value): array
{
    if ($value === null || trim($value) === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? array_values(array_filter($decoded, static function ($item) {
        return is_string($item) && $item !== '';
    })) : [];
}

function bulletinNormalizeAudience(?string $audience, bool $allowResident = true): string
{
    $audience = strtolower(trim((string) $audience));
    $allowed = ['all', 'patrol', 'watcher'];
    if ($allowResident) {
        $allowed[] = 'resident';
    }
    return in_array($audience, $allowed, true) ? $audience : 'all';
}

function bulletinFormatPost(array $row): array
{
    $media = bulletinDecodeJsonList($row['media_json'] ?? null);
    $attachments = bulletinDecodeJsonList($row['attachments_json'] ?? null);

    return [
        'id' => (int) ($row['id'] ?? 0),
        'title' => (string) ($row['title'] ?? ''),
        'body' => (string) ($row['body'] ?? ''),
        'target_audience' => bulletinNormalizeAudience($row['target_audience'] ?? 'all'),
        'media' => $media,
        'attachments' => $attachments,
        'publish_at' => $row['publish_at'] ?? null,
        'expires_at' => $row['expires_at'] ?? null,
        'is_pinned' => !empty($row['is_pinned']),
        'status' => (string) ($row['status'] ?? 'active'),
        'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

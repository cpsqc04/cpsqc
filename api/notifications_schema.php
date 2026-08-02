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
        nw_member_id INT DEFAULT NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL DEFAULT '',
        message TEXT NOT NULL,
        link VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_patrol_id (patrol_id),
        INDEX idx_nw_member_id (nw_member_id),
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
    if (!isset($columns['nw_member_id'])) {
        try {
            $pdo->exec('ALTER TABLE notifications ADD COLUMN nw_member_id INT DEFAULT NULL AFTER patrol_id');
            $pdo->exec('ALTER TABLE notifications ADD INDEX idx_nw_member_id (nw_member_id)');
        } catch (PDOException $e) {
            // Column may already exist without being detected.
        }
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

function createNwMemberNotification(PDO $pdo, int $nwMemberId, string $type, string $title, string $message, ?string $link = null, ?string $createdAt = null): bool
{
    ensureNotificationsTable($pdo);

    if ($link !== null && $link !== '') {
        $checkStmt = $pdo->prepare('SELECT id FROM notifications WHERE nw_member_id = :nw_member_id AND type = :type AND link = :link LIMIT 1');
        $checkStmt->execute([':nw_member_id' => $nwMemberId, ':type' => $type, ':link' => $link]);
        if ($checkStmt->fetch()) {
            return false;
        }
    }

    $sql = 'INSERT INTO notifications (nw_member_id, type, title, message, link, created_at) VALUES (:nw_member_id, :type, :title, :message, :link, ' . ($createdAt ? ':created_at' : 'NOW()') . ')';
    $stmt = $pdo->prepare($sql);
    $params = [
        ':nw_member_id' => $nwMemberId,
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

/**
 * Notify admin of a named patrol or watcher portal activity.
 */
function notifyAdminActorActivity(
    PDO $pdo,
    string $actorRole,
    string $actorName,
    string $activity,
    ?string $link = null
): bool {
    ensureNotificationsTable($pdo);

    $actorRole = strtolower(trim($actorRole));
    $actorName = trim($actorName);
    $activity = trim($activity);
    if ($actorName === '' || $activity === '') {
        return false;
    }

    $roleLabel = $actorRole === 'watcher' || $actorRole === 'neighborhood_watch'
        ? 'Neighborhood Watch'
        : 'Patrol';

    $title = $roleLabel . ' Activity';
    $message = $actorName . ' ' . ltrim($activity);

    $uniqueLink = $link;
    if ($uniqueLink === null || trim($uniqueLink) === '') {
        $uniqueLink = 'portal-activity:' . $actorRole . ':' . time() . ':' . bin2hex(random_bytes(4));
    } else {
        $uniqueLink .= (str_contains($uniqueLink, '?') ? '&' : '?') . 'activity=' . rawurlencode(time() . '_' . bin2hex(random_bytes(3)));
    }

    return createAdminNotification($pdo, 'portal_activity', $title, $message, $uniqueLink);
}

/**
 * Notify Patrol and/or Neighborhood Watch members about a bulletin announcement.
 */
function notifyBulletinAudiences(PDO $pdo, array $post): void
{
    ensureNotificationsTable($pdo);

    $audience = strtolower(trim((string) ($post['target_audience'] ?? 'all')));
    $status = strtolower(trim((string) ($post['status'] ?? 'active')));
    if ($status !== 'active') {
        return;
    }

    $publishAt = $post['publish_at'] ?? null;
    if ($publishAt !== null && $publishAt !== '' && strtotime((string) $publishAt) > time()) {
        return;
    }

    $postId = (int) ($post['id'] ?? 0);
    if ($postId <= 0) {
        return;
    }

    $title = trim((string) ($post['title'] ?? 'New Announcement'));
    $body = trim((string) ($post['body'] ?? ''));
    $snippet = $body !== ''
        ? (strlen($body) > 160 ? substr($body, 0, 157) . '...' : $body)
        : 'A new announcement was posted on the Digital Bulletin.';
    $notifTitle = 'New Announcement';
    $message = $title . ' — ' . $snippet;
    $linkPatrol = 'tab:bulletin:' . $postId;
    $linkWatcher = 'section:bulletinSection:' . $postId;
    $createdAt = $post['publish_at'] ?: ($post['created_at'] ?? null);

    $notifyPatrol = in_array($audience, ['all', 'patrol'], true);
    $notifyWatcher = in_array($audience, ['all', 'watcher'], true);

    if ($notifyPatrol) {
        try {
            $patrolStmt = $pdo->query('SELECT id FROM patrols ORDER BY id ASC');
            $patrolIds = $patrolStmt ? $patrolStmt->fetchAll(PDO::FETCH_COLUMN) : [];
            foreach ($patrolIds as $patrolId) {
                createPatrolNotification(
                    $pdo,
                    (int) $patrolId,
                    'bulletin_announcement',
                    $notifTitle,
                    $message,
                    $linkPatrol,
                    is_string($createdAt) && $createdAt !== '' ? $createdAt : null
                );
            }
        } catch (PDOException $e) {
            error_log('Bulletin patrol notify failed: ' . $e->getMessage());
        }
    }

    if ($notifyWatcher) {
        try {
            require_once __DIR__ . '/neighborhood-watcher-members-schema.php';
            ensureNwMembersTable($pdo);
            $table = nwMembersTableName();
            $nwStmt = $pdo->query("SELECT id FROM {$table} WHERE status = 'Active'");
            $nwIds = $nwStmt ? $nwStmt->fetchAll(PDO::FETCH_COLUMN) : [];
            foreach ($nwIds as $nwId) {
                createNwMemberNotification(
                    $pdo,
                    (int) $nwId,
                    'bulletin_announcement',
                    $notifTitle,
                    $message,
                    $linkWatcher,
                    is_string($createdAt) && $createdAt !== '' ? $createdAt : null
                );
            }
        } catch (PDOException $e) {
            error_log('Bulletin watcher notify failed: ' . $e->getMessage());
        }
    }
}

/**
 * Sync active bulletin announcements into a patrol member's notification feed.
 * Covers missed creates, new patrol accounts, and deferred publish_at times.
 */
function syncBulletinNotificationsForPatrol(PDO $pdo, int $patrolId): int
{
    if ($patrolId <= 0) {
        return 0;
    }

    ensureNotificationsTable($pdo);
    require_once __DIR__ . '/bulletin_board_schema.php';
    ensureBulletinBoardTable($pdo);
    archiveExpiredBulletinPosts($pdo);

    $synced = 0;
    try {
        $stmt = $pdo->query("
            SELECT id, title, body, target_audience, status, publish_at, created_at
            FROM bulletin_posts
            WHERE status = 'active'
              AND (target_audience = 'all' OR target_audience = 'patrol')
              AND (publish_at IS NULL OR publish_at <= NOW())
              AND (expires_at IS NULL OR expires_at > NOW())
              AND COALESCE(publish_at, created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY COALESCE(publish_at, created_at) DESC, id DESC
            LIMIT 50
        ");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $row) {
            $postId = (int) ($row['id'] ?? 0);
            if ($postId <= 0) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? 'New Announcement'));
            $body = trim((string) ($row['body'] ?? ''));
            $snippet = $body !== ''
                ? (strlen($body) > 160 ? substr($body, 0, 157) . '...' : $body)
                : 'A new announcement was posted on the Digital Bulletin.';
            $createdAt = $row['publish_at'] ?: ($row['created_at'] ?? null);
            if (createPatrolNotification(
                $pdo,
                $patrolId,
                'bulletin_announcement',
                'New Announcement',
                $title . ' — ' . $snippet,
                'tab:bulletin:' . $postId,
                is_string($createdAt) && $createdAt !== '' ? $createdAt : null
            )) {
                $synced++;
            }
        }
    } catch (PDOException $e) {
        error_log('Bulletin patrol sync failed: ' . $e->getMessage());
    }

    return $synced;
}

/**
 * Sync active bulletin announcements into a Neighborhood Watch member feed.
 */
function syncBulletinNotificationsForNwMember(PDO $pdo, int $nwMemberId): int
{
    if ($nwMemberId <= 0) {
        return 0;
    }

    ensureNotificationsTable($pdo);
    require_once __DIR__ . '/bulletin_board_schema.php';
    ensureBulletinBoardTable($pdo);
    archiveExpiredBulletinPosts($pdo);

    $synced = 0;
    try {
        $stmt = $pdo->query("
            SELECT id, title, body, target_audience, status, publish_at, created_at
            FROM bulletin_posts
            WHERE status = 'active'
              AND (target_audience = 'all' OR target_audience = 'watcher')
              AND (publish_at IS NULL OR publish_at <= NOW())
              AND (expires_at IS NULL OR expires_at > NOW())
              AND COALESCE(publish_at, created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY COALESCE(publish_at, created_at) DESC, id DESC
            LIMIT 50
        ");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $row) {
            $postId = (int) ($row['id'] ?? 0);
            if ($postId <= 0) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? 'New Announcement'));
            $body = trim((string) ($row['body'] ?? ''));
            $snippet = $body !== ''
                ? (strlen($body) > 160 ? substr($body, 0, 157) . '...' : $body)
                : 'A new announcement was posted on the Digital Bulletin.';
            $createdAt = $row['publish_at'] ?: ($row['created_at'] ?? null);
            if (createNwMemberNotification(
                $pdo,
                $nwMemberId,
                'bulletin_announcement',
                'New Announcement',
                $title . ' — ' . $snippet,
                'section:bulletinSection:' . $postId,
                is_string($createdAt) && $createdAt !== '' ? $createdAt : null
            )) {
                $synced++;
            }
        }
    } catch (PDOException $e) {
        error_log('Bulletin watcher sync failed: ' . $e->getMessage());
    }

    return $synced;
}

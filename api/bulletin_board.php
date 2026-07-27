<?php

/**
 * Bulletin Board API
 *
 * GET    — list posts (admin: all; patrol/watcher: feed for audience)
 * POST   — create (admin, multipart or JSON)
 * PUT    — update (admin)
 * DELETE — delete (admin) ?id=
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/bulletin_board_schema.php';
require_once __DIR__ . '/../includes/bulletin_media.php';
require_once __DIR__ . '/../includes/bpso_auth.php';
require_once __DIR__ . '/../includes/neighborhood-watcher-member-auth.php';
require_once __DIR__ . '/notifications_schema.php';

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit;
}

try {
    ensureBulletinBoardTable($pdo);
    archiveExpiredBulletinPosts($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare bulletin board: ' . $e->getMessage()]);
    exit;
}

$isAdmin = isAdminLoggedIn();
$isPatrol = isBpsoLoggedIn();
$isWatcher = isNwMemberLoggedIn();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$rawAudienceParam = strtolower(trim((string) ($_GET['audience'] ?? '')));
// Public feed is limited to the Resident Digital Bulletin (active posts only).
$isPublicResidentFeed = $method === 'GET'
    && !$isAdmin
    && !$isPatrol
    && !$isWatcher
    && $rawAudienceParam === 'resident';

if (!$isAdmin && !$isPatrol && !$isWatcher && !$isPublicResidentFeed) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

function bulletinReadJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function bulletinParseDateTime(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d H:i:s', $ts);
}

try {
    if ($method === 'GET') {
        $statusFilter = strtolower(trim($_GET['status'] ?? ($isAdmin ? 'all' : 'active')));
        $audienceFilter = bulletinNormalizeAudience($_GET['audience'] ?? 'all');

        // Portal Digital Bulletin feeds: always include target_audience = role OR 'all'.
        // Prefer the explicit ?audience= query so this still works if an admin session
        // shares the same browser cookie jar.
        $portalFeedAudience = null;
        if (in_array($rawAudienceParam, ['patrol', 'watcher', 'resident'], true)) {
            $portalFeedAudience = $rawAudienceParam;
        } elseif (!$isAdmin) {
            if ($isPatrol) {
                $portalFeedAudience = 'patrol';
            } elseif ($isWatcher) {
                $portalFeedAudience = 'watcher';
            } elseif ($isPublicResidentFeed) {
                $portalFeedAudience = 'resident';
            }
        }

        $where = [];
        $params = [];

        if ($portalFeedAudience !== null) {
            $where[] = '(target_audience = :aud OR target_audience = \'all\')';
            $params[':aud'] = $portalFeedAudience;
            $where[] = 'status = \'active\'';
            $where[] = '(publish_at IS NULL OR publish_at <= NOW())';
            $where[] = '(expires_at IS NULL OR expires_at > NOW())';
        } else {
            // Admin management list
            if ($statusFilter !== 'all') {
                $where[] = 'status = :status';
                $params[':status'] = $statusFilter === '' ? 'active' : $statusFilter;
            }
            if (isset($_GET['audience']) && trim((string) $_GET['audience']) !== '') {
                $where[] = 'target_audience = :aud';
                $params[':aud'] = $audienceFilter;
            }
        }

        $sql = 'SELECT * FROM bulletin_posts';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        if ($portalFeedAudience !== null) {
            // SQL order is refined in PHP below for Digital Bulletin rules.
            $sql .= ' ORDER BY is_pinned DESC, created_at ASC, id ASC';
        } else {
            $sql .= ' ORDER BY is_pinned DESC, COALESCE(publish_at, created_at) DESC, id DESC';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data = array_map('bulletinFormatPost', $rows);

        if ($portalFeedAudience !== null) {
            // Digital Bulletin order:
            // 1) Pinned first (newest pinned first among pins)
            // 2) Non-pinned: oldest uploaded → newest uploaded last
            usort($data, static function (array $a, array $b): int {
                $pinA = !empty($a['is_pinned']) ? 1 : 0;
                $pinB = !empty($b['is_pinned']) ? 1 : 0;
                if ($pinA !== $pinB) {
                    return $pinB <=> $pinA;
                }

                $timeA = strtotime((string) ($a['created_at'] ?: ($a['publish_at'] ?? ''))) ?: 0;
                $timeB = strtotime((string) ($b['created_at'] ?: ($b['publish_at'] ?? ''))) ?: 0;
                $idA = (int) ($a['id'] ?? 0);
                $idB = (int) ($b['id'] ?? 0);

                if ($pinA === 1) {
                    // Pinned group: most recent first
                    if ($timeA !== $timeB) {
                        return $timeB <=> $timeA;
                    }
                    return $idB <=> $idA;
                }

                // Non-pinned: earliest uploaded first, last uploaded last
                if ($timeA !== $timeB) {
                    return $timeA <=> $timeB;
                }
                return $idA <=> $idB;
            });
        }

        echo json_encode([
            'success' => true,
            'count' => count($data),
            'feed_audience' => $portalFeedAudience,
            'data' => $data,
        ]);
        exit;
    }

    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only admins can manage the bulletin board.']);
        exit;
    }

    if ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $body = bulletinReadJsonBody();
            $id = (int) ($body['id'] ?? 0);
        }
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Post id is required.']);
            exit;
        }
        $stmt = $pdo->prepare('DELETE FROM bulletin_posts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Post deleted.']);
        exit;
    }

    if ($method === 'POST' || $method === 'PUT') {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        $isMultipart = stripos($contentType, 'multipart/form-data') !== false;
        $input = $isMultipart ? $_POST : bulletinReadJsonBody();
        if ($input === [] && !empty($_POST)) {
            $input = $_POST;
        }

        $id = (int) ($input['id'] ?? ($_GET['id'] ?? 0));
        if ($method === 'PUT' && $id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Post id is required for update.']);
            exit;
        }

        $title = trim((string) ($input['title'] ?? ''));
        $body = trim((string) ($input['body'] ?? ''));
        $rawAudience = $input['target_audience'] ?? $_POST['target_audience'] ?? 'all';
        $audience = bulletinNormalizeAudience(is_string($rawAudience) ? $rawAudience : 'all', false);
        $publishAt = bulletinParseDateTime($input['publish_at'] ?? null);
        $expiresAt = bulletinParseDateTime($input['expires_at'] ?? null);
        $isPinned = !empty($input['is_pinned']) && ($input['is_pinned'] === true || $input['is_pinned'] === '1' || $input['is_pinned'] === 1 || $input['is_pinned'] === 'true');
        $status = strtolower(trim((string) ($input['status'] ?? 'active')));
        if (!in_array($status, ['active', 'archived', 'draft'], true)) {
            $status = 'active';
        }

        if ($title === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Title is required.']);
            exit;
        }

        $existingMedia = [];
        $existingAttachments = [];
        if ($id > 0) {
            $existingStmt = $pdo->prepare('SELECT media_json, attachments_json FROM bulletin_posts WHERE id = :id LIMIT 1');
            $existingStmt->execute([':id' => $id]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Post not found.']);
                exit;
            }
            $existingMedia = bulletinDecodeJsonList($existing['media_json'] ?? null);
            $existingAttachments = bulletinDecodeJsonList($existing['attachments_json'] ?? null);
        }

        if (isset($input['media']) && is_array($input['media'])) {
            $existingMedia = array_values(array_filter($input['media'], 'is_string'));
        } elseif (isset($input['existing_media'])) {
            $decoded = is_string($input['existing_media']) ? json_decode($input['existing_media'], true) : $input['existing_media'];
            if (is_array($decoded)) {
                $existingMedia = array_values(array_filter($decoded, 'is_string'));
            }
        }

        if (isset($input['attachments']) && is_array($input['attachments'])) {
            $existingAttachments = array_values(array_filter($input['attachments'], 'is_string'));
        } elseif (isset($input['existing_attachments'])) {
            $decoded = is_string($input['existing_attachments']) ? json_decode($input['existing_attachments'], true) : $input['existing_attachments'];
            if (is_array($decoded)) {
                $existingAttachments = array_values(array_filter($decoded, 'is_string'));
            }
        }

        if ($isMultipart) {
            foreach (bulletinNormalizeFilesArray($_FILES['media'] ?? null) as $file) {
                $existingMedia[] = bulletinStoreUploadedFile($file, 'media');
            }
            foreach (bulletinNormalizeFilesArray($_FILES['attachments'] ?? null) as $file) {
                $existingAttachments[] = bulletinStoreUploadedFile($file, 'file');
            }
        }

        $mediaJson = json_encode(array_values(array_unique($existingMedia)));
        $attachmentsJson = json_encode(array_values(array_unique($existingAttachments)));
        $createdBy = (int) ($_SESSION['user_id'] ?? 0) ?: null;

        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE bulletin_posts SET
                title = :title,
                body = :body,
                target_audience = :audience,
                media_json = :media,
                attachments_json = :attachments,
                publish_at = :publish_at,
                expires_at = :expires_at,
                is_pinned = :pinned,
                status = :status
                WHERE id = :id');
            $stmt->execute([
                ':title' => $title,
                ':body' => $body !== '' ? $body : null,
                ':audience' => $audience,
                ':media' => $mediaJson,
                ':attachments' => $attachmentsJson,
                ':publish_at' => $publishAt,
                ':expires_at' => $expiresAt,
                ':pinned' => $isPinned ? 1 : 0,
                ':status' => $status,
                ':id' => $id,
            ]);
            $message = 'Bulletin post updated.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO bulletin_posts
                (title, body, target_audience, media_json, attachments_json, publish_at, expires_at, is_pinned, status, created_by)
                VALUES (:title, :body, :audience, :media, :attachments, :publish_at, :expires_at, :pinned, :status, :created_by)');
            $stmt->execute([
                ':title' => $title,
                ':body' => $body !== '' ? $body : null,
                ':audience' => $audience,
                ':media' => $mediaJson,
                ':attachments' => $attachmentsJson,
                ':publish_at' => $publishAt,
                ':expires_at' => $expiresAt,
                ':pinned' => $isPinned ? 1 : 0,
                ':status' => $status,
                ':created_by' => $createdBy,
            ]);
            $id = (int) $pdo->lastInsertId();
            $message = 'Bulletin post created.';
        }

        $fetch = $pdo->prepare('SELECT * FROM bulletin_posts WHERE id = :id LIMIT 1');
        $fetch->execute([':id' => $id]);
        $row = $fetch->fetch(PDO::FETCH_ASSOC);
        $formatted = bulletinFormatPost($row ?: ['id' => $id, 'title' => $title]);

        // Notify Patrol/Watcher for every active announcement (create or first publish).
        // Deduped by type+link so edits do not spam duplicate notifications.
        try {
            notifyBulletinAudiences($pdo, $formatted);
        } catch (Throwable $notifyError) {
            error_log('Bulletin notify failed: ' . $notifyError->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $formatted,
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

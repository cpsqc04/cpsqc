<?php

/**
 * Admin-triggered password reset tokens (email link flow).
 */

function passwordResetTokensColumns(PDO $pdo): array
{
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM password_reset_tokens') as $row) {
        $columns[$row['Field']] = $row;
    }
    return $columns;
}

function ensurePasswordResetTokensTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_type VARCHAR(20) NOT NULL,
        account_id INT NOT NULL,
        email VARCHAR(255) NOT NULL,
        token_hash VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = passwordResetTokensColumns($pdo);

    $alterations = [
        'account_type' => 'ALTER TABLE password_reset_tokens ADD COLUMN account_type VARCHAR(20) NOT NULL DEFAULT \'admin\' AFTER id',
        'account_id' => 'ALTER TABLE password_reset_tokens ADD COLUMN account_id INT NOT NULL DEFAULT 0 AFTER account_type',
        'email' => 'ALTER TABLE password_reset_tokens ADD COLUMN email VARCHAR(255) NOT NULL DEFAULT \'\' AFTER account_id',
        'token_hash' => 'ALTER TABLE password_reset_tokens ADD COLUMN token_hash VARCHAR(64) NOT NULL DEFAULT \'\' AFTER email',
        'expires_at' => 'ALTER TABLE password_reset_tokens ADD COLUMN expires_at DATETIME NULL AFTER token_hash',
        'used_at' => 'ALTER TABLE password_reset_tokens ADD COLUMN used_at DATETIME DEFAULT NULL AFTER expires_at',
        'created_at' => 'ALTER TABLE password_reset_tokens ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER used_at',
    ];

    foreach ($alterations as $column => $sql) {
        if (!isset($columns[$column])) {
            $pdo->exec($sql);
        }
    }

    // Legacy installs may have a required user_id column (sometimes FK to admins).
    // Soften it so Admin / Patrol / NW password resets can all insert successfully.
    if (isset($columns['user_id'])) {
        try {
            $fkStmt = $pdo->query("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'password_reset_tokens'
                  AND COLUMN_NAME = 'user_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            foreach ($fkStmt->fetchAll(PDO::FETCH_ASSOC) as $fkRow) {
                $constraint = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($fkRow['CONSTRAINT_NAME'] ?? ''));
                if ($constraint !== '') {
                    $pdo->exec('ALTER TABLE password_reset_tokens DROP FOREIGN KEY `' . $constraint . '`');
                }
            }
        } catch (PDOException $e) {
            // Best-effort FK cleanup.
        }

        try {
            $pdo->exec('ALTER TABLE password_reset_tokens MODIFY COLUMN user_id INT NULL DEFAULT NULL');
        } catch (PDOException $e) {
            // Column may already be nullable.
        }

        // Backfill account_id from legacy user_id when missing.
        try {
            $pdo->exec("UPDATE password_reset_tokens
                SET account_id = user_id
                WHERE (account_id IS NULL OR account_id = 0)
                  AND user_id IS NOT NULL
                  AND user_id > 0");
            $pdo->exec("UPDATE password_reset_tokens
                SET account_type = 'admin'
                WHERE (account_type IS NULL OR account_type = '')
                  AND user_id IS NOT NULL");
        } catch (PDOException $e) {
            // Best-effort backfill only.
        }
    }

    try {
        $indexes = [];
        foreach ($pdo->query('SHOW INDEX FROM password_reset_tokens') as $row) {
            $indexes[$row['Key_name']] = true;
        }
        if (empty($indexes['idx_token_hash'])) {
            $pdo->exec('ALTER TABLE password_reset_tokens ADD INDEX idx_token_hash (token_hash)');
        }
        if (empty($indexes['idx_account'])) {
            $pdo->exec('ALTER TABLE password_reset_tokens ADD INDEX idx_account (account_type, account_id)');
        }
        if (empty($indexes['idx_expires'])) {
            $pdo->exec('ALTER TABLE password_reset_tokens ADD INDEX idx_expires (expires_at)');
        }
    } catch (PDOException $e) {
        // Indexes are optional for functionality.
    }
}

/**
 * Create a one-time reset token and return the raw token (for email URL).
 */
function createPasswordResetToken(PDO $pdo, string $accountType, int $accountId, string $email, int $ttlMinutes = 60): string
{
    ensurePasswordResetTokensTable($pdo);

    $accountType = strtolower(trim($accountType));
    $email = trim($email);
    if (!in_array($accountType, ['admin', 'bpso', 'nw'], true) || $accountId <= 0 || $email === '') {
        throw new InvalidArgumentException('Invalid password reset token request.');
    }

    $columns = passwordResetTokensColumns($pdo);
    $hasUserId = isset($columns['user_id']);

    // Invalidate prior unused tokens for this account.
    if ($hasUserId) {
        $invalidate = $pdo->prepare('
            UPDATE password_reset_tokens
            SET used_at = NOW()
            WHERE used_at IS NULL
              AND (
                    (account_type = :account_type AND account_id = :account_id)
                 OR (account_type = :account_type2 AND user_id = :user_id)
              )
        ');
        $invalidate->execute([
            ':account_type' => $accountType,
            ':account_id' => $accountId,
            ':account_type2' => $accountType,
            ':user_id' => $accountId,
        ]);
    } else {
        $invalidate = $pdo->prepare('
            UPDATE password_reset_tokens
            SET used_at = NOW()
            WHERE account_type = :account_type
              AND account_id = :account_id
              AND used_at IS NULL
        ');
        $invalidate->execute([
            ':account_type' => $accountType,
            ':account_id' => $accountId,
        ]);
    }

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + max(5, $ttlMinutes) * 60);

    if ($hasUserId) {
        // Populate legacy user_id for older schemas. Retry with NULL if a leftover
        // admins FK rejects Patrol / NW account IDs.
        try {
            $insert = $pdo->prepare('
                INSERT INTO password_reset_tokens (account_type, account_id, user_id, email, token_hash, expires_at)
                VALUES (:account_type, :account_id, :user_id, :email, :token_hash, :expires_at)
            ');
            $insert->execute([
                ':account_type' => $accountType,
                ':account_id' => $accountId,
                ':user_id' => $accountId,
                ':email' => $email,
                ':token_hash' => $tokenHash,
                ':expires_at' => $expiresAt,
            ]);
        } catch (PDOException $e) {
            $insert = $pdo->prepare('
                INSERT INTO password_reset_tokens (account_type, account_id, user_id, email, token_hash, expires_at)
                VALUES (:account_type, :account_id, NULL, :email, :token_hash, :expires_at)
            ');
            $insert->execute([
                ':account_type' => $accountType,
                ':account_id' => $accountId,
                ':email' => $email,
                ':token_hash' => $tokenHash,
                ':expires_at' => $expiresAt,
            ]);
        }
    } else {
        $insert = $pdo->prepare('
            INSERT INTO password_reset_tokens (account_type, account_id, email, token_hash, expires_at)
            VALUES (:account_type, :account_id, :email, :token_hash, :expires_at)
        ');
        $insert->execute([
            ':account_type' => $accountType,
            ':account_id' => $accountId,
            ':email' => $email,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
        ]);
    }

    return $rawToken;
}

/**
 * @return array{id:int,account_type:string,account_id:int,email:string}|null
 */
function findValidPasswordResetToken(PDO $pdo, string $rawToken): ?array
{
    ensurePasswordResetTokensTable($pdo);
    $rawToken = trim($rawToken);
    if ($rawToken === '' || strlen($rawToken) < 32) {
        return null;
    }

    $tokenHash = hash('sha256', $rawToken);
    $columns = passwordResetTokensColumns($pdo);
    $selectUserId = isset($columns['user_id']) ? ', user_id' : '';

    $stmt = $pdo->prepare("
        SELECT id, account_type, account_id, email{$selectUserId}
        FROM password_reset_tokens
        WHERE token_hash = :token_hash
          AND used_at IS NULL
          AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([':token_hash' => $tokenHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $accountId = (int) ($row['account_id'] ?? 0);
    if ($accountId <= 0 && isset($row['user_id'])) {
        $accountId = (int) $row['user_id'];
    }

    $accountType = strtolower(trim((string) ($row['account_type'] ?? '')));
    if ($accountType === '' && $accountId > 0) {
        $accountType = 'admin';
    }

    return [
        'id' => (int) $row['id'],
        'account_type' => $accountType,
        'account_id' => $accountId,
        'email' => (string) $row['email'],
    ];
}

function markPasswordResetTokenUsed(PDO $pdo, int $tokenId): void
{
    $stmt = $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id');
    $stmt->execute([':id' => $tokenId]);
}

function buildPasswordResetUrl(string $rawToken): string
{
    require_once __DIR__ . '/app_url.php';
    return getAppBaseUrl() . '/reset-password.php?token=' . rawurlencode($rawToken);
}

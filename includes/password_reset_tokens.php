<?php

/**
 * Admin-triggered password reset tokens (email link flow).
 */

function passwordResetTokensColumns(PDO $pdo): array
{
    $columns = [];
    try {
        foreach ($pdo->query('SHOW COLUMNS FROM password_reset_tokens') as $row) {
            $columns[$row['Field']] = $row;
        }
    } catch (PDOException $e) {
        // Table may not exist yet.
    }
    return $columns;
}

function passwordResetTokensTableExists(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
        return $stmt !== false && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function passwordResetTokensCreateFreshTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE password_reset_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_type VARCHAR(20) NOT NULL,
        account_id INT NOT NULL,
        email VARCHAR(255) NOT NULL,
        token_hash VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token_hash (token_hash),
        INDEX idx_account (account_type, account_id),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function passwordResetTokensSupportedColumns(): array
{
    return [
        'id' => true,
        'account_type' => true,
        'account_id' => true,
        'email' => true,
        'token_hash' => true,
        'expires_at' => true,
        'used_at' => true,
        'created_at' => true,
        // Optional legacy column we can populate safely.
        'user_id' => true,
    ];
}

function passwordResetTokensColumnRequiresValue(array $columnMeta): bool
{
    $null = strtoupper((string) ($columnMeta['Null'] ?? ''));
    $default = $columnMeta['Default'] ?? null;
    $extra = strtolower((string) ($columnMeta['Extra'] ?? ''));

    if ($null !== 'NO') {
        return false;
    }
    if ($default !== null && $default !== '') {
        return false;
    }
    // Auto-increment / timestamp defaults are fine.
    if (str_contains($extra, 'auto_increment')) {
        return false;
    }
    if (str_contains($extra, 'default_generated') || str_contains($extra, 'on update')) {
        return false;
    }

    return true;
}

/**
 * True when the live table has required legacy fields (otp, token, etc.)
 * that the link-based reset flow does not populate.
 */
function passwordResetTokensIsLegacyIncompatible(array $columns): bool
{
    $supported = passwordResetTokensSupportedColumns();

    foreach ($columns as $name => $meta) {
        if (isset($supported[$name])) {
            // Legacy required user_id is still incompatible for NW/Patrol unless nullable.
            if ($name === 'user_id' && passwordResetTokensColumnRequiresValue($meta)) {
                return true;
            }
            continue;
        }

        if (passwordResetTokensColumnRequiresValue($meta)) {
            return true;
        }
    }

    // New flow requires these columns.
    foreach (['account_type', 'account_id', 'token_hash', 'expires_at'] as $required) {
        if (!isset($columns[$required])) {
            // Missing columns can be added, but if OTP-era schema is present prefer rebuild.
            if (isset($columns['otp']) || isset($columns['token']) || isset($columns['code'])) {
                return true;
            }
        }
    }

    return isset($columns['otp']) || isset($columns['token']) || isset($columns['code']);
}

function passwordResetTokensDropAllForeignKeys(PDO $pdo): void
{
    try {
        $fkStmt = $pdo->query("
            SELECT DISTINCT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'password_reset_tokens'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        foreach ($fkStmt->fetchAll(PDO::FETCH_ASSOC) as $fkRow) {
            $constraint = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($fkRow['CONSTRAINT_NAME'] ?? ''));
            if ($constraint !== '') {
                try {
                    $pdo->exec('ALTER TABLE password_reset_tokens DROP FOREIGN KEY `' . $constraint . '`');
                } catch (PDOException $e) {
                    // Ignore missing constraints.
                }
            }
        }
    } catch (PDOException $e) {
        // Best-effort FK cleanup.
    }

    foreach (['password_reset_tokens_ibfk_1', 'password_reset_tokens_ibfk_2', 'fk_password_reset_user'] as $constraint) {
        try {
            $pdo->exec('ALTER TABLE password_reset_tokens DROP FOREIGN KEY `' . $constraint . '`');
        } catch (PDOException $e) {
            // Ignore missing constraints.
        }
    }
}

function passwordResetTokensRebuildCleanTable(PDO $pdo): void
{
    passwordResetTokensDropAllForeignKeys($pdo);
    $backup = 'password_reset_tokens_legacy_' . date('Ymd_His');
    if (passwordResetTokensTableExists($pdo)) {
        $pdo->exec('RENAME TABLE password_reset_tokens TO `' . str_replace('`', '``', $backup) . '`');
    }
    passwordResetTokensCreateFreshTable($pdo);
}

function ensurePasswordResetTokensTable(PDO $pdo): void
{
    if (!passwordResetTokensTableExists($pdo)) {
        passwordResetTokensCreateFreshTable($pdo);
        return;
    }

    $columns = passwordResetTokensColumns($pdo);

    // OTP-era / admin-only schemas are incompatible with link-based multi-account resets.
    if (passwordResetTokensIsLegacyIncompatible($columns)) {
        // Try softening only user_id first when that is the sole blocker.
        $onlyUserIdBlocker = isset($columns['user_id'])
            && passwordResetTokensColumnRequiresValue($columns['user_id'])
            && !isset($columns['otp'])
            && !isset($columns['token'])
            && !isset($columns['code']);

        if ($onlyUserIdBlocker) {
            passwordResetTokensDropAllForeignKeys($pdo);
            try {
                $pdo->exec('ALTER TABLE password_reset_tokens MODIFY COLUMN user_id INT NULL DEFAULT NULL');
            } catch (PDOException $e) {
                // Fall through to rebuild.
            }
            $columns = passwordResetTokensColumns($pdo);
        }

        if (passwordResetTokensIsLegacyIncompatible($columns)) {
            passwordResetTokensRebuildCleanTable($pdo);
            $columns = passwordResetTokensColumns($pdo);
        }
    }

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

    $columns = passwordResetTokensColumns($pdo);
    if (isset($columns['user_id'])) {
        passwordResetTokensDropAllForeignKeys($pdo);
        try {
            $pdo->exec('ALTER TABLE password_reset_tokens MODIFY COLUMN user_id INT NULL DEFAULT NULL');
        } catch (PDOException $e) {
            // Column may already be nullable.
        }

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

    // Soften leftover required legacy columns if rebuild was skipped somehow.
    foreach (['otp', 'token', 'code'] as $legacyCol) {
        if (!isset($columns[$legacyCol])) {
            continue;
        }
        try {
            $pdo->exec('ALTER TABLE password_reset_tokens MODIFY COLUMN `' . $legacyCol . '` VARCHAR(255) NULL DEFAULT NULL');
        } catch (PDOException $e) {
            // Ignore; rebuild path handles hard failures.
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
function createPasswordResetToken(PDO $pdo, string $accountType, int $accountId, string $email, int $ttlMinutes = 10): string
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
                 OR user_id = :user_id
              )
        ');
        $invalidate->execute([
            ':account_type' => $accountType,
            ':account_id' => $accountId,
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

    $attempts = [];
    if ($hasUserId) {
        $attempts[] = [
            'sql' => 'INSERT INTO password_reset_tokens (account_type, account_id, user_id, email, token_hash, expires_at)
                      VALUES (:account_type, :account_id, :user_id, :email, :token_hash, :expires_at)',
            'params' => [
                ':account_type' => $accountType,
                ':account_id' => $accountId,
                ':user_id' => $accountId,
                ':email' => $email,
                ':token_hash' => $tokenHash,
                ':expires_at' => $expiresAt,
            ],
        ];
        $attempts[] = [
            'sql' => 'INSERT INTO password_reset_tokens (account_type, account_id, user_id, email, token_hash, expires_at)
                      VALUES (:account_type, :account_id, NULL, :email, :token_hash, :expires_at)',
            'params' => [
                ':account_type' => $accountType,
                ':account_id' => $accountId,
                ':email' => $email,
                ':token_hash' => $tokenHash,
                ':expires_at' => $expiresAt,
            ],
        ];
    }
    $attempts[] = [
        'sql' => 'INSERT INTO password_reset_tokens (account_type, account_id, email, token_hash, expires_at)
                  VALUES (:account_type, :account_id, :email, :token_hash, :expires_at)',
        'params' => [
            ':account_type' => $accountType,
            ':account_id' => $accountId,
            ':email' => $email,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
        ],
    ];

    $lastError = null;
    foreach ($attempts as $attempt) {
        try {
            $insert = $pdo->prepare($attempt['sql']);
            $insert->execute($attempt['params']);
            return $rawToken;
        } catch (PDOException $e) {
            $lastError = $e;
        }
    }

    // Rebuild on any legacy "Field X doesn't have a default value" / schema mismatch.
    $message = $lastError ? $lastError->getMessage() : '';
    $shouldRebuild = $lastError && (
        str_contains($message, "doesn't have a default value")
        || str_contains($message, 'user_id')
        || str_contains($message, 'otp')
        || str_contains($message, 'Unknown column')
    );

    if ($shouldRebuild) {
        try {
            passwordResetTokensRebuildCleanTable($pdo);
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
            return $rawToken;
        } catch (Throwable $rebuildError) {
            throw new RuntimeException(
                'Password reset token storage is incompatible on this server. '
                . $rebuildError->getMessage(),
                0,
                $rebuildError
            );
        }
    }

    throw $lastError ?: new RuntimeException('Failed to create password reset token.');
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

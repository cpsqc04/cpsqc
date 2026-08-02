<?php

/**
 * Admin-triggered password reset tokens (email link flow).
 */

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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token_hash (token_hash),
        INDEX idx_account (account_type, account_id),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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

    // Invalidate prior unused tokens for this account.
    $invalidate = $pdo->prepare("
        UPDATE password_reset_tokens
        SET used_at = NOW()
        WHERE account_type = :account_type
          AND account_id = :account_id
          AND used_at IS NULL
    ");
    $invalidate->execute([
        ':account_type' => $accountType,
        ':account_id' => $accountId,
    ]);

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + max(5, $ttlMinutes) * 60);

    $insert = $pdo->prepare("
        INSERT INTO password_reset_tokens (account_type, account_id, email, token_hash, expires_at)
        VALUES (:account_type, :account_id, :email, :token_hash, :expires_at)
    ");
    $insert->execute([
        ':account_type' => $accountType,
        ':account_id' => $accountId,
        ':email' => $email,
        ':token_hash' => $tokenHash,
        ':expires_at' => $expiresAt,
    ]);

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
    $stmt = $pdo->prepare("
        SELECT id, account_type, account_id, email
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

    return [
        'id' => (int) $row['id'],
        'account_type' => (string) $row['account_type'],
        'account_id' => (int) $row['account_id'],
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

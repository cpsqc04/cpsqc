<?php

/**
 * Generate credentials when a neighborhood watch application is approved.
 */
function generateNwMemberTempPassword(): string
{
    // 16-character alphanumeric temporary password
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $digits = '23456789';
    $all = $upper . $lower . $digits;

    $password = $upper[random_int(0, strlen($upper) - 1)]
        . $lower[random_int(0, strlen($lower) - 1)]
        . $digits[random_int(0, strlen($digits) - 1)]
        . $digits[random_int(0, strlen($digits) - 1)];

    for ($i = strlen($password); $i < 16; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }

    return str_shuffle($password);
}

function isValidNwMemberPassword(string $password): bool
{
    return (bool) preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9]{16}$/', $password);
}

function generateNwMemberCode(PDO $pdo, int $volunteerId): string
{
    $year = date('Y');
    $baseCode = sprintf('NW-%s-%03d', $year, $volunteerId);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM nw_members WHERE member_code = :code AND id != :id');
    $stmt->execute([':code' => $baseCode, ':id' => $volunteerId]);
    if ((int) $stmt->fetchColumn() === 0) {
        return $baseCode;
    }

    for ($attempt = 1; $attempt <= 99; $attempt++) {
        $code = sprintf('NW-%s-%03d-%02d', $year, $volunteerId, $attempt);
        $stmt->execute([':code' => $code, ':id' => $volunteerId]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $code;
        }
    }

    return $baseCode . '-' . bin2hex(random_bytes(2));
}

/**
 * @return array{member_code: string, temp_password: string, password_hash: string}
 */
function provisionNwMemberCredentials(PDO $pdo, int $volunteerId): array
{
    $memberCode = generateNwMemberCode($pdo, $volunteerId);
    $tempPassword = generateNwMemberTempPassword();
    $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('UPDATE nw_members SET member_code = :member_code, password_hash = :password_hash, must_change_password = 1 WHERE id = :id');
    $stmt->execute([
        ':member_code' => $memberCode,
        ':password_hash' => $passwordHash,
        ':id' => $volunteerId,
    ]);

    return [
        'member_code' => $memberCode,
        'temp_password' => $tempPassword,
        'password_hash' => $passwordHash,
    ];
}

function buildNwMemberDisplayName(?string $firstName, ?string $middleName, ?string $lastName, ?string $legacyName = null): string
{
    $first = trim((string) $firstName);
    $middle = trim((string) $middleName);
    $last = trim((string) $lastName);
    $parts = array_values(array_filter([$first, $middle, $last], static fn($p) => $p !== ''));
    if (!empty($parts)) {
        return implode(' ', $parts);
    }

    $legacy = trim((string) $legacyName);
    if ($legacy === '') {
        return '';
    }
    if (preg_match('/^([^,]+),\s*(.+)$/', $legacy, $m)) {
        return trim($m[2] . ' ' . $m[1]);
    }
    return $legacy;
}

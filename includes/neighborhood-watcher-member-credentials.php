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

/**
 * Password must be at least 8 characters and include uppercase, lowercase,
 * and a number or special character (e.g. @, #, _).
 */
function isValidNwMemberPassword(string $password): bool
{
    if (strlen($password) < 8) {
        return false;
    }

    $hasUpper = (bool) preg_match('/[A-Z]/', $password);
    $hasLower = (bool) preg_match('/[a-z]/', $password);
    $hasNumberOrSpecial = (bool) preg_match('/[0-9@#_$%^&*!?\-+=.]/', $password);

    return $hasUpper && $hasLower && $hasNumberOrSpecial;
}

function nwMemberPasswordRequirementMessage(): string
{
    return 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, and a number or special character (e.g. @, #, _).';
}

function generateNwMemberCode(PDO $pdo, int $volunteerId): string
{
    require_once __DIR__ . '/managed_user_display_ids.php';
    syncNwMemberCodesToDisplayIds($pdo);

    return resolveNwMemberDisplayCode($pdo, $volunteerId);
}

/**
 * Normalize email for storage and login matching.
 */
function normalizeNwMemberEmail(string $email): string
{
    return strtolower(trim($email));
}

/**
 * Find the best matching member row for portal login.
 * Prefers Active accounts that already have a password hash.
 */
function findNwMemberForLogin(PDO $pdo, string $email): ?array
{
    $email = normalizeNwMemberEmail($email);
    if ($email === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, name, email, member_code, password_hash, must_change_password, status
         FROM nw_members
         WHERE LOWER(TRIM(email)) = :email
         ORDER BY
            CASE
                WHEN status = 'Active' AND password_hash IS NOT NULL AND password_hash <> '' THEN 0
                WHEN status = 'Active' THEN 1
                ELSE 2
            END,
            id DESC
         LIMIT 1"
    );
    $stmt->execute([':email' => $email]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    return $member ?: null;
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

function nwNormalizeNameParts(array $input): array
{
    $firstName = trim((string) ($input['first_name'] ?? ''));
    $middleName = trim((string) ($input['middle_name'] ?? ''));
    $lastName = trim((string) ($input['last_name'] ?? ''));
    $legacyName = trim((string) ($input['name'] ?? ''));

    if (($firstName === '' || $lastName === '') && $legacyName !== '') {
        if (preg_match('/^([^,]+),\s*(.+)$/', $legacyName, $m)) {
            $lastName = $lastName !== '' ? $lastName : trim($m[1]);
            $rest = trim($m[2]);
            if ($firstName === '') {
                $parts = preg_split('/\s+/', $rest) ?: [];
                $firstName = trim((string) ($parts[0] ?? ''));
                if ($middleName === '' && count($parts) > 1) {
                    $middleName = trim(implode(' ', array_slice($parts, 1)));
                }
            }
        } else {
            $parts = preg_split('/\s+/', $legacyName) ?: [];
            if ($firstName === '' && !empty($parts)) {
                $firstName = trim((string) $parts[0]);
            }
            if ($lastName === '' && count($parts) > 1) {
                $lastName = trim((string) $parts[count($parts) - 1]);
            }
            if ($middleName === '' && count($parts) > 2) {
                $middleName = trim(implode(' ', array_slice($parts, 1, -1)));
            }
        }
    }

    $fullName = buildNwMemberDisplayName($firstName, $middleName, $lastName, $legacyName);

    return [
        'first_name' => $firstName !== '' ? $firstName : null,
        'middle_name' => $middleName !== '' ? $middleName : null,
        'last_name' => $lastName !== '' ? $lastName : null,
        'name' => $fullName,
    ];
}

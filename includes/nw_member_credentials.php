<?php

/**
 * Generate credentials when a neighborhood watch application is approved.
 */
function generateNwMemberTempPassword(): string
{
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $digits = '23456789';
    $special = '!@#';
    $all = $upper . $lower . $digits . $special;

    $password = $upper[random_int(0, strlen($upper) - 1)]
        . $lower[random_int(0, strlen($lower) - 1)]
        . $digits[random_int(0, strlen($digits) - 1)]
        . $special[random_int(0, strlen($special) - 1)];

    for ($i = 0; $i < 6; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }

    return str_shuffle($password);
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

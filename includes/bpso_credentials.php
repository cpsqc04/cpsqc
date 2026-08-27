<?php

require_once __DIR__ . '/app_url.php';
require_once __DIR__ . '/login_otp.php';

/**
 * Temporary password + welcome email helpers for BPSO personnel.
 */

function ensureBpsoMustChangePasswordColumn(PDO $pdo): void
{
    try {
        $columns = [];
        foreach ($pdo->query('SHOW COLUMNS FROM patrols') as $row) {
            $columns[$row['Field']] = true;
        }
        if (!isset($columns['must_change_password'])) {
            $pdo->exec('ALTER TABLE patrols ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash');
        }
    } catch (PDOException $e) {
        // Table may not exist yet; create path in api/patrols.php handles that.
    }
}

function generateBpsoTempPassword(): string
{
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $digits = '23456789';
    $special = '@#_';
    $all = $upper . $lower . $digits . $special;

    $password = $upper[random_int(0, strlen($upper) - 1)]
        . $lower[random_int(0, strlen($lower) - 1)]
        . $digits[random_int(0, strlen($digits) - 1)]
        . $special[random_int(0, strlen($special) - 1)];

    for ($i = strlen($password); $i < 12; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }

    return str_shuffle($password);
}

function isValidBpsoAccountPassword(string $password): bool
{
    if (strlen($password) < 8) {
        return false;
    }

    $hasUpper = (bool) preg_match('/[A-Z]/', $password);
    $hasLower = (bool) preg_match('/[a-z]/', $password);
    $hasNumberOrSpecial = (bool) preg_match('/[0-9@#_$%^&*!?\-+=.]/', $password);

    return $hasUpper && $hasLower && $hasNumberOrSpecial;
}

function bpsoAccountPasswordMessage(): string
{
    return 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, and a number or special character (e.g. @, #, _).';
}

/**
 * @return array{success: bool, error?: string}
 */
function sendBpsoWelcomeCredentialsEmail(
    string $email,
    string $personnelName,
    string $personnelCode,
    string $tempPassword,
    ?string $portalUrl = null
): array {
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Personnel email address is missing or invalid.'];
    }

    $portalUrl = $portalUrl ?: getBpsoPortalUrl();
    $safeName = htmlspecialchars(trim($personnelName) !== '' ? $personnelName : 'BPSO Personnel', ENT_QUOTES, 'UTF-8');
    $safePortalUrl = htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($personnelCode, ENT_QUOTES, 'UTF-8');
    $safeTempPassword = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');

    $subject = 'AlerTara QC Patrol Portal Access';
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.7; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
            .email-container { max-width: 640px; margin: 0 auto; background: #ffffff; }
            .body-content { padding: 32px 28px; background: #ffffff; }
            .body-content p { margin: 0 0 16px 0; color: #333; font-size: 15px; }
            .credentials-box { border-radius: 8px; padding: 18px 20px; margin: 16px 0; border: 1px solid #c7e7e6; background: #f0fdfa; }
            .credentials-title { font-size: 16px; font-weight: 700; color: #2a5a59; margin: 0 0 12px 0; }
            .temp-password { font-family: 'Courier New', monospace; font-size: 18px; font-weight: 700; color: #2a5a59; letter-spacing: 1px; }
            .credentials-note { font-size: 13px; color: #666; margin-top: 12px !important; }
            .signature { margin-top: 28px; }
            .signature p { margin: 0 0 4px 0; }
            .footer { background: #f5f5f5; padding: 15px 20px; text-align: center; color: #999; font-size: 11px; }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='body-content'>
                <p>Dear {$safeName},</p>
                <p>Your patrol account has been created in AlerTara QC. Use the temporary credentials below to sign in to the Patrol Portal.</p>
                <div class='credentials-box'>
                    <p class='credentials-title'>Temporary Sign-In Credentials</p>
                    <p><strong>Patrol Portal:</strong> <a href='{$safePortalUrl}' style='color:#2a5a59;'>{$safePortalUrl}</a></p>
                    <p><strong>Email Address:</strong> {$safeEmail}</p>
                    <p><strong>Patrol ID:</strong> {$safeCode}</p>
                    <p><strong>Temporary Password:</strong> <span class='temp-password'>{$safeTempPassword}</span></p>
                    <p class='credentials-note'>On first login you must set a new password before you can access the portal. Your new password must include uppercase, lowercase, and a number or special character (e.g. @, #, _).</p>
                </div>
                <p>If you have any questions, please contact the barangay hall during office hours.</p>
                <div class='signature'>
                    <p>Respectfully yours,</p>
                    <p><strong>Barangay Peacekeeping and Security Office</strong></p>
                </div>
            </div>
            <div class='footer'>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendSmtpHtmlMail($email, $subject, $body);
}

/**
 * Email notice when a patrol assignment is created.
 *
 * @return array{success: bool, error?: string}
 */
function sendBpsoAssignmentEmail(
    string $email,
    string $personnelName,
    string $scheduleDate,
    string $shift,
    string $zone,
    ?string $portalUrl = null
): array {
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Personnel email address is missing or invalid.'];
    }

    $portalUrl = $portalUrl ?: getBpsoPortalUrl();
    $safeName = htmlspecialchars(trim($personnelName) !== '' ? $personnelName : 'BPSO Personnel', ENT_QUOTES, 'UTF-8');
    $safePortalUrl = htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8');
    $safeDate = htmlspecialchars($scheduleDate, ENT_QUOTES, 'UTF-8');
    $safeShift = htmlspecialchars($shift, ENT_QUOTES, 'UTF-8');
    $safeZone = htmlspecialchars($zone, ENT_QUOTES, 'UTF-8');

    $subject = 'New Patrol Assignment - AlerTara QC';
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.7; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
            .email-container { max-width: 640px; margin: 0 auto; background: #ffffff; }
            .body-content { padding: 32px 28px; background: #ffffff; }
            .body-content p { margin: 0 0 16px 0; color: #333; font-size: 15px; }
            .details-box { border-radius: 8px; padding: 18px 20px; margin: 16px 0; border: 1px solid #c7e7e6; background: #f0fdfa; }
            .footer { background: #f5f5f5; padding: 15px 20px; text-align: center; color: #999; font-size: 11px; }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='body-content'>
                <p>Dear {$safeName},</p>
                <p>You have been assigned a new patrol task. Open the Patrol Portal and check <strong>My Schedule</strong> for full details.</p>
                <div class='details-box'>
                    <p><strong>Date:</strong> {$safeDate}</p>
                    <p><strong>Shift:</strong> {$safeShift}</p>
                    <p><strong>Patrol Zone:</strong> {$safeZone}</p>
                    <p><strong>Portal:</strong> <a href='{$safePortalUrl}' style='color:#2a5a59;'>{$safePortalUrl}</a></p>
                </div>
                <p>Please review your assignment and prepare accordingly.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendSmtpHtmlMail($email, $subject, $body);
}

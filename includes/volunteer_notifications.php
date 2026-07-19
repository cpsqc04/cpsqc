<?php

require_once __DIR__ . '/login_otp.php';
require_once __DIR__ . '/app_url.php';
require_once __DIR__ . '/neighborhood-watcher-member-credentials.php';

$volunteerNotificationsAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($volunteerNotificationsAutoload)) {
    require_once $volunteerNotificationsAutoload;
}

function formatNwApplicantGreetingName(
    string $name,
    ?string $firstName = null,
    ?string $lastName = null,
    ?string $middleName = null
): string {
    $display = buildNwMemberDisplayName($firstName, $middleName, $lastName, $name);
    return $display !== '' ? $display : 'Applicant';
}

/**
 * @return array{success:bool,error:?string}
 */
function sendVolunteerApplicationStatusEmail(
    string $email,
    string $name,
    string $status,
    ?string $tempPassword = null,
    ?string $portalUrl = null,
    ?string $memberCode = null,
    ?string $rejectionReason = null,
    ?string $rejectionNotes = null,
    ?string $firstName = null,
    ?string $lastName = null,
    ?string $middleName = null
): array {
    $email = trim($email);
    if (!in_array($status, ['Active', 'Rejected'], true)) {
        return ['success' => false, 'error' => 'Invalid application status for email.'];
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Applicant email address is missing or invalid.'];
    }

    $safeName = htmlspecialchars(formatNwApplicantGreetingName($name, $firstName, $lastName, $middleName), ENT_QUOTES, 'UTF-8');
    $isApproved = $status === 'Active';

    if ($isApproved) {
        $subject = 'Barangay San Agustin Neighborhood Watcher Application Status';
        $portalUrl = $portalUrl ?: getNwMemberPortalUrl();
        $safePortalUrl = htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safeTempPassword = htmlspecialchars((string) $tempPassword, ENT_QUOTES, 'UTF-8');
        $safeMemberCode = htmlspecialchars((string) $memberCode, ENT_QUOTES, 'UTF-8');

        $credentialsBlock = '';
        if ($tempPassword !== null && $tempPassword !== '') {
            $credentialsBlock = "
                <div class='credentials-box'>
                    <p class='credentials-title'>Temporary Sign-In Credentials</p>
                    <p><strong>Neighborhood Watcher Login:</strong> <a href='{$safePortalUrl}' style='color:#2a5a59;'>{$safePortalUrl}</a></p>
                    <p><strong>Email Address:</strong> {$safeEmail}</p>
                    " . ($memberCode ? "<p><strong>Member Code:</strong> {$safeMemberCode}</p>" : '') . "
                    <p><strong>Temporary Password:</strong> <span class='temp-password'>{$safeTempPassword}</span></p>
                    <p class='credentials-note'>Sign in using your registered email and temporary password. You will be asked to set a new 16-character alphanumeric password on first login.</p>
                </div>
            ";
        }

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
                    <p>Thank you for applying to be a Barangay Neighborhood Watcher of San Agustin. We are pleased to inform you that your application has been <strong>approved</strong>.</p>
                    <p>Please use the temporary credentials below to sign in to the Neighborhood Watcher portal:</p>
                    {$credentialsBlock}
                    <p>If you have any questions, you may visit the Barangay Hall during office hours.</p>
                    <div class='signature'>
                        <p>Respectfully yours,</p>
                        <p><strong>Barangay Peacekeeping and Security Officer</strong></p>
                    </div>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    } else {
        $subject = 'Barangay San Agustin Neighborhood Watcher Application Status';
        $safeReason = htmlspecialchars(trim((string) $rejectionReason), ENT_QUOTES, 'UTF-8');
        if ($safeReason === '') {
            $safeReason = 'Not specified';
        }
        $notesHtml = '';
        $trimmedNotes = trim((string) $rejectionNotes);
        if ($trimmedNotes !== '') {
            $safeNotes = nl2br(htmlspecialchars($trimmedNotes, ENT_QUOTES, 'UTF-8'));
            $notesHtml = "<p><strong>Additional notes:</strong><br>{$safeNotes}</p>";
        }

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
                .reason-list { margin: 0 0 18px 0; padding-left: 1.25rem; color: #333; font-size: 15px; }
                .reason-list li { margin: 0 0 6px 0; }
                .signature { margin-top: 28px; }
                .signature p { margin: 0 0 4px 0; }
                .footer { background: #f5f5f5; padding: 15px 20px; text-align: center; color: #999; font-size: 11px; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='body-content'>
                    <p>Dear {$safeName},</p>
                    <p>Thank you for applying to be a Barangay Neighborhood Watcher of San Agustin. We truly appreciate your willingness to help maintain peace and order in our community.</p>
                    <p>After reviewing your application, we regret to inform you that it has been declined due to the following reason:</p>
                    <ul class='reason-list'>
                        <li>{$safeReason}</li>
                    </ul>
                    {$notesHtml}
                    <p>If you have any questions regarding this decision, you may visit the Barangay Hall during office hours.</p>
                    <p>Thank you for your understanding, and we wish you the best.</p>
                    <div class='signature'>
                        <p>Respectfully yours,</p>
                        <p><strong>Barangay Peacekeeping and Security Officer</strong></p>
                    </div>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    return sendSmtpHtmlMail($email, $subject, $body);
}

/**
 * OTP email confirming a successful password change.
 * @return array{success:bool,error:?string}
 */
function sendNwPasswordChangedOTPEmail(string $email, string $name, string $otp): array
{
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Applicant email address is missing or invalid.'];
    }

    $safeName = htmlspecialchars(formatNwApplicantGreetingName($name), ENT_QUOTES, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    $subject = 'AlerTara QC - Password Change Verification OTP';
    $body = "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'></head>
    <body style='font-family: Arial, sans-serif; color: #333;'>
        <p>Dear {$safeName},</p>
        <p>Your Neighborhood Watcher password was changed. Use this OTP to confirm the password change:</p>
        <p style='font-size: 30px; font-weight: 700; letter-spacing: 4px; color: #2a5a59;'>{$safeOtp}</p>
        <p>This code expires in 10 minutes. If you did not change your password, contact the barangay hall immediately.</p>
    </body>
    </html>
    ";

    return sendSmtpHtmlMail($email, $subject, $body);
}

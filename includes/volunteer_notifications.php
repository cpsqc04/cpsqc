<?php

require_once __DIR__ . '/login_otp.php';
require_once __DIR__ . '/app_url.php';

$volunteerNotificationsAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($volunteerNotificationsAutoload)) {
    require_once $volunteerNotificationsAutoload;
}

function sendVolunteerApplicationStatusEmail(
    string $email,
    string $name,
    string $status,
    ?string $tempPassword = null,
    ?string $portalUrl = null,
    ?string $memberCode = null
): bool {
    if (!in_array($status, ['Active', 'Rejected'], true)) {
        return false;
    }

    $mailConfig = loadMailConfigFromEnv();

    if ($mailConfig['username'] === '' || $mailConfig['password'] === '' || $email === '') {
        error_log('Volunteer application status email failed: Email credentials or recipient email not set.');
        return false;
    }

    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $isApproved = $status === 'Active';

    $subject = $isApproved
        ? 'AlerTara QC - Neighborhood Watch Application Approved'
        : 'AlerTara QC - Neighborhood Watch Application Update';

    $headline = $isApproved ? 'Application Approved' : 'Application Not Approved';
    $headlineColor = $isApproved ? '#059669' : '#dc3545';

    if ($isApproved) {
        $portalUrl = $portalUrl ?: getNwMemberPortalUrl();
        $safePortalUrl = htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safeTempPassword = htmlspecialchars((string) $tempPassword, ENT_QUOTES, 'UTF-8');
        $safeMemberCode = htmlspecialchars((string) $memberCode, ENT_QUOTES, 'UTF-8');

        $credentialsBlock = '';
        if ($tempPassword !== null && $tempPassword !== '') {
            $credentialsBlock = "
                <div class='credentials-box'>
                    <p class='credentials-title'>Your Member Portal Access</p>
                    <p><strong>Portal:</strong> <a href='{$safePortalUrl}' style='color:#2a5a59;'>{$safePortalUrl}</a></p>
                    <p><strong>Login Email:</strong> {$safeEmail}</p>
                    " . ($memberCode ? "<p><strong>Member Code:</strong> {$safeMemberCode}</p>" : '') . "
                    <p><strong>Temporary Password:</strong> <span class='temp-password'>{$safeTempPassword}</span></p>
                    <p class='credentials-note'>Please log in using your email and temporary password. You will be asked to change your password on first login.</p>
                </div>
            ";
        }

        $message = 'Your neighborhood watch membership application has been <strong>approved</strong>. Use the credentials below to access the member portal and report incidents to BPSO.'
            . $credentialsBlock;
    } else {
        $message = 'Thank you for your interest in joining the neighborhood watch program. After review, your application was <strong>not approved</strong> at this time. For questions, please visit the barangay hall.';
    }

    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; }
            .header { background: linear-gradient(135deg, #4c8a89 0%, #2a5a59 100%); color: white; padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
            .header p { margin: 10px 0 0 0; font-size: 16px; opacity: 0.95; }
            .body-content { padding: 30px 20px; background: #ffffff; }
            .body-content p { margin: 0 0 15px 0; color: #333; font-size: 14px; }
            .status-box { border-radius: 8px; padding: 18px 20px; margin: 20px 0; border: 1px solid #e5e7eb; background: #f8fafc; }
            .status-title { font-size: 18px; font-weight: 700; color: {$headlineColor}; margin: 0 0 10px 0; }
            .credentials-box { border-radius: 8px; padding: 18px 20px; margin: 16px 0 0 0; border: 1px solid #c7e7e6; background: #f0fdfa; }
            .credentials-title { font-size: 16px; font-weight: 700; color: #2a5a59; margin: 0 0 12px 0; }
            .temp-password { font-family: 'Courier New', monospace; font-size: 18px; font-weight: 700; color: #2a5a59; letter-spacing: 1px; }
            .credentials-note { font-size: 13px; color: #666; margin-top: 12px !important; }
            .footer { background: #f5f5f5; padding: 15px 20px; text-align: center; color: #999; font-size: 11px; }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h1>AlerTara QC</h1>
                <p>Neighborhood Watch Application</p>
            </div>
            <div class='body-content'>
                <p>Hello {$safeName},</p>
                <div class='status-box'>
                    <p class='status-title'>{$headline}</p>
                    <p>{$message}</p>
                </div>
                <p>This is an automated notification regarding your neighborhood watch membership application.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $mailConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $mailConfig['username'];
            $mail->Password = $mailConfig['password'];
            if ($mailConfig['port'] === 587 || strtolower($mailConfig['encryption']) === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }
            $mail->Port = $mailConfig['port'];
            $mail->CharSet = 'UTF-8';
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];

            $mail->setFrom($mailConfig['from'], $mailConfig['from_name']);
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Volunteer application status email send failed: ' . ($mail->ErrorInfo ?? $e->getMessage()));
            return false;
        }
    }

    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: {$mailConfig['from_name']} <{$mailConfig['from']}>\r\n";
    return mail($email, $subject, $body, $headers);
}

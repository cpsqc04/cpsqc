<?php

/**
 * Shared login OTP helpers for admin and BPSO personnel login.
 */

function generateLoginOTP(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function maskEmailAddress(string $email): string
{
    if (!str_contains($email, '@')) {
        return $email;
    }

    [$local, $domain] = explode('@', $email, 2);
    $localLength = strlen($local);

    if ($localLength <= 2) {
        $maskedLocal = substr($local, 0, 1) . '***';
    } else {
        $maskedLocal = substr($local, 0, 1) . str_repeat('*', min(4, $localLength - 2)) . substr($local, -1);
    }

    return $maskedLocal . '@' . $domain;
}

function buildOtpPromptMessage(bool $resent = false, ?string $email = null, bool $sessionRestore = false): string
{
    $destination = $email ? maskEmailAddress($email) : 'your registered email';

    if ($sessionRestore) {
        return "Enter the 6-digit OTP sent to {$destination}. Check your inbox and spam folder.";
    }

    if ($resent) {
        return "A new 6-digit OTP has been sent to {$destination}. Enter it below to continue.";
    }

    return "A 6-digit OTP has been sent to {$destination}. Enter it below to continue.";
}

function getOtpResendCooldownSeconds(array $pendingLogin): int
{
    $lastResendAt = (int) ($pendingLogin['last_resend_at'] ?? $pendingLogin['sent_at'] ?? 0);
    if ($lastResendAt <= 0) {
        return 0;
    }

    return max(0, 60 - (time() - $lastResendAt));
}

function populateOtpViewData(array $pendingLogin, bool $sessionRestore = false, bool $resent = false): array
{
    $email = (string) ($pendingLogin['email'] ?? '');

    return [
        'otp_prompt' => buildOtpPromptMessage($resent, $email !== '' ? $email : null, $sessionRestore),
        'otp_email_masked' => $email !== '' ? maskEmailAddress($email) : null,
        'otp_expires_at_text' => !empty($pendingLogin['expires_at'])
            ? date('g:i A', strtotime((string) $pendingLogin['expires_at']))
            : null,
        'otp_resend_cooldown' => getOtpResendCooldownSeconds($pendingLogin),
    ];
}

function renderLoginOtpForm(string $cancelUrl, ?string $emailMasked, ?string $expiresAtText, int $resendCooldown): void
{
    ?>
    <form method="POST" action="" class="otp-form">
        <div class="field">
            <label for="login_otp">Login OTP</label>
            <input
                id="login_otp"
                name="login_otp"
                type="text"
                inputmode="numeric"
                pattern="\d{6}"
                placeholder="Enter 6-digit OTP"
                maxlength="6"
                autofocus
            >
        </div>
        <?php if (!empty($emailMasked)): ?>
            <p class="otp-meta">
                Sent to <strong><?php echo htmlspecialchars($emailMasked); ?></strong>
            </p>
        <?php endif; ?>
        <div class="button-group">
            <button class="btn" type="submit" name="verify_login_otp" value="1">Verify OTP</button>
            <button
                class="btn btn-secondary"
                type="submit"
                name="resend_login_otp"
                value="1"
                id="resendOtpBtn"
                formnovalidate
                <?php echo $resendCooldown > 0 ? 'disabled' : ''; ?>
            >
                <?php echo $resendCooldown > 0 ? 'Resend OTP in ' . $resendCooldown . 's' : 'Resend OTP'; ?>
            </button>
        </div>
        <div class="actions otp-actions">
            <a href="<?php echo htmlspecialchars($cancelUrl); ?>">Back to login</a>
        </div>
    </form>
    <?php if ($resendCooldown > 0): ?>
        <script>
            (function() {
                let remaining = <?php echo (int) $resendCooldown; ?>;
                const btn = document.getElementById('resendOtpBtn');
                if (!btn) return;
                const timer = setInterval(function() {
                    remaining -= 1;
                    if (remaining <= 0) {
                        clearInterval(timer);
                        btn.disabled = false;
                        btn.textContent = 'Resend OTP';
                        return;
                    }
                    btn.textContent = 'Resend OTP in ' + remaining + 's';
                }, 1000);
            })();
        </script>
    <?php endif; ?>
    <?php
}

function loadMailConfigFromEnv(): array
{
    $config = [
        'host' => $_ENV['MAIL_HOST'] ?? getenv('MAIL_HOST') ?: 'smtp.resend.com',
        'port' => (int) ($_ENV['MAIL_PORT'] ?? getenv('MAIL_PORT') ?: 465),
        'username' => (string) ($_ENV['MAIL_USERNAME'] ?? getenv('MAIL_USERNAME') ?: ''),
        'password' => (string) ($_ENV['MAIL_PASSWORD'] ?? getenv('MAIL_PASSWORD') ?: ''),
        'from' => (string) ($_ENV['MAIL_FROM_ADDRESS'] ?? getenv('MAIL_FROM_ADDRESS') ?: ''),
        'from_name' => (string) ($_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: 'AlerTara QC'),
        'encryption' => (string) ($_ENV['MAIL_ENCRYPTION'] ?? getenv('MAIL_ENCRYPTION') ?: 'ssl'),
    ];

    // Always merge from .env so Apache/CLI both use the project mail settings.
    $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $value = trim($value, "\"'");
            }

            switch ($key) {
                case 'MAIL_HOST':
                    $config['host'] = $value !== '' ? $value : $config['host'];
                    break;
                case 'MAIL_PORT':
                    if ($value !== '') {
                        $config['port'] = (int) $value;
                    }
                    break;
                case 'MAIL_USERNAME':
                    if ($value !== '') {
                        $config['username'] = $value;
                    }
                    break;
                case 'MAIL_PASSWORD':
                    if ($value !== '') {
                        $config['password'] = $value;
                    }
                    break;
                case 'MAIL_FROM_ADDRESS':
                    if ($value !== '') {
                        $config['from'] = $value;
                    }
                    break;
                case 'MAIL_FROM_NAME':
                    if ($value !== '') {
                        $config['from_name'] = $value;
                    }
                    break;
                case 'MAIL_ENCRYPTION':
                    if ($value !== '') {
                        $config['encryption'] = $value;
                    }
                    break;
            }
        }
    }

    if ($config['from'] === '') {
        $config['from'] = $config['username'];
    }

    return $config;
}

/**
 * @return array{success:bool,error:?string}
 */
function sendSmtpHtmlMail(string $toEmail, string $subject, string $htmlBody): array
{
    $mailConfig = loadMailConfigFromEnv();

    if ($mailConfig['username'] === '' || $mailConfig['password'] === '' || trim($toEmail) === '') {
        $error = 'Email credentials or recipient email not set.';
        error_log('SMTP mail failed: ' . $error);
        return ['success' => false, 'error' => $error];
    }

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
    }

    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $mail = null;
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
            $mail->Timeout = 30;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];

            $mail->setFrom($mailConfig['from'], $mailConfig['from_name']);
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)), ENT_QUOTES, 'UTF-8'));
            $mail->send();
            error_log('SMTP mail sent successfully to ' . $toEmail . ' subject=' . $subject);
            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            $error = $mail && !empty($mail->ErrorInfo) ? $mail->ErrorInfo : $e->getMessage();
            error_log('SMTP mail send failed: ' . $error);
            return ['success' => false, 'error' => $error];
        }
    }

    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: {$mailConfig['from_name']} <{$mailConfig['from']}>\r\n";
    $ok = @mail($toEmail, $subject, $htmlBody, $headers);
    if (!$ok) {
        $error = 'PHP mail() failed. Configure SMTP/PHPMailer.';
        error_log('SMTP mail failed: ' . $error);
        return ['success' => false, 'error' => $error];
    }

    return ['success' => true, 'error' => null];
}

function sendLoginOTPEmail(string $email, string $username, string $otp, string $portal = 'admin'): bool
{
    $mailConfig = loadMailConfigFromEnv();

    if ($mailConfig['username'] === '' || $mailConfig['password'] === '' || $email === '') {
        error_log('Login OTP email failed: Email credentials or recipient email not set.');
        return false;
    }

    $subject = match ($portal) {
        'bpso' => 'AlerTara QC - BPSO Personnel Login OTP',
        'nw_member' => 'AlerTara QC - Neighborhood Watch Member Login OTP',
        default => 'AlerTara QC - Login OTP Verification',
    };

    $portalLabel = match ($portal) {
        'bpso' => 'BPSO Personnel Portal',
        'nw_member' => 'Neighborhood Watch Member Portal',
        default => 'Admin Portal',
    };
    $body = "
    <html><body style='font-family: Arial, sans-serif; color: #333;'>
        <h2 style='color:#2a5a59;'>Login Verification Required</h2>
        <p>Hello {$username},</p>
        <p>Use this OTP to complete your login to the {$portalLabel}:</p>
        <p style='font-size: 30px; font-weight: 700; letter-spacing: 4px; color: #2a5a59;'>{$otp}</p>
        <p>This code expires in 10 minutes.</p>
        <p>If this wasn't you, please contact your administrator immediately.</p>
    </body></html>
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
            error_log('Login OTP email send failed: ' . ($mail->ErrorInfo ?? $e->getMessage()));
            return false;
        }
    }

    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: {$mailConfig['from_name']} <{$mailConfig['from']}>\r\n";
    return mail($email, $subject, $body, $headers);
}

function sendPasswordResetOTPEmail(string $email, string $otp, string $portal = 'admin'): bool
{
    $mailConfig = loadMailConfigFromEnv();

    if ($mailConfig['username'] === '' || $mailConfig['password'] === '' || $email === '') {
        error_log('Password reset OTP email failed: Email credentials or recipient email not set.');
        return false;
    }

    $portalLabel = match ($portal) {
        'bpso' => 'BPSO Personnel Portal',
        'nw_member' => 'Neighborhood Watch Member Portal',
        default => 'Admin Portal',
    };
    $subject = match ($portal) {
        'bpso' => 'AlerTara QC - BPSO Password Reset OTP',
        'nw_member' => 'AlerTara QC - Neighborhood Watch Password Reset OTP',
        default => 'Password Reset OTP - AlerTara QC',
    };

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
            .otp-box { background: #ffffff; border: 2px solid #4c8a89; border-radius: 8px; padding: 20px; text-align: center; margin: 25px 0; }
            .otp-code { font-size: 32px; font-weight: 700; color: #4c8a89; letter-spacing: 4px; font-family: 'Courier New', monospace; }
            .expiry-notice { color: #666; font-size: 13px; margin-top: 15px; }
            .disclaimer { color: #666; font-size: 12px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0; }
            .footer { background: #f5f5f5; padding: 15px 20px; text-align: center; color: #999; font-size: 11px; }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h1>AlerTara QC</h1>
                <p>{$portalLabel} Password Reset</p>
            </div>
            <div class='body-content'>
                <p>Hello,</p>
                <p>You have requested to reset your password. Please use the following OTP to complete the process:</p>
                <div class='otp-box'>
                    <div class='otp-code'>{$otp}</div>
                </div>
                <p class='expiry-notice'>This OTP will expire in 10 minutes.</p>
                <p class='disclaimer'>If you did not request this password reset, please ignore this email.</p>
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
        } catch (Throwable $e) {
            error_log('Password reset OTP email send failed: ' . ($mail->ErrorInfo ?? $e->getMessage()));
            return false;
        }
    }

    error_log('Password reset OTP email failed: PHPMailer is not available.');
    return false;
}

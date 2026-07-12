<?php
require_once __DIR__ . '/includes/bpso_auth.php';
require_once __DIR__ . '/db.php';

$autoloadPath = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}
require_once __DIR__ . '/includes/login_otp.php';

bpsoSessionStart();

if (isBpsoLoggedIn()) {
    header('Location: bpso-dashboard.php');
    exit;
}

$error = null;
$otpPrompt = null;
$showOtpForm = false;
$otpEmailMasked = null;
$otpExpiresAtText = null;
$otpResendCooldown = 0;

if (isset($_GET['cancel_otp'])) {
    unset($_SESSION['pending_bpso_login']);
    header('Location: bpso-login.php');
    exit;
}

if (isset($_SESSION['pending_bpso_login'])) {
    $pendingLoginSession = $_SESSION['pending_bpso_login'];
    $pendingExpiresAt = strtotime((string) ($pendingLoginSession['expires_at'] ?? ''));

    if ($pendingExpiresAt === false || $pendingExpiresAt < time()) {
        unset($_SESSION['pending_bpso_login']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $error = 'OTP has expired. Please log in again.';
        }
    } else {
        $showOtpForm = true;
        $otpView = populateOtpViewData($pendingLoginSession, true);
        $otpPrompt = $otpView['otp_prompt'];
        $otpEmailMasked = $otpView['otp_email_masked'];
        $otpExpiresAtText = $otpView['otp_expires_at_text'];
        $otpResendCooldown = $otpView['otp_resend_cooldown'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend_login_otp'])) {
        $pendingLogin = $_SESSION['pending_bpso_login'] ?? null;

        if (!$pendingLogin) {
            $error = 'No pending login verification found. Please log in again.';
            $showOtpForm = false;
        } elseif (strtotime((string) ($pendingLogin['expires_at'] ?? '')) < time()) {
            unset($_SESSION['pending_bpso_login']);
            $error = 'OTP has expired. Please log in again.';
            $showOtpForm = false;
        } else {
            $otpResendCooldown = getOtpResendCooldownSeconds($pendingLogin);
            if ($otpResendCooldown > 0) {
                $error = "Please wait {$otpResendCooldown} second(s) before requesting another OTP.";
                $showOtpForm = true;
                $otpView = populateOtpViewData($pendingLogin, true);
                $otpPrompt = $otpView['otp_prompt'];
                $otpEmailMasked = $otpView['otp_email_masked'];
                $otpExpiresAtText = $otpView['otp_expires_at_text'];
            } else {
                $otp = generateLoginOTP();
                $_SESSION['pending_bpso_login']['otp'] = $otp;
                $_SESSION['pending_bpso_login']['expires_at'] = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                $_SESSION['pending_bpso_login']['attempts'] = 0;
                $_SESSION['pending_bpso_login']['last_resend_at'] = time();

                $otpSent = sendLoginOTPEmail(
                    $pendingLogin['email'] ?? '',
                    $pendingLogin['personnel_name'] ?? 'BPSO Personnel',
                    $otp,
                    'bpso'
                );

                if ($otpSent) {
                    $otpView = populateOtpViewData($_SESSION['pending_bpso_login'], false, true);
                    $otpPrompt = $otpView['otp_prompt'];
                    $otpEmailMasked = $otpView['otp_email_masked'];
                    $otpExpiresAtText = $otpView['otp_expires_at_text'];
                    $otpResendCooldown = 60;
                    $showOtpForm = true;
                } else {
                    $error = 'Failed to resend login OTP. Please try again later.';
                    $showOtpForm = true;
                }
            }
        }
    }

    $isOtpVerification = isset($_POST['verify_login_otp']) && !isset($_POST['resend_login_otp']);

    if ($isOtpVerification && $error === null) {
        $enteredOtp = trim($_POST['login_otp'] ?? '');
        $pendingLogin = $_SESSION['pending_bpso_login'] ?? null;

        if (!$pendingLogin) {
            $error = 'No pending login verification found. Please log in again.';
            $showOtpForm = false;
        } elseif ($enteredOtp === '' || !preg_match('/^\d{6}$/', $enteredOtp)) {
            $error = 'Please enter a valid 6-digit OTP.';
            $showOtpForm = true;
        } elseif (strtotime($pendingLogin['expires_at']) < time()) {
            unset($_SESSION['pending_bpso_login']);
            $error = 'OTP has expired. Please log in again.';
            $showOtpForm = false;
        } elseif (($pendingLogin['attempts'] ?? 0) >= 5) {
            unset($_SESSION['pending_bpso_login']);
            $error = 'Too many failed OTP attempts. Please log in again.';
            $showOtpForm = false;
        } elseif (!hash_equals((string) $pendingLogin['otp'], $enteredOtp)) {
            $_SESSION['pending_bpso_login']['attempts'] = (int) ($pendingLogin['attempts'] ?? 0) + 1;
            $remainingOtpAttempts = max(0, 5 - (int) $_SESSION['pending_bpso_login']['attempts']);
            if ($remainingOtpAttempts === 0) {
                unset($_SESSION['pending_bpso_login']);
                $error = 'Too many failed OTP attempts. Please log in again.';
                $showOtpForm = false;
            } else {
                $error = "Invalid OTP. {$remainingOtpAttempts} attempt(s) remaining.";
                $showOtpForm = true;
            }
        } else {
            $_SESSION['bpso_logged_in'] = true;
            $_SESSION['bpso_patrol_id'] = (int) $pendingLogin['patrol_id'];
            $_SESSION['bpso_personnel_name'] = $pendingLogin['personnel_name'];
            $_SESSION['bpso_personnel_code'] = $pendingLogin['personnel_code'];
            $_SESSION['bpso_email'] = $pendingLogin['email'];
            unset($_SESSION['pending_bpso_login']);
            header('Location: bpso-dashboard.php');
            exit;
        }
    } elseif (!isset($_POST['resend_login_otp']) && $error === null) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        unset($_SESSION['pending_bpso_login']);
        $showOtpForm = false;

        if ($email === '' || $password === '') {
            $error = 'Please enter both email and password.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT id, personnel_name, bpso_personnel_id, email, password_hash FROM patrols WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $personnel = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$personnel || empty($personnel['password_hash']) || !password_verify($password, $personnel['password_hash'])) {
                    $error = 'Invalid email or password.';
                } elseif (empty($personnel['email'])) {
                    $error = 'This account has no registered email. Please contact an administrator.';
                } else {
                    $otp = generateLoginOTP();
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                    $_SESSION['pending_bpso_login'] = [
                        'patrol_id' => (int) $personnel['id'],
                        'personnel_name' => $personnel['personnel_name'],
                        'personnel_code' => $personnel['bpso_personnel_id'],
                        'email' => $personnel['email'],
                        'otp' => $otp,
                        'expires_at' => $expiresAt,
                        'attempts' => 0,
                        'sent_at' => time(),
                        'last_resend_at' => time(),
                    ];

                    $otpSent = sendLoginOTPEmail($personnel['email'], $personnel['personnel_name'], $otp, 'bpso');
                    if ($otpSent) {
                        $otpView = populateOtpViewData($_SESSION['pending_bpso_login']);
                        $otpPrompt = $otpView['otp_prompt'];
                        $otpEmailMasked = $otpView['otp_email_masked'];
                        $otpExpiresAtText = $otpView['otp_expires_at_text'];
                        $otpResendCooldown = 60;
                        $showOtpForm = true;
                    } else {
                        unset($_SESSION['pending_bpso_login']);
                        $error = 'Failed to send login OTP. Please contact administrator or try again later.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'System error. Please try again later.';
            }
        }
    }
}

if ($showOtpForm && empty($otpPrompt) && isset($_SESSION['pending_bpso_login'])) {
    $otpView = populateOtpViewData($_SESSION['pending_bpso_login'], true);
    $otpPrompt = $otpView['otp_prompt'];
    $otpEmailMasked = $otpView['otp_email_masked'];
    $otpExpiresAtText = $otpView['otp_expires_at_text'];
    $otpResendCooldown = $otpView['otp_resend_cooldown'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BPSO Personnel Login - AlerTara QC</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        :root { --radius: 12px; }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding: 2rem clamp(1rem, 3vw, 2.5rem);
        }
        .page {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 5rem;
            flex: 1;
        }
        .main-content {
            display: grid;
            grid-template-columns: 1fr minmax(480px, 580px);
            gap: clamp(1.5rem, 3vw, 2.5rem);
            align-items: center;
            flex: 1;
            margin-top: 5rem;
        }
        .hero {
            display: flex;
            flex-direction: column;
            gap: 2rem;
            color: var(--text-color);
            align-items: center;
            justify-content: center;
        }
        .hero h1 {
            font-size: clamp(4rem, 6vw, 6.5rem);
            letter-spacing: -0.02em;
            margin: 0;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            text-align: center;
        }
        .hero h1 .logo-inline {
            height: 1.15em;
            width: auto;
            display: inline-block;
            vertical-align: middle;
            flex-shrink: 0;
        }
        .hero h1 .text-ler { color: var(--primary-color); }
        .hero h1 .text-taraqc { color: #2a2a2a; }
        .hero .welcome-text {
            margin-top: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .hero .welcome-text p {
            max-width: 540px;
            color: var(--text-secondary);
            line-height: 1.8;
            margin: 0;
            font-size: clamp(1.3rem, 2vw, 1.6rem);
            text-align: left;
        }
        .login-card {
            background: linear-gradient(145deg, var(--tertiary-color), var(--secondary-color));
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius);
            box-shadow: 0 20px 50px -25px rgba(0, 0, 0, 0.5);
            padding: clamp(2.5rem, 4vw, 3.5rem);
            display: grid;
            gap: 1.75rem;
            width: 100%;
        }
        .login-card h2 {
            margin: 0;
            color: #f8fafc;
            font-size: 1.75rem;
            font-weight: 600;
        }
        .field { display: grid; gap: 0.35rem; }
        .field label {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }
        .field input {
            width: 100%;
            padding: 1.15rem 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: var(--radius);
            font: inherit;
            font-size: 1.1rem;
            color: #f8fafc;
            background: rgba(255, 255, 255, 0.08);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            box-sizing: border-box;
        }
        .field input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.25);
        }
        .actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .actions a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 1.1rem;
        }
        .actions a:hover {
            text-decoration: underline;
        }
        .button-group {
            display: flex;
            gap: 0.75rem;
            margin-top: 0.5rem;
        }
        .button-group .btn {
            flex: 1;
        }
        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 1.15rem 1.75rem;
            border-radius: var(--radius);
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            color: #fff;
            background: var(--primary-color);
            box-shadow: 0 12px 30px -15px var(--primary-color);
            transition: transform 0.15s ease, box-shadow 0.2s ease, filter 0.2s ease;
        }
        .btn:hover {
            background: #4ca8a6;
            transform: translateY(-1px);
            filter: brightness(1.02);
        }
        .btn-secondary {
            background: transparent;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: none;
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }
        .btn-secondary:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none;
        }
        .otp-form {
            display: grid;
            gap: 1.75rem;
        }
        .otp-meta {
            margin: 0;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.75);
            line-height: 1.5;
        }
        .otp-meta strong {
            color: #f8fafc;
        }
        .otp-actions {
            justify-content: center;
            margin-top: -0.5rem;
        }
        .modal input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.25);
        }
        .modal .close:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }
        .mv-section {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-radius: var(--radius);
            padding: 3rem clamp(2rem, 4vw, 3rem);
            box-shadow: 0 10px 40px -15px rgba(0, 0, 0, 0.1);
            margin-top: -1rem;
        }
        .mv-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2.5rem;
        }
        .mv-item h3 {
            font-size: 1.5rem;
            color: var(--tertiary-color);
            margin: 0 0 1rem 0;
            font-weight: 700;
            position: relative;
            padding-bottom: 0.75rem;
        }
        .mv-item h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }
        .mv-item p {
            color: var(--text-secondary);
            line-height: 1.8;
            margin: 0;
            font-size: 0.95rem;
        }
        @media (max-width: 900px) {
            .main-content { grid-template-columns: 1fr; }
            .login-card { order: 2; }
            .hero { order: 1; }
            .mv-grid { grid-template-columns: 1fr; gap: 2rem; }
            .mv-section { padding: 2rem 1.5rem; }
        }
        @media (max-width: 768px) {
            body { padding: 1.5rem 1rem; }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="main-content">
            <section class="hero">
                <div class="welcome-text">
                    <h1>
                        <img src="images/tara.png" alt="A" class="logo-inline">
                        <span class="text-ler">ler</span><span class="text-taraqc">TaraQC</span>
                    </h1>
                    <p>Secure access for BPSO personnel to view patrol schedules and submit patrol reports.</p>
                </div>
            </section>

            <section class="login-card" aria-labelledby="login-title">
                <h2 id="login-title">BPSO Personnel Login</h2>

                <?php if ($error !== null): ?>
                    <div style="color: #ef4444; font-size: 0.9rem; padding: 0.75rem; background: rgba(239, 68, 68, 0.1); border-radius: 6px; border: 1px solid rgba(239, 68, 68, 0.2);">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($otpPrompt)): ?>
                    <div style="color: #10b981; font-size: 0.9rem; padding: 0.75rem; background: rgba(16, 185, 129, 0.1); border-radius: 6px; border: 1px solid rgba(16, 185, 129, 0.2);">
                        <?php echo htmlspecialchars($otpPrompt); ?>
                    </div>
                <?php endif; ?>

                <?php if ($showOtpForm): ?>
                    <?php renderLoginOtpForm(
                        'bpso-login.php?cancel_otp=1',
                        $otpEmailMasked,
                        $otpExpiresAtText,
                        (int) $otpResendCooldown
                    ); ?>
                <?php else: ?>
                    <form method="POST" action="">
                        <div class="field">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" placeholder="Enter your email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        <div class="field">
                            <label for="password">Password</label>
                            <input id="password" name="password" type="password" placeholder="••••••••" required>
                        </div>
                        <div class="actions">
                            <a href="#" onclick="event.preventDefault(); openForgotPasswordModal();" style="cursor: pointer;">Forgot password?</a>
                        </div>
                        <div class="button-group">
                            <button class="btn" type="submit" style="width: 100%;">Sign in</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        </div>

        <section class="mv-section">
            <div class="mv-grid">
                <div class="mv-item">
                    <h3>Our Mission</h3>
                    <p>To provide a unified, efficient, and responsive emergency management system that protects lives and property through seamless inter-departmental coordination and real-time information sharing.</p>
                </div>
                <div class="mv-item">
                    <h3>Our Vision</h3>
                    <p>To become the model for smart city emergency management in the Philippines, leveraging technology to create safer, more resilient communities through proactive and coordinated public safety initiatives.</p>
                </div>
                <div class="mv-item">
                    <h3>Our Values</h3>
                    <p>Integrity, Excellence, Collaboration, and Innovation guide our commitment to serving the people of Quezon City with dedication and professionalism in every emergency response and public safety operation.</p>
                </div>
            </div>
        </section>
    </main>

    <div id="forgotPasswordModal" class="modal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
        <div class="modal-content" style="background: linear-gradient(145deg, var(--tertiary-color), var(--secondary-color)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: var(--radius); box-shadow: 0 20px 50px -25px rgba(0, 0, 0, 0.5); padding: clamp(2.5rem, 4vw, 3.5rem); max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.16);">
                <h2 id="forgotPasswordTitle" style="margin: 0; color: #f8fafc; font-size: 1.75rem; font-weight: 600;">Forgot Password</h2>
                <span class="close" onclick="closeForgotPasswordModal()" style="color: rgba(255, 255, 255, 0.8); font-size: 1.75rem; cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: all 0.2s ease; line-height: 1;">&times;</span>
            </div>

            <div id="forgotPasswordStep1">
                <p style="color: rgba(255, 255, 255, 0.85); margin-bottom: 1.5rem; line-height: 1.6;">Enter your email to receive an OTP code.</p>
                <form id="forgotPasswordForm1" onsubmit="requestOTP(event)" style="display: grid; gap: 1.75rem;">
                    <div class="field">
                        <label for="forgotEmail" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Email *</label>
                        <input id="forgotEmail" name="email" type="email" placeholder="Enter your email" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                    </div>
                    <div id="forgotPasswordMessage" style="display: none; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem;"></div>
                    <div class="button-group" style="margin-top: 0.5rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeForgotPasswordModal()" style="flex: 1;">Cancel</button>
                        <button type="submit" class="btn" style="flex: 1;">Send OTP</button>
                    </div>
                </form>
            </div>

            <div id="forgotPasswordStep2" style="display: none;">
                <p style="color: rgba(255, 255, 255, 0.85); margin-bottom: 1.5rem; line-height: 1.6;">Enter the 6-digit OTP code sent to your email address.</p>
                <form id="forgotPasswordForm2" onsubmit="verifyOTP(event)" style="display: grid; gap: 1.75rem;">
                    <div class="field">
                        <label for="forgotOTP" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">OTP Code *</label>
                        <input id="forgotOTP" name="otp" type="text" placeholder="Enter 6-digit OTP" maxlength="6" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.5rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; text-align: center; letter-spacing: 0.5rem;">
                    </div>
                    <div id="forgotPasswordMessage2" style="display: none; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem;"></div>
                    <div class="button-group" style="margin-top: 0.5rem;">
                        <button type="button" class="btn btn-secondary" onclick="backToStep1()" style="flex: 1;">Back</button>
                        <button type="submit" class="btn" style="flex: 1;">Verify OTP</button>
                    </div>
                </form>
            </div>

            <div id="forgotPasswordStep3" style="display: none;">
                <p style="color: rgba(255, 255, 255, 0.85); margin-bottom: 1.5rem; line-height: 1.6;">Enter your new password.</p>
                <form id="forgotPasswordForm3" onsubmit="resetPassword(event)" style="display: grid; gap: 1.75rem;">
                    <div class="field">
                        <label for="newPassword" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">New Password *</label>
                        <input id="newPassword" name="new_password" type="password" placeholder="Enter new password" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                    </div>
                    <div class="field">
                        <label for="confirmPassword" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Confirm Password *</label>
                        <input id="confirmPassword" name="confirm_password" type="password" placeholder="Confirm new password" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                    </div>
                    <div id="forgotPasswordMessage3" style="display: none; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem;"></div>
                    <div class="button-group" style="margin-top: 0.5rem;">
                        <button type="button" class="btn btn-secondary" onclick="backToStep2()" style="flex: 1;">Back</button>
                        <button type="submit" class="btn" style="flex: 1;">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="successModal" class="modal" style="display: none; position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
        <div class="modal-content" style="background: linear-gradient(145deg, #10b981, #059669); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 16px; box-shadow: 0 20px 50px -25px rgba(0, 0, 0, 0.5); padding: 2.5rem; max-width: 500px; width: 90%; text-align: center; animation: slideIn 0.3s ease-out;">
            <div style="margin-bottom: 1.5rem;">
                <div style="width: 80px; height: 80px; margin: 0 auto; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; animation: scaleIn 0.3s ease-out;">
                    <i class="fas fa-check-circle" style="font-size: 3rem; color: #fff;"></i>
                </div>
            </div>
            <h2 style="margin: 0 0 1rem 0; color: #fff; font-size: 1.75rem; font-weight: 600;">Success!</h2>
            <p id="successMessage" style="margin: 0 0 2rem 0; color: rgba(255, 255, 255, 0.95); font-size: 1.1rem; line-height: 1.6;"></p>
            <button onclick="closeSuccessModal()" style="padding: 0.875rem 2rem; background: rgba(255, 255, 255, 0.2); color: #fff; border: 2px solid rgba(255, 255, 255, 0.3); border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; width: 100%;">OK</button>
        </div>
    </div>

    <script>
        function showSuccessModal(title, message, isError = false) {
            const modal = document.getElementById('successModal');
            const titleElement = modal.querySelector('h2');
            const messageElement = document.getElementById('successMessage');
            const iconElement = modal.querySelector('i');
            const modalContent = modal.querySelector('.modal-content');

            titleElement.textContent = title;
            messageElement.textContent = message;

            if (isError) {
                modalContent.style.background = 'linear-gradient(145deg, #ef4444, #dc2626)';
                iconElement.className = 'fas fa-exclamation-circle';
            } else {
                modalContent.style.background = 'linear-gradient(145deg, #10b981, #059669)';
                iconElement.className = 'fas fa-check-circle';
            }

            modal.style.display = 'flex';
        }

        function closeSuccessModal() {
            document.getElementById('successModal').style.display = 'none';
        }

        function openForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').style.display = 'flex';
            resetForgotPasswordModal();
        }

        function closeForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').style.display = 'none';
            resetForgotPasswordModal();
        }

        function resetForgotPasswordModal() {
            document.getElementById('forgotPasswordStep1').style.display = 'block';
            document.getElementById('forgotPasswordStep2').style.display = 'none';
            document.getElementById('forgotPasswordStep3').style.display = 'none';
            document.getElementById('forgotPasswordForm1').reset();
            document.getElementById('forgotPasswordForm2').reset();
            document.getElementById('forgotPasswordForm3').reset();
            document.getElementById('forgotPasswordMessage').style.display = 'none';
            document.getElementById('forgotPasswordMessage2').style.display = 'none';
            document.getElementById('forgotPasswordMessage3').style.display = 'none';
            document.getElementById('forgotPasswordTitle').textContent = 'Forgot Password';
        }

        function backToStep1() {
            document.getElementById('forgotPasswordStep1').style.display = 'block';
            document.getElementById('forgotPasswordStep2').style.display = 'none';
            document.getElementById('forgotPasswordTitle').textContent = 'Forgot Password';
        }

        function backToStep2() {
            document.getElementById('forgotPasswordStep2').style.display = 'block';
            document.getElementById('forgotPasswordStep3').style.display = 'none';
            document.getElementById('forgotPasswordTitle').textContent = 'Verify OTP';
        }

        function showForgotMessage(elementId, message, isSuccess) {
            const messageDiv = document.getElementById(elementId);
            messageDiv.textContent = message;
            messageDiv.style.display = 'block';
            messageDiv.style.color = isSuccess ? '#10b981' : '#ef4444';
            messageDiv.style.background = isSuccess ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)';
            messageDiv.style.border = isSuccess ? '1px solid rgba(16, 185, 129, 0.2)' : '1px solid rgba(239, 68, 68, 0.2)';
        }

        async function requestOTP(event) {
            event.preventDefault();
            const email = document.getElementById('forgotEmail').value.trim();

            if (!email) {
                showForgotMessage('forgotPasswordMessage', 'Please enter your email.', false);
                return;
            }

            try {
                const response = await fetch('api/bpso-forgot-password.php?action=request', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });
                const result = await response.json();

                if (result.success) {
                    showForgotMessage('forgotPasswordMessage', result.message, true);
                    setTimeout(() => {
                        document.getElementById('forgotPasswordStep1').style.display = 'none';
                        document.getElementById('forgotPasswordStep2').style.display = 'block';
                        document.getElementById('forgotPasswordTitle').textContent = 'Verify OTP';
                        document.getElementById('forgotOTP').focus();
                    }, 1000);
                } else {
                    showForgotMessage('forgotPasswordMessage', result.message, false);
                }
            } catch (err) {
                showForgotMessage('forgotPasswordMessage', 'An error occurred. Please try again.', false);
            }
        }

        async function verifyOTP(event) {
            event.preventDefault();
            const otp = document.getElementById('forgotOTP').value.trim();

            if (!otp || otp.length !== 6) {
                showForgotMessage('forgotPasswordMessage2', 'Please enter a valid 6-digit OTP code.', false);
                return;
            }

            try {
                const response = await fetch('api/bpso-forgot-password.php?action=verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ otp })
                });
                const result = await response.json();

                if (result.success) {
                    document.getElementById('forgotPasswordStep2').style.display = 'none';
                    document.getElementById('forgotPasswordStep3').style.display = 'block';
                    document.getElementById('forgotPasswordTitle').textContent = 'Reset Password';
                    document.getElementById('newPassword').focus();
                } else {
                    showForgotMessage('forgotPasswordMessage2', result.message, false);
                }
            } catch (err) {
                showForgotMessage('forgotPasswordMessage2', 'An error occurred. Please try again.', false);
            }
        }

        async function resetPassword(event) {
            event.preventDefault();
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (!newPassword || !confirmPassword) {
                showForgotMessage('forgotPasswordMessage3', 'Please fill in all fields.', false);
                return;
            }

            if (newPassword !== confirmPassword) {
                showForgotMessage('forgotPasswordMessage3', 'Passwords do not match.', false);
                return;
            }

            if (newPassword.length < 6) {
                showForgotMessage('forgotPasswordMessage3', 'Password must be at least 6 characters long.', false);
                return;
            }

            try {
                const response = await fetch('api/bpso-forgot-password.php?action=reset', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ new_password: newPassword, confirm_password: confirmPassword })
                });
                const result = await response.json();

                if (result.success) {
                    closeForgotPasswordModal();
                    showSuccessModal('Password Reset Successful!', result.message, false);
                } else {
                    showForgotMessage('forgotPasswordMessage3', result.message, false);
                }
            } catch (err) {
                showForgotMessage('forgotPasswordMessage3', 'An error occurred. Please try again.', false);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const loginOtpInput = document.getElementById('login_otp');
            if (loginOtpInput) {
                loginOtpInput.addEventListener('input', function(e) {
                    e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
                });
                loginOtpInput.focus();
            }

            const otpInput = document.getElementById('forgotOTP');
            if (otpInput) {
                otpInput.addEventListener('input', function(e) {
                    e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
                });
            }

            if (window.location.search.includes('reset=1')) {
                openForgotPasswordModal();
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        window.onclick = function(event) {
            const forgotPasswordModal = document.getElementById('forgotPasswordModal');
            const successModal = document.getElementById('successModal');

            if (event.target === forgotPasswordModal) {
                closeForgotPasswordModal();
            }
            if (event.target === successModal) {
                closeSuccessModal();
            }
        };
    </script>
</body>
</html>

<?php
require_once __DIR__ . '/includes/neighborhood-watcher-member-auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api/neighborhood-watcher-members-schema.php';
require_once __DIR__ . '/includes/neighborhood-watcher-member-credentials.php';
require_once __DIR__ . '/includes/volunteer_notifications.php';

$autoloadPath = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}
require_once __DIR__ . '/includes/login_otp.php';

nwMemberSessionStart();

$error = null;
$otpPrompt = null;
$showOtpForm = false;
$showSetPasswordModal = false;
$showPasswordChangeOtpModal = false;
$passwordChangeOtpPrompt = null;
$otpEmailMasked = null;
$otpExpiresAtText = null;
$otpResendCooldown = 0;

if (isset($_GET['cancel_otp'])) {
    unset($_SESSION['pending_nw_member_login']);
    header('Location: neighborhood-watcher-login.php');
    exit;
}

if (isset($_GET['cancel_password_otp'])) {
    unset($_SESSION['pending_nw_password_change_otp']);
    header('Location: neighborhood-watcher-login.php');
    exit;
}

// Fully authenticated members go to dashboard.
if (
    isNwMemberLoggedIn()
    && !nwMemberMustChangePassword()
    && empty($_SESSION['pending_nw_password_change_otp'])
) {
    header('Location: neighborhood-watcher-dashboard.php');
    exit;
}

if (!empty($_SESSION['pending_nw_password_change_otp'])) {
    $showPasswordChangeOtpModal = true;
    $pendingPwOtp = $_SESSION['pending_nw_password_change_otp'];
    $passwordChangeOtpPrompt = 'Enter the OTP sent to your email to confirm your password change.';
    $otpEmailMasked = maskEmailAddress((string) ($pendingPwOtp['email'] ?? ''));
    $otpExpiresAtText = date('g:i A', strtotime((string) ($pendingPwOtp['expires_at'] ?? 'now')));
} elseif (isNwMemberLoggedIn() && nwMemberMustChangePassword()) {
    $showSetPasswordModal = true;
}

if (isset($_SESSION['pending_nw_member_login'])) {
    $pendingLoginSession = $_SESSION['pending_nw_member_login'];
    $pendingExpiresAt = strtotime((string) ($pendingLoginSession['expires_at'] ?? ''));

    if ($pendingExpiresAt === false || $pendingExpiresAt < time()) {
        unset($_SESSION['pending_nw_member_login']);
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
        $pendingLogin = $_SESSION['pending_nw_member_login'] ?? null;

        if (!$pendingLogin) {
            $error = 'No pending login verification found. Please log in again.';
            $showOtpForm = false;
        } elseif (strtotime((string) ($pendingLogin['expires_at'] ?? '')) < time()) {
            unset($_SESSION['pending_nw_member_login']);
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
                $_SESSION['pending_nw_member_login']['otp'] = $otp;
                $_SESSION['pending_nw_member_login']['expires_at'] = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                $_SESSION['pending_nw_member_login']['attempts'] = 0;
                $_SESSION['pending_nw_member_login']['last_resend_at'] = time();

                $otpSent = sendLoginOTPEmail(
                    $pendingLogin['email'] ?? '',
                    $pendingLogin['member_name'] ?? 'Neighborhood Watch Member',
                    $otp,
                    'nw_member'
                );

                if ($otpSent) {
                    $otpView = populateOtpViewData($_SESSION['pending_nw_member_login'], false, true);
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
        $pendingLogin = $_SESSION['pending_nw_member_login'] ?? null;

        if (!$pendingLogin) {
            $error = 'No pending login verification found. Please log in again.';
            $showOtpForm = false;
        } elseif ($enteredOtp === '' || !preg_match('/^\d{6}$/', $enteredOtp)) {
            $error = 'Please enter a valid 6-digit OTP.';
            $showOtpForm = true;
        } elseif (strtotime($pendingLogin['expires_at']) < time()) {
            unset($_SESSION['pending_nw_member_login']);
            $error = 'OTP has expired. Please log in again.';
            $showOtpForm = false;
        } elseif (($pendingLogin['attempts'] ?? 0) >= 5) {
            unset($_SESSION['pending_nw_member_login']);
            $error = 'Too many failed OTP attempts. Please log in again.';
            $showOtpForm = false;
        } elseif (!hash_equals((string) $pendingLogin['otp'], $enteredOtp)) {
            $_SESSION['pending_nw_member_login']['attempts'] = (int) ($pendingLogin['attempts'] ?? 0) + 1;
            $remainingOtpAttempts = max(0, 5 - (int) $_SESSION['pending_nw_member_login']['attempts']);
            if ($remainingOtpAttempts === 0) {
                unset($_SESSION['pending_nw_member_login']);
                $error = 'Too many failed OTP attempts. Please log in again.';
                $showOtpForm = false;
            } else {
                $error = "Invalid OTP. {$remainingOtpAttempts} attempt(s) remaining.";
                $showOtpForm = true;
            }
        } else {
            $_SESSION['nw_member_logged_in'] = true;
            $_SESSION['nw_member_id'] = (int) $pendingLogin['volunteer_id'];
            $_SESSION['nw_member_name'] = $pendingLogin['member_name'];
            $_SESSION['nw_member_code'] = $pendingLogin['member_code'];
            $_SESSION['nw_member_email'] = $pendingLogin['email'];
            $_SESSION['nw_member_must_change_password'] = !empty($pendingLogin['must_change_password']);
            unset($_SESSION['pending_nw_member_login']);
            if (!empty($pendingLogin['must_change_password'])) {
                $showSetPasswordModal = true;
                $showOtpForm = false;
                $otpPrompt = null;
            } else {
                header('Location: neighborhood-watcher-dashboard.php');
                exit;
            }
        }
    } elseif (isset($_POST['set_nw_password']) && $error === null) {
        if (!isNwMemberLoggedIn() || !nwMemberMustChangePassword()) {
            $error = 'Please sign in again to set your password.';
        } else {
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
            if ($newPassword === '' || $confirmPassword === '') {
                $error = 'Please fill in all password fields.';
                $showSetPasswordModal = true;
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Passwords do not match.';
                $showSetPasswordModal = true;
            } elseif (!isValidNwMemberPassword($newPassword)) {
                $error = 'Password must be exactly 16 characters and alphanumeric (letters and numbers only).';
                $showSetPasswordModal = true;
            } else {
                try {
                    ensureNwMembersTable($pdo);
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $update = $pdo->prepare('UPDATE nw_members SET password_hash = :password_hash, must_change_password = 0 WHERE id = :id AND status = :status');
                    $update->execute([
                        ':password_hash' => $passwordHash,
                        ':id' => getNwMemberId(),
                        ':status' => 'Active',
                    ]);
                    $_SESSION['nw_member_must_change_password'] = false;

                    $otp = generateLoginOTP();
                    $_SESSION['pending_nw_password_change_otp'] = [
                        'otp' => $otp,
                        'email' => getNwMemberEmail(),
                        'member_name' => getNwMemberName(),
                        'expires_at' => date('Y-m-d H:i:s', strtotime('+10 minutes')),
                        'attempts' => 0,
                    ];

                    $mailResult = sendNwPasswordChangedOTPEmail(getNwMemberEmail(), getNwMemberName(), $otp);
                    if (empty($mailResult['success'])) {
                        $error = 'Password updated, but failed to send confirmation OTP. Please try again.';
                        $showSetPasswordModal = true;
                        unset($_SESSION['pending_nw_password_change_otp']);
                        $_SESSION['nw_member_must_change_password'] = true;
                        $pdo->prepare('UPDATE nw_members SET must_change_password = 1 WHERE id = :id')->execute([':id' => getNwMemberId()]);
                    } else {
                        $showPasswordChangeOtpModal = true;
                        $passwordChangeOtpPrompt = 'Password updated. Enter the OTP sent to your email to confirm.';
                        $otpEmailMasked = maskEmailAddress((string) getNwMemberEmail());
                        $otpExpiresAtText = date('g:i A', strtotime('+10 minutes'));
                    }
                } catch (PDOException $e) {
                    $error = 'System error. Please try again later.';
                    $showSetPasswordModal = true;
                }
            }
        }
    } elseif (isset($_POST['verify_password_change_otp']) && $error === null) {
        $pendingPw = $_SESSION['pending_nw_password_change_otp'] ?? null;
        $enteredOtp = trim($_POST['password_change_otp'] ?? '');
        if (!$pendingPw) {
            $error = 'No pending password change verification found.';
            $showSetPasswordModal = isNwMemberLoggedIn() && nwMemberMustChangePassword();
        } elseif ($enteredOtp === '' || !preg_match('/^\d{6}$/', $enteredOtp)) {
            $error = 'Please enter a valid 6-digit OTP.';
            $showPasswordChangeOtpModal = true;
            $passwordChangeOtpPrompt = 'Enter the OTP sent to your email to confirm your password change.';
            $otpEmailMasked = maskEmailAddress((string) ($pendingPw['email'] ?? ''));
        } elseif (strtotime((string) ($pendingPw['expires_at'] ?? '')) < time()) {
            unset($_SESSION['pending_nw_password_change_otp']);
            $error = 'OTP has expired. Please set your password again.';
            $_SESSION['nw_member_must_change_password'] = true;
            $showSetPasswordModal = true;
        } elseif (!hash_equals((string) ($pendingPw['otp'] ?? ''), $enteredOtp)) {
            $_SESSION['pending_nw_password_change_otp']['attempts'] = (int) ($pendingPw['attempts'] ?? 0) + 1;
            $remaining = max(0, 5 - (int) $_SESSION['pending_nw_password_change_otp']['attempts']);
            $error = $remaining === 0
                ? 'Too many failed OTP attempts. Please set your password again.'
                : "Invalid OTP. {$remaining} attempt(s) remaining.";
            if ($remaining === 0) {
                unset($_SESSION['pending_nw_password_change_otp']);
                $_SESSION['nw_member_must_change_password'] = true;
                $showSetPasswordModal = true;
            } else {
                $showPasswordChangeOtpModal = true;
                $passwordChangeOtpPrompt = 'Enter the OTP sent to your email to confirm your password change.';
                $otpEmailMasked = maskEmailAddress((string) ($pendingPw['email'] ?? ''));
            }
        } else {
            unset($_SESSION['pending_nw_password_change_otp']);
            header('Location: neighborhood-watcher-dashboard.php?password_changed=1');
            exit;
        }
    } elseif (!isset($_POST['resend_login_otp']) && !isset($_POST['set_nw_password']) && !isset($_POST['verify_password_change_otp']) && $error === null) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        unset($_SESSION['pending_nw_member_login']);
        $showOtpForm = false;

        if ($email === '' || $password === '') {
            $error = 'Please enter both email and password.';
        } else {
            try {
                ensureNwMembersTable($pdo);
                $stmt = $pdo->prepare('SELECT id, name, email, member_code, password_hash, must_change_password, status FROM nw_members WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $member = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$member || ($member['status'] ?? '') !== 'Active') {
                    $error = 'Invalid email or password.';
                } elseif (empty($member['password_hash']) || !password_verify($password, $member['password_hash'])) {
                    $error = 'Invalid email or password.';
                } elseif (empty($member['email'])) {
                    $error = 'This account has no registered email. Please contact an administrator.';
                } else {
                    $otp = generateLoginOTP();
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                    $_SESSION['pending_nw_member_login'] = [
                        'volunteer_id' => (int) $member['id'],
                        'member_name' => $member['name'],
                        'member_code' => $member['member_code'] ?? '',
                        'email' => $member['email'],
                        'must_change_password' => (int) ($member['must_change_password'] ?? 0),
                        'otp' => $otp,
                        'expires_at' => $expiresAt,
                        'attempts' => 0,
                        'sent_at' => time(),
                        'last_resend_at' => time(),
                    ];

                    $otpSent = sendLoginOTPEmail($member['email'], $member['name'], $otp, 'nw_member');
                    if ($otpSent) {
                        $otpView = populateOtpViewData($_SESSION['pending_nw_member_login']);
                        $otpPrompt = $otpView['otp_prompt'];
                        $otpEmailMasked = $otpView['otp_email_masked'];
                        $otpExpiresAtText = $otpView['otp_expires_at_text'];
                        $otpResendCooldown = 60;
                        $showOtpForm = true;
                    } else {
                        unset($_SESSION['pending_nw_member_login']);
                        $error = 'Failed to send login OTP. Please contact administrator or try again later.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'System error. Please try again later.';
            }
        }
    }
}

if ($showOtpForm && empty($otpPrompt) && isset($_SESSION['pending_nw_member_login'])) {
    $otpView = populateOtpViewData($_SESSION['pending_nw_member_login'], true);
    $otpPrompt = $otpView['otp_prompt'];
    $otpEmailMasked = $otpView['otp_email_masked'];
    $otpExpiresAtText = $otpView['otp_expires_at_text'];
    $otpResendCooldown = $otpView['otp_resend_cooldown'];
}

$autoOpenLogin = !empty($showOtpForm) || ($error !== null && !$showSetPasswordModal && !$showPasswordChangeOtpModal);
$autoOpenSetPassword = !empty($showSetPasswordModal);
$autoOpenPasswordChangeOtp = !empty($showPasswordChangeOtpModal);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Neighborhood Watch Login - AlerTara QC</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/portal-landing.css">
    <link rel="stylesheet" href="css/mobile-responsive.css">
</head>
<body>
    <div class="progress-bar" id="progressBar" aria-hidden="true"></div>

    <header class="site-nav" id="siteNav">
        <a href="login.php" class="nav-brand">
            <img src="images/logo.svg" alt="AlerTara QC">
            <span class="nav-brand-title">Community Policing and Surveillance</span>
        </a>
        <button class="nav-toggle" id="navToggle" aria-label="Menu"><i class="fas fa-bars"></i></button>
        <ul class="nav-links" id="navLinks">
            <li><a href="#about">About</a></li>
            <li><a href="#mission">Mission</a></li>
            <li><a href="#vision">Vision</a></li>
            <li><a href="#values">Values</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>
        <div class="nav-actions">
            <a class="nav-back" href="login.php"><i class="fas fa-arrow-left"></i> Main page</a>
            <button type="button" class="nav-back" onclick="openRegisterModal()">Register</button>
            <button type="button" class="nav-cta" onclick="openLoginModal()">Sign in</button>
        </div>
    </header>

    <main id="top">
        <section class="hero" aria-label="Hero">
            <div class="hero-bg"></div>
            <div class="hero-scan" aria-hidden="true"></div>
            <div class="hero-radar" aria-hidden="true"><div class="radar-beam"></div></div>
            <div class="hero-content">
                <p class="portal-pill"><i class="fas fa-house-user"></i> Neighborhood Watch Portal</p>
                <div class="brand-mark" aria-label="AlerTara QC">
                    <img src="images/tara.png" alt="">
                    <span class="ler">ler</span><span class="rest">Tara QC</span>
                </div>
                <h1>Neighborhood Watch Sign-In</h1>
                <p class="lead">Secure access for approved neighborhood watch members in Barangay San Agustin to report incidents and support community awareness.</p>
                <div class="hero-ctas">
                    <button type="button" class="btn" onclick="openLoginModal()">Sign in</button>
                    <button type="button" class="btn btn-ghost" onclick="openRegisterModal()">Register</button>
                    <a class="btn btn-ghost" href="login.php">Back to main page</a>
                </div>
            </div>
            <a class="scroll-cue" href="#about" aria-label="Scroll to about section">
                <i class="fas fa-chevron-down" aria-hidden="true"></i>
            </a>
        </section>

        <section class="section about-section" id="about">
            <div class="section-inner">
                <p class="section-label reveal">About this portal</p>
                <h2 class="reveal reveal-delay-1">Eyes and ears of Barangay San Agustin</h2>
                <p class="sub reveal reveal-delay-2">This portal is for approved neighborhood watch members serving Barangay San Agustin, Novaliches, Quezon City.</p>
                <p class="about-copy reveal reveal-delay-3">
                    Sign in to report incidents, share tips with BPSO, and help keep your community informed.
                    For full system details—including Who Uses This, FAQs, and policies—return to the main landing page.
                </p>
                <ul class="feature-list">
                    <li class="reveal">
                        <strong>Report incidents</strong>
                        Submit neighborhood tips and observations to support barangay response.
                    </li>
                    <li class="reveal reveal-delay-1">
                        <strong>Stay coordinated</strong>
                        Work with patrols and BPSO administrators under one shared platform.
                    </li>
                    <li class="reveal reveal-delay-2">
                        <strong>Secure access</strong>
                        OTP-verified sign-in protects sensitive community safety information.
                    </li>
                </ul>
            </div>
        </section>

        <section class="section mvv-section">
            <div class="section-inner mvv-stack">
                <div class="mvv-block reveal" id="mission">
                    <p class="section-label">Mission</p>
                    <h3>Our Mission</h3>
                    <p>To provide a unified, efficient, and responsive emergency management system for Barangay San Agustin that protects lives and property through seamless coordination and real-time information sharing.</p>
                </div>
                <div class="mvv-block reveal" id="vision">
                    <p class="section-label">Vision</p>
                    <h3>Our Vision</h3>
                    <p>To become a model barangay for community policing and surveillance in Novaliches, Quezon City—leveraging technology to create a safer, more resilient Barangay San Agustin through proactive and coordinated public safety initiatives.</p>
                </div>
                <div class="mvv-block reveal" id="values">
                    <p class="section-label">Values</p>
                    <h3>Our Values</h3>
                    <p>Integrity, Excellence, Collaboration, and Innovation guide our commitment to serving the people of Barangay San Agustin, Novaliches, Quezon City with dedication and professionalism in every public safety operation.</p>
                </div>
            </div>
        </section>

        <section class="section contact-section" id="contact">
            <div class="section-inner">
                <p class="section-label reveal">Contact us</p>
                <h2 class="reveal reveal-delay-1">We're here to help</h2>
                <div class="contact-info reveal reveal-delay-1">
                    <p>Reach the AlerTara QC team in Barangay San Agustin for support or account assistance.</p>
                    <div>
                        <strong>Email</strong>
                        contactcps@alertaraqc.gov.ph
                    </div>
                    <div>
                        <strong>Address</strong>
                        Barangay San Agustin, Novaliches, Quezon City, Metro Manila Philippines
                    </div>
                    <div>
                        <strong>Operation Hours</strong>
                        24/7
                    </div>
                    <div style="margin-top:1rem;">
                        <a class="btn btn-ghost" href="login.php">Explore the main page</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-brand">Aler<span>Tara</span> QC</div>
            <div class="footer-links">
                <button type="button" onclick="openLoginModal()">Sign in</button>
                <button type="button" onclick="openRegisterModal()">Register</button>
                <a href="login.php">Main page</a>
                <button type="button" onclick="openLegalModal('privacy')">Privacy Policy</button>
                <button type="button" onclick="openLegalModal('terms')">Terms of Service</button>
                <button type="button" onclick="openLegalModal('cookies')">Cookie Policy</button>
                <a href="#contact">Contact</a>
            </div>
            <p class="footer-copy">&copy; <?php echo date('Y'); ?> AlerTara QC — Neighborhood Watch Portal for Barangay San Agustin. All rights reserved.</p>
        </div>
    </footer>

    <div class="modal-overlay" id="loginModal" role="dialog" aria-modal="true" aria-labelledby="login-title">
        <div class="modal-panel">
            <div class="modal-header">
                <h2 id="login-title">Login</h2>
                <button type="button" class="modal-close" onclick="closeLoginModal()" aria-label="Close">&times;</button>
            </div>
            <?php if ($error !== null): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($otpPrompt)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($otpPrompt); ?></div>
            <?php endif; ?>
            <?php if ($showOtpForm): ?>
                <?php renderLoginOtpForm(
                    'neighborhood-watcher-login.php?cancel_otp=1',
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
                    <div class="login-actions">
                        <a href="#" onclick="event.preventDefault(); closeLoginModal(); openForgotPasswordModal();">Forgot password?</a>
                    </div>
                    <div class="button-group">
                        <button class="btn" type="submit" style="width:100%; border-radius:12px;">Sign in</button>
                    </div>
                    <p style="margin:1rem 0 0; text-align:center; color:rgba(255,255,255,0.72); font-size:0.92rem;">
                        New member?
                        <a href="#" style="color:var(--teal-bright);" onclick="event.preventDefault(); closeLoginModal(); openRegisterModal();">Register</a>
                    </p>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal-overlay" id="eligibilityModal" role="dialog" aria-modal="true" aria-labelledby="eligibility-title" style="z-index:2040;">
        <div class="modal-panel wide">
            <div class="modal-header">
                <h2 id="eligibility-title">Eligibility Criteria</h2>
                <button type="button" class="modal-close" onclick="closeEligibilityModal()" aria-label="Close">&times;</button>
            </div>
            <p class="register-hint">Please answer all items below. You may proceed only if you meet every requirement.</p>
            <form id="eligibilityCriteriaForm" onsubmit="event.preventDefault();">
                <ol class="eligibility-list">
                    <li class="eligibility-item">
                        <p class="eligibility-question">1. I am a Filipino Citizen. <em>Ako ay mamamayang Pilipino.</em></p>
                        <div class="eligibility-choices" role="radiogroup" aria-label="Filipino Citizen">
                            <label class="eligibility-choice"><input type="radio" name="eligibility_1" value="yes" required> Yes</label>
                            <label class="eligibility-choice"><input type="radio" name="eligibility_1" value="no"> No</label>
                        </div>
                    </li>
                    <li class="eligibility-item">
                        <p class="eligibility-question">2. I am Barangay San Agustin Resident. <em>Residente ako ng Barangay San Agustin.</em></p>
                        <div class="eligibility-choices" role="radiogroup" aria-label="Barangay San Agustin Resident">
                            <label class="eligibility-choice"><input type="radio" name="eligibility_2" value="yes" required> Yes</label>
                            <label class="eligibility-choice"><input type="radio" name="eligibility_2" value="no"> No</label>
                        </div>
                    </li>
                    <li class="eligibility-item">
                        <p class="eligibility-question">3. I have been a resident of Barangay San Agustin for at least six (6) months. <em>Ako ay naninirahan sa Barangay San Agustin nang hindi bababa sa anim (6) na buwan.</em></p>
                        <div class="eligibility-choices" role="radiogroup" aria-label="Resident for at least six months">
                            <label class="eligibility-choice"><input type="radio" name="eligibility_3" value="yes" required> Yes</label>
                            <label class="eligibility-choice"><input type="radio" name="eligibility_3" value="no"> No</label>
                        </div>
                    </li>
                    <li class="eligibility-item">
                        <p class="eligibility-question">4. I am a registered voter in Barangay San Agustin. <em>Ako ay isang rehistradong botante sa Barangay San Agustin.</em></p>
                        <div class="eligibility-choices" role="radiogroup" aria-label="Registered voter">
                            <label class="eligibility-choice"><input type="radio" name="eligibility_4" value="yes" required> Yes</label>
                            <label class="eligibility-choice"><input type="radio" name="eligibility_4" value="no"> No</label>
                        </div>
                    </li>
                    <li class="eligibility-item">
                        <p class="eligibility-question">5. I am between 18 and 60 years old. <em>Ang aking edad ay nasa pagitan ng 18 hanggang 60 taong gulang.</em></p>
                        <div class="eligibility-choices" role="radiogroup" aria-label="Age between 18 and 60">
                            <label class="eligibility-choice"><input type="radio" name="eligibility_5" value="yes" required> Yes</label>
                            <label class="eligibility-choice"><input type="radio" name="eligibility_5" value="no"> No</label>
                        </div>
                    </li>
                    <li class="eligibility-item">
                        <p class="eligibility-question">6. I can read and write in Tagalog o English. <em>Ako ay nakakabasa at nakakasulat sa wikang Tagalog at Ingles.</em></p>
                        <div class="eligibility-choices" role="radiogroup" aria-label="Can read and write Tagalog or English">
                            <label class="eligibility-choice"><input type="radio" name="eligibility_6" value="yes" required> Yes</label>
                            <label class="eligibility-choice"><input type="radio" name="eligibility_6" value="no"> No</label>
                        </div>
                    </li>
                </ol>
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="closeEligibilityModal()">Cancel</button>
                    <button type="button" class="btn" id="eligibilityProceedBtn" disabled onclick="proceedToApplicationForm()">Proceed to Application Form</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="registerModal" role="dialog" aria-modal="true" aria-labelledby="register-title" style="z-index:2050;">
        <div class="modal-panel wide">
            <div class="modal-header">
                <h2 id="register-title">Neighborhood Watch Application</h2>
                <button type="button" class="modal-close" onclick="closeRegisterModal()" aria-label="Close">&times;</button>
            </div>
            <p class="register-hint">Submit your application for review. Once approved, proceed to the barangay hall for further instructions before signing in.</p>
            <form id="memberApplicationForm" onsubmit="submitMemberApplication(event)" autocomplete="off">
                <div class="field">
                    <label for="memberLastName">Last Name *</label>
                    <input id="memberLastName" name="last_name" type="text" required>
                </div>
                <div class="field">
                    <label for="memberFirstName">First Name *</label>
                    <input id="memberFirstName" name="first_name" type="text" required>
                </div>
                <div class="field">
                    <label for="memberMiddleName">Middle Name <span style="font-weight:400;color:rgba(255,255,255,0.55);">(if applicable)</span></label>
                    <input id="memberMiddleName" name="middle_name" type="text">
                </div>
                <div class="field">
                    <label for="memberGender">Gender *</label>
                    <select id="memberGender" name="gender" required>
                        <option value="">Select gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="field">
                    <label for="memberMaritalStatus">Marital Status *</label>
                    <select id="memberMaritalStatus" name="marital_status" required>
                        <option value="">Select marital status</option>
                        <option value="Single">Single</option>
                        <option value="Married">Married</option>
                        <option value="Separated">Separated</option>
                        <option value="Widowed">Widowed</option>
                        <option value="Common-law (live-in)">Common-law (live-in)</option>
                    </select>
                </div>
                <div class="field">
                    <label for="memberBirthday">Birthday *</label>
                    <div class="birthday-input-wrap">
                        <input id="memberBirthday" name="birthday" type="text" inputmode="numeric" maxlength="10" autocomplete="bday" required>
                        <input id="memberBirthdayPicker" class="birthday-native-picker" type="date" tabindex="-1" aria-hidden="true" title="Open calendar">
                        <span class="birthday-calendar-icon" aria-hidden="true"><i class="fas fa-calendar-alt"></i></span>
                    </div>
                    <p id="memberBirthdayError" class="field-error" hidden>must be 18 years old and above</p>
                </div>
                <div class="field">
                    <label for="memberIdNumber">ID Number *</label>
                    <input id="memberIdNumber" name="id_number" type="text" required>
                </div>
                <div class="field">
                    <label for="memberContact">Contact Number *</label>
                    <input id="memberContact" name="contact" type="tel" class="contact-number-input" placeholder="" required>
                </div>
                <div class="field">
                    <label for="memberEmail">Email Address *</label>
                    <input id="memberEmail" name="email" type="email" required>
                </div>
                <div class="field">
                    <label for="memberUnitStreet">Unit/House Number &amp; Street Name *</label>
                    <input id="memberUnitStreet" name="unit_street" type="text" required placeholder="e.g., 123 Bonifacio St.">
                </div>
                <div class="field">
                    <label for="memberSubdivision">Subdivision/Village/Building *</label>
                    <select id="memberSubdivision" name="subdivision" required>
                        <option value="">Select subdivision</option>
                        <option value="T.S. Cruz Subdivision">T.S. Cruz Subdivision</option>
                        <option value="Clemente Subdivision">Clemente Subdivision</option>
                        <option value="Greenfields III Subdivision">Greenfields III Subdivision</option>
                        <option value="Millieville Subdivision">Millieville Subdivision</option>
                        <option value="Nova Homes Subdivision">Nova Homes Subdivision</option>
                        <option value="St. Francis/Blueville Subdivision">St. Francis/Blueville Subdivision</option>
                    </select>
                </div>
                <div class="field">
                    <label for="memberBarangay">Barangay *</label>
                    <select id="memberBarangay" name="barangay" required>
                        <option value="">Select barangay</option>
                        <option value="San Agustin" selected>San Agustin</option>
                    </select>
                </div>
                <div class="field">
                    <label for="memberCity">City/Municipality *</label>
                    <select id="memberCity" name="city" required>
                        <option value="">Select city/municipality</option>
                        <option value="Quezon City" selected>Quezon City</option>
                    </select>
                </div>
                <div class="field">
                    <label for="memberPostalCode">Postal Code *</label>
                    <input id="memberPostalCode" name="postal_code" type="text" required readonly>
                </div>
                <div class="field">
                    <label for="memberCountry">Country *</label>
                    <select id="memberCountry" name="country" required>
                        <option value="">Select country</option>
                        <option value="Philippines" selected>Philippines</option>
                    </select>
                </div>
                <div class="field">
                    <label for="memberEmergencyName">Emergency Contact Full Name *</label>
                    <input id="memberEmergencyName" name="emergencyName" type="text" required>
                </div>
                <div class="field">
                    <label for="memberEmergencyContact">Emergency Contact Number *</label>
                    <input id="memberEmergencyContact" name="emergencyContact" type="tel" class="contact-number-input" placeholder="" required>
                </div>
                <div class="field">
                    <label for="memberPhoto">Neighborhood Watch Member Photo *</label>
                    <input id="memberPhoto" name="photo" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" required onchange="previewMemberImage(this, 'memberPhotoPreview')">
                    <p class="field-hint">JPG or PNG, 10 MB or below.</p>
                    <div id="memberPhotoPreview" class="file-preview"></div>
                </div>
                <div class="field">
                    <label for="memberPhotoId">Photo of Valid ID *</label>
                    <input id="memberPhotoId" name="photoId" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" required onchange="previewMemberImage(this, 'memberPhotoIdPreview')">
                    <p class="field-hint">JPG or PNG, 10 MB or below.</p>
                    <div id="memberPhotoIdPreview" class="file-preview"></div>
                </div>
                <div class="field">
                    <label for="memberBarangayClearance">Barangay Clearance *</label>
                    <input id="memberBarangayClearance" name="barangayClearance" type="file" accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf" required onchange="previewBarangayClearance(this, 'memberBarangayClearancePreview')">
                    <p class="field-hint">Photo (JPG/PNG) or PDF, 10 MB or below.</p>
                    <div id="memberBarangayClearancePreview" class="file-preview"></div>
                </div>
                <div class="consent-box">
                    <input id="memberConsent" name="consent" type="checkbox" value="1" required>
                    <label for="memberConsent">
                        I have read and agree to the
                        <a href="#" onclick="openLegalModal('terms', event);">Terms of Service</a>
                        and
                        <a href="#" onclick="openLegalModal('privacy', event);">Privacy Policy</a>,
                        and I consent to the collection and processing of my personal data for this Neighborhood Watch membership application.
                    </label>
                </div>
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="closeRegisterModal()">Cancel</button>
                    <button type="submit" class="btn" id="registerSubmitBtn" disabled>Submit Application</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="registrationSuccessModal" role="dialog" aria-modal="true" style="z-index:3100;">
        <div class="modal-panel" style="text-align:center; background: linear-gradient(145deg, #10b981, #059669);">
            <div style="width:72px;height:72px;margin:0 auto 1rem;background:rgba(255,255,255,0.2);border-radius:50%;display:grid;place-items:center;">
                <i class="fas fa-check-circle" style="font-size:2.2rem;color:#fff;"></i>
            </div>
            <h2 style="margin:0 0 0.75rem;color:#fff;font-family:var(--font-display);">Application Submitted!</h2>
            <p style="margin:0 0 1.5rem;color:rgba(255,255,255,0.95);">Your neighborhood watch membership application has been submitted and is pending admin review. Please proceed to the barangay hall for further instructions once approved.</p>
            <button type="button" class="btn btn-ghost" onclick="closeRegistrationSuccessModal()" style="width:100%;">OK</button>
        </div>
    </div>

    <div class="modal-overlay<?php echo !empty($autoOpenSetPassword) ? ' open' : ''; ?>" id="setPasswordModal" role="dialog" aria-modal="true" aria-labelledby="set-password-title" style="z-index:3200;">
        <div class="modal-panel">
            <div class="modal-header">
                <h2 id="set-password-title">Set Your Password</h2>
            </div>
            <?php if ($error !== null && !empty($showSetPasswordModal)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <p class="register-hint">Create a new password to continue. It must be exactly 16 alphanumeric characters (letters and numbers only).</p>
            <form method="POST" action="">
                <input type="hidden" name="set_nw_password" value="1">
                <div class="field">
                    <label for="new_password">New Password *</label>
                    <input id="new_password" name="new_password" type="password" maxlength="16" minlength="16" pattern="(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9]{16}" required>
                </div>
                <div class="field">
                    <label for="confirm_password">Confirm Password *</label>
                    <input id="confirm_password" name="confirm_password" type="password" maxlength="16" minlength="16" pattern="(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9]{16}" required>
                </div>
                <div class="button-group">
                    <button class="btn" type="submit" style="width:100%;">Save Password</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay<?php echo !empty($autoOpenPasswordChangeOtp) ? ' open' : ''; ?>" id="passwordChangeOtpModal" role="dialog" aria-modal="true" aria-labelledby="password-otp-title" style="z-index:3300;">
        <div class="modal-panel">
            <div class="modal-header">
                <h2 id="password-otp-title">Confirm Password Change</h2>
            </div>
            <?php if ($error !== null && !empty($showPasswordChangeOtpModal)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($passwordChangeOtpPrompt)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($passwordChangeOtpPrompt); ?></div>
            <?php endif; ?>
            <p class="register-hint">OTP sent to <?php echo htmlspecialchars($otpEmailMasked ?: 'your email'); ?><?php echo $otpExpiresAtText ? ' (expires ' . htmlspecialchars($otpExpiresAtText) . ')' : ''; ?>.</p>
            <form method="POST" action="">
                <input type="hidden" name="verify_password_change_otp" value="1">
                <div class="field">
                    <label for="password_change_otp">6-digit OTP *</label>
                    <input id="password_change_otp" name="password_change_otp" type="text" inputmode="numeric" maxlength="6" pattern="\d{6}" required>
                </div>
                <div class="button-group">
                    <button class="btn" type="submit" style="width:100%;">Verify OTP</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/form-contact-validation.js"></script>
    <script src="js/neighborhood-watcher-register-application.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (<?php echo !empty($autoOpenSetPassword) ? 'true' : 'false'; ?>) {
                document.body.style.overflow = 'hidden';
            }
            if (<?php echo !empty($autoOpenPasswordChangeOtp) ? 'true' : 'false'; ?>) {
                document.body.style.overflow = 'hidden';
            }
        });
    </script>

<?php
$forgotApiEndpoint = 'api/neighborhood-watcher-forgot-password.php';
$portalHomePath = 'neighborhood-watcher-login.php';
require __DIR__ . '/includes/portal_landing_modals.php';

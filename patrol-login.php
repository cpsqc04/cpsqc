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
    header('Location: patrol-login.php');
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

$autoOpenLogin = !empty($showOtpForm) || ($error !== null);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Patrol Login - AlerTara QC</title>
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
            <button type="button" class="nav-cta" onclick="openLoginModal()">Sign in</button>
        </div>
    </header>

    <main id="top">
        <section class="hero" aria-label="Hero">
            <div class="hero-bg"></div>
            <div class="hero-scan" aria-hidden="true"></div>
            <div class="hero-radar" aria-hidden="true"><div class="radar-beam"></div></div>
            <div class="hero-content">
                <p class="portal-pill"><i class="fas fa-route"></i> Patrol Portal</p>
                <div class="brand-mark" aria-label="AlerTara QC">
                    <img src="images/tara.png" alt="">
                    <span class="ler">ler</span><span class="rest">Tara QC</span>
                </div>
                <h1>Patrol Sign-In</h1>
                <p class="lead">Secure access for patrol personnel in Barangay San Agustin to view schedules, submit reports, and coordinate with barangay responders.</p>
                <div class="hero-ctas">
                    <button type="button" class="btn" onclick="openLoginModal()">Sign in</button>
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
                <h2 class="reveal reveal-delay-1">Field coverage for Barangay San Agustin</h2>
                <p class="sub reveal reveal-delay-2">This portal is for patrol personnel serving Barangay San Agustin, Novaliches, Quezon City.</p>
                <p class="about-copy reveal reveal-delay-3">
                    Sign in to review assigned schedules, log patrol activity, and support coordinated barangay response.
                    For full system details—including Who Uses This, FAQs, and policies—return to the main landing page.
                </p>
                <ul class="feature-list">
                    <li class="reveal">
                        <strong>Patrol schedules</strong>
                        View assignments and stay aligned with barangay operations.
                    </li>
                    <li class="reveal reveal-delay-1">
                        <strong>Field reporting</strong>
                        Submit patrol logs and updates from on-ground activity.
                    </li>
                    <li class="reveal reveal-delay-2">
                        <strong>Secure access</strong>
                        OTP-verified sign-in protects operational information.
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
                <a href="login.php">Main page</a>
                <button type="button" onclick="openLegalModal('privacy')">Privacy Policy</button>
                <button type="button" onclick="openLegalModal('terms')">Terms of Service</button>
                <button type="button" onclick="openLegalModal('cookies')">Cookie Policy</button>
                <a href="#contact">Contact</a>
            </div>
            <p class="footer-copy">&copy; <?php echo date('Y'); ?> AlerTara QC — Patrol Portal for Barangay San Agustin. All rights reserved.</p>
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
                    'patrol-login.php?cancel_otp=1',
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
                </form>
            <?php endif; ?>
        </div>
    </div>

<?php
$forgotApiEndpoint = 'api/bpso-forgot-password.php';
$portalHomePath = 'patrol-login.php';
require __DIR__ . '/includes/portal_landing_modals.php';

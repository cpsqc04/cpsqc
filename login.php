<?php 
// Admin login page
session_start();

require_once __DIR__ . '/db.php';

/**
 * Ensure the admins table exists and has required columns.
 * Also creates a default admin account (admin / admin123) if table is empty.
 */
function ensureAdminsTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        full_name VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure newer columns exist
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM admins') as $row) {
        $columns[$row['Field']] = true;
    }
    if (!isset($columns['email'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN email VARCHAR(255) DEFAULT NULL');
    }
    if (!isset($columns['full_name'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN full_name VARCHAR(255) DEFAULT NULL');
    }
    if (!isset($columns['created_at'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    }

    // Create default admin account if none exists
    $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM admins');
    $count = (int)$stmt->fetch()['cnt'];
    if ($count === 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash, email, full_name) VALUES (:u, :p, :e, :f)');
        $stmt->execute([
            ':u' => 'admin',
            ':p' => $hash,
            ':e' => null,
            ':f' => 'Default Administrator'
        ]);
    }
}

try {
    ensureAdminsTable($pdo);
} catch (PDOException $e) {
    $error = 'Login system error: ' . htmlspecialchars($e->getMessage());
}

// Database-backed authentication
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error)) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password_hash, full_name FROM admins WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['username'] = $admin['full_name'] ?: $admin['username'];
            // Redirect to dashboard or home page
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    }
}

// Check if already logged in, redirect to index.php
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Policing and Surveillance</title>
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
        .hero h1 .text-ler {
            color: var(--primary-color);
        }
        .hero h1 .text-taraqc {
            color: #2a2a2a;
        }
        .logo-wrap {
            margin-bottom: 0;
        }
        .logo-wrap img {
            height: 300px;
            width: auto;
            object-fit: contain;
            filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.1));
        }
        .hero .welcome-text {
            margin-top: 0;
            margin-left: 0;
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
        .field {
            display: grid;
            gap: 0.35rem;
        }
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
        .btn-secondary {
            background: transparent;
            color: #ffffff;
            border: 1px solid #3a3a3a;
            box-shadow: none;
        }
        .btn-secondary:hover {
            background: #2a2a2a;
            border-color: var(--primary-color);
            transform: translateY(-1px);
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
        .btn:active {
            transform: translateY(0);
            box-shadow: 0 8px 20px -16px var(--secondary-color);
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
        .mv-item {
            display: flex;
            flex-direction: column;
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
            .main-content {
                grid-template-columns: 1fr;
            }
            .login-card { order: 2; }
            .hero { order: 1; }
            .mv-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            .mv-section {
                padding: 2rem 1.5rem;
            }
            .login-card h2 { color: #ffffff; }
            .field label { color: #a1a1aa; }
        }
        .action-buttons-section {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        @media (max-width: 768px) {
            body {
                padding: 1.5rem 1rem;
            }
            .logo-wrap img {
                height: 200px;
            }
            .action-buttons-section {
                padding: 1.5rem 1rem !important;
            }
            .action-buttons-section button {
                min-width: 100% !important;
                width: 100%;
            }
        }
        .modal input:focus, .modal textarea:focus, .modal select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.25);
        }
        .modal .close:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .success-modal {
            display: none;
            position: fixed;
            z-index: 2500;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(6px);
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        .success-modal.active {
            display: flex;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .success-modal-content {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border-radius: var(--radius);
            padding: 3rem clamp(2rem, 4vw, 3rem);
            max-width: 520px;
            width: 90%;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.4);
            text-align: center;
            position: relative;
            animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .success-icon-wrapper {
            width: 100px;
            height: 100px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            color: #ffffff;
            box-shadow: 0 10px 30px -10px rgba(16, 185, 129, 0.5);
            animation: scaleIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s both;
        }
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .success-icon-wrapper i {
            animation: checkmark 0.6s ease 0.3s both;
        }
        @keyframes checkmark {
            0% {
                transform: scale(0) rotate(-45deg);
            }
            50% {
                transform: scale(1.2) rotate(-45deg);
            }
            100% {
                transform: scale(1) rotate(0deg);
            }
        }
        .success-modal-content h2 {
            color: var(--tertiary-color);
            margin: 0 0 1rem 0;
            font-size: 1.75rem;
            font-weight: 700;
        }
        .success-modal-content p {
            color: var(--text-secondary);
            margin: 0 0 1.5rem 0;
            font-size: 1.05rem;
            line-height: 1.6;
        }
        .success-modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        .success-modal-actions .btn {
            min-width: 140px;
        }

        /* Entry Overlay (Frosted Glass) */
        .entry-overlay {
            position: fixed;
            inset: 0;
            z-index: 2600;
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .entry-overlay.is-hidden {
            display: none;
        }
        .entry-overlay-close {
            position: absolute;
            top: 18px;
            right: 18px;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            background: rgba(255, 255, 255, 0.7);
            color: rgba(28, 37, 65, 0.85);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.15s ease, background 0.2s ease, box-shadow 0.2s ease;
            z-index: 2;
        }
        .entry-overlay-close:hover {
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 8px 18px -14px rgba(0, 0, 0, 0.25);
            transform: translateY(-1px);
        }
        .entry-overlay-content {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 1.25rem;
        }
        .entry-overlay-actions {
            display: flex;
            gap: 2.5rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 0.25rem;
        }
        .entry-overlay-actions .btn {
            flex: 1;
            min-width: 220px;
            max-width: 260px;
            height: 220px;
            text-align: center;
            justify-content: center;
            flex-direction: column;
            padding: 1.2rem 1.25rem;
            font-size: 0.95rem;
        }
        .entry-overlay-actions .btn i {
            margin: 0 0 0.65rem 0 !important;
            font-size: 1in;
        }
    </style>
</head>
<body>
    <!-- Entry Overlay (shows first) -->
    <div id="entryOverlay" class="entry-overlay<?php echo isset($_GET['noOverlay']) ? ' is-hidden' : ''; ?>" aria-modal="true" role="dialog">
        <button type="button" class="entry-overlay-close" onclick="closeEntryOverlay()" aria-label="Close">
            &times;
        </button>
        <div class="entry-overlay-actions">
            <button class="btn btn-secondary" onclick="openTipModal()" type="button">
                <i class="fas fa-shield-alt"></i>
                <span>Magsumite ng reklamo nang palihim. Pindutin ito</span>
            </button>
            <button class="btn btn-secondary" onclick="openVolunteerModal()" type="button">
                <i class="fas fa-hand-holding-heart"></i>
                <span>Gusto ko mag volunteer</span>
            </button>
        </div>
    </div>

    <main class="page">
        <div class="main-content">
            <section class="hero">
                <div class="welcome-text">
                    <h1>
                        <img src="images/tara.png" alt="A" class="logo-inline">
                        <span class="text-ler">ler</span><span class="text-taraqc">TaraQC</span>
                    </h1>
                    <p>24/7 surveillance and instant alert system for potential threats.</p>
                </div>
            </section>

            <section class="login-card" aria-labelledby="login-title">
                <h2 id="login-title">Login</h2>
                <?php if (isset($_SESSION['registration_success'])): ?>
                    <div style="color: #10b981; font-size: 0.9rem; padding: 0.75rem; background: rgba(16, 185, 129, 0.1); border-radius: 6px; margin-bottom: 1rem; border: 1px solid rgba(16, 185, 129, 0.2);">
                        Registration successful! You can now login with username: <?php echo htmlspecialchars($_SESSION['registered_username'] ?? ''); ?>
                    </div>
                    <?php unset($_SESSION['registration_success'], $_SESSION['registered_username']); ?>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div style="color: #ef4444; font-size: 0.9rem; padding: 0.75rem; background: rgba(239, 68, 68, 0.1); border-radius: 6px; margin-bottom: 1rem; border: 1px solid rgba(239, 68, 68, 0.2);">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" placeholder="Enter your username" required>
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" placeholder="••••••••" required>
                    </div>
                    <div class="actions">
                        <a href="#">Forgot password?</a>
                    </div>
                    <div class="button-group">
                        <a href="register.php" class="btn btn-secondary" style="text-decoration: none; display: inline-flex; justify-content: center; align-items: center;">Register</a>
                        <button class="btn" type="submit">Sign in</button>
                    </div>
                </form>
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

    <!-- Registration Success Modal -->
    <div id="registrationSuccessModal" class="success-modal">
        <div class="success-modal-content">
            <div class="success-icon-wrapper">
                <i class="fas fa-check"></i>
            </div>
            <h2>Registration Successful!</h2>
            <p>Registration submitted successfully! Please proceed to the barangay hall to get your physical ID.</p>
            <div class="success-modal-actions">
                <button type="button" class="btn" onclick="closeRegistrationSuccessModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Tip Submission Modal -->
    <div id="tipModal" class="modal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
        <div class="modal-content" style="background: linear-gradient(145deg, var(--tertiary-color), var(--secondary-color)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: var(--radius); box-shadow: 0 20px 50px -25px rgba(0, 0, 0, 0.5); padding: clamp(2.5rem, 4vw, 3.5rem); max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.16);">
                <h2 style="margin: 0; color: #f8fafc; font-size: 1.75rem; font-weight: 600;">Submit Anonymous Tip</h2>
                <span class="close" onclick="closeTipModal()" style="color: rgba(255, 255, 255, 0.8); font-size: 1.75rem; cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: all 0.2s ease; line-height: 1;">&times;</span>
            </div>
            <form id="tipForm" onsubmit="submitTip(event)" style="display: grid; gap: 1.75rem;">
                <div class="field">
                    <label for="tipLocation" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Location *</label>
                    <input id="tipLocation" name="location" type="text" placeholder="Enter location where the incident occurred" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="tipDescription" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Tip Description *</label>
                    <textarea id="tipDescription" name="description" placeholder="Describe the incident or concern in detail" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); min-height: 120px; resize: vertical; transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; font-family: inherit;"></textarea>
                </div>
                <div class="button-group" style="margin-top: 0.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeTipModal()" style="flex: 1;">Cancel</button>
                    <button type="submit" class="btn" style="flex: 1;">Submit Tip</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Success Message Modal -->
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

    <!-- Volunteer Registration Modal -->
    <div id="volunteerModal" class="modal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
        <div class="modal-content" style="background: linear-gradient(145deg, var(--tertiary-color), var(--secondary-color)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: var(--radius); box-shadow: 0 20px 50px -25px rgba(0, 0, 0, 0.5); padding: clamp(2.5rem, 4vw, 3.5rem); max-width: 700px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.16);">
                <h2 style="margin: 0; color: #f8fafc; font-size: 1.75rem; font-weight: 600;">Volunteer Registration</h2>
                <span class="close" onclick="closeVolunteerModal()" style="color: rgba(255, 255, 255, 0.8); font-size: 1.75rem; cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: all 0.2s ease; line-height: 1;">&times;</span>
            </div>
            <form id="volunteerForm" onsubmit="submitVolunteer(event)" style="display: grid; gap: 1.75rem;">
                <div class="field">
                    <label for="volunteerName" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Full Name *</label>
                    <input id="volunteerName" name="name" type="text" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="volunteerContact" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Contact Number *</label>
                    <input id="volunteerContact" name="contact" type="text" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="volunteerEmail" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Email Address *</label>
                    <input id="volunteerEmail" name="email" type="email" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="volunteerAddress" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Home Address *</label>
                    <input id="volunteerAddress" name="address" type="text" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="volunteerCategory" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Volunteer Category *</label>
                    <select id="volunteerCategory" name="category" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; cursor: pointer;">
                        <option value="" style="background: var(--tertiary-color); color: #f8fafc;">Select category</option>
                        <option value="Community Outreach" style="background: var(--tertiary-color); color: #f8fafc;">Community Outreach</option>
                        <option value="Emergency Response" style="background: var(--tertiary-color); color: #f8fafc;">Emergency Response</option>
                        <option value="Event Management" style="background: var(--tertiary-color); color: #f8fafc;">Event Management</option>
                        <option value="Training and Education" style="background: var(--tertiary-color); color: #f8fafc;">Training and Education</option>
                        <option value="Administrative Support" style="background: var(--tertiary-color); color: #f8fafc;">Administrative Support</option>
                    </select>
                </div>
                <div class="field">
                    <label for="volunteerSkills" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Skills *</label>
                    <textarea id="volunteerSkills" name="skills" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); min-height: 80px; resize: vertical; transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; font-family: inherit;"></textarea>
                </div>
                <div class="field">
                    <label for="volunteerAvailability" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Availability *</label>
                    <select id="volunteerAvailability" name="availability" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; cursor: pointer;">
                        <option value="" style="background: var(--tertiary-color); color: #f8fafc;">Select availability</option>
                        <option value="Weekdays" style="background: var(--tertiary-color); color: #f8fafc;">Weekdays</option>
                        <option value="Weekends" style="background: var(--tertiary-color); color: #f8fafc;">Weekends</option>
                        <option value="Both" style="background: var(--tertiary-color); color: #f8fafc;">Both</option>
                        <option value="Flexible" style="background: var(--tertiary-color); color: #f8fafc;">Flexible</option>
                    </select>
                </div>
                <div class="field">
                    <label for="volunteerEmergencyName" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Emergency Contact Full Name *</label>
                    <input id="volunteerEmergencyName" name="emergencyName" type="text" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="volunteerEmergencyContact" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Emergency Contact Number *</label>
                    <input id="volunteerEmergencyContact" name="emergencyContact" type="text" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="volunteerPhoto" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Volunteer Photo *</label>
                    <input id="volunteerPhoto" name="photo" type="file" accept="image/*" required onchange="previewVolunteerImage(this, 'volunteerPhotoPreview')" style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; cursor: pointer;">
                    <div id="volunteerPhotoPreview" style="margin-top: 0.5rem; display: none;"></div>
                </div>
                <div class="field">
                    <label for="volunteerPhotoId" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Volunteer Valid ID *</label>
                    <input id="volunteerPhotoId" name="photoId" type="file" accept="image/*" required onchange="previewVolunteerImage(this, 'volunteerPhotoIdPreview')" style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; cursor: pointer;">
                    <div id="volunteerPhotoIdPreview" style="margin-top: 0.5rem; display: none;"></div>
                </div>
                <div class="field">
                    <label for="volunteerCertifications" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Certifications</label>
                    <input id="volunteerCertifications" name="certifications" type="file" accept=".jpeg,.jpg,.png,.pdf" multiple onchange="handleCertificationUpload(this)" style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; cursor: pointer;">
                    <div id="volunteerCertificationsPreview" style="margin-top: 0.5rem; display: none;"></div>
                </div>
                <div class="field">
                    <label for="volunteerCertificationsDescription" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Certification Details</label>
                    <textarea id="volunteerCertificationsDescription" name="certDescription" style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); min-height: 80px; resize: vertical; transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; font-family: inherit;"></textarea>
                </div>
                <div class="button-group" style="margin-top: 0.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeVolunteerModal()" style="flex: 1;">Cancel</button>
                    <button type="submit" class="btn" style="flex: 1;">Submit Registration</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let selectedCertFiles = [];

        function closeEntryOverlay() {
            const overlay = document.getElementById('entryOverlay');
            if (overlay) overlay.classList.add('is-hidden');
        }

        function openTipModal() {
            closeEntryOverlay();
            document.getElementById('tipModal').style.display = 'flex';
        }

        function closeTipModal() {
            document.getElementById('tipModal').style.display = 'none';
            document.getElementById('tipForm').reset();
        }

        function showSuccessModal(title, message, isError = false) {
            const modal = document.getElementById('successModal');
            const titleElement = modal.querySelector('h2');
            const messageElement = document.getElementById('successMessage');
            const iconElement = modal.querySelector('i');
            const modalContent = modal.querySelector('.modal-content');
            
            // Update title
            titleElement.textContent = title;
            
            // Update message
            messageElement.innerHTML = message;
            
            // Change styling if error
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

        function openVolunteerModal() {
            closeEntryOverlay();
            document.getElementById('volunteerModal').style.display = 'flex';
        }

        function closeVolunteerModal() {
            document.getElementById('volunteerModal').style.display = 'none';
            document.getElementById('volunteerForm').reset();
            document.getElementById('volunteerPhotoPreview').style.display = 'none';
            document.getElementById('volunteerPhotoIdPreview').style.display = 'none';
            document.getElementById('volunteerCertificationsPreview').style.display = 'none';
            document.getElementById('volunteerPhotoPreview').innerHTML = '';
            document.getElementById('volunteerPhotoIdPreview').innerHTML = '';
            document.getElementById('volunteerCertificationsPreview').innerHTML = '';
            selectedCertFiles = [];
        }

        function submitTip(event) {
            event.preventDefault();
            
            const location = document.getElementById('tipLocation').value.trim();
            const description = document.getElementById('tipDescription').value.trim();
            
            if (!location || !description) {
                showSuccessModal('Validation Error', 'Please fill in all required fields.', true);
                return;
            }
            
            // Submit to database
            fetch('api/tips.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'create',
                    location: location,
                    description: description
                })
            })
            .then(res => res.json())
            .then(result => {
                if (!result.success) {
                    showSuccessModal('Error', result.message || 'Failed to submit tip. Please try again.', true);
                    return;
                }
                
                const message = 'Your tip ID is: <strong style="font-size: 1.2em; color: #fff;">' + result.data.tip_id + '</strong><br><br>Your tip has been received and will be reviewed.';
                showSuccessModal('Tip Submitted Successfully!', message, false);
                document.getElementById('tipForm').reset();
                closeTipModal();
            })
            .catch(err => {
                console.error('Error submitting tip:', err);
                showSuccessModal('Error', 'Error submitting tip. Please try again.', true);
            });
        }

        function previewVolunteerImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (!preview) return;
            
            preview.innerHTML = '';
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.style.cssText = 'max-width: 100%; max-height: 200px; border-radius: 8px; border: 2px solid rgba(255, 255, 255, 0.2); object-fit: cover; cursor: pointer; transition: transform 0.2s ease;';
                    img.alt = file.name;
                    img.onmouseover = function() { this.style.transform = 'scale(1.02)'; };
                    img.onmouseout = function() { this.style.transform = 'scale(1)'; };
                    img.onclick = function() { viewPhoto(img.src); };
                    
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        img.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                    preview.appendChild(img);
                } else {
                    const label = document.createElement('div');
                    label.textContent = file.name;
                    label.style.cssText = 'color: #f8fafc; padding: 0.5rem; background: rgba(255, 255, 255, 0.05); border-radius: 6px;';
                    preview.appendChild(label);
                }
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }

        function handleCertificationUpload(input) {
            if (input.files && input.files.length > 0) {
                const existing = new Set(selectedCertFiles.map(f => `${f.name}|${f.size}`));
                Array.from(input.files).forEach(file => {
                    const key = `${file.name}|${file.size}`;
                    if (!existing.has(key)) {
                        selectedCertFiles.push(file);
                        existing.add(key);
                    }
                });
            }
            
            renderCertificationsPreview(input, document.getElementById('volunteerCertificationsPreview'));
        }

        function renderCertificationsPreview(input, preview) {
            preview.innerHTML = '';
            
            if (selectedCertFiles.length > 0) {
                selectedCertFiles.forEach((file, index) => {
                    const wrapper = document.createElement('div');
                    wrapper.style.cssText = 'display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; padding: 0.75rem; background: rgba(255, 255, 255, 0.08); border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.1); transition: background 0.2s ease;';
                    wrapper.onmouseover = function() { this.style.background = 'rgba(255, 255, 255, 0.12)'; };
                    wrapper.onmouseout = function() { this.style.background = 'rgba(255, 255, 255, 0.08)'; };
                    
                    if (file.type.startsWith('image/')) {
                        const img = document.createElement('img');
                        img.style.cssText = 'width: 60px; height: 60px; object-fit: cover; border-radius: 6px; border: 2px solid rgba(255, 255, 255, 0.2); cursor: pointer; transition: transform 0.2s ease;';
                        img.alt = file.name;
                        img.onmouseover = function() { this.style.transform = 'scale(1.1)'; };
                        img.onmouseout = function() { this.style.transform = 'scale(1)'; };
                        img.onclick = function() { viewPhoto(img.src); };
                        
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            img.src = e.target.result;
                        };
                        reader.readAsDataURL(file);
                        wrapper.appendChild(img);
                    } else {
                        const fileIcon = document.createElement('div');
                        fileIcon.style.cssText = 'width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; background: rgba(255, 255, 255, 0.1); border-radius: 6px; border: 2px solid rgba(255, 255, 255, 0.2);';
                        fileIcon.innerHTML = '<i class="fas fa-file-pdf" style="font-size: 1.5rem; color: rgba(255, 255, 255, 0.8);"></i>';
                        wrapper.appendChild(fileIcon);
                    }
                    
                    const label = document.createElement('div');
                    label.textContent = file.name;
                    label.style.cssText = 'flex: 1; font-size: 0.85rem; color: #f8fafc; padding: 0 0.5rem; word-break: break-word;';
                    wrapper.appendChild(label);
                    
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.textContent = 'Remove';
                    removeBtn.style.cssText = 'padding: 0.4rem 0.75rem; font-size: 0.75rem; border-radius: 6px; border: 1px solid rgba(255, 255, 255, 0.3); cursor: pointer; background: rgba(239, 68, 68, 0.2); color: #f8fafc; transition: all 0.2s ease;';
                    removeBtn.onmouseover = function() { this.style.background = 'rgba(239, 68, 68, 0.4)'; this.style.borderColor = 'rgba(255, 255, 255, 0.5)'; };
                    removeBtn.onmouseout = function() { this.style.background = 'rgba(239, 68, 68, 0.2)'; this.style.borderColor = 'rgba(255, 255, 255, 0.3)'; };
                    removeBtn.onclick = function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        selectedCertFiles = selectedCertFiles.filter(f => f !== file);
                        const dt = new DataTransfer();
                        selectedCertFiles.forEach(f => dt.items.add(f));
                        input.files = dt.files;
                        renderCertificationsPreview(input, preview);
                    };
                    wrapper.appendChild(removeBtn);
                    
                    preview.appendChild(wrapper);
                });
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }

        function submitVolunteer(event) {
            event.preventDefault();
            
            const name = document.getElementById('volunteerName').value.trim();
            const contact = document.getElementById('volunteerContact').value.trim();
            const email = document.getElementById('volunteerEmail').value.trim();
            const address = document.getElementById('volunteerAddress').value.trim();
            const category = document.getElementById('volunteerCategory').value;
            const skills = document.getElementById('volunteerSkills').value.trim();
            const availability = document.getElementById('volunteerAvailability').value;
            const emergencyName = document.getElementById('volunteerEmergencyName').value.trim();
            const emergencyContact = document.getElementById('volunteerEmergencyContact').value.trim();
            const photoFile = document.getElementById('volunteerPhoto').files[0];
            const photoIdFile = document.getElementById('volunteerPhotoId').files[0];
            const certDescription = document.getElementById('volunteerCertificationsDescription').value.trim();
            
            if (!name || !contact || !email || !address || !category || !skills || !availability || !emergencyName || !emergencyContact || !photoFile || !photoIdFile) {
                alert('Please fill in all required fields.');
                return;
            }
            
            // Process certifications
            let certificationsData = [];
            const certPromises = selectedCertFiles.map(file => {
                return new Promise(resolve => {
                    const reader = new FileReader();
                    reader.onload = e => {
                        certificationsData.push({
                            name: file.name,
                            data: e.target.result,
                            type: file.type
                        });
                        resolve();
                    };
                    reader.readAsDataURL(file);
                });
            });
            
            // Read photo files as base64
            const reader1 = new FileReader();
            reader1.onload = function(e1) {
                const photoSrc = e1.target.result;
                
                const reader2 = new FileReader();
                reader2.onload = function(e2) {
                    const photoIdSrc = e2.target.result;
                    
                    // Wait for all certifications to be processed
                    Promise.all(certPromises).then(() => {
                        // Send to API
                        const formData = {
                            action: 'create',
                            name: name,
                            contact: contact,
                            email: email,
                            address: address,
                            category: category,
                            skills: skills,
                            availability: availability,
                            status: 'Pending',
                            notes: '',
                            photo: photoSrc,
                            photo_id: photoIdSrc,
                            certifications: certificationsData,
                            certifications_description: certDescription,
                            emergency_contact_name: emergencyName,
                            emergency_contact_number: emergencyContact
                        };
                        
                        fetch('api/volunteers.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(formData)
                        })
                        .then(res => res.json())
                        .then(result => {
                            if (!result.success) {
                                alert(result.message || 'Failed to submit registration. Please try again.');
                                return;
                            }
                            
                            closeVolunteerModal();
                            // Show beautiful success modal
                            setTimeout(() => {
                                showRegistrationSuccessModal();
                            }, 300);
                        })
                        .catch(err => {
                            console.error('Error submitting volunteer registration:', err);
                            alert('Error submitting registration. Please try again.');
                        });
                    });
                };
                reader2.readAsDataURL(photoIdFile);
            };
            reader1.readAsDataURL(photoFile);
        }

        function showRegistrationSuccessModal() {
            document.getElementById('registrationSuccessModal').classList.add('active');
        }

        function closeRegistrationSuccessModal() {
            document.getElementById('registrationSuccessModal').classList.remove('active');
        }

        function viewPhoto(src) {
            const modal = document.createElement('div');
            modal.style.cssText = 'position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.9); display: flex; align-items: center; justify-content: center;';
            modal.onclick = function() { document.body.removeChild(modal); };
            
            const img = document.createElement('img');
            img.src = src;
            img.style.cssText = 'max-width: 90%; max-height: 90%; border-radius: 8px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);';
            img.onclick = function(e) { e.stopPropagation(); };
            
            modal.appendChild(img);
            document.body.appendChild(modal);
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const tipModal = document.getElementById('tipModal');
            const volunteerModal = document.getElementById('volunteerModal');
            const successModal = document.getElementById('registrationSuccessModal');
            if (event.target === tipModal) {
                closeTipModal();
            }
            if (event.target === volunteerModal) {
                closeVolunteerModal();
            }
            if (event.target === successModal) {
                closeRegistrationSuccessModal();
            }
            const tipSuccessModal = document.getElementById('successModal');
            if (event.target === tipSuccessModal && !tipSuccessModal.querySelector('.modal-content').contains(event.target)) {
                closeSuccessModal();
            }
        }
    </script>
</body>
</html>

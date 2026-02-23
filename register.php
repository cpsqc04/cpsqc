<?php 
// Admin registration page
session_start();

require_once __DIR__ . '/db.php';

/**
 * Ensure the admins table exists (same as in login.php).
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
}

try {
    ensureAdminsTable($pdo);
} catch (PDOException $e) {
    $error = 'Registration system error: ' . htmlspecialchars($e->getMessage());
}

// Database-backed registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error)) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    
    // Validation
    if ($username === '' || $password === '' || $confirmPassword === '' || $email === '' || $fullName === '') {
        $error = 'All fields are required';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        // Check for existing username or email
        $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = :u OR email = :e LIMIT 1');
        $stmt->execute([':u' => $username, ':e' => $email]);
        $existing = $stmt->fetch();
        if ($existing) {
            $error = 'Username or email is already taken';
        } else {
            // Save to database
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash, email, full_name) VALUES (:u, :p, :e, :f)');
            $stmt->execute([
                ':u' => $username,
                ':p' => $hash,
                ':e' => $email,
                ':f' => $fullName,
            ]);

            $_SESSION['registration_success'] = true;
            $_SESSION['registered_username'] = $username;
            header('Location: login.php?noOverlay=1');
            exit;
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
    <title>Admin Registration - Community Policing and Surveillance</title>
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
        .button-group a {
            flex: 1;
            text-decoration: none;
            display: inline-flex;
            justify-content: center;
            align-items: center;
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
            margin-top: 2rem;
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
        .error-message {
            color: #ef4444;
            font-size: 0.9rem;
            padding: 0.75rem;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 6px;
            margin-bottom: 1rem;
            border: 1px solid rgba(239, 68, 68, 0.2);
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
        @media (max-width: 768px) {
            body {
                padding: 1.5rem 1rem;
            }
            .logo-wrap img {
                height: 200px;
            }
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
                    <p>Create your admin account to access the Community Policing and Surveillance system.</p>
                </div>
            </section>

            <section class="login-card" aria-labelledby="register-title">
                <h2 id="register-title">Admin Registration</h2>
                <?php if (isset($error)): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="field">
                        <label for="full_name">Full Name</label>
                        <input id="full_name" name="full_name" type="text" placeholder="Enter your full name" required>
                    </div>
                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" placeholder="Enter your email" required>
                    </div>
                    <div class="field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" placeholder="Enter your username" required>
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" placeholder="••••••••" required minlength="6">
                    </div>
                    <div class="field">
                        <label for="confirm_password">Confirm Password</label>
                        <input id="confirm_password" name="confirm_password" type="password" placeholder="••••••••" required minlength="6">
                    </div>
                    <div class="actions">
                        <a href="login.php">Already have an account? Sign in</a>
                    </div>
                    <div class="button-group">
                        <a href="login.php?noOverlay=1" class="btn btn-secondary">Back to Login</a>
                        <button class="btn" type="submit">Register</button>
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
</body>
</html>


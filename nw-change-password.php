<?php
require_once __DIR__ . '/includes/nw_member_auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api/nw_members_schema.php';

nwMemberSessionStart();
requireNwMemberLogin();

if (!nwMemberMustChangePassword()) {
    header('Location: nw-account-settings.php');
    exit;
}

$error = null;
$memberName = htmlspecialchars(getNwMemberName());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword === '' || $confirmPassword === '') {
        $error = 'Please fill in all password fields.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            ensureNwMembersTable($pdo);
            $stmt = $pdo->prepare('SELECT password_hash FROM nw_members WHERE id = :id AND status = :status LIMIT 1');
            $stmt->execute([
                ':id' => getNwMemberId(),
                ':status' => 'Active',
            ]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$member) {
                $error = 'Account not found or inactive.';
            } else {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $pdo->prepare('UPDATE nw_members SET password_hash = :password_hash, must_change_password = 0 WHERE id = :id');
                $update->execute([
                    ':password_hash' => $passwordHash,
                    ':id' => getNwMemberId(),
                ]);

                $_SESSION['nw_member_must_change_password'] = false;
                header('Location: nw-dashboard.php?password_changed=1');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'System error. Please try again later.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Your Password - Alertara</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        :root { --radius: 12px; }
        body {
            margin: 0;
            font-family: var(--font-family);
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
        }
        .login-card {
            width: 100%;
            max-width: 520px;
            background: linear-gradient(145deg, var(--tertiary-color), var(--secondary-color));
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius);
            box-shadow: 0 20px 50px -25px rgba(0, 0, 0, 0.5);
            padding: clamp(2.5rem, 4vw, 3.5rem);
            color: #fff;
        }
        .login-card h1 { margin: 0 0 0.5rem; font-size: 1.75rem; }
        .login-card p { color: rgba(255,255,255,0.85); line-height: 1.6; margin: 0 0 1rem; }
        .field { display: grid; gap: 0.35rem; margin-top: 1rem; }
        .field label { font-weight: 500; color: rgba(255,255,255,0.85); }
        .field input {
            width: 100%;
            padding: 1rem 1.25rem;
            border-radius: var(--radius);
            border: 1px solid rgba(255,255,255,0.16);
            background: rgba(255,255,255,0.08);
            color: #fff;
            font: inherit;
            box-sizing: border-box;
        }
        .field input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(76,138,137,0.25); }
        .btn { margin-top: 1.5rem; width: 100%; padding: 1rem; border: none; border-radius: var(--radius); background: var(--primary-color); color: #fff; font-weight: 600; font-size: 1rem; cursor: pointer; }
        .btn:hover { background: #4ca8a6; }
        .alert-error { margin-top: 1rem; padding: 0.75rem 1rem; border-radius: 8px; background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.25); color: #fecaca; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Set Your Password</h1>
        <p>Hello, <strong><?php echo $memberName; ?></strong>.</p>
        <p>For security, please set a new password before using the member portal.</p>
        <?php if ($error): ?><div class="alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="POST">
            <div class="field">
                <label for="new_password">New Password</label>
                <input id="new_password" name="new_password" type="password" minlength="6" required>
            </div>
            <div class="field">
                <label for="confirm_password">Confirm New Password</label>
                <input id="confirm_password" name="confirm_password" type="password" minlength="6" required>
            </div>
            <button class="btn" type="submit">Set Password &amp; Continue</button>
        </form>
    </div>
</body>
</html>

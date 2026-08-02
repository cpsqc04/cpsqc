<?php
/**
 * Public password reset page (token from email link).
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/password_reset_tokens.php';
require_once __DIR__ . '/api/neighborhood-watcher-members-schema.php';

$error = '';
$success = '';
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$tokenRow = null;

if (!($pdo instanceof PDO)) {
    $error = 'Service temporarily unavailable. Please try again later.';
} elseif ($token === '') {
    $error = 'Invalid or missing reset link.';
} else {
    try {
        ensurePasswordResetTokensTable($pdo);
        $tokenRow = findValidPasswordResetToken($pdo, $token);
        if (!$tokenRow) {
            $error = 'This reset link is invalid or has expired. Ask an administrator to send a new one.';
        }
    } catch (Throwable $e) {
        error_log('Reset password token lookup failed: ' . $e->getMessage());
        $error = 'Unable to verify reset link. Please try again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '' && $tokenRow) {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($password === '' || $confirm === '') {
        $error = 'Please enter and confirm your new password.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9!@#$%^&*(),.?":{}|<>]/', $password)) {
        $error = 'Password must contain at least one capital letter and one number or special character.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $type = $tokenRow['account_type'];
            $id = $tokenRow['account_id'];
            $updated = false;

            if ($type === 'admin') {
                try {
                    $stmt = $pdo->prepare('UPDATE admins SET password_hash = :password_hash, must_change_password = 0 WHERE id = :id');
                    $stmt->execute([':password_hash' => $hash, ':id' => $id]);
                } catch (PDOException $e) {
                    $stmt = $pdo->prepare('UPDATE admins SET password_hash = :password_hash WHERE id = :id');
                    $stmt->execute([':password_hash' => $hash, ':id' => $id]);
                }
                $updated = true;
            } elseif ($type === 'bpso') {
                $stmt = $pdo->prepare('UPDATE patrols SET password_hash = :password_hash, must_change_password = 0 WHERE id = :id');
                try {
                    $stmt->execute([':password_hash' => $hash, ':id' => $id]);
                } catch (PDOException $e) {
                    $stmt = $pdo->prepare('UPDATE patrols SET password_hash = :password_hash WHERE id = :id');
                    $stmt->execute([':password_hash' => $hash, ':id' => $id]);
                }
                $updated = true;
            } elseif ($type === 'nw') {
                ensureNwMembersTable($pdo);
                $table = nwMembersTableName();
                $stmt = $pdo->prepare("UPDATE {$table} SET password_hash = :password_hash, must_change_password = 0 WHERE id = :id");
                $stmt->execute([':password_hash' => $hash, ':id' => $id]);
                $updated = true;
            }

            if ($updated) {
                markPasswordResetTokenUsed($pdo, $tokenRow['id']);
                $success = 'Your password has been updated. You can now sign in with your new password.';
                $tokenRow = null;
            } else {
                $error = 'Failed to update password. Please request a new reset link.';
            }
        } catch (Throwable $e) {
            error_log('Reset password update failed: ' . $e->getMessage());
            $error = 'Failed to update password. Please try again.';
        }
    }
}

$loginHref = 'login.php';
if ($tokenRow) {
    if ($tokenRow['account_type'] === 'bpso') {
        $loginHref = 'patrol-login.php';
    } elseif ($tokenRow['account_type'] === 'nw') {
        $loginHref = 'neighborhood-watcher-login.php';
    }
} elseif ($success !== '') {
    // After success, infer from posted token type if possible.
    $loginHref = 'login.php';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Alertara</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: var(--font-family); background: linear-gradient(145deg, #eef5f5, #f7fafc); }
        .card { width: min(440px, 92vw); background: #fff; border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        h1 { margin: 0 0 0.5rem; color: var(--tertiary-color); font-size: 1.5rem; }
        p { margin: 0 0 1rem; color: var(--text-secondary); font-size: 0.95rem; }
        label { display: block; margin: 0 0 0.4rem; font-weight: 600; color: var(--text-color); }
        input { width: 100%; box-sizing: border-box; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 1rem; font-size: 0.95rem; }
        button { width: 100%; border: none; border-radius: 8px; padding: 0.85rem 1rem; background: var(--primary-color); color: #fff; font-weight: 600; cursor: pointer; }
        button:hover { background: #4ca8a6; }
        .alert { padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .hint { font-size: 0.82rem; color: var(--text-secondary); margin: -0.5rem 0 1rem; }
        a { color: var(--primary-color); }
    </style>
</head>
<body>
    <div class="card">
        <h1>Reset Password</h1>
        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <p><a href="<?php echo htmlspecialchars($loginHref); ?>">Go to sign in</a></p>
        <?php elseif ($error !== '' && !$tokenRow): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <p><a href="login.php">Back to login</a></p>
        <?php else: ?>
            <p>Choose a new password for your account.</p>
            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <label for="password">New Password</label>
                <input id="password" name="password" type="password" required minlength="8">
                <p class="hint">Must be at least 8 characters with an uppercase letter and a number or symbol.</p>
                <label for="confirm_password">Confirm Password</label>
                <input id="confirm_password" name="confirm_password" type="password" required minlength="8">
                <button type="submit">Update Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

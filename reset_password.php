<?php
require_once 'includes/security.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

start_secure_session();

$error = '';
$success = '';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$newPassword = '';
$confirmPassword = '';
$userId = null;

if ($token === '') {
    $error = 'Invalid or missing password reset token.';
} else {
    ensure_password_resets_table_exists($pdo);


    $stmt = $pdo->prepare('SELECT pr.user_id, u.email FROM password_resets pr JOIN users u ON pr.user_id = u.id WHERE pr.token = ? AND pr.expires_at > NOW() LIMIT 1');
    $stmt->execute([$token]);
    $resetEntry = $stmt->fetch();

    if (!$resetEntry) {
        $error = 'This password reset link is invalid or has expired.';
    } else {
        $userId = $resetEntry['user_id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId !== null) {
    require_post_csrf();
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword === '' || $confirmPassword === '') {
        $error = 'Both password fields are required.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
        $error = 'Password must include at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $newPassword)) {
        $error = 'Password must include at least one lowercase letter.';
    } elseif (!preg_match('/\d/', $newPassword)) {
        $error = 'Password must include at least one number.';
    } elseif (!preg_match('/[^a-zA-Z\d]/', $newPassword)) {
        $error = 'Password must include at least one special character.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Password and confirmation do not match.';
    }

    if ($error === '') {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$hash, $userId]);
        $stmt = $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?');
        $stmt->execute([$userId]);
        $success = 'Your password has been reset successfully. You may now <a href="index.php">log in</a>.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container-fluid px-3" style="max-width: 520px; margin-top: min(100px, 12vh);">
    <div class="card shadow">
        <div class="card-header bg-primary text-white text-center">
            <h4>Reset Password</h4>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <?php if (!$success && !$error): ?>
                <form method="POST" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required>
                        <div class="invalid-feedback">Enter a new password.</div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        <div class="invalid-feedback">Confirm your new password.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Set new password</button>
                </form>
            <?php endif; ?>
            <div class="mt-3 text-center">
                <a href="index.php" class="small">Back to login</a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>
</body>
</html>

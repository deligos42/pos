<?php
require_once 'includes/security.php';

start_secure_session();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
require_once 'config/db.php';

$error = '';
$login = '';
$login_type = 'username';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $login = trim($_POST['username'] ?? '');
    $login_type = in_array($_POST['login_type'] ?? 'username', ['username', 'email'], true) ? $_POST['login_type'] : 'username';
    $password = $_POST['password'] ?? '';
    $attemptKey = 'login_attempts_' . sha1(strtolower($login) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'local'));
    $attempt = $_SESSION[$attemptKey] ?? ['count' => 0, 'until' => 0];

    if (($attempt['until'] ?? 0) > time()) {
        $error = 'Too many login attempts. Please try again in a few minutes.';
    } else {
        if ($login_type === 'email' && $login !== '' && !filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $query = $login_type === 'email'
                ? 'SELECT * FROM users WHERE email = ? LIMIT 1'
                : 'SELECT * FROM users WHERE username = ? LIMIT 1';
            $stmt = $pdo->prepare($query);
            $stmt->execute([$login]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if (empty($user['email_verified'])) {
                    $error = 'Please verify your email address before logging in. Check your inbox for the OTP.';
                } else {
                    session_regenerate_id(true);
                    unset($_SESSION[$attemptKey]);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['profile_photo'] = $user['profile_photo'] ?? null;
                    csrf_token();
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $attempt['count'] = (int)($attempt['count'] ?? 0) + 1;
                $attempt['until'] = $attempt['count'] >= 5 ? time() + 300 : 0;
                $_SESSION[$attemptKey] = $attempt;
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Login</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="assets/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .login-logo {
            width: 96px;
            height: 96px;
            max-width: 45%;
            object-fit: contain;
            border-radius: 50% !important;
            overflow: hidden;
        }
        .app-toast-container { z-index: 1080; }
        .app-toast { min-width: 280px; box-shadow: 0 0.75rem 1.5rem rgba(0,0,0,.18); }
        @media (max-width: 575.98px) {
            body { min-height: 100vh; }
            .card-header h4 { font-size: 1.2rem; }
            .app-toast-container { left: 0; right: 0; padding: 0.75rem !important; }
            .app-toast { width: 100%; min-width: 0; }
        }
    </style>
</head>
<body class="bg-light">
<div class="toast-container position-fixed top-0 end-0 p-3 app-toast-container" id="appToastContainer"></div>
<div class="container-fluid px-3" style="max-width: 420px; margin-top: min(100px, 12vh);">
    <div class="card shadow">
        <div class="card-header bg-primary text-white text-center">
            <img src="assets/DELIGOS%20LOGO.png" class="login-logo bg-white p-2 mb-2" alt="Deligos Company">
            <h4>POS System Login</h4>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Login with</label>
                    <div class="btn-group w-100" role="group" aria-label="Login method">
                        <input type="radio" class="btn-check" name="login_type" id="loginTypeUsername" value="username" <?= $login_type === 'username' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-secondary" for="loginTypeUsername">Username</label>
                        <input type="radio" class="btn-check" name="login_type" id="loginTypeEmail" value="email" <?= $login_type === 'email' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-secondary" for="loginTypeEmail">Email</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label id="loginFieldLabel">Username</label>
                    <input type="text" name="username" id="loginField" class="form-control" placeholder="Username" value="<?= htmlspecialchars($login) ?>" required autofocus>
                    <div class="invalid-feedback">Username is required.</div>
                </div>
                <div class="mb-3">
                    <label>Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="loginPassword" class="form-control password-input" data-hint-id="loginPasswordHint" required>
                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="loginPassword" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback">Password is required.</div>
                    <div class="form-text text-muted mt-1 password-hint" id="loginPasswordHint" style="display:none;">Password must be 8+ characters, include uppercase, lowercase, a number, and a special symbol.</div>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
            <div class="mt-3 text-center">
                <a href="forgot_password.php" class="small">Forgot password?</a>
            </div>
            <div class="mt-3 text-center">
                <span class="text-muted small">Don't have an account?</span>
                <a href="register.php" class="small">Register</a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.showToast = function(message, type = 'info') {
    const container = document.getElementById('appToastContainer');
    if (!container || !message) {
        return;
    }

    const styles = {
        success: { className: 'text-bg-success', icon: 'bi-check-circle-fill' },
        error: { className: 'text-bg-danger', icon: 'bi-exclamation-triangle-fill' },
        danger: { className: 'text-bg-danger', icon: 'bi-exclamation-triangle-fill' },
        warning: { className: 'text-bg-warning', icon: 'bi-exclamation-circle-fill' },
        info: { className: 'text-bg-primary', icon: 'bi-info-circle-fill' }
    };
    const style = styles[type] || styles.info;
    const toast = document.createElement('div');
    toast.className = `toast app-toast align-items-center border-0 ${style.className}`;
    toast.role = 'alert';
    toast.ariaLive = 'assertive';
    toast.ariaAtomic = 'true';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi ${style.icon} me-2"></i>${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    container.appendChild(toast);

    const instance = new bootstrap.Toast(toast, { delay: 3500 });
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
    instance.show();
};

document.querySelectorAll('.alert').forEach(alertBox => {
    const message = alertBox.textContent.trim();
    if (!message) {
        return;
    }

    const type = alertBox.classList.contains('alert-success') ? 'success'
        : alertBox.classList.contains('alert-danger') ? 'error'
        : alertBox.classList.contains('alert-warning') ? 'warning'
        : 'info';
    window.showToast(message, type);
});

const loginTypeRadios = document.querySelectorAll('input[name="login_type"]');
const loginField = document.getElementById('loginField');
const loginFieldLabel = document.getElementById('loginFieldLabel');
const loginFieldFeedback = loginField ? loginField.parentElement.querySelector('.invalid-feedback') : null;

function updateLoginFieldMode() {
    const selected = document.querySelector('input[name="login_type"]:checked')?.value || 'username';
    if (!loginField) {
        return;
    }

    if (selected === 'email') {
        loginField.type = 'email';
        loginField.placeholder = 'Email';
        loginFieldLabel.textContent = 'Email';
        if (loginFieldFeedback) {
            loginFieldFeedback.textContent = 'A valid email is required.';
        }
    } else {
        loginField.type = 'text';
        loginField.placeholder = 'Username';
        loginFieldLabel.textContent = 'Username';
        if (loginFieldFeedback) {
            loginFieldFeedback.textContent = 'Username is required.';
        }
    }
}

loginTypeRadios.forEach(radio => radio.addEventListener('change', updateLoginFieldMode));
updateLoginFieldMode();

document.querySelectorAll('input[type="email"]').forEach(input => {
    const validateEmailInput = () => {
        const value = input.value.trim();
        if (!value) {
            input.setCustomValidity('');
            return;
        }
        input.setCustomValidity(/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? '' : 'Please enter a valid email address.');
    };

    input.addEventListener('input', validateEmailInput);
    input.addEventListener('blur', validateEmailInput);
    input.addEventListener('change', validateEmailInput);
    validateEmailInput();
});

document.querySelectorAll('.password-input').forEach(input => {
    const hint = document.getElementById(input.dataset.hintId);
    if (!hint) {
        return;
    }

    const showHint = () => {
        hint.style.display = 'block';
    };
    const hideHint = () => {
        hint.style.display = 'none';
    };

    input.addEventListener('focus', showHint);
    input.addEventListener('click', showHint);
    input.addEventListener('blur', hideHint);
});

document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.target);
        const icon = button.querySelector('i');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
});
</script>
</body>
</html>

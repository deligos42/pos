<?php
require_once 'includes/security.php';

start_secure_session();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
require_once 'config/db.php';

$message = '';
$error = '';
$full_name = '';
$username = '';
$phone = '';
$id_number = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($full_name === '' || $username === '' || $phone === '' || $id_number === '' || $email === '' || $password === '' || $confirm_password === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must include at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must include at least one lowercase letter.';
    } elseif (!preg_match('/\d/', $password)) {
        $error = 'Password must include at least one number.';
    } elseif (!preg_match('/[^a-zA-Z\d]/', $password)) {
        $error = 'Password must include at least one special character.';
    } elseif ($password !== $confirm_password) {
        $error = 'Password and confirmation do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT username, email, id_number FROM users WHERE username = ? OR email = ? OR id_number = ? LIMIT 1");
        $stmt->execute([$username, $email, $id_number]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_user && $existing_user['username'] === $username) {
            $error = 'That username is already registered.';
        } elseif ($existing_user && $existing_user['email'] === $email) {
            $error = 'That email is already registered.';
        } elseif ($existing_user && $existing_user['id_number'] === $id_number) {
            $error = 'That ID number is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, phone, id_number, email, role) VALUES (?, ?, ?, ?, ?, ?, 'cashier')");
            $stmt->execute([$username, $hash, $full_name, $phone, $id_number, $email]);
            $message = 'Account created successfully. You can now log in.';
            $full_name = '';
            $username = '';
            $phone = '';
            $id_number = '';
            $email = '';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Register</title>
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
        .required-mark { color: #dc3545; }
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
<div class="container-fluid px-3" style="max-width: 460px; margin-top: min(70px, 8vh);">
    <div class="card shadow">
        <div class="card-header bg-primary text-white text-center">
            <img src="assets/DELIGOS%20LOGO.png" class="login-logo bg-white p-2 mb-2" alt="Deligos Company">
            <h4>Create Account</h4>
        </div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label>Full Name <span class="required-mark">*</span></label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($full_name) ?>" required autofocus>
                    <div class="invalid-feedback">Full name is required.</div>
                </div>
                <div class="mb-3">
                    <label>Username <span class="required-mark">*</span></label>
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($username) ?>" required>
                    <div class="invalid-feedback">Username is required.</div>
                </div>
                <div class="mb-3">
                    <label>Phone Number <span class="required-mark">*</span></label>
                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($phone) ?>" required>
                    <div class="invalid-feedback">Phone number is required.</div>
                </div>
                <div class="mb-3">
                    <label>ID Number <span class="required-mark">*</span></label>
                    <input type="text" name="id_number" class="form-control" value="<?= htmlspecialchars($id_number) ?>" required>
                    <div class="invalid-feedback">ID number is required.</div>
                </div>
                <div class="mb-3">
                    <label>Email <span class="required-mark">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
                    <div class="invalid-feedback">A valid email is required.</div>
                </div>
                <div class="mb-3">
                    <label>Password <span class="required-mark">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password" id="registerPassword" class="form-control password-input" data-hint-id="registerPasswordHint" minlength="8" required>
                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="registerPassword" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback">Password is required.</div>
                    <div class="form-text text-muted mt-1 password-hint" id="registerPasswordHint" style="display:none;">Use at least 8 characters, with uppercase, lowercase, a number, and a special character.</div>
                </div>
                <div class="mb-3">
                    <label>Confirm Password <span class="required-mark">*</span></label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="confirmPassword" class="form-control password-input" data-hint-id="confirmPasswordHint" minlength="8" required>
                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirmPassword" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback">Please confirm your password.</div>
                    <div class="form-text text-muted mt-1 password-hint" id="confirmPasswordHint" style="display:none;">Re-enter the same password to confirm it.</div>
                </div>
                <button type="submit" class="btn btn-primary w-100">Register</button>
            </form>
            <div class="mt-3 text-center">
                <span class="text-muted small">Already have an account?</span>
                <a href="index.php" class="small">Login</a>
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

(function () {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(form => {
        form.addEventListener('submit', event => {
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

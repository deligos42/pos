<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
require_once 'config/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['profile_photo'] = $user['profile_photo'] ?? null;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
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
            <form method="POST">
                <div class="mb-3">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>
                <div class="mb-3">
                    <label>Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="loginPassword" class="form-control" required>
                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="loginPassword" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
            <div class="mt-3 text-center">
                <span class="text-muted small">Don't have an account?</span>
                <a href="register.php" class="small">Register</a>
            </div>
            <div class="mt-3 text-muted small">Default: admin / admin123</div>
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


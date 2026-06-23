<?php
session_start();
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

$required_user_columns = [
    'phone' => "ALTER TABLE users ADD COLUMN phone varchar(30) DEFAULT NULL AFTER full_name",
    'id_number' => "ALTER TABLE users ADD COLUMN id_number varchar(50) DEFAULT NULL AFTER phone",
    'email' => "ALTER TABLE users ADD COLUMN email varchar(100) DEFAULT NULL AFTER id_number",
];

foreach ($required_user_columns as $column => $sql) {
    try {
        $pdo->query("SELECT `$column` FROM users LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec($sql);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
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
    <title>POS Register</title>
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
    </style>
</head>
<body class="bg-light">
<div class="container" style="max-width: 440px; margin-top: 70px;">
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
            <form method="POST">
                <div class="mb-3">
                    <label>Full Name <span class="required-mark">*</span></label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($full_name) ?>" required autofocus>
                </div>
                <div class="mb-3">
                    <label>Username <span class="required-mark">*</span></label>
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($username) ?>" required>
                </div>
                <div class="mb-3">
                    <label>Phone Number <span class="required-mark">*</span></label>
                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($phone) ?>" required>
                </div>
                <div class="mb-3">
                    <label>ID Number <span class="required-mark">*</span></label>
                    <input type="text" name="id_number" class="form-control" value="<?= htmlspecialchars($id_number) ?>" required>
                </div>
                <div class="mb-3">
                    <label>Email <span class="required-mark">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
                </div>
                <div class="mb-3">
                    <label>Password <span class="required-mark">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password" id="registerPassword" class="form-control" minlength="6" required>
                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="registerPassword" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label>Confirm Password <span class="required-mark">*</span></label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="confirmPassword" class="form-control" minlength="6" required>
                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirmPassword" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
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
<script>
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

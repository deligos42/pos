<?php
$required_role = 'admin';
require_once 'includes/auth.php';
require_once 'config/db.php';
$pageTitle = 'Users';

$message = '';
$error = '';
$form_username = '';
$form_full_name = '';
$form_role = 'cashier';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    require_post_csrf();
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'cashier';
    $form_username = $username;
    $form_full_name = $full_name;
    $form_role = in_array($role, ['admin', 'cashier'], true) ? $role : 'cashier';

    if (!$username || !$full_name || !$password) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^a-zA-Z\d]/', $password)) {
        $error = 'Password must be 8+ characters and include uppercase, lowercase, a number, and a special symbol.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);

            if ($stmt->fetch()) {
                throw new RuntimeException('DUPLICATE_USERNAME');
            }

            $pdo->beginTransaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $hash, $full_name, $form_role]);
            $pdo->commit();
            $message = 'User added successfully!';
            $form_username = '';
            $form_full_name = '';
            $form_role = 'cashier';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e->getMessage() === 'DUPLICATE_USERNAME' || ($e instanceof PDOException && $e->getCode() === '23000')) {
                $error = 'Username "' . $username . '" is already taken. Please choose another username.';
            } else {
                $error = app_exception_message($e, 'We could not add this user right now. Please try again.');
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    require_post_csrf();
    $id = validate_int($_POST['user_id'] ?? null, 1) ?? 0;
    if ($id !== (int)($_SESSION['user_id'] ?? 0)) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    }
    header('Location: users.php');
    exit;
}

include 'includes/header.php';
?>
<h2>User Management</h2>
<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Add User</div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <input name="username" class="form-control" placeholder="Username" value="<?= htmlspecialchars($form_username) ?>" required>
                        <div class="invalid-feedback">Username is required.</div>
                    </div>
                    <div class="mb-2">
                        <input name="full_name" class="form-control" placeholder="Full Name" value="<?= htmlspecialchars($form_full_name) ?>" required>
                        <div class="invalid-feedback">Full name is required.</div>
                    </div>
                    <div class="mb-2">
                        <div class="input-group">
                            <input name="password" id="newUserPassword" type="password" class="form-control" placeholder="Password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="newUserPassword" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Password is required.</div>
                    </div>
                    <div class="mb-2">
                        <select name="role" class="form-select">
                            <option value="cashier" <?= $form_role === 'cashier' ? 'selected' : '' ?>>Cashier</option>
                            <option value="admin" <?= $form_role === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <button type="submit" name="add_user" value="1" class="btn btn-primary">Add User</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Existing Users</div>
            <div class="card-body">
                <table class="table">
                    <thead><tr><th>Username</th><th>Full Name</th><th>Role</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($pdo->query("SELECT * FROM users ORDER BY id") as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['full_name']) ?></td>
                            <td><?= htmlspecialchars($u['role']) ?></td>
                            <td>
                                <?php if ($u['role'] === 'cashier'): ?>
                                    <form method="POST" action="download_recommendation.php" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-file-earmark-pdf"></i> Recommendation PDF
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ((int)$u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit" name="delete_user" value="1" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
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
<?php include 'includes/footer.php'; ?>


<?php
$required_role = 'admin';
require_once 'includes/auth.php';
require_once 'config/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'cashier';

    if (!$username || !$full_name || !$password) {
        $error = 'All fields are required.';
    } else {
        try {
            $pdo->beginTransaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $hash, $full_name, $role]);
            $pdo->commit();
            $message = 'User added successfully!';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
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
                <form method="POST">
                    <div class="mb-2"><input name="username" class="form-control" placeholder="Username" required></div>
                    <div class="mb-2"><input name="full_name" class="form-control" placeholder="Full Name" required></div>
                    <div class="mb-2">
                        <div class="input-group">
                            <input name="password" id="newUserPassword" type="password" class="form-control" placeholder="Password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="newUserPassword" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-2">
                        <select name="role" class="form-select">
                            <option value="cashier">Cashier</option>
                            <option value="admin">Admin</option>
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
                                <?php if ((int)$u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                                    <a href="users.php?delete=<?= (int)$u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</a>
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


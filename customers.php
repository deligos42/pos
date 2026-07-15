<?php
require_once 'includes/auth.php';
require_once 'config/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    require_post_csrf();
    $fname = trim($_POST['first_name'] ?? '');
    $lname = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (!$fname || !$lname) {
        $error = 'First name and last name are required.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        try {
            if ($email !== '') {
                $check = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE email = ?");
                $check->execute([$email]);
                if ($check->fetchColumn() > 0) {
                    throw new Exception('A customer with that email already exists.');
                }
            }

            $stmt = $pdo->prepare("INSERT INTO customers (first_name, last_name, email, phone) VALUES (?, ?, ?, ?)");
            $stmt->execute([$fname, $lname, $email ?: null, $phone ?: null]);
            header('Location: customers.php');
            exit;
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    require_post_csrf();
    $id = validate_int($_POST['customer_id'] ?? null, 1);
    if ($id) {
        $pdo->prepare("DELETE FROM customers WHERE id = ?")->execute([$id]);
    }
    header('Location: customers.php');
    exit;
}

$pageTitle = 'Customers';
include 'includes/header.php';
?>
<h2>Customers</h2>
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Add Customer</div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <input name="first_name" class="form-control" placeholder="First Name" required>
                        <div class="invalid-feedback">First name is required.</div>
                    </div>
                    <div class="mb-2">
                        <input name="last_name" class="form-control" placeholder="Last Name" required>
                        <div class="invalid-feedback">Last name is required.</div>
                    </div>
                    <div class="mb-2">
                        <input name="email" type="email" class="form-control" placeholder="Email" autocomplete="email">
                        <div class="invalid-feedback">A valid email is required if provided.</div>
                    </div>
                    <div class="mb-2"><input name="phone" class="form-control" placeholder="Phone"></div>
                    <button type="submit" name="add_customer" class="btn btn-primary">Add</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Customer List</div>
            <div class="card-body">
                <table class="table">
                    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Points</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($pdo->query("SELECT * FROM customers ORDER BY id DESC") as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></td>
                            <td><?= htmlspecialchars((string)$c['email']) ?></td>
                            <td><?= htmlspecialchars((string)$c['phone']) ?></td>
                            <td><?= (int)$c['loyalty_points'] ?></td>
                            <td>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
                                    <button type="submit" name="delete_customer" value="1" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>


<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
$pageTitle = 'Customers';
include 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $fname = trim($_POST['first_name']);
    $lname = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    $stmt = $pdo->prepare("INSERT INTO customers (first_name, last_name, email, phone) VALUES (?, ?, ?, ?)");
    $stmt->execute([$fname, $lname, $email ?: null, $phone ?: null]);
    header('Location: customers.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM customers WHERE id = ?")->execute([$id]);
    header('Location: customers.php');
    exit;
}
?>
<h2>Customers</h2>
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Add Customer</div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="mb-2">
                        <input name="first_name" class="form-control" placeholder="First Name" required>
                        <div class="invalid-feedback">First name is required.</div>
                    </div>
                    <div class="mb-2">
                        <input name="last_name" class="form-control" placeholder="Last Name" required>
                        <div class="invalid-feedback">Last name is required.</div>
                    </div>
                    <div class="mb-2"><input name="email" type="email" class="form-control" placeholder="Email"></div>
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
                                <a href="customers.php?delete=<?= (int)$c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</a>
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


<?php
$required_role = 'admin';
require_once 'includes/auth.php';
require_once 'config/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    require_post_csrf();
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = validate_decimal($_POST['amount'] ?? null, 0.01);
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');

    if ($category === '' || $amount === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expense_date)) {
        $error = 'Category, valid date, and amount greater than zero are required.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO expenses (user_id, category, description, amount, expense_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $category, $description ?: null, $amount, $expense_date]);
        header('Location: expenses.php?added=1');
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_expense'])) {
    require_post_csrf();
    $id = validate_int($_POST['expense_id'] ?? null, 1);
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
    if ($id) {
        $stmt->execute([$id]);
    }
    header('Location: expenses.php?deleted=1');
    exit;
}

if (isset($_GET['added'])) {
    $message = 'Expense added successfully.';
}
if (isset($_GET['deleted'])) {
    $message = 'Expense deleted successfully.';
}

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');

$stmt = $pdo->prepare(
    "SELECT e.*, u.full_name
     FROM expenses e
     JOIN users u ON e.user_id = u.id
     WHERE e.expense_date BETWEEN ? AND ?
     ORDER BY e.expense_date DESC, e.id DESC"
);
$stmt->execute([$start, $end]);
$expenses = $stmt->fetchAll();

$total_expenses = 0;
foreach ($expenses as $expense) {
    $total_expenses += (float)$expense['amount'];
}

$pageTitle = 'Expenses';
include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="mb-0">Expenses Manager</h2>
    <a class="btn btn-outline-primary" href="profits.php"><i class="bi bi-graph-up"></i> Profit Manager</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="GET" class="row g-3 mb-3">
    <div class="col-auto"><label>From</label><input type="date" name="start" class="form-control" value="<?= htmlspecialchars($start) ?>"></div>
    <div class="col-auto"><label>To</label><input type="date" name="end" class="form-control" value="<?= htmlspecialchars($end) ?>"></div>
    <div class="col-auto align-self-end"><button class="btn btn-primary" type="submit">Filter</button></div>
</form>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header">Add Expense</div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <input name="category" class="form-control" placeholder="Category" required>
                        <div class="invalid-feedback">Category is required.</div>
                    </div>
                    <div class="mb-2"><input name="description" class="form-control" placeholder="Description"></div>
                    <div class="mb-2">
                        <input name="amount" type="number" step="0.01" min="0.01" class="form-control" placeholder="Amount" required>
                        <div class="invalid-feedback">Amount is required and must be greater than zero.</div>
                    </div>
                    <div class="mb-2">
                        <input name="expense_date" type="date" class="form-control" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
                        <div class="invalid-feedback">Expense date is required.</div>
                    </div>
                    <button type="submit" name="add_expense" value="1" class="btn btn-primary">Add Expense</button>
                </form>
            </div>
        </div>
        <div class="card text-white bg-danger">
            <div class="card-body">
                <h5 class="card-title">Total Expenses</h5>
                <p class="card-text display-6">KSh <?= number_format($total_expenses, 2) ?></p>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Expense List</div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Added By</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($expense['expense_date']))) ?></td>
                            <td><?= htmlspecialchars($expense['category']) ?></td>
                            <td><?= htmlspecialchars((string)$expense['description']) ?></td>
                            <td>KSh <?= number_format((float)$expense['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($expense['full_name']) ?></td>
                            <td>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this expense?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="expense_id" value="<?= (int)$expense['id'] ?>">
                                    <button type="submit" name="delete_expense" value="1" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$expenses): ?>
                        <tr><td colspan="6" class="text-muted">No expenses found for this period.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>

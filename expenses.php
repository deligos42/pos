<?php
$required_role = 'admin';
require_once 'includes/auth.php';
require_once 'config/db.php';

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS expenses (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        category varchar(100) NOT NULL,
        description varchar(255) DEFAULT NULL,
        amount decimal(10,2) NOT NULL,
        expense_date date NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY expense_date (expense_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');

    if ($category === '' || $amount <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expense_date)) {
        $error = 'Category, valid date, and amount greater than zero are required.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO expenses (user_id, category, description, amount, expense_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $category, $description ?: null, $amount, $expense_date]);
        header('Location: expenses.php?added=1');
        exit;
    }
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
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
                <form method="POST">
                    <div class="mb-2"><input name="category" class="form-control" placeholder="Category" required></div>
                    <div class="mb-2"><input name="description" class="form-control" placeholder="Description"></div>
                    <div class="mb-2"><input name="amount" type="number" step="0.01" min="0.01" class="form-control" placeholder="Amount" required></div>
                    <div class="mb-2"><input name="expense_date" type="date" class="form-control" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required></div>
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
                            <td><a href="expenses.php?delete=<?= (int)$expense['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this expense?')">Delete</a></td>
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


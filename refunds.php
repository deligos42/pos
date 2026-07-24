<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Allow only logged-in users to request refunds
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'Please log in to request a refund.';
    exit;
}

if (!user_can('refunds.create')) {
    http_response_code(403);
    $_SESSION['error'] = 'You do not have permission to request refunds.';
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Request Refund';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $sale_id = (int)($_POST['sale_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if ($sale_id <= 0 || $amount <= 0) {
        $_SESSION['error'] = 'Invalid sale or amount.';
        header('Location: refunds.php');
        exit;
    }

    $refund_id = create_refund_request($sale_id, $amount, $reason);
    if ($refund_id) {
        $_SESSION['message'] = 'Refund request submitted successfully.';
        header('Location: refunds.php');
        exit;
    } else {
        $_SESSION['error'] = 'Could not submit refund request. Try again later.';
    }
}

include 'includes/header.php';
?>

<h2>Request Refund</h2>

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']) ?></div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<form method="POST" action="">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="sale_id" class="form-label">Sale ID</label>
        <input type="number" name="sale_id" id="sale_id" class="form-control" required>
    </div>
    <div class="mb-3">
        <label for="amount" class="form-label">Amount</label>
        <input type="number" step="0.01" name="amount" id="amount" class="form-control" required>
    </div>
    <div class="mb-3">
        <label for="reason" class="form-label">Reason</label>
        <textarea name="reason" id="reason" class="form-control"></textarea>
    </div>
    <button class="btn btn-primary">Submit Refund Request</button>
</form>

<?php include 'includes/footer.php'; ?>

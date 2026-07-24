<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_permission('receipts.reprint', '/admin/index.php');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid receipt id.';
    exit;
}

$stmt = $pdo->prepare("SELECT r.*, s.invoice_no FROM receipts r LEFT JOIN sales s ON r.sale_id = s.id WHERE r.id = ?");
$stmt->execute([$id]);
$rec = $stmt->fetch();
if (!$rec) {
    http_response_code(404);
    echo 'Receipt not found.';
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<h2>Receipt #<?= $rec['id'] ?> (Sale: <?= htmlspecialchars($rec['invoice_no'] ?? $rec['sale_id']) ?>)</h2>

<pre><?= htmlspecialchars(json_encode(json_decode($rec['snapshot']), JSON_PRETTY_PRINT)) ?></pre>

<a href="admin/receipts.php" class="btn btn-secondary">Back</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>

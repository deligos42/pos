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

try {
    $stmt = $pdo->prepare("SELECT r.*, s.invoice_no FROM receipts r LEFT JOIN sales s ON r.sale_id = s.id WHERE r.id = ?");
    $stmt->execute([$id]);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$rec) {
        http_response_code(404);
        echo 'Receipt not found.';
        exit;
    }
} catch (Throwable $e) {
    if (function_exists('app_log')) {
        app_log('receipt_view error: ' . $e->getMessage());
    }
    http_response_code(500);
    echo 'An internal error occurred while loading the receipt. Please check the application logs.';
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<h2>Receipt #<?= $rec['id'] ?> (Sale: <?= htmlspecialchars($rec['invoice_no'] ?? $rec['sale_id']) ?>)</h2>

<?php
$requestId = bin2hex(random_bytes(8));
$snapshotPretty = '';
try {
    if (!empty($rec['snapshot'])) {
        $decoded = json_decode($rec['snapshot'], true, 512, JSON_THROW_ON_ERROR);
        $snapshotPretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } else {
        $snapshotPretty = 'No snapshot available for this receipt.';
    }
} catch (Throwable $e) {
    if (function_exists('app_log')) {
        app_log('receipt_view snapshot decode failed (req ' . $requestId . '): ' . $e->getMessage());
    }
    $raw = $rec['snapshot'] ?? '';
    $snapshotPretty = "Snapshot could not be decoded. Request ID: {$requestId}\n\nRaw (truncated): " . substr($raw, 0, 1000);
}
?>

<pre><?= htmlspecialchars($snapshotPretty, ENT_QUOTES, 'UTF-8') ?></pre>

<a href="admin/receipts.php" class="btn btn-secondary">Back</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>

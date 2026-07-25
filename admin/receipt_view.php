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
$decoded = null;
$raw = $rec['snapshot'] ?? '';
try {
    if (!empty($raw)) {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
} catch (Throwable $e) {
    if (function_exists('app_log')) {
        app_log('receipt_view snapshot decode failed (req ' . $requestId . '): ' . $e->getMessage());
    }
}

if (is_array($decoded) && !empty($decoded['items']) && is_array($decoded['items'])):
    $items = $decoded['items'];
    ?>
    <div class="card">
        <div class="card-body">
            <h5>Invoice: <?= htmlspecialchars($decoded['invoice_no'] ?? ($rec['invoice_no'] ?? '')) ?></h5>
            <p class="text-muted mb-2">Created: <?= htmlspecialchars($decoded['created_at'] ?? '') ?></p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Qty</th>
                            <th>SKU</th>
                            <th>Item</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td><?= htmlspecialchars((int)($it['qty'] ?? 0)) ?></td>
                                <td><?= htmlspecialchars($it['sku'] ?? '') ?></td>
                                <td><?= htmlspecialchars($it['name'] ?? '') ?></td>
                                <td class="text-end"><?= htmlspecialchars(number_format((float)($it['unit_price'] ?? 0), 2)) ?></td>
                                <td class="text-end"><?= htmlspecialchars(number_format((float)($it['total'] ?? 0), 2)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-end">
                <div style="min-width:220px;text-align:right;">
                    <div>Subtotal: <strong>KSh <?= htmlspecialchars(number_format((float)($decoded['total_amount'] ?? $decoded['grand_total'] ?? 0), 2)) ?></strong></div>
                    <div>Discount: <?= htmlspecialchars(number_format((float)($decoded['discount'] ?? 0), 2)) ?></div>
                    <div class="mt-2"><strong>Grand Total: KSh <?= htmlspecialchars(number_format((float)($decoded['grand_total'] ?? $decoded['total_amount'] ?? 0), 2)) ?></strong></div>
                </div>
            </div>
            <hr>
            <div class="small text-muted">Reference: <?= htmlspecialchars($rec['id']) ?> &nbsp;|&nbsp; Stored: <?= htmlspecialchars($rec['created_at'] ?? '') ?></div>
        </div>
    </div>
    <a href="admin/receipts.php" class="btn btn-secondary mt-3">Back</a>
<?php else: ?>
    <div class="alert alert-warning">Snapshot could not be rendered as a receipt. Showing raw data. Request ID: <?= htmlspecialchars($requestId) ?></div>
    <pre><?= htmlspecialchars(substr($raw, 0, 2000), ENT_QUOTES, 'UTF-8') ?></pre>
    <a href="admin/receipts.php" class="btn btn-secondary">Back</a>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
// Test Phase 2 flow: create sale, store receipt, request refund, approve refund, create closing report
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Simulate session user (admin)
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['user_id'] = (int)($argv[1] ?? 1);
$_SESSION['full_name'] = 'CLI Test User';

try {
    // Pick a product
    $p = $pdo->query('SELECT id, price, stock_qty, name FROM products LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$p) throw new RuntimeException('No product found in products table');
    if ((int)$p['stock_qty'] < 1) throw new RuntimeException('Not enough stock to run test; increase product stock');

    $invoice = generateInvoiceNo();
    $user_id = $_SESSION['user_id'];

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO sales (invoice_no, user_id, customer_id, total_amount, discount, grand_total, payment_method) VALUES (?, ?, ?, ?, ?, ?, 'Cash')");
    $subtotal = (float)$p['price'];
    $discount = 0.00;
    $grand = $subtotal - $discount;
    $stmt->execute([$invoice, $user_id, null, $subtotal, $discount, $grand]);
    $sale_id = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, qty, unit_price, total) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$sale_id, $p['id'], 1, $p['price'], $subtotal]);

    // Deduct stock using updateStock
    if (!updateStock($pdo, (int)$p['id'], -1, $user_id, 'sale', 'Test sale ' . $invoice)) {
        throw new RuntimeException('updateStock failed');
    }

    $pdo->commit();
    echo "Sale created: id={$sale_id}, invoice={$invoice}\n";

    // Store receipt
    $snapshot = [
        'sale_id' => $sale_id,
        'invoice_no' => $invoice,
        'user_id' => $user_id,
        'items' => [ ['name'=>$p['name'], 'qty'=>1, 'unit_price'=>(float)$p['price'], 'total'=> (float)$p['price'] ] ],
        'total_amount' => (float)$subtotal,
        'discount' => (float)$discount,
        'grand_total' => (float)$grand,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $receipt_id = store_receipt_snapshot($sale_id, $snapshot);
    echo "Stored receipt_id={$receipt_id}\n";

    // Create a refund request for partial amount
    $refund_amount = round($subtotal * 0.5, 2);
    $refund_id = create_refund_request($sale_id, $refund_amount, 'Test refund');
    echo "Refund requested: id={$refund_id}, amount={$refund_amount}\n";

    // Approve refund (simulate approver as same user)
    $_SESSION['user_id'] = $user_id;
    $approve_ok = approve_refund($refund_id, true, 'Approved in test');
    echo "Refund approved: " . ($approve_ok ? 'OK' : 'FAILED') . "\n";

    // Create closing report
    $shift_start = date('Y-m-d H:i:s', strtotime('-8 hours'));
    $shift_end = date('Y-m-d H:i:s');
    $expected = $grand;
    $counted = $grand;
    $closing_id = create_closing_report($user_id, $shift_start, $shift_end, $expected, $counted, 'Test closing');
    echo "Closing report created: id={$closing_id}\n";

    echo "TEST PHASE 2 FLOW: SUCCESS\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

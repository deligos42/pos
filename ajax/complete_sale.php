<?php
require_once '../includes/security.php';

start_secure_session();
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
if (!verify_csrf_request()) { http_response_code(403); exit; }

require_once '../config/db.php';
require_once '../includes/functions.php';

function cc_log($msg) {
    $file = __DIR__ . '/../logs/complete_sale.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    @file_put_contents($file, $line, FILE_APPEND);
}

$data = json_decode(file_get_contents('php://input'), true);
cc_log('Request received. Data: ' . json_encode($data) . ' User: ' . $_SESSION['user_id']);
if (!$data || empty($data['items'])) {
    cc_log('ERROR: No data or empty items');
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => app_error_message('No items', 'Please add at least one item before completing the sale.')]);
    exit;
}

$invoice_no = preg_match('/^INV-\d{8}-[A-Za-z0-9]{4,16}$/', (string)($data['invoice_no'] ?? '')) ? (string)$data['invoice_no'] : generateInvoiceNo();
$customer_id = !empty($data['customer_id']) ? validate_int($data['customer_id'], 1) : null;
if (!empty($data['customer_id']) && $customer_id === null) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => app_error_message('Invalid customer', 'The selected customer could not be validated.')]);
    exit;
}
$discount = validate_decimal($data['discount'] ?? 0, 0);
if ($discount === null) {
    $discount = 0;
}
$user_id = $_SESSION['user_id'];

$total_amount = 0;
foreach ($data['items'] as $item) {
    $qty = validate_int($item['qty'] ?? null, 1);
    $productId = validate_int($item['product_id'] ?? null, 1);
    if ($qty === null || $productId === null) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => app_error_message('Invalid sale item', 'One or more cart items are invalid.')]);
        exit;
    }
}

try {
    // validate customer_id if provided
    if ($customer_id !== null) {
        $cstmt = $pdo->prepare("SELECT id FROM customers WHERE id = ?");
        $cstmt->execute([$customer_id]);
        if (!$cstmt->fetch()) {
            throw new RuntimeException('Invalid customer id: ' . $customer_id);
        }
    }

    $pdo->beginTransaction();
    cc_log('Transaction started for invoice: ' . $invoice_no . ', customer: ' . $customer_id . ', user: ' . $user_id);

    // ensure invoice_no uniqueness (fail early with explicit message)
    $istmt = $pdo->prepare("SELECT id FROM sales WHERE invoice_no = ?");
    $istmt->execute([$invoice_no]);
    if ($istmt->fetch()) {
        throw new RuntimeException('Duplicate invoice_no: ' . $invoice_no);
    }

    $saleItems = [];
    foreach ($data['items'] as $item) {
        $product_id = (int)$item['product_id'];
        $qty = (int)$item['qty'];

        // Fetch authoritative product price and current stock with row lock
        $pstmt = $pdo->prepare("SELECT price, stock_qty FROM products WHERE id = ? FOR UPDATE");
        $pstmt->execute([$product_id]);
        $prod = $pstmt->fetch(PDO::FETCH_ASSOC);
        if (!$prod) {
            throw new RuntimeException('Product not found: ' . $product_id);
        }
        if ((int)$prod['stock_qty'] < $qty) {
            throw new RuntimeException('Insufficient stock for product id ' . $product_id);
        }

        $unit_price = (float)$prod['price'];
        $line_total = $unit_price * $qty;
        $total_amount += $line_total;
        $saleItems[] = [
            'product_id' => $product_id,
            'qty' => $qty,
            'unit_price' => $unit_price,
            'line_total' => $line_total,
        ];
    }

    if ($discount > $total_amount) {
        throw new RuntimeException('Discount cannot be greater than subtotal.');
    }
    $grand_total = $total_amount - $discount;

    $stmt = $pdo->prepare("INSERT INTO sales (invoice_no, user_id, customer_id, total_amount, discount, grand_total, payment_method)
                            VALUES (?, ?, ?, ?, ?, ?, 'Cash')");
    $stmt->execute([$invoice_no, $user_id, $customer_id, $total_amount, $discount, $grand_total]);
    $sale_id = $pdo->lastInsertId();

    foreach ($saleItems as $item) {
        $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, qty, unit_price, total)
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$sale_id, $item['product_id'], $item['qty'], $item['unit_price'], $item['line_total']]);

        // stock deduction + log (updateStock will not start a nested transaction)
        if (!updateStock($pdo, $item['product_id'], -$item['qty'], $user_id, 'sale', 'Sale #' . $invoice_no)) {
            throw new RuntimeException('Failed to update stock for product ' . $item['product_id']);
        }
    }

    $pdo->commit();
    cc_log('SUCCESS: Sale committed. Sale ID: ' . $sale_id . ', Invoice: ' . $invoice_no);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'invoice_no' => $invoice_no, 'sale_id' => (int)$sale_id]);
    exit;
} catch (Throwable $e) {
    cc_log('ERROR: Exception caught: ' . $e->getMessage());
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => app_exception_message($e, 'We could not complete the sale right now. Please try again.')]);
    exit;
}


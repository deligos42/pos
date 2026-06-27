<?php
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function generateInvoiceNo() {
    return 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
}

function getProductById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateStock($pdo, $product_id, $qty_change, $user_id, $type, $note = '') {
    // Avoid opening nested transactions. If an outer transaction exists, rely on it.
    $manageTx = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $manageTx = true;
    }
    try {
        // Update product stock and make sure the row exists.
        $stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
        $stmt->execute([$qty_change, $product_id]);
        if ($stmt->rowCount() === 0) {
            throw new Exception('Failed to update stock for product id ' . $product_id);
        }

        // Log the change
        $stmt = $pdo->prepare("INSERT INTO inventory_logs (product_id, user_id, qty_change, type, note) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$product_id, $user_id, $qty_change, $type, $note]);

        if ($manageTx) {
            $pdo->commit();
        }
        return true;
    } catch (Exception $e) {
        if ($manageTx) {
            $pdo->rollBack();
            return false;
        }
        throw $e;
    }
}


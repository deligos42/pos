<?php
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function generateInvoiceNo() {
    return 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
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

function generate_email_verification_code(): string
{
    return (string)random_int(100000, 999999);
}

function ensure_password_resets_table_exists(PDO $pdo): void
{
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS password_resets (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  token varchar(128) NOT NULL,
  expires_at datetime NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY token (token),
  KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

    $pdo->exec($sql);
}


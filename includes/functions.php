<?php
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function normalize_text(string $input): string
{
    return trim($input);
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function validate_password_strength(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must include at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must include at least one lowercase letter.';
    }
    if (!preg_match('/\d/', $password)) {
        return 'Password must include at least one number.';
    }
    if (!preg_match('/[^a-zA-Z\d]/', $password)) {
        return 'Password must include at least one special character.';
    }
    return null;
}

function app_error_message(string $message, string $fallback = 'Something went wrong. Please try again later.'): string
{
    $message = trim($message);
    if ($message === '') {
        return $fallback;
    }

    if (stripos($message, 'duplicate') !== false || stripos($message, 'already exists') !== false || stripos($message, 'already taken') !== false || stripos($message, 'already in use') !== false) {
        return 'A matching record already exists. Please review the information and try again.';
    }

    if (stripos($message, 'database') !== false || stripos($message, 'sqlstate') !== false || stripos($message, 'pdoexception') !== false) {
        return 'We could not complete the request because of a database issue. Please try again later.';
    }

    if (stripos($message, 'upload') !== false) {
        return 'The uploaded file could not be processed. Please try another file.';
    }

    return $message;
}

function app_exception_message(Throwable $e, string $fallback = 'Something went wrong. Please try again later.'): string
{
    return app_error_message($e->getMessage(), $fallback);
}

function cleanup_stale_unverified_accounts(PDO $pdo, int $days = 7): int
{
    $stmt = $pdo->prepare(
        'DELETE FROM users WHERE email_verified = 0 AND email_verification_expires_at IS NOT NULL AND email_verification_expires_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
    );
    $stmt->execute([$days]);
    return $stmt->rowCount();
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


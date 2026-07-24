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
    // The sales.invoice_no column is varchar(20). This format is 19 characters:
    // INV-YYYYMMDD-XXXXXX.
    return 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
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

/**
 * Log an audit trail entry for compliance and accountability
 * 
 * @param string $action - Action type: 'create', 'update', 'delete', 'approve', etc.
 * @param string $entity_type - Entity being modified: 'sales', 'products', 'users', 'refunds', etc.
 * @param int|null $entity_id - ID of the entity
 * @param mixed $old_value - Previous value (for updates)
 * @param mixed $new_value - New value (for updates/creates)
 * @param string|null $reason - Reason for the change (optional)
 * @return bool
 */
function audit_log(string $action, string $entity_type, ?int $entity_id, $old_value = null, $new_value = null, ?string $reason = null): bool
{
    try {
        global $pdo;
        if (!$pdo) return false;
        
        $user_id = $_SESSION['user_id'] ?? null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        
        $stmt = $pdo->prepare(
            "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_value, new_value, change_reason, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        return $stmt->execute([
            $user_id,
            $action,
            $entity_type,
            $entity_id,
            is_array($old_value) || is_object($old_value) ? json_encode($old_value) : $old_value,
            is_array($new_value) || is_object($new_value) ? json_encode($new_value) : $new_value,
            $reason,
            $ip_address,
            $user_agent
        ]);
    } catch (Throwable $e) {
        app_log('Audit log failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get user permissions from their role
 * 
 * @param int $user_id
 * @return array Permission names
 */
function get_user_permissions(int $user_id): array
{
    try {
        global $pdo;
        if (!$pdo) return [];
        
        $stmt = $pdo->prepare(
            "SELECT DISTINCT p.name FROM permissions p
             JOIN role_permissions rp ON p.id = rp.permission_id
             JOIN roles r ON rp.role_id = r.id
             JOIN users u ON u.role_id = r.id
             WHERE u.id = ? AND u.is_active = 1"
        );
        
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        app_log('Get permissions failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Check if current user has a specific permission
 * 
 * @param string $permission - Permission name to check
 * @return bool
 */
function user_can(string $permission): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Admin always has all permissions
    if (($_SESSION['role'] ?? '') === 'admin') {
        return true;
    }
    
    $permissions = get_user_permissions($_SESSION['user_id']);
    return in_array($permission, $permissions, true);
}

/**
 * Get user's primary role
 * 
 * @param int $user_id
 * @return string|null
 */
function get_user_role(int $user_id): ?string
{
    try {
        global $pdo;
        if (!$pdo) return null;
        
        $stmt = $pdo->prepare("SELECT r.name FROM roles r JOIN users u ON u.role_id = r.id WHERE u.id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() ?: null;
    } catch (Throwable $e) {
        app_log('Get user role failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Require a specific permission or redirect
 * Use at the start of admin pages
 * 
 * @param string $permission
 * @param string $fallback_url
 * @return void
 */
function require_permission(string $permission, string $fallback_url = 'dashboard.php'): void
{
    if (!user_can($permission)) {
        http_response_code(403);
        $_SESSION['error'] = 'You do not have permission to access this page.';
        header("Location: $fallback_url");
        exit;
    }
}

/**
 * Create a refund request for a sale
 * @param int $sale_id
 * @param float $amount
 * @param string|null $reason
 * @return int|false Refund ID or false on failure
 */
function create_refund_request(int $sale_id, float $amount, ?string $reason = null)
{
    try {
        global $pdo;
        if (!$pdo) return false;
        $user_id = $_SESSION['user_id'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO refunds (sale_id, requested_by, amount, reason) VALUES (?, ?, ?, ?)");
        $stmt->execute([$sale_id, $user_id, $amount, $reason]);
        $refund_id = (int)$pdo->lastInsertId();

        audit_log('create', 'refunds', $refund_id, null, ['sale_id'=>$sale_id,'amount'=>$amount,'reason'=>$reason], 'Refund requested');
        return $refund_id;
    } catch (Throwable $e) {
        app_log('create_refund_request failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Approve or reject a refund
 * @param int $refund_id
 * @param bool $approve
 * @param string|null $note
 * @return bool
 */
function approve_refund(int $refund_id, bool $approve = true, ?string $note = null): bool
{
    try {
        global $pdo;
        if (!$pdo) return false;
        $approver = $_SESSION['user_id'] ?? null;
        $status = $approve ? 'approved' : 'rejected';
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("UPDATE refunds SET status = ?, approved_by = ?, approved_at = ?, reason = COALESCE(reason, ?) WHERE id = ?");
        $stmt->execute([$status, $approver, $now, $note, $refund_id]);

        audit_log($approve ? 'approve' : 'reject', 'refunds', $refund_id, null, ['status'=>$status,'note'=>$note], 'Refund decision');
        return true;
    } catch (Throwable $e) {
        app_log('approve_refund failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Store a receipt snapshot for a sale
 * @param int $sale_id
 * @param array $snapshot
 * @return int|false
 */
function store_receipt_snapshot(int $sale_id, array $snapshot)
{
    try {
        global $pdo;
        if (!$pdo) return false;
        $user_id = $_SESSION['user_id'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO receipts (sale_id, snapshot, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$sale_id, json_encode($snapshot), $user_id]);
        $receipt_id = (int)$pdo->lastInsertId();

        audit_log('create', 'receipts', $receipt_id, null, $snapshot, 'Receipt snapshot stored');
        return $receipt_id;
    } catch (Throwable $e) {
        app_log('store_receipt_snapshot failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create a cashier closing report
 * @param int $cashier_id
 * @param string $shift_start
 * @param string $shift_end
 * @param float $expected_total
 * @param float $counted_cash
 * @param string|null $notes
 * @return int|false
 */
function create_closing_report(int $cashier_id, string $shift_start, string $shift_end, float $expected_total, float $counted_cash, ?string $notes = null)
{
    try {
        global $pdo;
        if (!$pdo) return false;
        $discrepancy = $counted_cash - $expected_total;
        $stmt = $pdo->prepare("INSERT INTO cashier_closing_reports (cashier_id, shift_start, shift_end, expected_total, counted_cash, discrepancy, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$cashier_id, $shift_start, $shift_end, $expected_total, $counted_cash, $discrepancy, $notes]);
        $report_id = (int)$pdo->lastInsertId();

        audit_log('create', 'cashier_closing', $report_id, null, ['expected'=>$expected_total,'counted'=>$counted_cash,'discrepancy'=>$discrepancy], 'Closing report submitted');
        return $report_id;
    } catch (Throwable $e) {
        app_log('create_closing_report failed: ' . $e->getMessage());
        return false;
    }
}


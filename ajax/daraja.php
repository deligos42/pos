<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/daraja.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are supported.']);
    exit;
}

require_post_csrf();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? ($_POST['action'] ?? '');

if ($action === 'validate_phone') {
    $phone = trim((string)($input['phone_number'] ?? ($_POST['phone_number'] ?? '')));
    echo json_encode([
        'success' => validate_phone_number($phone),
        'normalized_phone' => normalize_phone_number($phone),
        'message' => validate_phone_number($phone) ? 'Phone number looks valid.' : 'Phone number is not valid for Mpesa.',
    ]);
    exit;
}

if ($action === 'stk_push') {
    $result = daraja_stk_push([
        'amount' => (string)($input['amount'] ?? ($_POST['amount'] ?? 0)),
        'phone_number' => (string)($input['phone_number'] ?? ($_POST['phone_number'] ?? '')),
        'account_reference' => (string)($input['account_reference'] ?? ($_POST['account_reference'] ?? 'POS')),
        'transaction_desc' => (string)($input['transaction_desc'] ?? ($_POST['transaction_desc'] ?? 'POS payment')),
        'callback_url' => (string)($input['callback_url'] ?? ($_POST['callback_url'] ?? '')),
    ]);

    echo json_encode($result);
    exit;
}

if ($action === 'b2b') {
    $result = daraja_b2b_payment([
        'amount' => (string)($input['amount'] ?? ($_POST['amount'] ?? 0)),
        'account_reference' => (string)($input['account_reference'] ?? ($_POST['account_reference'] ?? 'POS')),
        'remarks' => (string)($input['remarks'] ?? ($_POST['remarks'] ?? 'POS B2B payment')),
        'callback_url' => (string)($input['callback_url'] ?? ($_POST['callback_url'] ?? '')),
    ]);

    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown Daraja action.']);

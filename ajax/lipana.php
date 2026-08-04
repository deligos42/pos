<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lipana.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are supported.']);
    exit;
}

require_post_csrf();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? ($_POST['action'] ?? '');

if ($action === 'initiate_payment') {
    $result = lipana_stk_push([
        'amount' => (string)($input['amount'] ?? ($_POST['amount'] ?? 0)),
        'phone_number' => (string)($input['phone_number'] ?? ($_POST['phone_number'] ?? '')),
        'account_reference' => (string)($input['account_reference'] ?? ($_POST['account_reference'] ?? 'POS')),
        'transaction_desc' => (string)($input['transaction_desc'] ?? ($_POST['transaction_desc'] ?? 'POS payment')),
        'callback_url' => (string)($input['callback_url'] ?? ($_POST['callback_url'] ?? '')),
    ]);

    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown Lipana action.']);

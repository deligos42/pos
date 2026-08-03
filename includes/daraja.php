<?php
require_once __DIR__ . '/functions.php';

function normalize_phone_number(string $phone): string
{
    $phone = preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';
    if ($phone === '') {
        return '';
    }

    if (str_starts_with($phone, '+254')) {
        return substr($phone, 1);
    }

    if (str_starts_with($phone, '254')) {
        return $phone;
    }

    if (str_starts_with($phone, '0')) {
        return '254' . substr($phone, 1);
    }

    return $phone;
}

function validate_phone_number(string $phone): bool
{
    $normalized = normalize_phone_number($phone);
    return preg_match('/^254[1-9]\d{8}$/', $normalized) === 1;
}

function get_daraja_config(): array
{
    return [
        'base_url' => getenv('DARAJA_BASE_URL') ?: 'https://sandbox.safaricom.co.ke',
        'consumer_key' => getenv('DARAJA_CONSUMER_KEY') ?: '',
        'consumer_secret' => getenv('DARAJA_CONSUMER_SECRET') ?: '',
        'shortcode' => getenv('DARAJA_SHORTCODE') ?: '',
        'passkey' => getenv('DARAJA_PASSKEY') ?: '',
        'initiator_name' => getenv('DARAJA_INITIATOR_NAME') ?: '',
        'security_credential' => getenv('DARAJA_SECURITY_CREDENTIAL') ?: '',
        'b2b_shortcode' => getenv('DARAJA_B2B_SHORTCODE') ?: '',
        'b2b_type' => getenv('DARAJA_B2B_TYPE') ?: 'BusinessBuyGoods',
        'callback_url' => getenv('DARAJA_CALLBACK_URL') ?: '',
    ];
}

function daraja_request_token(): array
{
    $config = get_daraja_config();
    if ($config['consumer_key'] === '' || $config['consumer_secret'] === '') {
        return ['success' => false, 'message' => 'Daraja credentials are not configured.'];
    }

    $url = rtrim($config['base_url'], '/') . '/oauth/v1/generate?grant_type=client_credentials';
    $auth = base64_encode($config['consumer_key'] . ':' . $config['consumer_secret']);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'message' => 'Unable to reach Daraja auth service.'];
    }

    $payload = json_decode($response, true);
    if ($httpCode >= 400 || !is_array($payload) || empty($payload['access_token'])) {
        return ['success' => false, 'message' => 'Daraja authentication failed.', 'details' => $payload];
    }

    return ['success' => true, 'access_token' => $payload['access_token']];
}

function build_daraja_timestamp(): string
{
    return gmdate('YmdHis');
}

function build_daraja_password(string $shortcode, string $passkey, string $timestamp): string
{
    return base64_encode($shortcode . $passkey . $timestamp);
}

function daraja_stk_push(array $payload): array
{
    $config = get_daraja_config();
    $tokenResponse = daraja_request_token();
    if (!$tokenResponse['success']) {
        return $tokenResponse;
    }

    $timestamp = build_daraja_timestamp();
    $password = build_daraja_password($config['shortcode'], $config['passkey'], $timestamp);

    $body = [
        'BusinessShortCode' => $config['shortcode'],
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => $payload['transaction_type'] ?? 'CustomerPayBillOnline',
        'Amount' => (string)($payload['amount'] ?? 0),
        'PartyA' => $payload['party_a'] ?? '',
        'PartyB' => $config['shortcode'],
        'PhoneNumber' => normalize_phone_number($payload['phone_number'] ?? ''),
        'CallBackURL' => $payload['callback_url'] ?? $config['callback_url'],
        'AccountReference' => $payload['account_reference'] ?? 'POS',
        'TransactionDesc' => $payload['transaction_desc'] ?? 'POS payment',
    ];

    if ($body['PhoneNumber'] === '' || !validate_phone_number($body['PhoneNumber'])) {
        return ['success' => false, 'message' => 'A valid Kenyan phone number is required.'];
    }

    if ($config['shortcode'] === '' || $config['passkey'] === '') {
        return ['success' => false, 'message' => 'Daraja STK shortcode and passkey must be configured.'];
    }

    $url = rtrim($config['base_url'], '/') . '/mpesa/stkpush/v1/processrequest';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $tokenResponse['access_token'],
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'message' => 'Unable to reach Daraja STK service.'];
    }

    $payloadResponse = json_decode($response, true);
    return [
        'success' => $httpCode < 400,
        'http_code' => $httpCode,
        'request' => $body,
        'response' => $payloadResponse,
        'message' => $httpCode < 400 ? 'STK push initiated.' : 'STK push request failed.',
    ];
}

function daraja_b2b_payment(array $payload): array
{
    $config = get_daraja_config();
    $tokenResponse = daraja_request_token();
    if (!$tokenResponse['success']) {
        return $tokenResponse;
    }

    $timestamp = build_daraja_timestamp();
    $password = build_daraja_password($config['shortcode'], $config['passkey'], $timestamp);

    $body = [
        'Initiator' => $payload['initiator'] ?? $config['initiator_name'],
        'SecurityCredential' => $payload['security_credential'] ?? $config['security_credential'],
        'CommandID' => $payload['command_id'] ?? 'BusinessPayBill',
        'SenderIdentifierType' => $payload['sender_identifier_type'] ?? 4,
        'RecieverIdentifierType' => $payload['receiver_identifier_type'] ?? 4,
        'Amount' => (string)($payload['amount'] ?? 0),
        'PartyA' => $payload['party_a'] ?? $config['shortcode'],
        'PartyB' => $payload['party_b'] ?? $config['b2b_shortcode'],
        'AccountReference' => $payload['account_reference'] ?? 'POS',
        'Requester' => $payload['requester'] ?? '',
        'Remarks' => $payload['remarks'] ?? 'POS B2B payment',
        'QueueTimeOutURL' => $payload['queue_timeout_url'] ?? $config['callback_url'],
        'ResultURL' => $payload['result_url'] ?? $config['callback_url'],
        'Occassion' => $payload['occasion'] ?? 'POS',
    ];

    $url = rtrim($config['base_url'], '/') . '/mpesa/b2b/v1/paymentrequest';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $tokenResponse['access_token'],
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'message' => 'Unable to reach Daraja B2B service.'];
    }

    $payloadResponse = json_decode($response, true);
    return [
        'success' => $httpCode < 400,
        'http_code' => $httpCode,
        'request' => $body,
        'response' => $payloadResponse,
        'message' => $httpCode < 400 ? 'B2B request submitted.' : 'B2B request failed.',
    ];
}

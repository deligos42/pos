<?php

function load_mail_env(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $envFile = __DIR__ . '/../.env';
    if (!is_file($envFile)) {
        $loaded = true;
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        $loaded = true;
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if (getenv($name) === false) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    $loaded = true;
}

function mail_debug_enabled(): bool
{
    return filter_var(getenv('MAIL_DEBUG'), FILTER_VALIDATE_BOOLEAN);
}

function mail_debug_log(string $message): void
{
    if (mail_debug_enabled()) {
        error_log($message);
    }
    app_log($message);
}

function get_resend_config(): array
{
    load_mail_env();

    return [
        'api_key' => getenv('RESEND_API_KEY') ?: '',
        'from_address' => getenv('RESEND_FROM_ADDRESS') ?: (getenv('MAIL_FROM_ADDRESS') ?: ''),
        'from_name' => getenv('RESEND_FROM_NAME') ?: (getenv('MAIL_FROM_NAME') ?: 'POS System'),
    ];
}

function get_brevo_config(): array
{
    load_mail_env();

    return [
        'api_key' => getenv('BREVO_API_KEY') ?: '',
        'from_address' => getenv('BREVO_FROM_ADDRESS') ?: (getenv('MAIL_FROM_ADDRESS') ?: ''),
        'from_name' => getenv('BREVO_FROM_NAME') ?: (getenv('MAIL_FROM_NAME') ?: 'POS System'),
    ];
}

function send_via_brevo(array $config, string $email, string $name, string $subject, string $html, string $text): bool
{
    if (!function_exists('curl_init')) {
        mail_debug_log('Password reset email failed: PHP cURL is required for Brevo API.');
        return false;
    }

    $payload = json_encode([
        'sender' => [
            'email' => $config['from_address'],
            'name' => $config['from_name'],
        ],
        'to' => [
            [
                'email' => $email,
                'name' => $name !== '' ? $name : $email,
            ],
        ],
        'subject' => $subject,
        'htmlContent' => $html,
        'textContent' => $text,
    ]);

    $curl = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . $config['api_key'],
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response !== false && $status >= 200 && $status < 300) {
        mail_debug_log('Password reset email sent successfully through Brevo API.');
        return true;
    }

    if ($response === false) {
        mail_debug_log('Password reset email failed through Brevo API: cURL error: ' . $error);
    } else {
        $body = trim($response);
        mail_debug_log('Password reset email failed through Brevo API: HTTP ' . $status . ' response: ' . ($body !== '' ? $body : '(empty body)'));
    }
    return false;
}

function send_via_resend(array $config, string $email, string $name, string $subject, string $html, string $text): bool
{
    if (!function_exists('curl_init')) {
        mail_debug_log('Password reset email failed: PHP cURL is required for Resend.');
        return false;
    }

    $from = $config['from_name'] !== ''
        ? sprintf('%s <%s>', $config['from_name'], $config['from_address'])
        : $config['from_address'];
    $payload = json_encode([
        'from' => $from,
        'to' => [$name !== '' ? sprintf('%s <%s>', $name, $email) : $email],
        'subject' => $subject,
        'html' => $html,
        'text' => $text,
    ]);

    $curl = curl_init('https://api.resend.com/emails');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response !== false && $status >= 200 && $status < 300) {
        mail_debug_log('Password reset email sent successfully through Resend API.');
        return true;
    }

    mail_debug_log('Password reset email failed through Resend: ' . ($error !== '' ? $error : 'HTTP ' . $status));
    return false;
}

function send_email_message(string $email, string $name, string $subject, string $html, string $text): bool
{
    require_once __DIR__ . '/../vendor/autoload.php';

    $brevo = get_brevo_config();
    $resend = get_resend_config();

    if ($brevo['api_key'] !== '') {
        mail_debug_log($subject . ' email using Brevo API.');
        return send_via_brevo($brevo, $email, $name, $subject, $html, $text);
    }

    if ($resend['api_key'] !== '') {
        mail_debug_log($subject . ' email using Resend API.');
        return send_via_resend($resend, $email, $name, $subject, $html, $text);
    }

    $msg = $subject . ' email skipped: no mail API configured. Set BREVO_API_KEY or RESEND_API_KEY.';
    mail_debug_log($msg);
    return false;
}

function send_email_verification_code(string $email, string $name, string $code): bool
{
    $subject = 'Verify your POS account';
    $html = '<p>Hello ' . htmlspecialchars($name ?: $email, ENT_QUOTES, 'UTF-8') . ',</p>' .
        '<p>Use the following one-time code to verify your email address and activate your POS account:</p>' .
        '<p style="font-size:1.35rem;font-weight:700;letter-spacing:0.08em;">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</p>' .
        '<p>This code expires in 1 hour.</p>' .
        '<p>If you did not create this account, please ignore this email.</p>' .
        '<p>Regards,<br>POS System</p>';
    $text = 'Hello ' . ($name ?: $email) . ",\n\n" .
        'Use the following one-time code to verify your email address and activate your POS account:' . "\n\n" .
        $code . "\n\n" .
        'This code expires in 1 hour.' . "\n\n" .
        'If you did not create this account, please ignore this email.' . "\n\n" .
        'Regards, POS System';

    return send_email_message($email, $name, $subject, $html, $text);
}

function send_password_reset_email(string $email, string $name, string $token): bool
{
    require_once __DIR__ . '/../vendor/autoload.php';

    $brevo = get_brevo_config();
    $resend = get_resend_config();

    $subject = 'Reset your POS password';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Allow overriding the base URL via APP_URL or BASE_URL in .env
    $appUrl = getenv('APP_URL') ?: getenv('BASE_URL') ?: '';
    if ($appUrl !== '') {
        $base = rtrim($appUrl, '/');
        $resetUrl = $base . '/reset_password.php?token=' . urlencode($token);
    } else {
        // Determine current script directory (handles apps in a subdirectory, e.g. /pos)
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        $scriptDir = ($scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;
        $resetUrl = sprintf('%s://%s%s/reset_password.php?token=%s', $scheme, $host, $scriptDir, urlencode($token));
    }

    $html = '<p>Hello ' . htmlspecialchars($name ?: $email, ENT_QUOTES, 'UTF-8') . ',</p>' .
        '<p>You requested a password reset for your POS account. Click the button below to set a new password:</p>' .
        '<p><a href="' . $resetUrl . '" style="display:inline-block;padding:10px 18px;background:#0d6efd;color:#ffffff;text-decoration:none;border-radius:4px;">Reset Password</a></p>' .
        '<p>If the button does not work, paste this link into your browser:</p>' .
        '<p><a href="' . $resetUrl . '">' . $resetUrl . '</a></p>' .
        '<p>If you did not request this password reset, you can safely ignore this email.</p>' .
        '<p>Regards,<br>POS System</p>';
    $text = 'Hello ' . ($name ?: $email) . ",\n\n" .
        'You requested a password reset for your POS account. Copy and paste the link below into your browser:' . "\n" .
        $resetUrl . "\n\n" .
        'If you did not request this password reset, ignore this email.' . "\n\n" .
        'Regards, POS System';

    if ($brevo['api_key'] !== '') {
        mail_debug_log('Password reset email using Brevo API.');
        return send_via_brevo($brevo, $email, $name, $subject, $html, $text);
    }

    if ($resend['api_key'] !== '') {
        mail_debug_log('Password reset email using Resend API.');
        return send_via_resend($resend, $email, $name, $subject, $html, $text);
    }

    $msg = 'Password reset email skipped: no mail API configured. Set BREVO_API_KEY or RESEND_API_KEY.';
    mail_debug_log($msg);
    return false;
}

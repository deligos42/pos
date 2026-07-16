<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

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
        }
    }

    $loaded = true;
}

function get_mail_config(): array
{
    load_mail_env();

    $smtpUsername = getenv('MAIL_SMTP_USERNAME') ?: getenv('SMTP_USERNAME') ?: '';
    $smtpPassword = getenv('MAIL_SMTP_PASSWORD') ?: getenv('SMTP_PASSWORD') ?: '';
    $smtpHost = getenv('MAIL_SMTP_HOST') ?: getenv('SMTP_HOST') ?: getenv('SMTP_SERVER') ?: '';
    $smtpPort = getenv('MAIL_SMTP_PORT') ?: getenv('SMTP_PORT') ?: getenv('SMTP_SERVER_PORT') ?: 587;
    $smtpEncryption = getenv('MAIL_SMTP_ENCRYPTION') ?: getenv('MAIL_SMTP_SECURE') ?: getenv('SMTP_ENCRYPTION') ?: 'tls';
    $smtpAuth = getenv('MAIL_SMTP_AUTH');
    if ($smtpAuth === false) {
        $smtpAuth = $smtpUsername !== '' && $smtpPassword !== '' ? 'true' : 'false';
    }

    return [
        'host' => $smtpHost,
        'port' => (int)$smtpPort,
        'username' => $smtpUsername,
        'password' => $smtpPassword,
        'auth' => filter_var($smtpAuth, FILTER_VALIDATE_BOOLEAN),
        'encryption' => $smtpEncryption,
        'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@localhost',
        'from_name' => getenv('MAIL_FROM_NAME') ?: 'POS System',
    ];
}

function mail_debug_enabled(): bool
{
    return filter_var(getenv('MAIL_DEBUG') ?: getenv('MAIL_SMTP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
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
        return true;
    }

    mail_debug_log('Password reset email failed through Resend: ' . ($error !== '' ? $error : 'HTTP ' . $status));
    return false;
}

function send_password_reset_email(string $email, string $name, string $token): bool
{
    require_once __DIR__ . '/../vendor/autoload.php';

    $config = get_mail_config();
    $resend = get_resend_config();
    if ($resend['api_key'] === '' && empty($config['host'])) {
        $msg = 'Password reset email skipped: SMTP host is not configured.';
        mail_debug_log($msg);
        return false;
    }
    if ($resend['api_key'] === '' && $config['auth'] && (empty($config['username']) || empty($config['password']))) {
        $msg = 'Password reset email skipped: SMTP auth is enabled but username/password are not configured.';
        mail_debug_log($msg);
        return false;
    }

    if (mail_debug_enabled()) {
        $msg = sprintf('PHPMailer config: host=%s port=%d auth=%s encryption=%s from=%s',
            $config['host'],
            $config['port'],
            $config['auth'] ? 'true' : 'false',
            $config['encryption'] ?: 'none',
            $config['from_address']
        );
        error_log($msg);
    }

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

    $subject = 'Reset your POS password';
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

    if ($resend['api_key'] !== '') {
        return send_via_resend($resend, $email, $name, $subject, $html, $text);
    }

    $mail = new PHPMailer(true);
    // Enable SMTP debug when explicitly requested via env var `MAIL_DEBUG` or `MAIL_SMTP_DEBUG`.
    // Debug output is sent to stderr via error_log so Railway captures it in container logs.
    if (mail_debug_enabled()) {
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function ($str, $level) {
            error_log('PHPMailer debug[' . $level . ']: ' . $str);
        };
    }
    try {
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = $config['auth'];

        if ($config['auth']) {
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
        }

        if (!empty($config['encryption'])) {
            $mail->SMTPSecure = $config['encryption'];
        }

        $mail->Port = $config['port'];
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($config['from_address'], $config['from_name']);
        $mail->addAddress($email, $name ?: $email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $text;

        $sent = $mail->send();
        if (!$sent) {
            $err = 'Password reset email failed: ' . ($mail->ErrorInfo ?? 'unknown');
            app_log($err);
            error_log($err);
        }

        return $sent;
    } catch (Exception $e) {
        $msg = 'Password reset email failed: ' . $e->getMessage();
        app_log($msg);
        error_log($msg);
        return false;
    }
}

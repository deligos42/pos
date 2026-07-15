<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

function get_mail_config(): array
{
    $smtpUsername = getenv('MAIL_SMTP_USERNAME') ?: getenv('SMTP_USERNAME') ?: '';
    $smtpPassword = getenv('MAIL_SMTP_PASSWORD') ?: getenv('SMTP_PASSWORD') ?: '';
    $smtpHost = getenv('MAIL_SMTP_HOST') ?: getenv('SMTP_HOST') ?: '';
    $smtpPort = getenv('MAIL_SMTP_PORT') ?: getenv('SMTP_PORT') ?: 587;
    $smtpEncryption = getenv('MAIL_SMTP_ENCRYPTION') ?: getenv('MAIL_SMTP_SECURE') ?: 'tls';
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

function send_password_reset_email(string $email, string $name, string $token): bool
{
    require_once __DIR__ . '/../vendor/autoload.php';

    $config = get_mail_config();
    if (empty($config['host'])) {
        app_log('Password reset email skipped: SMTP host is not configured.');
        return false;
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

    $mail = new PHPMailer(true);
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
        $mail->Subject = 'Reset your POS password';
        $mail->Body = '<p>Hello ' . htmlspecialchars($name ?: $email, ENT_QUOTES, 'UTF-8') . ',</p>' .
            '<p>You requested a password reset for your POS account. Click the button below to set a new password:</p>' .
            '<p><a href="' . $resetUrl . '" style="display:inline-block;padding:10px 18px;background:#0d6efd;color:#ffffff;text-decoration:none;border-radius:4px;">Reset Password</a></p>' .
            '<p>If the button does not work, paste this link into your browser:</p>' .
            '<p><a href="' . $resetUrl . '">' . $resetUrl . '</a></p>' .
            '<p>If you did not request this password reset, you can safely ignore this email.</p>' .
            '<p>Regards,<br>POS System</p>';
        $mail->AltBody = 'Hello ' . ($name ?: $email) . ",\n\n" .
            'You requested a password reset for your POS account. Copy and paste the link below into your browser:' . "\n" .
            $resetUrl . "\n\n" .
            'If you did not request this password reset, ignore this email.' . "\n\n" .
            'Regards, POS System';

        return $mail->send();
    } catch (Exception $e) {
        app_log('Password reset email failed: ' . $e->getMessage());
        return false;
    }
}

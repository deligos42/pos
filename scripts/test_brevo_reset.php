<?php
require_once __DIR__ . '/../includes/mail.php';
require_once __DIR__ . '/../config/db.php';

try {
    $email = $argv[1] ?? '';
    if ($email === '') {
        fwrite(STDERR, "Usage: php scripts/test_brevo_reset.php email@example.com\n");
        exit(1);
    }

    $stmt = $pdo->prepare('SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo "NOT_FOUND {$email}\n";
        exit(1);
    }

    $token = bin2hex(random_bytes(16));
    if (send_password_reset_email($user['email'], $user['full_name'] ?: $user['email'], $token)) {
        echo "SENT {$user['email']}\n";
        exit(0);
    }
    echo "FAILED {$user['email']}\n";
    exit(2);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(3);
}

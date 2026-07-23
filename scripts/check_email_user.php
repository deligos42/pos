<?php
require_once __DIR__ . '/../config/db.php';

$email = $argv[1] ?? '';
if ($email === '') {
    fwrite(STDERR, "Usage: php scripts/check_email_user.php email@example.com\n");
    exit(1);
}

$stmt = $pdo->prepare('SELECT id, username, full_name, email FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if ($user) {
    echo "FOUND: " . json_encode($user) . "\n";
    exit(0);
}
echo "NOT_FOUND\n";
exit(2);

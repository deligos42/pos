<?php
require_once 'includes/security.php';

start_secure_session();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$email = trim($_SESSION['pending_verify_email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: register.php');
    exit;
}

function mask_email_address(string $email): string
{
    [$localPart, $domain] = explode('@', $email, 2);
    $visibleCharacters = min(2, strlen($localPart));
    $maskedLocalPart = substr($localPart, 0, $visibleCharacters) . str_repeat('*', max(1, strlen($localPart) - $visibleCharacters));

    return $maskedLocalPart . '@' . $domain;
}

$maskedEmail = mask_email_address($email);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Your Email</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="assets/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<main class="container-fluid px-3" style="max-width: 460px; margin-top: min(70px, 8vh);">
    <section class="card shadow text-center">
        <div class="card-body p-4 p-sm-5">
            <div class="text-primary mb-3" aria-hidden="true"><i class="bi bi-envelope-check-fill" style="font-size: 3rem;"></i></div>
            <h1 class="h3 mb-3">Check your email</h1>
            <p class="mb-2">Your account has been created. We sent a 6-digit verification code to:</p>
            <p class="fw-semibold text-break mb-4"><?= htmlspecialchars($maskedEmail) ?></p>
            <p class="text-muted small mb-4">Enter the code to verify your email and activate your account. If you do not see it soon, check your spam folder.</p>
            <a href="verify_email.php" class="btn btn-primary w-100">Enter Verification Code</a>
            <a href="verify_email.php" class="btn btn-link mt-2">Didn't receive the email?</a>
        </div>
    </section>
</main>
</body>
</html>

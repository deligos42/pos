<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title>POS System</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="assets/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        html, body { width: 100%; min-height: 100%; }
        body { padding-top: 60px; overflow-x: hidden; }
        img, svg, canvas, video { max-width: 100%; height: auto; }
        .navbar { background: #2c3e50; }
        .navbar-brand, .nav-link { color: #fff !important; }
        .navbar-brand { display: flex; align-items: center; gap: 8px; }
        .navbar-logo { width: 32px; height: 32px; object-fit: contain; background: #fff; border-radius: 4px; padding: 2px; }
        .navbar-profile-photo { width: 26px; height: 26px; object-fit: cover; border-radius: 50%; border: 1px solid rgba(255,255,255,.6); }
        .app-shell {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            padding-inline: clamp(24px, 6vw, 96px);
            padding-block: clamp(24px, 4vw, 48px);
        }
        .table-responsive { margin-top: 1.5rem; }
        .container-fluid, .row, .row > * { min-width: 0; }
        .card { max-width: 100%; min-width: 0; }
        .card-body {
            overflow-x: auto;
            padding: 1rem;
        }
        .card-header {
            padding: 0.9rem 1rem;
        }
        .table { min-width: max-content; margin-bottom: 0; }
        .table th, .table td { vertical-align: middle; white-space: nowrap; }
        .btn { white-space: nowrap; }
        .row { --bs-gutter-y: 1rem; }
        .pos-cart { background: #f8f9fa; padding: 1.25rem; border-radius: 8px; }
        .receipt { font-family: monospace; }
        .receipt-logo { width: 72px; max-width: 40%; height: auto; object-fit: contain; margin-bottom: 6px; }
        .discount-input { width: min(140px, 100%); }
        #searchResults .d-flex { gap: 0.75rem; }
        #searchResults span { min-width: 0; overflow-wrap: anywhere; white-space: normal; }
        .app-toast-container { z-index: 1080; }
        .app-toast { min-width: 280px; box-shadow: 0 0.75rem 1.5rem rgba(0,0,0,.18); }
        @media (max-width: 767.98px) {
            body { padding-top: 56px; }
            h1, .h1 { font-size: 1.75rem; }
            h2, .h2 { font-size: 1.5rem; }
            h3, .h3 { font-size: 1.25rem; }
            .navbar .container-fluid { padding-inline: 12px; }
            .navbar-collapse { max-height: calc(100vh - 56px); overflow-y: auto; }
            .navbar-text { display: block; margin: 8px 0 !important; }
            .navbar .btn { width: 100%; margin: 4px 0 !important; }
            .app-shell { padding: 12px; }
            .card-header { padding: 0.65rem 0.75rem; }
            .card-body { padding: 0.75rem; }
            .display-6 { font-size: 1.75rem; overflow-wrap: anywhere; }
            .input-group { flex-wrap: nowrap; }
            .table { font-size: 0.92rem; }
            #searchResults .d-flex { align-items: stretch !important; flex-direction: column; }
            #searchResults .btn { width: 100%; }
            #completeSaleBtn, #clearCartBtn { flex: 1 1 160px; }
            .app-toast-container { left: 0; right: 0; padding: 0.75rem !important; }
            .app-toast { width: 100%; min-width: 0; }
        }
    </style>
</head>
<body>
<div class="toast-container position-fixed top-0 end-0 p-3 app-toast-container" id="appToastContainer"></div>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php"><img src="assets/DELIGOS%20LOGO.png" class="navbar-logo" alt="Deligos Company"> POS</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="sales.php">Sales</a></li>
                <li class="nav-item"><a class="nav-link" href="inventory.php">Inventory</a></li>
                <li class="nav-item"><a class="nav-link" href="customers.php">Customers</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php">Reports</a></li>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <li class="nav-item"><a class="nav-link" href="profits.php">Profits</a></li>
                <li class="nav-item"><a class="nav-link" href="expenses.php">Expenses</a></li>
                <li class="nav-item"><a class="nav-link" href="users.php">Users</a></li>
                <?php endif; ?>
            </ul>
            <span class="navbar-text text-white me-3">
                <?php if (!empty($_SESSION['profile_photo'])): ?>
                    <img src="<?= htmlspecialchars($_SESSION['profile_photo']) ?>" class="navbar-profile-photo me-1" alt="Profile photo">
                <?php else: ?>
                    <i class="bi bi-person-circle"></i>
                <?php endif; ?>
                <?= htmlspecialchars($_SESSION['full_name'] ?? '') ?> (<?= htmlspecialchars($_SESSION['role'] ?? '') ?>)
            </span>
            <a href="profile.php" class="btn btn-outline-light btn-sm me-2"><i class="bi bi-gear"></i> Profile</a>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>
<div class="container-fluid app-shell">

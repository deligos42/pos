<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { padding-top: 60px; }
        .navbar { background: #2c3e50; }
        .navbar-brand, .nav-link { color: #fff !important; }
        .navbar-brand { display: flex; align-items: center; gap: 8px; }
        .navbar-logo { width: 32px; height: 32px; object-fit: contain; background: #fff; border-radius: 4px; padding: 2px; }
        .navbar-profile-photo { width: 26px; height: 26px; object-fit: cover; border-radius: 50%; border: 1px solid rgba(255,255,255,.6); }
        .table-responsive { margin-top: 20px; }
        .pos-cart { background: #f8f9fa; padding: 15px; border-radius: 8px; }
        .receipt { font-family: monospace; }
        .receipt-logo { width: 72px; max-width: 40%; height: auto; object-fit: contain; margin-bottom: 6px; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container">
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
<div class="container">


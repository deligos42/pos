<?php
/**
 * Railway Database Connection Diagnostic
 * Visit: https://deligoscompany.online/test_db_connection.php
 */

require_once 'includes/security.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

?><!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { color: #007bff; font-weight: bold; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Database Connection Diagnostic</h1>
    <hr>
    
    <h3>Environment Configuration</h3>
    <table class="table table-sm">
        <tr>
            <td><strong>DATABASE_URL</strong></td>
            <td><code><?= getenv('DATABASE_URL') ? '✓ Set' : '✗ Not Set' ?></code></td>
        </tr>
        <tr>
            <td><strong>MYSQL_URL</strong></td>
            <td><code><?= getenv('MYSQL_URL') ? '✓ Set' : '✗ Not Set' ?></code></td>
        </tr>
        <tr>
            <td><strong>DB_HOST</strong></td>
            <td><code><?= getenv('DB_HOST') ?? 'Not set' ?></code></td>
        </tr>
        <tr>
            <td><strong>PHP_VERSION</strong></td>
            <td><code><?= phpversion() ?></code></td>
        </tr>
    </table>
    
    <hr>
    <h3>PDO Connection Status</h3>
    <?php
    try {
        // Try to use the already connected $pdo from config/db.php
        $stmt = $pdo->query("SELECT VERSION() AS version");
        $result = $stmt->fetch();
        ?>
        <div class="alert alert-success">
            <strong>✓ Database connection successful!</strong>
            <p>MySQL Version: <?= htmlspecialchars($result['version']) ?></p>
        </div>
        
        <hr>
        <h3>Database Tables</h3>
        <?php
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        ?>
        <div class="alert alert-info">
            Found <strong><?= count($tables) ?></strong> tables:
            <ul>
                <?php foreach ($tables as $table): ?>
                    <li><code><?= htmlspecialchars($table) ?></code></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <hr>
        <h3>Sample Data Check</h3>
        <?php
        $checks = [
            'users' => "SELECT COUNT(*) as count FROM users",
            'products' => "SELECT COUNT(*) as count FROM products",
            'customers' => "SELECT COUNT(*) as count FROM customers",
            'sales' => "SELECT COUNT(*) as count FROM sales",
        ];
        
        foreach ($checks as $table => $query) {
            $stmt = $pdo->query($query);
            $result = $stmt->fetch();
            $count = $result['count'] ?? 0;
            $icon = $count > 0 ? '✓' : '●';
            echo "<p><code>{$table}</code>: $icon {$count} records</p>";
        }
        ?>
        
        <div class="alert alert-success mt-4">
            <strong>✓ All systems operational!</strong>
            <p>The database connection is working correctly and all tables are present.</p>
        </div>
        
    <?php } catch (Throwable $e) { ?>
        <div class="alert alert-danger">
            <strong>✗ Database connection failed!</strong>
            <p><?= htmlspecialchars($e->getMessage()) ?></p>
            <hr>
            <p><strong>Troubleshooting:</strong></p>
            <ul>
                <li>Check that DATABASE_URL environment variable is set on the POS service in Railway</li>
                <li>Verify MySQL service is online in Railway dashboard</li>
                <li>Ensure the database name, username, and password are correct</li>
                <li>Try using the public MySQL URL if services are in different regions</li>
            </ul>
        </div>
    <?php } ?>
    
    <hr>
    <p><small><em>This page is for diagnostics only. Can be deleted after verification.</em></small></p>
</div>
</body>
</html>

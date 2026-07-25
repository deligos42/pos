<?php
require_once __DIR__ . '/../config/db.php';
try {
    $stmt = $pdo->query('SHOW CREATE TABLE sales');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row['Create Table'] ?? var_export($row, true);
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage();
}

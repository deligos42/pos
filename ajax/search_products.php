<?php
require_once '../config/db.php';
$q = $_GET['q'] ?? '';

$stmt = $pdo->prepare("SELECT id, sku, name, price, stock_qty FROM products WHERE sku LIKE ? OR name LIKE ? LIMIT 10");
$stmt->execute(["%$q%", "%$q%"]);

header('Content-Type: application/json');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));


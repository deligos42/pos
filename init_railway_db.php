<?php
// Initialize Railway database with POS schema
$dbUrl = 'mysql://root:nkeNTtgmLXheOiwwrvFgJYqdzmmqpCoO@switchback.proxy.rlwy.net:54987/railway';
$dbopts = parse_url($dbUrl);

$host = $dbopts['host'];
$db   = ltrim($dbopts['path'], '/');
$user = $dbopts['user'];
$pass = $dbopts['pass'];
$port = $dbopts['port'] ?? 3306;

echo "[INFO] Connecting to $host:$port/$db ...\n";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "[OK] Connected to database\n";
    
    // Check if tables exist
    $result = $pdo->query("SHOW TABLES");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "[INFO] Database already has " . count($tables) . " tables\n";
        echo "[INFO] Tables: " . implode(', ', $tables) . "\n";
        exit(0);
    }
    
    echo "[INFO] Database is empty. Initializing schema...\n";
    
    // Read and execute database.sql
    $sql = file_get_contents(__DIR__ . '/database.sql');
    $pdo->exec($sql);
    
    echo "[OK] Database schema initialized successfully!\n";
    
} catch (PDOException $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

<?php
// Creates the base POS schema on a new Railway MySQL database.
$databaseUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');

if ($databaseUrl) {
    $parts = parse_url($databaseUrl);
    $host = $parts['host'] ?? '';
    $database = rawurldecode(ltrim($parts['path'] ?? '', '/'));
    $user = rawurldecode($parts['user'] ?? 'root');
    $password = rawurldecode($parts['pass'] ?? '');
    $port = $parts['port'] ?? 3306;
} else {
    $host = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: '';
    $database = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: '';
    $user = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: 'root';
    $password = getenv('DB_PASS') ?: getenv('MYSQLPASSWORD') ?: '';
    $port = getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: 3306;
}

if ($host === '' || $database === '') {
    echo "[database] Connection settings are incomplete; skipping initialization.\n";
    exit(0);
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]
    );

    $tableCount = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')
        ->fetchColumn();
    if ($tableCount > 0) {
        echo "[database] Schema already exists ({$tableCount} tables).\n";
        exit(0);
    }

    $schema = file_get_contents(__DIR__ . '/database.sql');
    if ($schema === false) {
        throw new RuntimeException('database.sql could not be read.');
    }

    $pdo->exec($schema);
    echo "[database] POS schema initialized successfully.\n";
} catch (Throwable $exception) {
    error_log('[database] Initialization failed: ' . $exception->getMessage());
    echo "[database] Initialization failed; see service logs.\n";
}

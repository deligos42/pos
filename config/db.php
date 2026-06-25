<?php
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}

// Railway / generic production environment support
$databaseUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: getenv('RAILWAY_DATABASE_URL');
if ($databaseUrl) {
    $dbopts = parse_url($databaseUrl);
    $host     = $dbopts['host'] ?? 'localhost';
    $dbname   = rawurldecode(ltrim($dbopts['path'] ?? '', '/'));
    $username = rawurldecode($dbopts['user'] ?? 'root');
    $password = rawurldecode($dbopts['pass'] ?? '');
    $port     = $dbopts['port'] ?? 3306;
} else {
    // Local XAMPP / manual environment variable fallbacks
    $host     = getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: getenv('MYSQLHOST') ?: 'localhost';
    $dbname   = getenv('DB_NAME') ?: getenv('DB_DATABASE') ?: getenv('MYSQL_DATABASE') ?: getenv('MYSQLDATABASE') ?: 'pos_system';
    $username = getenv('DB_USER') ?: getenv('DB_USERNAME') ?: getenv('MYSQL_USER') ?: getenv('MYSQLUSER') ?: 'root';
    $password = getenv('DB_PASS') ?: getenv('DB_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: '';
    $port     = getenv('DB_PORT') ?: getenv('MYSQL_PORT') ?: getenv('MYSQLPORT') ?: 3306;
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}


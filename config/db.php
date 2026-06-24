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
    $dbname   = ltrim($dbopts['path'] ?? '', '/');
    $username = $dbopts['user'] ?? 'root';
    $password = $dbopts['pass'] ?? '';
} else {
    // Local XAMPP / manual environment variable fallbacks
    $host     = getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: 'localhost';
    $dbname   = getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: 'pos_system';
    $username = getenv('DB_USER') ?: getenv('MYSQL_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: getenv('MYSQL_PASSWORD') ?: '';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}


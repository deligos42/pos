<?php
/**
 * Database Migration Runner
 * Automatically applies pending migrations to the database
 */

require_once __DIR__ . '/config/db.php';

$migrations_dir = __DIR__ . '/migrations';
$migrations_table = 'schema_migrations';

// Create migrations tracking table if it doesn't exist
try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS $migrations_table (
            id INT PRIMARY KEY AUTO_INCREMENT,
            migration_name VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );
} catch (Throwable $e) {
    die("Migration table creation failed: " . $e->getMessage());
}

// Get list of migration files
$migration_files = array_filter(
    scandir($migrations_dir),
    fn($f) => preg_match('/^[0-9]+_.*\.sql$/', $f)
);

sort($migration_files);

$applied = 0;
$skipped = 0;

foreach ($migration_files as $file) {
    $migration_name = pathinfo($file, PATHINFO_FILENAME);
    
    // Check if migration already applied
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $migrations_table WHERE migration_name = ?");
    $stmt->execute([$migration_name]);
    
    if ($stmt->fetchColumn() > 0) {
        echo "⏭️  Skipping: $migration_name (already applied)\n";
        $skipped++;
        continue;
    }
    
    // Read and execute migration
    $migration_path = "$migrations_dir/$file";
    $sql = file_get_contents($migration_path);
    
    try {
        // Split into individual statements (basic parsing)
        $statements = preg_split('/;(?=\s*\n|$)/', $sql);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) continue;
            
            $pdo->exec($statement);
        }
        
        // Record migration as applied
        $stmt = $pdo->prepare("INSERT INTO $migrations_table (migration_name) VALUES (?)");
        $stmt->execute([$migration_name]);
        
        echo "✅ Applied: $migration_name\n";
        $applied++;
    } catch (Throwable $e) {
        echo "❌ Failed: $migration_name\n";
        echo "   Error: " . $e->getMessage() . "\n";
    }
}

echo "\n📊 Migration Summary:\n";
echo "   Applied: $applied\n";
echo "   Skipped: $skipped\n";
echo "   Total:   " . ($applied + $skipped) . "\n";

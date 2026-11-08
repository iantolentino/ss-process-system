<?php
// Usage: php database/migrate.php  (applies database/migrations/*.sql not yet recorded, in filename order)
require __DIR__ . '/../backend/bootstrap.php';

$pdo = db();
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(190) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)');
$done = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

foreach (glob(__DIR__ . '/migrations/*.sql') as $file) {
    $name = basename($file);
    if (in_array($name, $done, true)) continue;
    // Statements end with ";" at end of line; keep SQL free of ";" inside strings.
    foreach (preg_split('/;\s*\n/', file_get_contents($file)) as $sql) {
        if (trim(preg_replace('/^--.*$/m', '', $sql)) !== '') $pdo->exec($sql);
    }
    $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)')->execute([$name]);
    log_line('app', 'INFO', "migration applied: $name");
    echo "applied $name\n";
}
echo "up to date\n";

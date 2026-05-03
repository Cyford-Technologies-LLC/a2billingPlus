<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$projectRoot = dirname(__DIR__);
$migrationsPath = $projectRoot . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'migrations';
$pdo = new PDO(databaseDsn(), envString('A2BP_DB_USER', 'a2billinguser'), envString('A2BP_DB_PASSWORD', 'a2billing'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

ensureMigrationsTable($pdo);
$applied = [];
$skipped = [];
$files = glob($migrationsPath . DIRECTORY_SEPARATOR . '*.sql') ?: [];
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    if (migrationApplied($pdo, $name)) {
        $skipped[] = $name;
        continue;
    }

    $sql = file_get_contents($file);
    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException('Migration is empty or unreadable: ' . $name);
    }

    $pdo->exec($sql);
    recordMigration($pdo, $name);
    $applied[] = $name;
}

echo json_encode([
    'success' => true,
    'applied' => $applied,
    'skipped' => $skipped,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

function databaseDsn(): string
{
    $dsn = envString('A2BP_DB_DSN');
    if ($dsn !== '') {
        return $dsn;
    }

    return sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        envString('A2BP_DB_HOST', 'db'),
        envString('A2BP_DB_NAME', 'mya2billing')
    );
}

function ensureMigrationsTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS cc_schema_migrations (
            migration VARCHAR(191) NOT NULL,
            applied_at DATETIME NOT NULL,
            PRIMARY KEY (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function migrationApplied(PDO $pdo, string $migrationName): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM cc_schema_migrations WHERE migration = ?');
    $statement->execute([$migrationName]);
    return (int)$statement->fetchColumn() > 0;
}

function recordMigration(PDO $pdo, string $migrationName): void
{
    $statement = $pdo->prepare('INSERT INTO cc_schema_migrations (migration, applied_at) VALUES (?, ?)');
    $statement->execute([$migrationName, gmdate('Y-m-d H:i:s')]);
}

function envString(string $key, string $default = ''): string
{
    $value = getenv($key);
    return is_string($value) && $value !== '' ? $value : $default;
}

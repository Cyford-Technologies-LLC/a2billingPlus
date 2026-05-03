<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$projectRoot = dirname(__DIR__);
$migrationsPath = $projectRoot . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'migrations';

bootstrapDatabaseIfConfigured();

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
    'database_bootstrap' => databaseBootstrapConfigured(),
    'applied' => $applied,
    'skipped' => $skipped,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

function bootstrapDatabaseIfConfigured(): void
{
    if (!databaseBootstrapConfigured()) {
        return;
    }

    $admin = new PDO(adminDatabaseDsn(), envString('A2BP_DB_ADMIN_USER'), envString('A2BP_DB_ADMIN_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $database = mysqlIdentifier(envString('A2BP_DB_NAME', 'mya2billing'), 'A2BP_DB_NAME');
    $charset = mysqlIdentifier(envString('A2BP_DB_CHARSET', 'utf8mb4'), 'A2BP_DB_CHARSET');
    $collation = mysqlIdentifier(envString('A2BP_DB_COLLATION', 'utf8mb4_unicode_ci'), 'A2BP_DB_COLLATION');
    $username = envString('A2BP_DB_USER', 'a2billinguser');
    $password = envString('A2BP_DB_PASSWORD', 'a2billing');
    $host = envString('A2BP_DB_USER_HOST', '%');

    if ($username === '') {
        throw new RuntimeException('A2BP_DB_USER is required when database bootstrap is enabled.');
    }

    $quotedUser = $admin->quote($username);
    $quotedHost = $admin->quote($host);
    $quotedPassword = $admin->quote($password);

    $admin->exec(sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET `%s` COLLATE `%s`',
        $database,
        $charset,
        $collation
    ));
    $admin->exec(sprintf('CREATE USER IF NOT EXISTS %s@%s IDENTIFIED BY %s', $quotedUser, $quotedHost, $quotedPassword));
    $admin->exec(sprintf('ALTER USER %s@%s IDENTIFIED BY %s', $quotedUser, $quotedHost, $quotedPassword));
    $admin->exec(sprintf('GRANT ALL PRIVILEGES ON `%s`.* TO %s@%s', $database, $quotedUser, $quotedHost));
    $admin->exec('FLUSH PRIVILEGES');
}

function databaseBootstrapConfigured(): bool
{
    return envString('A2BP_DB_ADMIN_USER') !== '' || envString('A2BP_DB_ADMIN_PASSWORD') !== '' || envString('A2BP_DB_ADMIN_DSN') !== '';
}

function adminDatabaseDsn(): string
{
    $dsn = envString('A2BP_DB_ADMIN_DSN');
    if ($dsn !== '') {
        return $dsn;
    }

    return sprintf(
        'mysql:host=%s;charset=%s',
        envString('A2BP_DB_HOST', 'db'),
        envString('A2BP_DB_CHARSET', 'utf8mb4')
    );
}

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

function mysqlIdentifier(string $value, string $envName): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
        throw new RuntimeException($envName . ' may only contain letters, numbers, and underscores.');
    }

    return $value;
}

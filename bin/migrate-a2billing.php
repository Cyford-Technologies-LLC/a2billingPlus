<?php

declare(strict_types=1);

use A2BillingPlus\Module\Migration\CustomerMigrationService;

require_once __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['apply', 'limit::', 'help']);
if (isset($options['help'])) {
    echo "Usage: php bin/migrate-a2billing.php [--apply] [--limit=100]\n";
    echo "\n";
    echo "Defaults to dry-run. Set A2BP_SOURCE_DSN/A2BP_SOURCE_USER/A2BP_SOURCE_PASSWORD for the old A2Billing DB.\n";
    echo "Set A2BP_DB_DSN/A2BP_DB_USER/A2BP_DB_PASSWORD for the A2BillingPlus target DB.\n";
    exit(0);
}

$dryRun = !isset($options['apply']);
$limit = isset($options['limit']) ? max(0, (int)$options['limit']) : 0;

$source = pdoFromEnv('A2BP_SOURCE_DSN', 'A2BP_SOURCE_USER', 'A2BP_SOURCE_PASSWORD');
$target = pdoFromEnv('A2BP_DB_DSN', 'A2BP_DB_USER', 'A2BP_DB_PASSWORD');

$summary = (new CustomerMigrationService($source, $target))->migrateCustomers($dryRun, $limit);

echo json_encode([
    'success' => $summary->isSuccessful(),
    'dry_run' => $dryRun,
    'scanned_rows' => $summary->getScannedRows(),
    'inserted_rows' => $summary->getInsertedRows(),
    'updated_rows' => $summary->getUpdatedRows(),
    'skipped_rows' => $summary->getSkippedRows(),
    'message' => $summary->getMessage(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($summary->isSuccessful() ? 0 : 1);

function pdoFromEnv(string $dsnKey, string $userKey, string $passwordKey): PDO
{
    $dsn = getenv($dsnKey);
    if (!is_string($dsn) || $dsn === '') {
        throw new RuntimeException($dsnKey . ' is required.');
    }

    $pdo = new PDO($dsn, envString($userKey), envString($passwordKey), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function envString(string $key): string
{
    $value = getenv($key);
    return is_string($value) ? $value : '';
}

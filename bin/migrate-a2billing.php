<?php

declare(strict_types=1);

use A2BillingPlus\Module\Migration\CustomerMigrationService;

require_once __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['apply', 'limit::', 'scope::', 'help']);
if (isset($options['help'])) {
    echo "Usage: php bin/migrate-a2billing.php [--apply] [--limit=100] [--scope=all|customers|voip]\n";
    echo "\n";
    echo "Defaults to dry-run. Set A2BP_SOURCE_DSN/A2BP_SOURCE_USER/A2BP_SOURCE_PASSWORD for the old A2Billing DB.\n";
    echo "Set A2BP_DB_DSN/A2BP_DB_USER/A2BP_DB_PASSWORD for the A2BillingPlus target DB.\n";
    exit(0);
}

$dryRun = !isset($options['apply']);
$limit = isset($options['limit']) ? max(0, (int)$options['limit']) : 0;
$scope = isset($options['scope']) ? (string)$options['scope'] : 'all';
if (!in_array($scope, ['all', 'customers', 'voip'], true)) {
    throw new InvalidArgumentException('Invalid scope. Use all, customers, or voip.');
}

$source = pdoFromEnv('A2BP_SOURCE_DSN', 'A2BP_SOURCE_USER', 'A2BP_SOURCE_PASSWORD');
$target = pdoFromEnv('A2BP_DB_DSN', 'A2BP_DB_USER', 'A2BP_DB_PASSWORD');

$service = new CustomerMigrationService($source, $target);
$summaries = [];

if ($scope === 'all' || $scope === 'customers') {
    $summaries['customers'] = $service->migrateCustomers($dryRun, $limit);
}
if ($scope === 'all' || $scope === 'voip') {
    $summaries['voip'] = $service->migrateVoipSettings($dryRun, $limit);
}

echo json_encode([
    'success' => summariesSuccessful($summaries),
    'dry_run' => $dryRun,
    'scope' => $scope,
    'scanned_rows' => sumSummaries($summaries, 'getScannedRows'),
    'inserted_rows' => sumSummaries($summaries, 'getInsertedRows'),
    'updated_rows' => sumSummaries($summaries, 'getUpdatedRows'),
    'skipped_rows' => sumSummaries($summaries, 'getSkippedRows'),
    'results' => summaryPayloads($summaries),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit(summariesSuccessful($summaries) ? 0 : 1);

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

/**
 * @param array<string, \A2BillingPlus\Module\Migration\MigrationSummary> $summaries
 */
function summariesSuccessful(array $summaries): bool
{
    foreach ($summaries as $summary) {
        if (!$summary->isSuccessful()) {
            return false;
        }
    }

    return true;
}

/**
 * @param array<string, \A2BillingPlus\Module\Migration\MigrationSummary> $summaries
 */
function sumSummaries(array $summaries, string $method): int
{
    $total = 0;
    foreach ($summaries as $summary) {
        $total += $summary->{$method}();
    }

    return $total;
}

/**
 * @param array<string, \A2BillingPlus\Module\Migration\MigrationSummary> $summaries
 * @return array<string, array<string, int|string|bool>>
 */
function summaryPayloads(array $summaries): array
{
    $payloads = [];
    foreach ($summaries as $name => $summary) {
        $payloads[$name] = [
            'success' => $summary->isSuccessful(),
            'scanned_rows' => $summary->getScannedRows(),
            'inserted_rows' => $summary->getInsertedRows(),
            'updated_rows' => $summary->getUpdatedRows(),
            'skipped_rows' => $summary->getSkippedRows(),
            'message' => $summary->getMessage(),
        ];
    }

    return $payloads;
}

<?php

declare(strict_types=1);

use A2BillingPlus\Module\Migration\CustomerMigrationService;
use A2BillingPlus\Module\Migration\MigrationReportFormatter;

require_once __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['apply', 'limit::', 'scope::', 'from::', 'to::', 'help']);
if (isset($options['help'])) {
    echo "Usage: php bin/migrate-a2billing.php [--apply] [--limit=100] [--scope=all|customers|voip|cdrs] [--from=YYYY-MM-DD] [--to=YYYY-MM-DD]\n";
    echo "\n";
    echo "Defaults to dry-run. Set A2BP_SOURCE_DSN/A2BP_SOURCE_USER/A2BP_SOURCE_PASSWORD for the old A2Billing DB.\n";
    echo "Set A2BP_DB_DSN/A2BP_DB_USER/A2BP_DB_PASSWORD for the A2BillingPlus target DB.\n";
    exit(0);
}

$dryRun = !isset($options['apply']);
$limit = isset($options['limit']) ? max(0, (int)$options['limit']) : 0;
$scope = isset($options['scope']) ? (string)$options['scope'] : 'all';
$from = isset($options['from']) ? (string)$options['from'] : '';
$to = isset($options['to']) ? (string)$options['to'] : '';
if (!in_array($scope, ['all', 'customers', 'voip', 'cdrs'], true)) {
    throw new InvalidArgumentException('Invalid scope. Use all, customers, voip, or cdrs.');
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
if ($scope === 'cdrs') {
    $summaries['cdrs'] = $service->migrateCdrs($dryRun, $limit, $from, $to);
}

$payload = (new MigrationReportFormatter())->jsonPayload($summaries, $dryRun, $scope);
if ($from !== '' || $to !== '') {
    $payload['date_window'] = ['from' => $from, 'to' => $to];
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

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

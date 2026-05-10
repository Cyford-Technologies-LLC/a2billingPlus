#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Module\Security\AuditLogRepository;
use A2BillingPlus\Module\Telephony\PjsipProvisioningService;

function env_string(string $key): string
{
    $value = getenv($key);
    return is_string($value) ? trim($value) : '';
}

function env_bool(string $key, bool $default): bool
{
    $value = env_string($key);
    if ($value === '') {
        return $default;
    }
    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

function trunk_env_key(string $trunkCode, string $suffix): string
{
    $safe = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $trunkCode) ?? '');
    return 'A2BP_PJSIP_TRUNK_' . trim($safe, '_') . '_' . $suffix;
}

$config = AppConfig::fromEnvironment();
$dsn = $config->databaseDsn();
$user = $config->string('A2BP_DB_USER', 'a2billinguser');
$password = $config->string('A2BP_DB_PASSWORD', 'a2billing');
$actor = 'system:backfill-pjsip-trunks';

$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$statement = $pdo->query(
    "SELECT * FROM cc_trunk
     WHERE UPPER(providertech) IN ('SIP', 'PJSIP')
       AND COALESCE(providerip, '') <> ''
       AND COALESCE(status, 1) = 1
     ORDER BY id_trunk"
);
$trunks = $statement->fetchAll();
$pjsip = new PjsipProvisioningService($pdo, new AuditLogRepository($pdo));

$provisioned = 0;
$skipped = 0;
$errors = [];

foreach ($trunks as $trunk) {
    $trunkCode = is_scalar($trunk['trunkcode'] ?? null) ? (string)$trunk['trunkcode'] : '';
    $host = is_scalar($trunk['providerip'] ?? null) ? (string)$trunk['providerip'] : '';
    if ($trunkCode === '' || $host === '') {
        $skipped++;
        continue;
    }

    $username = env_string(trunk_env_key($trunkCode, 'USERNAME'));
    $secret = env_string(trunk_env_key($trunkCode, 'SECRET'));
    $register = env_bool(trunk_env_key($trunkCode, 'REGISTER'), $username !== '' && $secret !== '');

    $result = $pjsip->syncLegacyTrunk($trunk + [
        'username' => $username,
        'secret' => $secret,
        'register' => $register,
    ], $actor);
    if (($result['body']['success'] ?? false) === true) {
        $provisioned++;
        continue;
    }

    $errors[] = [
        'id_trunk' => $trunk['id_trunk'] ?? null,
        'trunkcode' => $trunkCode,
        'host' => $host,
        'message' => (string)($result['body']['message'] ?? 'Unknown PJSIP trunk provisioning failure.'),
    ];
}

echo json_encode([
    'success' => $errors === [],
    'processed' => count($trunks),
    'provisioned' => $provisioned,
    'skipped' => $skipped,
    'errors' => $errors,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($errors === [] ? 0 : 1);

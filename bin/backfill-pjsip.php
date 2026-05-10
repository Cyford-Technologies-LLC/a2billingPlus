#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Module\Security\AuditLogRepository;
use A2BillingPlus\Module\Telephony\PjsipProvisioningService;
use A2BillingPlus\Module\Telephony\TelephonyAccountRepository;

$config = AppConfig::fromEnvironment();
$dsn = $config->databaseDsn();
$user = $config->string('A2BP_DB_USER', 'a2billinguser');
$password = $config->string('A2BP_DB_PASSWORD', 'a2billing');
$actor = 'system:backfill-pjsip';

$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$repository = new TelephonyAccountRepository($pdo);
$pjsip = new PjsipProvisioningService($pdo, new AuditLogRepository($pdo));
$accounts = $repository->allProvisioningSources('sip');

$provisioned = 0;
$skipped = 0;
$errors = [];

foreach ($accounts as $account) {
    $username = is_scalar($account['username'] ?? null) ? (string)$account['username'] : '';
    $secret = is_scalar($account['secret'] ?? null) ? (string)$account['secret'] : '';
    $customerId = isset($account['id_cc_card']) ? (int)$account['id_cc_card'] : 0;

    if ($customerId <= 0 || $username === '' || $secret === '') {
        $skipped++;
        continue;
    }

    $result = $pjsip->syncLegacySipAccount($account, $actor);
    if (($result['body']['success'] ?? false) === true) {
        $provisioned++;
        continue;
    }

    $errors[] = [
        'customer_id' => $customerId,
        'username' => $username,
        'message' => (string)($result['body']['message'] ?? 'Unknown PJSIP provisioning failure.'),
    ];
}

echo json_encode([
    'success' => $errors === [],
    'channel_driver' => $config->string('A2BP_ASTERISK_CHANNEL_DRIVER', 'pjsip'),
    'realtime_enabled' => $config->string('A2BP_ASTERISK_REALTIME', 'yes'),
    'processed' => count($accounts),
    'provisioned' => $provisioned,
    'skipped' => $skipped,
    'errors' => $errors,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($errors === [] ? 0 : 1);

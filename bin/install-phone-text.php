#!/usr/bin/env php
<?php

declare(strict_types=1);

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Module\Messaging\PhoneTextInstaller;

require_once __DIR__ . '/../vendor/autoload.php';

$action = $argv[1] ?? 'install';

if (!in_array($action, ['install', 'uninstall'], true)) {
    fwrite(STDERR, "Usage: php bin/install-phone-text.php [install|uninstall]\n");
    exit(1);
}

$config = AppConfig::fromEnvironment();
$pdo = new PDO(
    $config->databaseDsn(),
    $config->string('A2BP_DB_USER', 'a2billinguser'),
    $config->string('A2BP_DB_PASSWORD', 'a2billing'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$installer = new PhoneTextInstaller($pdo);
$result = $action === 'install' ? $installer->install() : $installer->uninstall();

foreach ($result['steps'] as $step) {
    echo $step . PHP_EOL;
}

echo PHP_EOL . ($result['success']
    ? "Phone & Text integration {$action}ed successfully."
    : "Phone & Text integration {$action} completed with warnings.") . PHP_EOL;
exit($result['success'] ? 0 : 1);

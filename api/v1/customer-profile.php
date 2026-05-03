<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiCustomerContextAuthenticator;
use A2BillingPlus\Api\CustomerSelfServiceController;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;

require_once __DIR__ . '/../../vendor/autoload.php';

$config = AppConfig::fromEnvironment();
$controller = new CustomerSelfServiceController(
    new ApiCustomerContextAuthenticator($config),
    static function () use ($config): PDO {
        return new PDO($config->databaseDsn(), $config->string('A2BP_DB_USER', 'a2billinguser'), $config->string('A2BP_DB_PASSWORD', 'a2billing'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
);

$controller->handle(JsonRequest::fromGlobals())->send();

<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Api\RestApiController;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;

require_once __DIR__ . '/../../vendor/autoload.php';

function a2bp_rest_controller(): RestApiController
{
    $config = AppConfig::fromEnvironment();

    return new RestApiController(
        new ApiServiceKeyAuthenticator($config),
        static function () use ($config): PDO {
            return new PDO($config->databaseDsn(), $config->string('A2BP_DB_USER', 'a2billinguser'), $config->string('A2BP_DB_PASSWORD', 'a2billing'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
    );
}

function a2bp_send_rest_resource(string $resource): void
{
    a2bp_rest_controller()
        ->handle($resource, JsonRequest::fromGlobals())
        ->send();
}

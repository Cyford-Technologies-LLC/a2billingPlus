<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Api\AsteriskHealthController;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;

require_once __DIR__ . '/../../vendor/autoload.php';

$config = AppConfig::fromEnvironment();
$controller = new AsteriskHealthController(new ApiServiceKeyAuthenticator($config), $config);

$controller->handle(JsonRequest::fromGlobals())->send();

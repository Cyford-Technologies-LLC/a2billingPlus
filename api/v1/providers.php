<?php

declare(strict_types=1);

use A2BillingPlus\Api\ProviderApiController;
use A2BillingPlus\Bootstrap\ProviderRegistryFactory;
use A2BillingPlus\Http\JsonRequest;

require_once __DIR__ . '/../../vendor/autoload.php';

$controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
$controller->handle(JsonRequest::fromGlobals())->send();

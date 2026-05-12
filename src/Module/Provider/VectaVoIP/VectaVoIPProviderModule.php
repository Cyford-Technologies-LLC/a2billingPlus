<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\VectaVoIP;

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Module\Provider\ProviderModuleInterface;

final class VectaVoIPProviderModule implements ProviderModuleInterface
{
    public function __construct(private readonly AppConfig $config)
    {
    }

    public function connectors(): iterable
    {
        yield new VectaVoIPConnector();
    }

    public function apiBaseUrl(): string
    {
        return VectaVoIPConnector::API_BASE_URL;
    }
}

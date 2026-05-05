<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\Didww;

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Module\Provider\ProviderModuleInterface;

final class DidwwProviderModule implements ProviderModuleInterface
{
    public function __construct(private readonly AppConfig $config)
    {
    }

    public function connectors(): iterable
    {
        yield new DidwwConnector();
    }

    public function apiBaseUrl(): string
    {
        return $this->config->string('DIDWW_API_BASE_URL', DidwwConnector::API_BASE_URL);
    }
}

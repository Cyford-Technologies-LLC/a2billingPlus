<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider;

interface ProviderConnectorInterface
{
    public function getProviderCode(): string;

    public function getDisplayName(): string;

    public function getSupportEmail(): string;

    public function getApiBaseUrl(): string;

    public function testConnection(ProviderCredentials $credentials): ProviderConnectionResult;

    public function getRateImporter(ProviderCredentials $credentials): RateImporterInterface;
}

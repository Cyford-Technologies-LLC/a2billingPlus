<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\VectaVoIP;

use A2BillingPlus\Module\Provider\ProviderConnectionResult;
use A2BillingPlus\Module\Provider\ProviderConnectorInterface;
use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\RateImporterInterface;

final class VectaVoIPConnector implements ProviderConnectorInterface
{
    public function getProviderCode(): string
    {
        return 'vectavoip';
    }

    public function getDisplayName(): string
    {
        return 'VectaVoIP';
    }

    public function testConnection(ProviderCredentials $credentials): ProviderConnectionResult
    {
        if ($credentials->getBaseUrl() === '') {
            return new ProviderConnectionResult(false, 'VectaVoIP API base URL is required.');
        }

        if ($credentials->getApiKey() === '') {
            return new ProviderConnectionResult(false, 'VectaVoIP API key is required.');
        }

        return new ProviderConnectionResult(true, 'VectaVoIP credentials are structurally valid.', [
            'base_url' => $credentials->getBaseUrl(),
            'provider' => $this->getProviderCode(),
        ]);
    }

    public function getRateImporter(ProviderCredentials $credentials): RateImporterInterface
    {
        return new VectaVoIPRateImporter($credentials);
    }
}

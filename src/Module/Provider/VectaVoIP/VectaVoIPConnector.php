<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\VectaVoIP;

use A2BillingPlus\Module\Provider\ProviderConnectionResult;
use A2BillingPlus\Module\Provider\ProviderConnectorInterface;
use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\RateImporterInterface;

final class VectaVoIPConnector implements ProviderConnectorInterface
{
    public const API_BASE_URL = 'https://api.VectaVoIP.com';
    public const SUPPORT_EMAIL = 'info@VectaVoIP.com';

    public function getProviderCode(): string
    {
        return 'vectavoip';
    }

    public function getDisplayName(): string
    {
        return 'VectaVoIP';
    }

    public function getSupportEmail(): string
    {
        return self::SUPPORT_EMAIL;
    }

    public function getApiBaseUrl(): string
    {
        return self::API_BASE_URL;
    }

    public function testConnection(ProviderCredentials $credentials): ProviderConnectionResult
    {
        if ($credentials->getBaseUrl() === '') {
            return new ProviderConnectionResult(false, 'VectaVoIP API base URL is required. Use https://api.VectaVoIP.com.');
        }

        if ($credentials->getApiKey() === '') {
            return new ProviderConnectionResult(false, 'VectaVoIP API key is required. Contact info@VectaVoIP.com for access.');
        }

        return new ProviderConnectionResult(true, 'VectaVoIP credentials are structurally valid.', [
            'base_url' => $credentials->getBaseUrl(),
            'provider' => $this->getProviderCode(),
            'support_email' => $this->getSupportEmail(),
        ]);
    }

    public function getRateImporter(ProviderCredentials $credentials): RateImporterInterface
    {
        return new VectaVoIPRateImporter($credentials);
    }
}

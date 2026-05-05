<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\Didww;

use A2BillingPlus\Module\Provider\ProviderConnectionResult;
use A2BillingPlus\Module\Provider\ProviderConnectorInterface;
use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\RateImporterInterface;
use A2BillingPlus\Module\Provider\UnsupportedRateImporter;

final class DidwwConnector implements ProviderConnectorInterface
{
    public const API_BASE_URL = 'https://api.didww.com';
    public const SUPPORT_EMAIL = 'support@didww.com';

    /**
     * @param null|callable(): DidwwStatusClient $statusClientFactory
     */
    public function __construct(private $statusClientFactory = null)
    {
    }

    public function getProviderCode(): string
    {
        return 'didww';
    }

    public function getDisplayName(): string
    {
        return 'DIDWW';
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
            return new ProviderConnectionResult(false, 'DIDWW API base URL is required. Use https://api.didww.com.');
        }

        if ($credentials->getApiKey() === '') {
            return new ProviderConnectionResult(false, 'DIDWW API key is required.');
        }

        return $this->statusClient()->check($credentials);
    }

    public function getRateImporter(ProviderCredentials $credentials): RateImporterInterface
    {
        return new UnsupportedRateImporter('DIDWW');
    }

    private function statusClient(): DidwwStatusClient
    {
        if (is_callable($this->statusClientFactory)) {
            return ($this->statusClientFactory)();
        }

        return new DidwwStatusClient();
    }
}

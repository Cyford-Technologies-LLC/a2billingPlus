<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\Twilio;

use A2BillingPlus\Module\Provider\ProviderConnectionResult;
use A2BillingPlus\Module\Provider\ProviderConnectorInterface;
use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\RateImporterInterface;
use A2BillingPlus\Module\Provider\UnsupportedRateImporter;

final class TwilioConnector implements ProviderConnectorInterface
{
    public const API_BASE_URL = TwilioApiClient::API_BASE_URL;
    public const SUPPORT_EMAIL = 'help@twilio.com';

    /**
     * @param null|callable(): TwilioStatusClient $statusClientFactory
     */
    public function __construct(private $statusClientFactory = null)
    {
    }

    public function getProviderCode(): string
    {
        return 'twilio';
    }

    public function getDisplayName(): string
    {
        return 'Twilio';
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
        if ($credentials->getMetadataValue('account_sid') === '') {
            return new ProviderConnectionResult(false, 'Twilio Account SID is required.');
        }

        if ($credentials->getApiSecret() === '') {
            return new ProviderConnectionResult(false, 'Twilio API secret or Auth Token is required.');
        }

        return $this->statusClient()->check($credentials);
    }

    public function getRateImporter(ProviderCredentials $credentials): RateImporterInterface
    {
        return new TwilioVoiceRateImporter($credentials);
    }

    private function statusClient(): TwilioStatusClient
    {
        if (is_callable($this->statusClientFactory)) {
            return ($this->statusClientFactory)();
        }

        return new TwilioStatusClient();
    }
}

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
        $details = $this->diagnosticDetails($credentials);

        if ($credentials->getMetadataValue('account_sid') === '') {
            return new ProviderConnectionResult(false, 'Twilio Account SID is required.', $details);
        }

        if ($credentials->getApiSecret() === '') {
            return new ProviderConnectionResult(false, 'Twilio API secret or Auth Token is required.', $details);
        }

        if (!str_starts_with($credentials->getMetadataValue('account_sid'), 'AC')) {
            $details['account_sid_format'] = 'invalid';
            return new ProviderConnectionResult(false, 'Twilio Account SID must start with AC.', $details);
        }

        if ($credentials->getApiKey() !== '' && !str_starts_with($credentials->getApiKey(), 'SK') && $credentials->getApiKey() !== $credentials->getMetadataValue('account_sid')) {
            $details['auth_user_format'] = 'unexpected';
            return new ProviderConnectionResult(false, 'Twilio API Key should start with SK, or leave it blank to use Account SID + Auth Token.', $details);
        }

        return $this->statusClient()->check($credentials, $details);
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

    /**
     * @return array<string, mixed>
     */
    private function diagnosticDetails(ProviderCredentials $credentials): array
    {
        $accountSid = $credentials->getMetadataValue('account_sid');
        $apiKey = $credentials->getApiKey();
        $authMode = str_starts_with($apiKey, 'SK') ? 'API Key + API Secret' : 'Account SID + Auth Token';

        return [
            'auth_mode' => $authMode,
            'account_sid' => $this->maskedIdentifier($accountSid, 6, 4),
            'auth_user' => $this->maskedIdentifier($apiKey !== '' ? $apiKey : $accountSid, 6, 4),
            'api_secret_present' => $credentials->getApiSecret() !== '',
            'account_sid_format' => str_starts_with($accountSid, 'AC') ? 'ok' : 'invalid',
            'api_key_format' => $apiKey === '' || str_starts_with($apiKey, 'SK') || $apiKey === $accountSid ? 'ok' : 'unexpected',
            'base_url' => $credentials->getBaseUrl(),
            'byoc_trunk_sid' => $this->maskedIdentifier($credentials->getMetadataValue('byoc_trunk_sid'), 6, 4),
        ];
    }

    private function maskedIdentifier(string $value, int $left, int $right): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (strlen($value) <= $left + $right) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, $left) . str_repeat('*', max(4, strlen($value) - $left - $right)) . substr($value, -$right);
    }
}

<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\Twilio;

use A2BillingPlus\Module\Provider\ProviderConnectionResult;
use A2BillingPlus\Module\Provider\ProviderCredentials;

final class TwilioStatusClient
{
    public function __construct(private readonly TwilioApiClient $client = new TwilioApiClient())
    {
    }

    /**
     * @param array<string, mixed> $details
     */
    public function check(ProviderCredentials $credentials, array $details = []): ProviderConnectionResult
    {
        try {
            $account = $this->client->account($credentials);
        } catch (\Throwable $exception) {
            $details['twilio_error'] = $exception->getMessage();
            $details['likely_bad_field'] = str_starts_with($credentials->getApiKey(), 'SK')
                ? 'Twilio API Key or API Secret'
                : 'Twilio Account SID or Auth Token';

            return new ProviderConnectionResult(false, 'Twilio authentication failed: ' . $exception->getMessage(), $details);
        }

        return new ProviderConnectionResult(true, 'Twilio authentication verified.', $details + [
            'account_sid' => (string)($account['sid'] ?? $credentials->getMetadataValue('account_sid')),
            'account_status' => (string)($account['status'] ?? ''),
        ]);
    }
}

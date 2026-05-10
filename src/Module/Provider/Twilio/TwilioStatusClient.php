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

    public function check(ProviderCredentials $credentials): ProviderConnectionResult
    {
        try {
            $account = $this->client->account($credentials);
        } catch (\Throwable $exception) {
            return new ProviderConnectionResult(false, 'Twilio authentication failed: ' . $exception->getMessage());
        }

        return new ProviderConnectionResult(true, 'Twilio authentication verified.', [
            'account_sid' => (string)($account['sid'] ?? $credentials->getMetadataValue('account_sid')),
            'account_status' => (string)($account['status'] ?? ''),
        ]);
    }
}

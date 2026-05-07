<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\Twilio;

use A2BillingPlus\Module\Provider\ProviderConnectionResult;
use A2BillingPlus\Module\Provider\ProviderCredentials;

final class TwilioStatusClient
{
    /**
     * @param null|callable(): TwilioApiClient $clientFactory
     */
    public function __construct(private $clientFactory = null)
    {
    }

    public function check(ProviderCredentials $credentials): ProviderConnectionResult
    {
        try {
            $account = $this->client()->account($credentials);
        } catch (\Throwable $exception) {
            return new ProviderConnectionResult(false, 'Twilio connection failed: ' . $exception->getMessage());
        }

        $friendlyName = is_scalar($account['friendly_name'] ?? null) ? (string) $account['friendly_name'] : '';

        return new ProviderConnectionResult(true, 'Twilio credentials are valid.', [
            'account_sid' => $credentials->getMetadataValue('account_sid'),
            'friendly_name' => $friendlyName,
        ]);
    }

    private function client(): TwilioApiClient
    {
        if (is_callable($this->clientFactory)) {
            return ($this->clientFactory)();
        }

        return new TwilioApiClient();
    }
}

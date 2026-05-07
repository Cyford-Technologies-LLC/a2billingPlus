<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\Twilio;

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Module\Provider\ProviderModuleInterface;

final class TwilioProviderModule implements ProviderModuleInterface
{
    public function __construct(private readonly AppConfig $config)
    {
    }

    public function connectors(): iterable
    {
        yield new TwilioConnector();
    }

    public function apiBaseUrl(): string
    {
        return $this->config->string('TWILIO_API_BASE_URL', TwilioConnector::API_BASE_URL);
    }
}

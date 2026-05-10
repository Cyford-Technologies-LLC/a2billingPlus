<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\Twilio;

use A2BillingPlus\Module\Provider\ProviderModuleInterface;

final class TwilioProviderModule implements ProviderModuleInterface
{
    public function connectors(): iterable
    {
        yield new TwilioConnector();
    }
}

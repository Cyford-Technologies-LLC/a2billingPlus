<?php

declare(strict_types=1);

namespace A2BillingPlus\Bootstrap;

use A2BillingPlus\Module\Provider\ProviderRegistry;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPConnector;

final class ProviderRegistryFactory
{
    public static function createDefault(): ProviderRegistry
    {
        return new ProviderRegistry([
            new VectaVoIPConnector(),
        ]);
    }
}

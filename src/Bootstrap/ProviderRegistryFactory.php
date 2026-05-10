<?php

declare(strict_types=1);

namespace A2BillingPlus\Bootstrap;

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Module\Provider\ProviderModuleInterface;
use A2BillingPlus\Module\Provider\ProviderRegistry;
use A2BillingPlus\Module\Provider\Twilio\TwilioProviderModule;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPProviderModule;

final class ProviderRegistryFactory
{
    /**
     * @param null|iterable<ProviderModuleInterface> $modules
     */
    public static function createDefault(?iterable $modules = null): ProviderRegistry
    {
        $registry = new ProviderRegistry();

        foreach ($modules ?? self::defaultModules() as $module) {
            foreach ($module->connectors() as $connector) {
                $registry->register($connector);
            }
        }

        return $registry;
    }

    /**
     * @return list<ProviderModuleInterface>
     */
    public static function defaultModules(): array
    {
        $config = AppConfig::fromEnvironment();

        return [
            new VectaVoIPProviderModule($config),
            new TwilioProviderModule(),
        ];
    }
}

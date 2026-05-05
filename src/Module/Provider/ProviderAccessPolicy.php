<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider;

use A2BillingPlus\Config\AppConfig;

final class ProviderAccessPolicy
{
    public function __construct(private readonly AppConfig $config)
    {
    }

    public function isAllowed(string $providerCode, string $actor = ''): bool
    {
        if (!$this->isLocked($providerCode)) {
            return true;
        }

        $actor = strtolower(trim($actor));
        if ($actor === '') {
            return false;
        }

        return in_array($actor, $this->licensedActors(), true);
    }

    public function isLocked(string $providerCode): bool
    {
        return in_array(strtolower(trim($providerCode)), $this->csv('A2BP_LOCKED_PROVIDERS'), true);
    }

    public function denialMessage(string $providerCode): string
    {
        return strtoupper($providerCode) . ' is locked to company admins and licensed individuals.';
    }

    /**
     * @return list<string>
     */
    private function licensedActors(): array
    {
        return array_values(array_unique(array_merge(
            $this->csv('A2BP_PROVIDER_OWNER_ADMINS'),
            $this->csv('A2BP_PROVIDER_LICENSED_ADMINS')
        )));
    }

    /**
     * @return list<string>
     */
    private function csv(string $key): array
    {
        $value = strtolower($this->config->string($key));
        if ($value === '') {
            return [];
        }

        $parts = array_map(static fn (string $item): string => trim($item), explode(',', $value));
        return array_values(array_filter($parts, static fn (string $item): bool => $item !== ''));
    }
}

<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider;

use A2BillingPlus\Config\AppConfig;

final class ProviderAccessPolicy
{
    private const BUILT_IN_PROVIDER = 'vectavoip';

    public function __construct(private readonly AppConfig $config)
    {
    }

    public function isAllowed(string $providerCode, string $actor = '', string $unlockToken = ''): bool
    {
        if (!$this->isLocked($providerCode)) {
            return true;
        }

        $configuredToken = $this->unlockToken();
        if ($configuredToken !== '' && hash_equals($configuredToken, trim($unlockToken))) {
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
        $providerCode = strtolower(trim($providerCode));
        if ($providerCode === self::BUILT_IN_PROVIDER) {
            return false;
        }

        return true;
    }

    public function denialMessage(string $providerCode): string
    {
        return strtoupper($providerCode) . ' is a locked provider module. Supply the provider unlock token to view or use it.';
    }

    public function unlockTokenConfigured(): bool
    {
        return $this->unlockToken() !== '';
    }

    private function unlockToken(): string
    {
        $token = trim($this->config->string('VECTAVOIP_PROVIDER_UNLOCK_TOKEN'));
        if ($token !== '') {
            return $token;
        }

        return trim($this->config->string('A2BP_PROVIDER_UNLOCK_TOKEN'));
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

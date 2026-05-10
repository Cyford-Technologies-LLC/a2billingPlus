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

        return false;
    }

    public function isLocked(string $providerCode): bool
    {
        return strtolower(trim($providerCode)) !== self::BUILT_IN_PROVIDER;
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
}

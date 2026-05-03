<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider;

final class ProviderRegistry
{
    /** @var array<string, ProviderConnectorInterface> */
    private array $connectors = [];

    /**
     * @param iterable<ProviderConnectorInterface> $connectors
     */
    public function __construct(iterable $connectors = [])
    {
        foreach ($connectors as $connector) {
            $this->register($connector);
        }
    }

    public function register(ProviderConnectorInterface $connector): void
    {
        $this->connectors[$connector->getProviderCode()] = $connector;
    }

    public function get(string $providerCode): ?ProviderConnectorInterface
    {
        return $this->connectors[$providerCode] ?? null;
    }

    /**
     * @return array<string, ProviderConnectorInterface>
     */
    public function all(): array
    {
        return $this->connectors;
    }
}

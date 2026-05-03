<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider;

final class ProviderCredentials
{
    /**
     * @param array<string, string> $metadata
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $apiSecret = '',
        private readonly array $metadata = []
    ) {
    }

    public function getBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getApiSecret(): string
    {
        return $this->apiSecret;
    }

    /**
     * @return array<string, string>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getMetadataValue(string $key, string $default = ''): string
    {
        return $this->metadata[$key] ?? $default;
    }
}

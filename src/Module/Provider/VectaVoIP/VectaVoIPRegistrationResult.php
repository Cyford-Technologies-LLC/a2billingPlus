<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\VectaVoIP;

final class VectaVoIPRegistrationResult
{
    /**
     * @param array<string, string> $metadata
     */
    public function __construct(
        private readonly bool $successful,
        private readonly string $message,
        private readonly string $installationId = '',
        private readonly string $apiKey = '',
        private readonly string $apiSecret = '',
        private readonly array $metadata = []
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getInstallationId(): string
    {
        return $this->installationId;
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
}

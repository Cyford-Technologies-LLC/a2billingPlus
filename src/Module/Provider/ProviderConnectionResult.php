<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider;

final class ProviderConnectionResult
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        private readonly bool $successful,
        private readonly string $message,
        private readonly array $details = []
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

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}

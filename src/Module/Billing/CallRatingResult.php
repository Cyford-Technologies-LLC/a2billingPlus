<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Billing;

final class CallRatingResult
{
    public function __construct(
        private readonly bool $rated,
        private readonly string $message,
        private readonly string $dialPrefix = '',
        private readonly int $billableSeconds = 0,
        private readonly string $customerCost = '0.00000',
        private readonly string $providerCost = '0.00000'
    ) {
    }

    public function isRated(): bool
    {
        return $this->rated;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getDialPrefix(): string
    {
        return $this->dialPrefix;
    }

    public function getBillableSeconds(): int
    {
        return $this->billableSeconds;
    }

    public function getCustomerCost(): string
    {
        return $this->customerCost;
    }

    public function getProviderCost(): string
    {
        return $this->providerCost;
    }
}

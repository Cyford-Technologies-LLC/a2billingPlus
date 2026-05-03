<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider;

final class RateImportRequest
{
    /**
     * @param array<string, string> $filters
     */
    public function __construct(
        private readonly string $targetCurrency,
        private readonly string $rateDeck = 'default',
        private readonly array $filters = [],
        private readonly bool $dryRun = true
    ) {
    }

    public function getTargetCurrency(): string
    {
        return strtoupper($this->targetCurrency);
    }

    public function getRateDeck(): string
    {
        return $this->rateDeck;
    }

    /**
     * @return array<string, string>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }
}

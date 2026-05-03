<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Rate;

final class RatecardImportSummary
{
    public function __construct(
        private readonly bool $successful,
        private readonly int $importedRows,
        private readonly int $skippedRows,
        private readonly string $message
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function getImportedRows(): int
    {
        return $this->importedRows;
    }

    public function getSkippedRows(): int
    {
        return $this->skippedRows;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}

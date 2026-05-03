<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Migration;

final class MigrationSummary
{
    public function __construct(
        private readonly bool $successful,
        private readonly int $scannedRows,
        private readonly int $insertedRows,
        private readonly int $updatedRows,
        private readonly int $skippedRows,
        private readonly string $message
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function getScannedRows(): int
    {
        return $this->scannedRows;
    }

    public function getInsertedRows(): int
    {
        return $this->insertedRows;
    }

    public function getUpdatedRows(): int
    {
        return $this->updatedRows;
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

<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider;

final class RateImportPreview
{
    /**
     * @param array<int, array<string, mixed>> $sampleRows
     */
    public function __construct(
        private readonly int $totalRows,
        private readonly array $sampleRows = [],
        private readonly string $message = ''
    ) {
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSampleRows(): array
    {
        return $this->sampleRows;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}

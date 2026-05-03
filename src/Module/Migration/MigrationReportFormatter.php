<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Migration;

final class MigrationReportFormatter
{
    /**
     * @param array<string, MigrationSummary> $summaries
     * @return array<string, mixed>
     */
    public function jsonPayload(array $summaries, bool $dryRun, string $scope): array
    {
        return [
            'success' => $this->successful($summaries),
            'dry_run' => $dryRun,
            'scope' => $scope,
            'scanned_rows' => $this->sum($summaries, 'getScannedRows'),
            'inserted_rows' => $this->sum($summaries, 'getInsertedRows'),
            'updated_rows' => $this->sum($summaries, 'getUpdatedRows'),
            'skipped_rows' => $this->sum($summaries, 'getSkippedRows'),
            'results' => $this->summaryPayloads($summaries),
        ];
    }

    /**
     * @param array<string, MigrationSummary> $summaries
     */
    private function successful(array $summaries): bool
    {
        foreach ($summaries as $summary) {
            if (!$summary->isSuccessful()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, MigrationSummary> $summaries
     */
    private function sum(array $summaries, string $method): int
    {
        $total = 0;
        foreach ($summaries as $summary) {
            $total += $summary->{$method}();
        }

        return $total;
    }

    /**
     * @param array<string, MigrationSummary> $summaries
     * @return array<string, array<string, int|string|bool>>
     */
    private function summaryPayloads(array $summaries): array
    {
        $payloads = [];
        foreach ($summaries as $name => $summary) {
            $payloads[$name] = [
                'success' => $summary->isSuccessful(),
                'scanned_rows' => $summary->getScannedRows(),
                'inserted_rows' => $summary->getInsertedRows(),
                'updated_rows' => $summary->getUpdatedRows(),
                'skipped_rows' => $summary->getSkippedRows(),
                'message' => $summary->getMessage(),
            ];
        }

        return $payloads;
    }
}

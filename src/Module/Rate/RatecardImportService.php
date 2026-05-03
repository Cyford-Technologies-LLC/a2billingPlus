<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Rate;

final class RatecardImportService
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly RatecardRowMapper $mapper = new RatecardRowMapper()
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $providerRows
     */
    public function importRows(array $providerRows, int $tariffPlanId, string $tag, bool $dryRun): RatecardImportSummary
    {
        if ($tariffPlanId <= 0) {
            return new RatecardImportSummary(false, 0, count($providerRows), 'A target ratecard ID is required.');
        }

        $mappedRows = [];
        $skippedRows = 0;

        foreach ($providerRows as $providerRow) {
            if (!$this->mapper->isImportable($providerRow)) {
                $skippedRows++;
                continue;
            }

            $mappedRows[] = $this->mapper->map($providerRow, $tariffPlanId, $tag);
        }

        if ($dryRun) {
            return new RatecardImportSummary(true, count($mappedRows), $skippedRows, 'Dry run completed. No rates were imported.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO cc_ratecard (
                idtariffplan, dialprefix, destination, buyrate, buyrateinitblock, buyrateincrement,
                rateinitial, initblock, billingblock, tag
            ) VALUES (
                :idtariffplan, :dialprefix, :destination, :buyrate, :buyrateinitblock, :buyrateincrement,
                :rateinitial, :initblock, :billingblock, :tag
            )'
        );

        $insertedRows = 0;
        foreach ($mappedRows as $row) {
            $statement->execute($row);
            $insertedRows++;
        }

        return new RatecardImportSummary(true, $insertedRows, $skippedRows, 'Rate import completed.');
    }
}

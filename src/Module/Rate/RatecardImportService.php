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
    public function importRows(
        array $providerRows,
        int $tariffPlanId,
        string $tag,
        bool $dryRun,
        bool $updateExisting = false
    ): RatecardImportSummary
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

        $insertStatement = $this->pdo->prepare(
            'INSERT INTO cc_ratecard (
                idtariffplan, dialprefix, destination, buyrate, buyrateinitblock, buyrateincrement,
                rateinitial, initblock, billingblock, tag
            ) VALUES (
                :idtariffplan, :dialprefix, :destination, :buyrate, :buyrateinitblock, :buyrateincrement,
                :rateinitial, :initblock, :billingblock, :tag
            )'
        );
        $updateStatement = $this->pdo->prepare(
            'UPDATE cc_ratecard
             SET destination = :destination,
                 buyrate = :buyrate,
                 buyrateinitblock = :buyrateinitblock,
                 buyrateincrement = :buyrateincrement,
                 rateinitial = :rateinitial,
                 initblock = :initblock,
                 billingblock = :billingblock
             WHERE idtariffplan = :idtariffplan AND dialprefix = :dialprefix AND tag = :tag'
        );

        $changedRows = 0;
        foreach ($mappedRows as $row) {
            $existing = $this->existingRateId((int)$row['idtariffplan'], (string)$row['dialprefix'], (string)$row['tag']);
            if ($existing !== null && !$updateExisting) {
                $skippedRows++;
                continue;
            }

            if ($dryRun) {
                $changedRows++;
                continue;
            }

            if ($existing !== null) {
                $updateStatement->execute($row);
            } else {
                $insertStatement->execute($row);
            }
            $changedRows++;
        }

        if ($dryRun) {
            return new RatecardImportSummary(true, $changedRows, $skippedRows, 'Dry run completed. No rates were imported.');
        }

        return new RatecardImportSummary(true, $changedRows, $skippedRows, 'Rate import completed.');
    }

    private function existingRateId(int $tariffPlanId, string $dialPrefix, string $tag): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM cc_ratecard WHERE idtariffplan = ? AND dialprefix = ? AND tag = ? LIMIT 1'
        );
        $statement->execute([$tariffPlanId, $dialPrefix, $tag]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int)$id;
    }
}

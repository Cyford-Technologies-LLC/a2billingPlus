<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Rate;

final class RatecardImportService
{
    /** @var null|list<string> */
    private ?array $ratecardColumns = null;

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
        bool $updateExisting = false,
        int $trunkId = 0
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

        $optionalColumns = [];
        if ($this->ratecardHasColumn('musiconhold')) {
            $optionalColumns['musiconhold'] = '';
        }
        if ($trunkId > 0 && $this->ratecardHasColumn('id_trunk')) {
            $optionalColumns['id_trunk'] = $trunkId;
        }

        $insertColumns = [
            'idtariffplan',
            'dialprefix',
            'destination',
            'buyrate',
            'buyrateinitblock',
            'buyrateincrement',
            'rateinitial',
            'initblock',
            'billingblock',
            'tag',
            ...array_keys($optionalColumns),
        ];
        $insertPlaceholders = array_map(static fn (string $column): string => ':' . $column, $insertColumns);

        $insertStatement = $this->pdo->prepare(
            'INSERT INTO cc_ratecard (' . implode(', ', $insertColumns) . ')
             VALUES (' . implode(', ', $insertPlaceholders) . ')'
        );
        $updateAssignments = [
            'destination = :destination',
            'buyrate = :buyrate',
            'buyrateinitblock = :buyrateinitblock',
            'buyrateincrement = :buyrateincrement',
            'rateinitial = :rateinitial',
            'initblock = :initblock',
            'billingblock = :billingblock',
        ];
        if (array_key_exists('musiconhold', $optionalColumns)) {
            $updateAssignments[] = 'musiconhold = :musiconhold';
        }
        if (array_key_exists('id_trunk', $optionalColumns)) {
            $updateAssignments[] = 'id_trunk = :id_trunk';
        }
        $updateStatement = $this->pdo->prepare(
            'UPDATE cc_ratecard
             SET ' . implode(",\n                 ", $updateAssignments) . '
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

            $row = array_merge($row, $optionalColumns);
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

    private function ratecardHasColumn(string $column): bool
    {
        if (is_array($this->ratecardColumns)) {
            return in_array($column, $this->ratecardColumns, true);
        }

        try {
            if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $statement = $this->pdo->query('PRAGMA table_info(cc_ratecard)');
                $rows = $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
                $this->ratecardColumns = array_map(static fn (array $row): string => (string)($row['name'] ?? ''), $rows);
                return in_array($column, $this->ratecardColumns, true);
            }

            $statement = $this->pdo->prepare(
                'SELECT COLUMN_NAME
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
            );
            $statement->execute(['cc_ratecard']);
            $this->ratecardColumns = array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
        } catch (\Throwable) {
            $this->ratecardColumns = [];
        }

        return in_array($column, $this->ratecardColumns, true);
    }
}

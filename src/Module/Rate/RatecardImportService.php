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
        ];
        $insertDefaults = [];
        if ($this->columnExists('cc_ratecard', 'musiconhold')) {
            $insertColumns[] = 'musiconhold';
            $insertDefaults['musiconhold'] = '';
        }

        $insertStatement = $this->pdo->prepare(
            'INSERT INTO cc_ratecard (' . implode(', ', $insertColumns) . ')
             VALUES (:' . implode(', :', $insertColumns) . ')'
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

            $this->upsertPrefix((string)$row['dialprefix'], (string)($row['destination_name'] ?? ''));
            $dbRow = $this->ratecardDbRow($row);
            if ($existing !== null) {
                $updateStatement->execute($dbRow);
            } else {
                $insertStatement->execute($dbRow + $insertDefaults);
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

    /**
     * @param array<string, int|string> $row
     * @return array<string, int|string>
     */
    private function ratecardDbRow(array $row): array
    {
        unset($row['destination_name']);
        return $row;
    }

    private function upsertPrefix(string $prefix, string $destination): void
    {
        $prefix = preg_replace('/\D+/', '', $prefix) ?: '';
        $destination = trim($destination);
        if ($prefix === '' || $destination === '' || !$this->tableExists('cc_prefix')) {
            return;
        }

        $destination = substr($destination, 0, 60);
        $existing = $this->pdo->prepare('SELECT destination FROM cc_prefix WHERE prefix = ? LIMIT 1');
        $existing->execute([$prefix]);
        $current = $existing->fetchColumn();

        if ($current === false) {
            $insert = $this->pdo->prepare('INSERT INTO cc_prefix (prefix, destination) VALUES (?, ?)');
            $insert->execute([$prefix, $destination]);
            return;
        }

        if (trim((string)$current) === '') {
            $update = $this->pdo->prepare('UPDATE cc_prefix SET destination = ? WHERE prefix = ?');
            $update->execute([$destination, $prefix]);
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            return false;
        }

        try {
            $statement = $this->pdo->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $this->pdo->quote($column));
            if ($statement !== false && $statement->fetch(\PDO::FETCH_ASSOC) !== false) {
                return true;
            }
        } catch (\Throwable) {
        }

        try {
            $statement = $this->pdo->query('PRAGMA table_info(' . $table . ')');
            if ($statement === false) {
                return false;
            }
            while (($row = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
                if (strcasecmp((string)($row['name'] ?? ''), $column) === 0) {
                    return true;
                }
            }
        } catch (\Throwable) {
        }

        return false;
    }

    private function tableExists(string $table): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }

        try {
            $statement = $this->pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
            return $statement !== false;
        } catch (\Throwable) {
        }

        try {
            $statement = $this->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = " . $this->pdo->quote($table));
            return $statement !== false && $statement->fetch(\PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
        }

        return false;
    }
}

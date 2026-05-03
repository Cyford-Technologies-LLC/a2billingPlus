<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Migration;

final class CustomerMigrationService
{
    public function __construct(
        private readonly \PDO $source,
        private readonly \PDO $target
    ) {
    }

    public function migrateCustomers(bool $dryRun = true, int $limit = 0): MigrationSummary
    {
        return $this->migrateTable('cc_card', 'username', $dryRun, $limit, 'Customer migration');
    }

    public function migrateVoipSettings(bool $dryRun = true, int $limit = 0): MigrationSummary
    {
        $sip = $this->migrateTable('cc_sip_buddies', 'name', $dryRun, $limit, 'SIP migration');
        $iax = $this->migrateTable('cc_iax_buddies', 'name', $dryRun, $limit, 'IAX migration');

        return new MigrationSummary(
            $sip->isSuccessful() && $iax->isSuccessful(),
            $sip->getScannedRows() + $iax->getScannedRows(),
            $sip->getInsertedRows() + $iax->getInsertedRows(),
            $sip->getUpdatedRows() + $iax->getUpdatedRows(),
            $sip->getSkippedRows() + $iax->getSkippedRows(),
            $dryRun ? 'VoIP settings migration dry run completed.' : 'VoIP settings migration completed.'
        );
    }

    public function migrateCdrs(bool $dryRun = true, int $limit = 0, string $from = '', string $to = ''): MigrationSummary
    {
        return $this->migrateTable('cc_call', 'uniqueid', $dryRun, $limit, 'CDR migration', $from, $to);
    }

    private function migrateTable(
        string $tableName,
        string $uniqueColumn,
        bool $dryRun,
        int $limit,
        string $label,
        string $from = '',
        string $to = ''
    ): MigrationSummary {
        $columns = $this->commonColumns($tableName);
        if (!in_array('id', $columns, true) || !in_array($uniqueColumn, $columns, true)) {
            return new MigrationSummary(false, 0, 0, 0, 0, $tableName . ' must have id and ' . $uniqueColumn . ' columns in both databases.');
        }

        $rows = $this->sourceRows($tableName, $columns, $limit, $from, $to);
        $insertedRows = 0;
        $updatedRows = 0;
        $skippedRows = 0;

        foreach ($rows as $row) {
            if (!$this->isImportable($row, $uniqueColumn)) {
                $skippedRows++;
                continue;
            }

            $targetId = $this->targetId($tableName, (string)$row['id'], $uniqueColumn, (string)$row[$uniqueColumn]);
            if ($dryRun) {
                if ($targetId === null) {
                    $insertedRows++;
                } else {
                    $updatedRows++;
                }
                continue;
            }

            if ($targetId === null) {
                $this->insertRow($tableName, $columns, $row);
                $insertedRows++;
            } else {
                $this->updateRow($tableName, $columns, $row, $targetId);
                $updatedRows++;
            }
        }

        return new MigrationSummary(
            true,
            count($rows),
            $insertedRows,
            $updatedRows,
            $skippedRows,
            $dryRun ? $label . ' dry run completed.' : $label . ' completed.'
        );
    }

    /**
     * @return list<string>
     */
    private function commonColumns(string $tableName): array
    {
        $sourceColumns = $this->tableColumns($this->source, $tableName);
        $targetColumns = $this->tableColumns($this->target, $tableName);

        return array_values(array_intersect($sourceColumns, $targetColumns));
    }

    /**
     * @return list<string>
     */
    private function tableColumns(\PDO $pdo, string $tableName): array
    {
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->query('PRAGMA table_info(' . $tableName . ')');
            $columns = [];
            foreach ($statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [] as $row) {
                $columns[] = (string)$row['name'];
            }

            return $columns;
        }

        $statement = $pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION'
        );
        $statement->execute([$tableName]);

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * @param list<string> $columns
     * @return array<int, array<string, mixed>>
     */
    private function sourceRows(string $tableName, array $columns, int $limit, string $from = '', string $to = ''): array
    {
        $sql = 'SELECT ' . implode(', ', array_map([$this, 'quoteIdentifier'], $columns))
            . ' FROM ' . $this->quoteIdentifier($tableName) . ' ORDER BY id ASC';
        $params = [];
        if (in_array('starttime', $columns, true) && ($from !== '' || $to !== '')) {
            $clauses = [];
            if ($from !== '') {
                $clauses[] = 'starttime >= :from_date';
                $params['from_date'] = $from;
            }
            if ($to !== '') {
                $clauses[] = 'starttime < :to_date';
                $params['to_date'] = $to;
            }

            $sql = 'SELECT ' . implode(', ', array_map([$this, 'quoteIdentifier'], $columns))
                . ' FROM ' . $this->quoteIdentifier($tableName)
                . ' WHERE ' . implode(' AND ', $clauses)
                . ' ORDER BY id ASC';
        }

        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }

        $statement = $params === [] ? $this->source->query($sql) : $this->source->prepare($sql);
        if ($statement && $params !== []) {
            $statement->execute($params);
        }

        return $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isImportable(array $row, string $uniqueColumn): bool
    {
        return trim((string)($row['id'] ?? '')) !== '' && trim((string)($row[$uniqueColumn] ?? '')) !== '';
    }

    private function targetId(string $tableName, string $sourceId, string $uniqueColumn, string $uniqueValue): ?string
    {
        $statement = $this->target->prepare(
            'SELECT id FROM ' . $this->quoteIdentifier($tableName)
            . ' WHERE id = ? OR ' . $this->quoteIdentifier($uniqueColumn) . ' = ? LIMIT 1'
        );
        $statement->execute([$sourceId, $uniqueValue]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (string)$id;
    }

    /**
     * @param list<string> $columns
     * @param array<string, mixed> $row
     */
    private function insertRow(string $tableName, array $columns, array $row): void
    {
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($tableName),
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            implode(', ', $placeholders)
        );

        $statement = $this->target->prepare($sql);
        $statement->execute($this->params($columns, $row));
    }

    /**
     * @param list<string> $columns
     * @param array<string, mixed> $row
     */
    private function updateRow(string $tableName, array $columns, array $row, string $targetId): void
    {
        $updateColumns = array_values(array_filter($columns, static fn (string $column): bool => $column !== 'id'));
        $assignments = array_map(fn (string $column): string => $this->quoteIdentifier($column) . ' = :' . $column, $updateColumns);
        $sql = sprintf(
            'UPDATE %s SET %s WHERE id = :target_id',
            $this->quoteIdentifier($tableName),
            implode(', ', $assignments)
        );

        $params = $this->params($updateColumns, $row);
        $params['target_id'] = $targetId;

        $statement = $this->target->prepare($sql);
        $statement->execute($params);
    }

    /**
     * @param list<string> $columns
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function params(array $columns, array $row): array
    {
        $params = [];
        foreach ($columns as $column) {
            $params[$column] = $row[$column] ?? null;
        }

        return $params;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}

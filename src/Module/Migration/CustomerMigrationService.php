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
        $columns = $this->commonColumns('cc_card');
        if (!in_array('id', $columns, true) || !in_array('username', $columns, true)) {
            return new MigrationSummary(false, 0, 0, 0, 0, 'cc_card must have id and username columns in both databases.');
        }

        $rows = $this->sourceRows($columns, $limit);
        $insertedRows = 0;
        $updatedRows = 0;
        $skippedRows = 0;

        foreach ($rows as $row) {
            if (!$this->isImportable($row)) {
                $skippedRows++;
                continue;
            }

            $targetId = $this->targetId((string)$row['id'], (string)$row['username']);
            if ($dryRun) {
                if ($targetId === null) {
                    $insertedRows++;
                } else {
                    $updatedRows++;
                }
                continue;
            }

            if ($targetId === null) {
                $this->insertRow('cc_card', $columns, $row);
                $insertedRows++;
            } else {
                $this->updateRow('cc_card', $columns, $row, $targetId);
                $updatedRows++;
            }
        }

        return new MigrationSummary(
            true,
            count($rows),
            $insertedRows,
            $updatedRows,
            $skippedRows,
            $dryRun ? 'Customer migration dry run completed.' : 'Customer migration completed.'
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
    private function sourceRows(array $columns, int $limit): array
    {
        $sql = 'SELECT ' . implode(', ', array_map([$this, 'quoteIdentifier'], $columns)) . ' FROM cc_card ORDER BY id ASC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }

        $statement = $this->source->query($sql);
        return $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isImportable(array $row): bool
    {
        return trim((string)($row['id'] ?? '')) !== '' && trim((string)($row['username'] ?? '')) !== '';
    }

    private function targetId(string $sourceId, string $username): ?string
    {
        $statement = $this->target->prepare('SELECT id FROM cc_card WHERE id = ? OR username = ? LIMIT 1');
        $statement->execute([$sourceId, $username]);
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

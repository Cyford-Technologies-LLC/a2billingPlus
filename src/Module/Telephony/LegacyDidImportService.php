<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Telephony;

final class LegacyDidImportService
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @param list<array<string,mixed>> $inventoryRows
     * @return array{imported:int, skipped:int}
     */
    public function importMissingFromInventory(array $inventoryRows): array
    {
        if ($inventoryRows === [] || !$this->tableExists('cc_did')) {
            return ['imported' => 0, 'skipped' => count($inventoryRows)];
        }

        $columns = $this->availableColumns('cc_did', [
            'id_cc_didgroup',
            'id_cc_country',
            'activated',
            'reserved',
            'iduser',
            'did',
            'startingdate',
            'expirationdate',
            'description',
            'billingtype',
            'fixrate',
            'max_concurrent',
        ]);

        $imported = 0;
        $skipped = 0;
        foreach ($inventoryRows as $row) {
            $did = $this->didValue($row);
            if ($did === '' || $this->legacyDidExists($did)) {
                $skipped++;
                continue;
            }

            $values = [
                'id_cc_didgroup' => 0,
                'id_cc_country' => $this->countryIdForInventory($row),
                'activated' => 1,
                'reserved' => 0,
                'iduser' => 0,
                'did' => $did,
                'startingdate' => '0000-00-00 00:00:00',
                'expirationdate' => '0000-00-00 00:00:00',
                'description' => $this->descriptionForInventory($row),
                'billingtype' => 0,
                'fixrate' => $this->stringValue($row, 'monthly_rate', '0.00000'),
                'max_concurrent' => 10,
            ];

            $insertColumns = [];
            $insertValues = [];
            foreach ($columns as $column) {
                if (!array_key_exists($column, $values)) {
                    continue;
                }
                $insertColumns[] = $column;
                $insertValues[] = $values[$column];
            }

            $statement = $this->pdo->prepare(sprintf(
                'INSERT INTO cc_did (%s) VALUES (%s)',
                implode(', ', array_map([$this, 'quoteIdentifier'], $insertColumns)),
                implode(', ', array_fill(0, count($insertColumns), '?'))
            ));
            $statement->execute($insertValues);
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * @return array{imported:int, skipped:int}
     */
    public function importAllCachedInventory(): array
    {
        if (!$this->tableExists('cc_vectavoip_did_inventory')) {
            return ['imported' => 0, 'skipped' => 0];
        }

        $statement = $this->pdo->query(
            'SELECT did, country, region, monthly_rate, provider_code, provider_trunk_name, provider_reference, provider_trunk_reference, order_reference
             FROM cc_vectavoip_did_inventory
             ORDER BY did ASC'
        );
        $rows = $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
        if (!is_array($rows) || $rows === []) {
            return ['imported' => 0, 'skipped' => 0];
        }

        return $this->importMissingFromInventory($rows);
    }

    private function legacyDidExists(string $did): bool
    {
        $statement = $this->pdo->prepare('SELECT id FROM cc_did WHERE did = ? LIMIT 1');
        $statement->execute([$did]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function countryIdForInventory(array $row): int
    {
        if (!$this->tableExists('cc_country')) {
            return 0;
        }

        $country = strtoupper($this->stringValue($row, 'country', $this->stringValue($row, 'country_code')));
        if ($country === '') {
            return 0;
        }

        foreach (['countrycode', 'dialingcode'] as $column) {
            if (!$this->columnExists('cc_country', $column)) {
                continue;
            }
            $statement = $this->pdo->prepare(sprintf('SELECT id FROM cc_country WHERE %s = ? LIMIT 1', $this->quoteIdentifier($column)));
            $statement->execute([$country]);
            $id = $statement->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }
        }

        if ($this->columnExists('cc_country', 'countryname')) {
            $statement = $this->pdo->prepare('SELECT id FROM cc_country WHERE UPPER(countryname) = ? LIMIT 1');
            $statement->execute([$country]);
            $id = $statement->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function descriptionForInventory(array $row): string
    {
        $provider = strtoupper($this->stringValue($row, 'provider_code'));
        $trunk = $this->stringValue($row, 'provider_trunk_name');

        $parts = array_values(array_filter([
            $provider !== '' ? 'Synced from ' . $provider : 'Synced provider DID',
            $trunk !== '' ? 'Trunk: ' . $trunk : '',
        ]));

        return implode(' | ', $parts);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function didValue(array $row): string
    {
        return $this->stringValue($row, 'did', $this->stringValue($row, 'phone_number', $this->stringValue($row, 'number')));
    }

    /**
     * @param array<string,mixed> $row
     */
    private function stringValue(array $row, string $key, string $default = ''): string
    {
        $value = $row[$key] ?? $default;
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /**
     * @param list<string> $expected
     * @return list<string>
     */
    private function availableColumns(string $table, array $expected): array
    {
        $driver = (string) $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $statement = $this->pdo->query('PRAGMA table_info(' . $this->quoteIdentifier($table) . ')');
            $rows = $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
            $available = array_map(static fn (array $row): string => (string) $row['name'], $rows);
        } else {
            $statement = $this->pdo->query('SHOW COLUMNS FROM ' . $this->quoteIdentifier($table));
            $rows = $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
            $available = array_map(static fn (array $row): string => (string) $row['Field'], $rows);
        }

        return array_values(array_intersect($expected, $available));
    }

    private function tableExists(string $table): bool
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            return false;
        }

        if ((string) $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table");
        } else {
            $statement = $this->pdo->prepare('SHOW TABLES LIKE :table');
        }
        $statement->execute([':table' => $table]);

        return $statement->fetchColumn() !== false;
    }

    private function columnExists(string $table, string $column): bool
    {
        return in_array($column, $this->availableColumns($table, [$column]), true);
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new \InvalidArgumentException('Unsafe SQL identifier.');
        }

        return '`' . $identifier . '`';
    }
}

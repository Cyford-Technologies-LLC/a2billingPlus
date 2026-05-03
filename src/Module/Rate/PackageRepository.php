<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Rate;

final class PackageRepository
{
    private const PACKAGE_TABLE = 'cc_package_offer';
    private const PACKAGE_RATE_TABLE = 'cc_package_rate';
    private const RATE_TABLE = 'cc_ratecard';

    private const PACKAGE_COLUMNS = [
        'id',
        'creationdate',
        'label',
        'packagetype',
        'billingtype',
        'startday',
        'freetimetocall',
    ];

    private const RATE_COLUMNS = [
        'id',
        'idtariffplan',
        'dialprefix',
        'destination',
        'buyrate',
        'rateinitial',
        'initblock',
        'billingblock',
        'tag',
    ];

    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function list(int $limit, int $offset, string $search = ''): array
    {
        $columns = $this->availableColumns(self::PACKAGE_TABLE, self::PACKAGE_COLUMNS);
        if ($columns === []) {
            throw new \RuntimeException('No supported package columns were found.');
        }

        $whereSql = '';
        if ($search !== '' && in_array('label', $columns, true)) {
            $whereSql = ' WHERE ' . $this->quoteIdentifier('label') . ' LIKE :search';
        }

        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM %s%s ORDER BY %s DESC LIMIT :limit OFFSET :offset',
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            $this->quoteIdentifier(self::PACKAGE_TABLE),
            $whereSql,
            $this->quoteIdentifier(in_array('id', $columns, true) ? 'id' : $columns[0])
        ));
        if ($search !== '') {
            $statement->bindValue(':search', '%' . $search . '%');
        }
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return ['items' => $statement->fetchAll(\PDO::FETCH_ASSOC), 'columns' => $columns];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(int $id): ?array
    {
        $columns = $this->availableColumns(self::PACKAGE_TABLE, self::PACKAGE_COLUMNS);
        if ($columns === [] || !in_array('id', $columns, true)) {
            throw new \RuntimeException('No supported package id column was found.');
        }

        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM %s WHERE %s = :id',
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            $this->quoteIdentifier(self::PACKAGE_TABLE),
            $this->quoteIdentifier('id')
        ));
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function rates(int $packageId, int $limit, int $offset): array
    {
        $rateColumns = $this->availableColumns(self::RATE_TABLE, self::RATE_COLUMNS);
        $linkColumns = $this->availableColumns(self::PACKAGE_RATE_TABLE, ['package_id', 'rate_id']);
        if ($rateColumns === [] || count($linkColumns) < 2) {
            return ['items' => [], 'columns' => $rateColumns];
        }

        $select = implode(', ', array_map(
            fn (string $column): string => 'r.' . $this->quoteIdentifier($column),
            $rateColumns
        ));

        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s
             FROM %s pr
             INNER JOIN %s r ON r.%s = pr.%s
             WHERE pr.%s = :package_id
             ORDER BY r.%s DESC
             LIMIT :limit OFFSET :offset',
            $select,
            $this->quoteIdentifier(self::PACKAGE_RATE_TABLE),
            $this->quoteIdentifier(self::RATE_TABLE),
            $this->quoteIdentifier('id'),
            $this->quoteIdentifier('rate_id'),
            $this->quoteIdentifier('package_id'),
            $this->quoteIdentifier(in_array('id', $rateColumns, true) ? 'id' : $rateColumns[0])
        ));
        $statement->bindValue(':package_id', $packageId, \PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return ['items' => $statement->fetchAll(\PDO::FETCH_ASSOC), 'columns' => $rateColumns];
    }

    /**
     * @param list<string> $preferred
     * @return list<string>
     */
    private function availableColumns(string $table, array $preferred): array
    {
        $driver = (string)$this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $statement = $this->pdo->query('PRAGMA table_info(' . $this->quoteIdentifier($table) . ')');
            $rows = $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
            $available = array_map(static fn (array $row): string => (string)$row['name'], $rows);
        } else {
            $statement = $this->pdo->query('SHOW COLUMNS FROM ' . $this->quoteIdentifier($table));
            $rows = $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
            $available = array_map(static fn (array $row): string => (string)$row['Field'], $rows);
        }

        return array_values(array_intersect($preferred, $available));
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new \InvalidArgumentException('Unsafe SQL identifier.');
        }

        return '`' . $identifier . '`';
    }
}

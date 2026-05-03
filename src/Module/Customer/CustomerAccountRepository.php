<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Customer;

final class CustomerAccountRepository
{
    private const TABLE = 'cc_card';

    private const COLUMNS = [
        'id',
        'username',
        'useralias',
        'firstname',
        'lastname',
        'email',
        'credit',
        'currency',
        'status',
        'activated',
        'id_group',
        'creationdate',
    ];

    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return array{items:list<array<string, mixed>>,columns:list<string>}
     */
    public function search(CustomerSearchCriteria $criteria): array
    {
        $columns = $this->availableColumns(self::TABLE, self::COLUMNS);
        if ($columns === []) {
            throw new \RuntimeException('No supported customer columns were found.');
        }

        $where = [];
        $bindings = [];
        if ($criteria->status !== null && in_array('status', $columns, true)) {
            $where[] = $this->quoteIdentifier('status') . ' = :status';
            $bindings[':status'] = $criteria->status;
        }

        if ($criteria->search !== '') {
            $searchColumns = array_values(array_intersect(['username', 'useralias', 'firstname', 'lastname', 'email'], $columns));
            if ($searchColumns !== []) {
                $parts = [];
                foreach ($searchColumns as $index => $column) {
                    $parameter = ':search' . $index;
                    $parts[] = $this->quoteIdentifier($column) . ' LIKE ' . $parameter;
                    $bindings[$parameter] = '%' . $criteria->search . '%';
                }
                $where[] = '(' . implode(' OR ', $parts) . ')';
            }
        }

        $columnSql = implode(', ', array_map([$this, 'quoteIdentifier'], $columns));
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $orderColumn = in_array('id', $columns, true) ? 'id' : $columns[0];

        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM %s%s ORDER BY %s DESC LIMIT :limit OFFSET :offset',
            $columnSql,
            $this->quoteIdentifier(self::TABLE),
            $whereSql,
            $this->quoteIdentifier($orderColumn)
        ));

        foreach ($bindings as $parameter => $value) {
            $statement->bindValue($parameter, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $criteria->limit, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $criteria->offset, \PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => $statement->fetchAll(\PDO::FETCH_ASSOC),
            'columns' => $columns,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $columns = $this->availableColumns(self::TABLE, self::COLUMNS);
        if ($columns === []) {
            throw new \RuntimeException('No supported customer columns were found.');
        }

        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM %s WHERE %s = :id',
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            $this->quoteIdentifier(self::TABLE),
            $this->quoteIdentifier('id')
        ));
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function updateStatus(int $id, int $status): ?array
    {
        $columns = $this->availableColumns(self::TABLE, self::COLUMNS);
        if (!in_array('status', $columns, true)) {
            throw new \RuntimeException('Customer status column is unavailable.');
        }

        $statement = $this->pdo->prepare(sprintf(
            'UPDATE %s SET %s = :status WHERE %s = :id',
            $this->quoteIdentifier(self::TABLE),
            $this->quoteIdentifier('status'),
            $this->quoteIdentifier('id')
        ));
        $statement->bindValue(':status', $status, \PDO::PARAM_INT);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        if ($statement->rowCount() === 0) {
            return $this->findById($id);
        }

        return $this->findById($id);
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

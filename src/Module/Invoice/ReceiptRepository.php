<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Invoice;

final class ReceiptRepository
{
    private const TABLE = 'cc_receipt';

    private const COLUMNS = [
        'id',
        'id_card',
        'date',
        'title',
        'description',
        'status',
    ];

    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return array{items:list<array<string, mixed>>,columns:list<string>}
     */
    public function search(ReceiptSearchCriteria $criteria): array
    {
        $columns = $this->availableColumns(self::TABLE, self::COLUMNS);
        if ($columns === []) {
            throw new \RuntimeException('No supported receipt columns were found.');
        }

        $where = [];
        $bindings = [];

        if ($criteria->from !== '' && in_array('date', $columns, true)) {
            $where[] = $this->quoteIdentifier('date') . ' >= :from_date';
            $bindings[':from_date'] = $criteria->from;
        }
        if ($criteria->to !== '' && in_array('date', $columns, true)) {
            $where[] = $this->quoteIdentifier('date') . ' < :to_date';
            $bindings[':to_date'] = $criteria->to;
        }
        if ($criteria->customerId !== null && in_array('id_card', $columns, true)) {
            $where[] = $this->quoteIdentifier('id_card') . ' = :customer_id';
            $bindings[':customer_id'] = $criteria->customerId;
        }
        if ($criteria->status !== null && in_array('status', $columns, true)) {
            $where[] = $this->quoteIdentifier('status') . ' = :status';
            $bindings[':status'] = $criteria->status;
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $orderColumn = in_array('date', $columns, true) ? 'date' : $columns[0];

        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM %s%s ORDER BY %s DESC LIMIT :limit OFFSET :offset',
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
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

<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Rate;

final class RatecardRepository
{
    private const TABLE = 'cc_ratecard';

    private const COLUMNS = [
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
     * @return array{items:list<array<string, mixed>>,columns:list<string>}
     */
    public function search(RatecardSearchCriteria $criteria): array
    {
        $columns = $this->availableColumns(self::TABLE, self::COLUMNS);
        if ($columns === []) {
            throw new \RuntimeException('No supported ratecard columns were found.');
        }

        $where = [];
        $bindings = [];

        if ($criteria->prefix !== '' && in_array('dialprefix', $columns, true)) {
            $where[] = $this->quoteIdentifier('dialprefix') . ' LIKE :prefix';
            $bindings[':prefix'] = $criteria->prefix . '%';
        }

        if ($criteria->tariffPlanId !== null && in_array('idtariffplan', $columns, true)) {
            $where[] = $this->quoteIdentifier('idtariffplan') . ' = :tariff_plan_id';
            $bindings[':tariff_plan_id'] = $criteria->tariffPlanId;
        }

        if ($criteria->tag !== '' && in_array('tag', $columns, true)) {
            $where[] = $this->quoteIdentifier('tag') . ' = :tag';
            $bindings[':tag'] = $criteria->tag;
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $orderColumn = in_array('id', $columns, true) ? 'id' : $columns[0];

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

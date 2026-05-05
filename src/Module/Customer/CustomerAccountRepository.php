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
        'uipass',
        'firstname',
        'lastname',
        'email',
        'address',
        'city',
        'state',
        'country',
        'zipcode',
        'phone',
        'credit',
        'currency',
        'status',
        'activated',
        'id_group',
        'creationdate',
        'redial',
        'loginkey',
        'tag',
        'email_notification',
        'company_name',
        'company_website',
        'traffic_target',
    ];

    private const SAFE_COLUMNS = [
        'id',
        'username',
        'useralias',
        'firstname',
        'lastname',
        'email',
        'address',
        'city',
        'state',
        'country',
        'zipcode',
        'phone',
        'credit',
        'currency',
        'status',
        'activated',
        'id_group',
        'creationdate',
        'company_name',
        'company_website',
    ];

    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return array{items:list<array<string, mixed>>,columns:list<string>}
     */
    public function search(CustomerSearchCriteria $criteria): array
    {
        $columns = $this->safeColumns();
        if ($columns === []) {
            throw new \RuntimeException('No supported customer columns were found.');
        }

        [$whereSql, $bindings] = $this->buildSearchFilter($criteria, $columns);
        $where = [];
        $columnSql = implode(', ', array_map([$this, 'quoteIdentifier'], $columns));
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
     * @return array{total:int,active:int,blocked:int}
     */
    public function summary(CustomerSearchCriteria $criteria): array
    {
        $columns = $this->safeColumns();
        if ($columns === []) {
            throw new \RuntimeException('No supported customer columns were found.');
        }

        [$whereSql, $bindings] = $this->buildSearchFilter($criteria, $columns);
        $statement = $this->pdo->prepare(sprintf(
            'SELECT COUNT(*) AS total,
                SUM(CASE WHEN %1$s = 1 THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN %1$s = 0 THEN 1 ELSE 0 END) AS blocked
             FROM %2$s%3$s',
            $this->quoteIdentifier('status'),
            $this->quoteIdentifier(self::TABLE),
            $whereSql
        ));
        foreach ($bindings as $parameter => $value) {
            $statement->bindValue($parameter, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $statement->execute();
        $row = $statement->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'active' => (int)($row['active'] ?? 0),
            'blocked' => (int)($row['blocked'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $columns = $this->safeColumns();
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
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $columns = $this->availableColumns(self::TABLE, self::COLUMNS);
        $row = $this->defaultCreateValues($data);
        $insert = array_intersect_key($row, array_flip($columns));
        unset($insert['id']);

        $names = array_keys($insert);
        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier(self::TABLE),
            implode(', ', array_map([$this, 'quoteIdentifier'], $names)),
            implode(', ', array_map(static fn (string $name): string => ':' . $name, $names))
        ));
        foreach ($insert as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $statement->execute();

        $id = (int)$this->pdo->lastInsertId();
        return $this->findById($id) ?? ['id' => $id] + $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $data): ?array
    {
        $columns = $this->availableColumns(self::TABLE, self::COLUMNS);
        $allowed = array_values(array_intersect([
            'useralias',
            'firstname',
            'lastname',
            'email',
            'address',
            'city',
            'state',
            'country',
            'zipcode',
            'phone',
            'currency',
            'status',
            'activated',
            'id_group',
            'company_name',
            'company_website',
        ], $columns));

        $updates = array_intersect_key($data, array_flip($allowed));
        if ($updates === []) {
            return $this->findById($id);
        }

        $assignments = [];
        foreach (array_keys($updates) as $column) {
            $assignments[] = $this->quoteIdentifier($column) . ' = :' . $column;
        }

        $statement = $this->pdo->prepare(sprintf(
            'UPDATE %s SET %s WHERE %s = :id',
            $this->quoteIdentifier(self::TABLE),
            implode(', ', $assignments),
            $this->quoteIdentifier('id')
        ));
        foreach ($updates as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        return $this->findById($id);
    }

    public function valueExists(string $field, string $value, ?int $excludeId = null): bool
    {
        if (!in_array($field, ['username', 'useralias', 'email'], true)) {
            throw new \InvalidArgumentException('Unsupported unique customer field.');
        }

        $columns = $this->availableColumns(self::TABLE, self::COLUMNS);
        if (!in_array($field, $columns, true)) {
            return false;
        }

        $sql = sprintf(
            'SELECT 1 FROM %s WHERE %s = :value',
            $this->quoteIdentifier(self::TABLE),
            $this->quoteIdentifier($field)
        );
        if ($excludeId !== null && in_array('id', $columns, true)) {
            $sql .= ' AND ' . $this->quoteIdentifier('id') . ' <> :exclude_id';
        }
        $sql .= ' LIMIT 1';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':value', $value);
        if ($excludeId !== null && in_array('id', $columns, true)) {
            $statement->bindValue(':exclude_id', $excludeId, \PDO::PARAM_INT);
        }
        $statement->execute();

        return (bool)$statement->fetchColumn();
    }

    /**
     * @return list<array{id:string,name:string}>
     */
    public function groups(): array
    {
        $driver = (string)$this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $tableExists = false;
        if ($driver === 'sqlite') {
            $tableExists = (bool)$this->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'cc_card_group'")->fetchColumn();
        } else {
            $tableExists = (bool)$this->pdo->query("SHOW TABLES LIKE 'cc_card_group'")->fetchColumn();
        }

        if (!$tableExists) {
            return [];
        }

        $statement = $this->pdo->query('SELECT id, name FROM ' . $this->quoteIdentifier('cc_card_group') . ' ORDER BY name ASC');
        $rows = $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];

        return array_map(static fn (array $row): array => [
            'id' => (string)($row['id'] ?? ''),
            'name' => (string)($row['name'] ?? ''),
        ], $rows);
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

    /**
     * @return list<string>
     */
    private function safeColumns(): array
    {
        return $this->availableColumns(self::TABLE, self::SAFE_COLUMNS);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function defaultCreateValues(array $data): array
    {
        return [
            'username' => $data['username'],
            'useralias' => $data['useralias'],
            'uipass' => $data['uipass'] ?? bin2hex(random_bytes(10)),
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'email' => $data['email'],
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'state' => $data['state'] ?? '',
            'country' => $data['country'] ?? '',
            'zipcode' => $data['zipcode'] ?? '',
            'phone' => $data['phone'] ?? '',
            'credit' => $data['credit'] ?? '0.00000',
            'currency' => $data['currency'] ?? 'USD',
            'status' => $data['status'] ?? 1,
            'activated' => $data['activated'] ?? '1',
            'id_group' => $data['id_group'] ?? 1,
            'creationdate' => $data['creationdate'] ?? gmdate('Y-m-d H:i:s'),
            'redial' => '',
            'loginkey' => '',
            'tag' => '',
            'email_notification' => '',
            'company_name' => $data['company_name'] ?? '',
            'company_website' => $data['company_website'] ?? '',
            'traffic_target' => '',
        ];
    }

    /**
     * @param list<string> $columns
     * @return array{0:string,1:array<string, int|string>}
     */
    private function buildSearchFilter(CustomerSearchCriteria $criteria, array $columns): array
    {
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

        return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $bindings];
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new \InvalidArgumentException('Unsafe SQL identifier.');
        }

        return '`' . $identifier . '`';
    }
}

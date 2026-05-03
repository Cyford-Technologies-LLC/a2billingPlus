<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Rate;

final class TariffRepository
{
    private const DEFINITIONS = [
        'tariff-plans' => [
            'table' => 'cc_tariffplan',
            'columns' => ['id', 'iduser', 'tariffname', 'creationdate', 'description', 'id_trunk', 'idowner', 'dnidprefix', 'calleridprefix'],
            'name' => 'tariffname',
        ],
        'tariff-groups' => [
            'table' => 'cc_tariffgroup',
            'columns' => ['id', 'iduser', 'idtariffplan', 'tariffgroupname', 'lcrtype', 'creationdate', 'removeinterprefix', 'id_cc_package_offer'],
            'name' => 'tariffgroupname',
        ],
    ];

    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function list(string $resource, int $limit, int $offset, string $search = ''): array
    {
        $definition = $this->definition($resource);
        $columns = $this->availableColumns($definition['table'], $definition['columns']);
        $whereSql = '';
        if ($search !== '' && in_array($definition['name'], $columns, true)) {
            $whereSql = ' WHERE ' . $this->quoteIdentifier($definition['name']) . ' LIKE :search';
        }

        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM %s%s ORDER BY %s DESC LIMIT :limit OFFSET :offset',
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            $this->quoteIdentifier($definition['table']),
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
    public function find(string $resource, int $id): ?array
    {
        $definition = $this->definition($resource);
        $columns = $this->availableColumns($definition['table'], $definition['columns']);
        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM %s WHERE %s = :id',
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            $this->quoteIdentifier($definition['table']),
            $this->quoteIdentifier('id')
        ));
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function create(string $resource, array $data): array
    {
        $definition = $this->definition($resource);
        $columns = $this->availableColumns($definition['table'], $definition['columns']);
        $insert = array_intersect_key($this->defaults($resource, $data), array_flip($columns));
        unset($insert['id']);

        $names = array_keys($insert);
        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($definition['table']),
            implode(', ', array_map([$this, 'quoteIdentifier'], $names)),
            implode(', ', array_map(static fn (string $name): string => ':' . $name, $names))
        ));
        foreach ($insert as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $statement->execute();

        $id = (int)$this->pdo->lastInsertId();
        return $this->find($resource, $id) ?? ['id' => $id] + $insert;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>|null
     */
    public function update(string $resource, int $id, array $data): ?array
    {
        $definition = $this->definition($resource);
        $columns = $this->availableColumns($definition['table'], $definition['columns']);
        $updates = array_intersect_key($data, array_flip($columns));
        unset($updates['id'], $updates['creationdate']);
        if ($updates === []) {
            return $this->find($resource, $id);
        }

        $assignments = [];
        foreach (array_keys($updates) as $column) {
            $assignments[] = $this->quoteIdentifier($column) . ' = :' . $column;
        }

        $statement = $this->pdo->prepare(sprintf(
            'UPDATE %s SET %s WHERE %s = :id',
            $this->quoteIdentifier($definition['table']),
            implode(', ', $assignments),
            $this->quoteIdentifier('id')
        ));
        foreach ($updates as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        return $this->find($resource, $id);
    }

    /**
     * @return array{table:string,columns:list<string>,name:string}
     */
    private function definition(string $resource): array
    {
        $definition = self::DEFINITIONS[$resource] ?? null;
        if ($definition === null) {
            throw new \InvalidArgumentException('Unsupported tariff resource.');
        }

        return $definition;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function defaults(string $resource, array $data): array
    {
        if ($resource === 'tariff-plans') {
            return [
                'iduser' => $data['iduser'] ?? 0,
                'tariffname' => $data['tariffname'],
                'creationdate' => gmdate('Y-m-d H:i:s'),
                'description' => $data['description'] ?? null,
                'id_trunk' => $data['id_trunk'] ?? 0,
                'idowner' => $data['idowner'] ?? 0,
                'dnidprefix' => $data['dnidprefix'] ?? 'all',
                'calleridprefix' => $data['calleridprefix'] ?? 'all',
            ];
        }

        return [
            'iduser' => $data['iduser'] ?? 0,
            'idtariffplan' => $data['idtariffplan'],
            'tariffgroupname' => $data['tariffgroupname'],
            'lcrtype' => $data['lcrtype'] ?? 0,
            'creationdate' => gmdate('Y-m-d H:i:s'),
            'removeinterprefix' => $data['removeinterprefix'] ?? 0,
            'id_cc_package_offer' => $data['id_cc_package_offer'] ?? -1,
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

        $columns = array_values(array_intersect($preferred, $available));
        if ($columns === []) {
            throw new \RuntimeException('No supported tariff columns were found.');
        }

        return $columns;
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new \InvalidArgumentException('Unsafe SQL identifier.');
        }

        return '`' . $identifier . '`';
    }
}

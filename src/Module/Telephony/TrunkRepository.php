<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Telephony;

final class TrunkRepository
{
    private const TABLE = 'cc_trunk';
    private const COLUMNS = [
        'id_trunk',
        'trunkcode',
        'trunkprefix',
        'providertech',
        'providerip',
        'removeprefix',
        'creationdate',
        'failover_trunk',
        'addparameter',
        'id_provider',
        'inuse',
        'maxuse',
        'status',
        'if_max_use',
    ];

    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function list(int $limit, int $offset, ?int $status = null): array
    {
        $columns = $this->availableColumns();
        $whereSql = '';
        if ($status !== null && in_array('status', $columns, true)) {
            $whereSql = ' WHERE ' . $this->quoteIdentifier('status') . ' = :status';
        }

        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM %s%s ORDER BY %s DESC LIMIT :limit OFFSET :offset',
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            $this->quoteIdentifier(self::TABLE),
            $whereSql,
            $this->quoteIdentifier(in_array('id_trunk', $columns, true) ? 'id_trunk' : $columns[0])
        ));
        if ($status !== null && in_array('status', $columns, true)) {
            $statement->bindValue(':status', $status, \PDO::PARAM_INT);
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
        $columns = $this->availableColumns();
        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM %s WHERE %s = :id',
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            $this->quoteIdentifier(self::TABLE),
            $this->quoteIdentifier('id_trunk')
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
    public function create(array $data): array
    {
        $columns = $this->availableColumns();
        $insert = array_intersect_key($this->defaults($data), array_flip($columns));
        unset($insert['id_trunk']);

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
        return $this->find($id) ?? ['id_trunk' => $id] + $insert;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>|null
     */
    public function update(int $id, array $data): ?array
    {
        $columns = $this->availableColumns();
        $updates = array_intersect_key($data, array_flip($columns));
        unset($updates['id_trunk'], $updates['creationdate']);
        if ($updates === []) {
            return $this->find($id);
        }

        $assignments = [];
        foreach (array_keys($updates) as $column) {
            $assignments[] = $this->quoteIdentifier($column) . ' = :' . $column;
        }
        $statement = $this->pdo->prepare(sprintf(
            'UPDATE %s SET %s WHERE %s = :id',
            $this->quoteIdentifier(self::TABLE),
            implode(', ', $assignments),
            $this->quoteIdentifier('id_trunk')
        ));
        foreach ($updates as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        return $this->find($id);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function defaults(array $data): array
    {
        return [
            'trunkcode' => $data['trunkcode'],
            'trunkprefix' => $data['trunkprefix'] ?? '',
            'providertech' => $data['providertech'],
            'providerip' => $data['providerip'],
            'removeprefix' => $data['removeprefix'] ?? '',
            'creationdate' => gmdate('Y-m-d H:i:s'),
            'failover_trunk' => $data['failover_trunk'] ?? 0,
            'addparameter' => $data['addparameter'] ?? '',
            'id_provider' => $data['id_provider'] ?? null,
            'inuse' => $data['inuse'] ?? 0,
            'maxuse' => $data['maxuse'] ?? -1,
            'status' => $data['status'] ?? 1,
            'if_max_use' => $data['if_max_use'] ?? 0,
        ];
    }

    /**
     * @return list<string>
     */
    private function availableColumns(): array
    {
        $driver = (string)$this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $statement = $this->pdo->query('PRAGMA table_info(' . $this->quoteIdentifier(self::TABLE) . ')');
            $rows = $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
            $available = array_map(static fn (array $row): string => (string)$row['name'], $rows);
        } else {
            $statement = $this->pdo->query('SHOW COLUMNS FROM ' . $this->quoteIdentifier(self::TABLE));
            $rows = $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
            $available = array_map(static fn (array $row): string => (string)$row['Field'], $rows);
        }

        $columns = array_values(array_intersect(self::COLUMNS, $available));
        if ($columns === []) {
            throw new \RuntimeException('No supported trunk columns were found.');
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

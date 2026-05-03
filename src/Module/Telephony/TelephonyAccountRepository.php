<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Telephony;

final class TelephonyAccountRepository
{
    private const TABLES = ['sip' => 'cc_sip_buddies', 'iax' => 'cc_iax_buddies'];
    private const READ_COLUMNS = [
        'id',
        'id_cc_card',
        'name',
        'accountcode',
        'regexten',
        'callerid',
        'context',
        'host',
        'port',
        'qualify',
        'type',
        'username',
        'disallow',
        'allow',
        'regseconds',
        'ipaddr',
        'trunk',
        'defaultuser',
        'cid_number',
    ];
    private const WRITE_COLUMNS = [
        'id_cc_card',
        'name',
        'accountcode',
        'regexten',
        'callerid',
        'context',
        'host',
        'port',
        'qualify',
        'secret',
        'type',
        'username',
        'disallow',
        'allow',
        'trunk',
        'defaultuser',
        'cid_number',
    ];

    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function list(string $technology, int $limit, int $offset, ?int $customerId = null): array
    {
        $table = $this->table($technology);
        $columns = $this->availableColumns($table, self::READ_COLUMNS);
        $whereSql = '';
        if ($customerId !== null && in_array('id_cc_card', $columns, true)) {
            $whereSql = ' WHERE ' . $this->quoteIdentifier('id_cc_card') . ' = :customer_id';
        }

        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM %s%s ORDER BY %s DESC LIMIT :limit OFFSET :offset',
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            $this->quoteIdentifier($table),
            $whereSql,
            $this->quoteIdentifier('id')
        ));
        if ($customerId !== null && in_array('id_cc_card', $columns, true)) {
            $statement->bindValue(':customer_id', $customerId, \PDO::PARAM_INT);
        }
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return ['items' => $statement->fetchAll(\PDO::FETCH_ASSOC), 'columns' => $columns];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(string $technology, int $id): ?array
    {
        $table = $this->table($technology);
        $columns = $this->availableColumns($table, self::READ_COLUMNS);
        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM %s WHERE %s = :id',
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            $this->quoteIdentifier($table),
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
    public function create(string $technology, array $data): array
    {
        $table = $this->table($technology);
        $columns = $this->availableColumns($table, self::WRITE_COLUMNS);
        $insert = array_intersect_key($this->defaults($data), array_flip($columns));
        $names = array_keys($insert);

        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', array_map([$this, 'quoteIdentifier'], $names)),
            implode(', ', array_map(static fn (string $name): string => ':' . $name, $names))
        ));
        foreach ($insert as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $statement->execute();

        return $this->find($technology, (int)$this->pdo->lastInsertId()) ?? $insert;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>|null
     */
    public function update(string $technology, int $id, array $data): ?array
    {
        $table = $this->table($technology);
        $columns = $this->availableColumns($table, self::WRITE_COLUMNS);
        $updates = array_intersect_key($data, array_flip($columns));
        unset($updates['id']);
        if ($updates === []) {
            return $this->find($technology, $id);
        }

        $assignments = [];
        foreach (array_keys($updates) as $column) {
            $assignments[] = $this->quoteIdentifier($column) . ' = :' . $column;
        }
        $statement = $this->pdo->prepare(sprintf(
            'UPDATE %s SET %s WHERE %s = :id',
            $this->quoteIdentifier($table),
            implode(', ', $assignments),
            $this->quoteIdentifier('id')
        ));
        foreach ($updates as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        return $this->find($technology, $id);
    }

    public function table(string $technology): string
    {
        $key = strtolower($technology);
        if (!isset(self::TABLES[$key])) {
            throw new \InvalidArgumentException('Unsupported telephony account technology.');
        }

        return self::TABLES[$key];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function defaults(array $data): array
    {
        $username = (string)$data['username'];
        return [
            'id_cc_card' => $data['id_cc_card'],
            'name' => $data['name'] ?? $username,
            'accountcode' => $data['accountcode'] ?? $username,
            'regexten' => $data['regexten'] ?? $username,
            'callerid' => $data['callerid'] ?? $username,
            'context' => $data['context'] ?? 'a2billing',
            'host' => $data['host'] ?? 'dynamic',
            'port' => $data['port'] ?? '',
            'qualify' => $data['qualify'] ?? 'yes',
            'secret' => $data['secret'] ?? '',
            'type' => $data['type'] ?? 'friend',
            'username' => $username,
            'disallow' => $data['disallow'] ?? 'all',
            'allow' => $data['allow'] ?? 'ulaw,alaw',
            'trunk' => $data['trunk'] ?? 'no',
            'defaultuser' => $data['defaultuser'] ?? $username,
            'cid_number' => $data['cid_number'] ?? '',
        ];
    }

    /**
     * @return list<string>
     */
    private function availableColumns(string $table, array $expected): array
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

        $columns = array_values(array_intersect($expected, $available));
        if ($columns === []) {
            throw new \RuntimeException('No supported telephony account columns were found.');
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

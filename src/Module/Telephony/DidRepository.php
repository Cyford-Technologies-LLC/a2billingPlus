<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Telephony;

final class DidRepository
{
    private const DID_COLUMNS = [
        'id',
        'id_cc_didgroup',
        'id_cc_country',
        'activated',
        'reserved',
        'iduser',
        'did',
        'creationdate',
        'startingdate',
        'expirationdate',
        'description',
        'billingtype',
        'fixrate',
        'connection_charge',
        'selling_rate',
        'max_concurrent',
    ];

    private const DESTINATION_COLUMNS = [
        'id',
        'destination',
        'priority',
        'id_cc_card',
        'id_cc_did',
        'creationdate',
        'activated',
        'secondusedreal',
        'voip_call',
        'validated',
    ];

    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function list(int $limit, int $offset, ?int $customerId = null, ?int $reserved = null, ?int $activated = null): array
    {
        $columns = $this->availableColumns('cc_did', self::DID_COLUMNS);
        $filters = [];
        if ($customerId !== null && in_array('iduser', $columns, true)) {
            $filters['iduser'] = $customerId;
        }
        if ($reserved !== null && in_array('reserved', $columns, true)) {
            $filters['reserved'] = $reserved;
        }
        if ($activated !== null && in_array('activated', $columns, true)) {
            $filters['activated'] = $activated;
        }

        $where = [];
        foreach (array_keys($filters) as $column) {
            $where[] = $this->quoteIdentifier($column) . ' = :' . $column;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM %s%s ORDER BY %s DESC LIMIT :limit OFFSET :offset',
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            $this->quoteIdentifier('cc_did'),
            $whereSql,
            $this->quoteIdentifier('id')
        ));
        foreach ($filters as $column => $value) {
            $statement->bindValue(':' . $column, $value, \PDO::PARAM_INT);
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
        $columns = $this->availableColumns('cc_did', self::DID_COLUMNS);
        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM %s WHERE %s = :id',
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            $this->quoteIdentifier('cc_did'),
            $this->quoteIdentifier('id')
        ));
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function assign(int $didId, int $customerId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->prepare(
            'UPDATE ' . $this->quoteIdentifier('cc_did') . '
             SET ' . $this->quoteIdentifier('iduser') . ' = :customer_id, ' . $this->quoteIdentifier('reserved') . ' = 1
             WHERE ' . $this->quoteIdentifier('id') . ' = :did_id'
        )->execute([':customer_id' => $customerId, ':did_id' => $didId]);

        $this->pdo->prepare(
            'UPDATE ' . $this->quoteIdentifier('cc_did_use') . '
             SET ' . $this->quoteIdentifier('releasedate') . ' = :releasedate
             WHERE ' . $this->quoteIdentifier('id_did') . ' = :did_id AND ' . $this->quoteIdentifier('activated') . ' = 1'
        )->execute([':releasedate' => $now, ':did_id' => $didId]);

        $this->pdo->prepare(
            'INSERT INTO ' . $this->quoteIdentifier('cc_did_use') . '
                (' . $this->quoteIdentifier('activated') . ', ' . $this->quoteIdentifier('id_cc_card') . ', ' . $this->quoteIdentifier('id_did') . ', ' . $this->quoteIdentifier('month_payed') . ', ' . $this->quoteIdentifier('reservationdate') . ')
             VALUES (1, :customer_id, :did_id, 1, :reservationdate)'
        )->execute([':customer_id' => $customerId, ':did_id' => $didId, ':reservationdate' => $now]);
    }

    public function release(int $didId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->prepare(
            'UPDATE ' . $this->quoteIdentifier('cc_did') . '
             SET ' . $this->quoteIdentifier('iduser') . ' = 0, ' . $this->quoteIdentifier('reserved') . ' = 0
             WHERE ' . $this->quoteIdentifier('id') . ' = :did_id'
        )->execute([':did_id' => $didId]);

        $this->pdo->prepare(
            'UPDATE ' . $this->quoteIdentifier('cc_did_use') . '
             SET ' . $this->quoteIdentifier('releasedate') . ' = :releasedate
             WHERE ' . $this->quoteIdentifier('id_did') . ' = :did_id AND ' . $this->quoteIdentifier('activated') . ' = 1'
        )->execute([':releasedate' => $now, ':did_id' => $didId]);

        $this->pdo->prepare(
            'INSERT INTO ' . $this->quoteIdentifier('cc_did_use') . '
                (' . $this->quoteIdentifier('activated') . ', ' . $this->quoteIdentifier('id_did') . ', ' . $this->quoteIdentifier('reservationdate') . ')
             VALUES (0, :did_id, :reservationdate)'
        )->execute([':did_id' => $didId, ':reservationdate' => $now]);

        $this->pdo->prepare(
            'DELETE FROM ' . $this->quoteIdentifier('cc_did_destination') . '
             WHERE ' . $this->quoteIdentifier('id_cc_did') . ' = :did_id'
        )->execute([':did_id' => $didId]);
    }

    /**
     * @param list<array{destination:string,priority:int,voip_call:int,activated:int,validated:int}> $destinations
     */
    public function replaceDestinations(int $didId, int $customerId, array $destinations): void
    {
        $this->pdo->prepare(
            'DELETE FROM ' . $this->quoteIdentifier('cc_did_destination') . '
             WHERE ' . $this->quoteIdentifier('id_cc_did') . ' = :did_id'
        )->execute([':did_id' => $didId]);

        $statement = $this->pdo->prepare(
            'INSERT INTO ' . $this->quoteIdentifier('cc_did_destination') . '
                (' . $this->quoteIdentifier('destination') . ', ' . $this->quoteIdentifier('priority') . ', ' . $this->quoteIdentifier('id_cc_card') . ', ' . $this->quoteIdentifier('id_cc_did') . ', ' . $this->quoteIdentifier('creationdate') . ', ' . $this->quoteIdentifier('activated') . ', ' . $this->quoteIdentifier('voip_call') . ', ' . $this->quoteIdentifier('validated') . ')
             VALUES (:destination, :priority, :customer_id, :did_id, :creationdate, :activated, :voip_call, :validated)'
        );

        $now = gmdate('Y-m-d H:i:s');
        foreach ($destinations as $destination) {
            $statement->execute([
                ':destination' => $destination['destination'],
                ':priority' => $destination['priority'],
                ':customer_id' => $customerId,
                ':did_id' => $didId,
                ':creationdate' => $now,
                ':activated' => $destination['activated'],
                ':voip_call' => $destination['voip_call'],
                ':validated' => $destination['validated'],
            ]);
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function destinations(int $didId): array
    {
        $columns = $this->availableColumns('cc_did_destination', self::DESTINATION_COLUMNS);
        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM %s WHERE %s = :did_id ORDER BY %s ASC, %s ASC',
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            $this->quoteIdentifier('cc_did_destination'),
            $this->quoteIdentifier('id_cc_did'),
            $this->quoteIdentifier(in_array('priority', $columns, true) ? 'priority' : 'id'),
            $this->quoteIdentifier('id')
        ));
        $statement->bindValue(':did_id', $didId, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
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
            throw new \RuntimeException('No supported DID columns were found for ' . $table . '.');
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

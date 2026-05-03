<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

final class ResourceListRepository
{
    /**
     * @param array<string, array{table:string,columns:list<string>,order:string}> $resources
     */
    public function __construct(
        private readonly \PDO $pdo,
        private readonly array $resources = self::DEFAULT_RESOURCES
    ) {
    }

    private const DEFAULT_RESOURCES = [
        'customers' => [
            'table' => 'cc_card',
            'columns' => ['id', 'username', 'useralias', 'firstname', 'lastname', 'email', 'credit', 'currency', 'status'],
            'order' => 'id',
        ],
        'balances' => [
            'table' => 'cc_card',
            'columns' => ['id', 'username', 'credit', 'currency', 'status'],
            'order' => 'id',
        ],
        'rates' => [
            'table' => 'cc_ratecard',
            'columns' => ['id', 'idtariffplan', 'dialprefix', 'destination', 'buyrate', 'rateinitial', 'initblock', 'billingblock', 'tag'],
            'order' => 'id',
        ],
        'payments' => [
            'table' => 'cc_logpayment',
            'columns' => ['id', 'date', 'payment', 'card_id', 'reseller_id', 'description', 'added_refill'],
            'order' => 'id',
        ],
        'cdrs' => [
            'table' => 'cc_call',
            'columns' => ['id', 'sessionid', 'uniqueid', 'starttime', 'stoptime', 'sessiontime', 'calledstation', 'sessionbill', 'id_card'],
            'order' => 'id',
        ],
        'providers' => [
            'table' => 'cc_provider',
            'columns' => ['id', 'provider_name', 'description', 'creationdate'],
            'order' => 'id',
        ],
        'invoices' => [
            'table' => 'cc_invoice',
            'columns' => ['id', 'id_card', 'title', 'reference', 'date', 'paid_status', 'status', 'price', 'vat'],
            'order' => 'id',
        ],
    ];

    /**
     * @return array{items:list<array<string, mixed>>,columns:list<string>}
     */
    public function list(string $resource, int $limit, int $offset): array
    {
        $definition = $this->resources[$resource] ?? null;
        if ($definition === null) {
            throw new \InvalidArgumentException('Unknown API resource.');
        }

        $columns = $this->availableColumns($definition['table'], $definition['columns']);
        if ($columns === []) {
            throw new \RuntimeException(sprintf('No supported columns were found for %s.', $definition['table']));
        }

        $columnSql = implode(', ', array_map([$this, 'quoteIdentifier'], $columns));
        $tableSql = $this->quoteIdentifier($definition['table']);
        $orderSql = in_array($definition['order'], $columns, true) ? $this->quoteIdentifier($definition['order']) : $this->quoteIdentifier($columns[0]);

        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM %s ORDER BY %s DESC LIMIT :limit OFFSET :offset',
            $columnSql,
            $tableSql,
            $orderSql
        ));
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
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

<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Payment;

final class CustomerPaymentPortalService
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly PaymentIntentService $intentService
    ) {
    }

    /**
     * @return array{payments:array{items:list<array<string,mixed>>,columns:list<string>},documents:array{invoices:list<array<string,mixed>>,receipts:list<array<string,mixed>>}}
     */
    public function history(int $customerId, int $limit, int $offset, string $from = '', string $to = ''): array
    {
        $payments = (new PaymentLedgerService(new PaymentLedgerRepository($this->pdo)))
            ->search(new PaymentSearchCriteria($limit, $offset, $from, $to, $customerId));

        return [
            'payments' => $payments,
            'documents' => [
                'invoices' => $this->documents('cc_invoice', $customerId, $limit),
                'receipts' => $this->documents('cc_receipt', $customerId, $limit),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function createHostedIntent(int $customerId, array $payload): PaymentIntentResult
    {
        $payload['customer_id'] = $customerId;

        return $this->intentService->createStripeIntent($payload);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function documents(string $table, int $customerId, int $limit): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }

        $columns = $this->availableColumns($table, ['id', 'id_card', 'title', 'reference', 'date', 'status', 'paid_status']);
        if (!in_array('id_card', $columns, true)) {
            return [];
        }

        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM %s WHERE %s = :customer_id ORDER BY %s DESC LIMIT :limit',
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            $this->quoteIdentifier($table),
            $this->quoteIdentifier('id_card'),
            $this->quoteIdentifier(in_array('date', $columns, true) ? 'date' : 'id')
        ));
        $statement->bindValue(':customer_id', $customerId, \PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function tableExists(string $table): bool
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table");
            $statement->execute([':table' => $table]);
            return $statement->fetchColumn() !== false;
        }

        $statement = $this->pdo->prepare('SHOW TABLES LIKE :table');
        $statement->execute([':table' => $table]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param list<string> $preferred
     * @return list<string>
     */
    private function availableColumns(string $table, array $preferred): array
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
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

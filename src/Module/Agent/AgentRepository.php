<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Agent;

final class AgentRepository
{
    private const AGENT_COLUMNS = ['id', 'datecreation', 'active', 'login', 'language', 'credit', 'currency', 'commission', 'vat', 'perms', 'lastname', 'firstname', 'phone', 'email', 'company', 'com_balance', 'threshold_remittance'];
    private const CUSTOMER_COLUMNS = ['id', 'username', 'useralias', 'firstname', 'lastname', 'credit', 'currency', 'status', 'id_group', 'creationdate', 'email'];
    private const COMMISSION_COLUMNS = ['id', 'id_payment', 'id_card', 'date', 'amount', 'description', 'id_agent', 'commission_type', 'commission_percent'];

    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function list(int $limit, int $offset, ?string $active = null): array
    {
        $columns = $this->availableColumns('cc_agent', self::AGENT_COLUMNS);
        $where = $active === null ? '' : ' WHERE active = :active';
        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM cc_agent%s ORDER BY id DESC LIMIT :limit OFFSET :offset',
            implode(', ', $columns),
            $where
        ));
        if ($active !== null) {
            $statement->bindValue(':active', $active);
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
        $columns = $this->availableColumns('cc_agent', self::AGENT_COLUMNS);
        $statement = $this->pdo->prepare(sprintf('SELECT %s FROM cc_agent WHERE id = :id', implode(', ', $columns)));
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function commissions(int $agentId, int $limit): array
    {
        if (!$this->tableExists('cc_agent_commission')) {
            return [];
        }
        $columns = $this->availableColumns('cc_agent_commission', self::COMMISSION_COLUMNS);
        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM cc_agent_commission WHERE id_agent = :agent_id ORDER BY date DESC LIMIT :limit',
            implode(', ', $columns)
        ));
        $statement->bindValue(':agent_id', $agentId, \PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function customers(int $agentId, int $limit, int $offset): array
    {
        $columns = $this->availableColumns('cc_card', self::CUSTOMER_COLUMNS);
        $select = array_map(static fn (string $column): string => 'c.' . $column, $columns);
        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM cc_card c INNER JOIN cc_card_group g ON g.id = c.id_group WHERE g.id_agent = :agent_id ORDER BY c.id DESC LIMIT :limit OFFSET :offset',
            implode(', ', $select)
        ));
        $statement->bindValue(':agent_id', $agentId, \PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return ['items' => $statement->fetchAll(\PDO::FETCH_ASSOC), 'columns' => $columns];
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
            $statement = $this->pdo->query('PRAGMA table_info(' . $table . ')');
            $rows = $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
            $available = array_map(static fn (array $row): string => (string)$row['name'], $rows);
        } else {
            $statement = $this->pdo->query('SHOW COLUMNS FROM ' . $table);
            $rows = $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
            $available = array_map(static fn (array $row): string => (string)$row['Field'], $rows);
        }

        return array_values(array_intersect($preferred, $available));
    }
}

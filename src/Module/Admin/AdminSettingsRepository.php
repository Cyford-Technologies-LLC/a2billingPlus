<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Admin;

final class AdminSettingsRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @param list<string> $keys
     * @return list<array<string,mixed>>
     */
    public function list(array $keys = []): array
    {
        $sql = 'SELECT id, config_title, config_key, config_value, config_description, config_valuetype, config_listvalues, config_group_title FROM cc_config';
        $params = [];
        if ($keys !== []) {
            $placeholders = implode(', ', array_fill(0, count($keys), '?'));
            $sql .= ' WHERE config_key IN (' . $placeholders . ')';
            $params = $keys;
        }
        $sql .= ' ORDER BY config_group_title ASC, config_key ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(string $key): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, config_title, config_key, config_value, config_description, config_valuetype, config_listvalues, config_group_title
             FROM cc_config WHERE config_key = ? LIMIT 1'
        );
        $statement->execute([$key]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function update(string $key, string $value): ?array
    {
        $statement = $this->pdo->prepare('UPDATE cc_config SET config_value = ? WHERE config_key = ?');
        $statement->execute([$value, $key]);

        return $this->find($key);
    }
}

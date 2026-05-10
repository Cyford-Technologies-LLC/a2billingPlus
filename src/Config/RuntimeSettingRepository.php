<?php

declare(strict_types=1);

namespace A2BillingPlus\Config;

final class RuntimeSettingRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS cc_a2bp_runtime_settings (
                setting_key VARCHAR(191) NOT NULL,
                setting_value TEXT NOT NULL,
                is_secret TINYINT(1) NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $this->ensureTable();
        $statement = $this->pdo->query('SELECT setting_key, setting_value FROM cc_a2bp_runtime_settings');
        $values = [];
        while (($row = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $values[(string)$row['setting_key']] = (string)$row['setting_value'];
        }

        return $values;
    }

    /**
     * @param array<string, string> $values
     * @param list<string> $secretKeys
     */
    public function saveMany(array $values, array $secretKeys = []): void
    {
        $this->ensureTable();
        $statement = $this->pdo->prepare(
            'INSERT INTO cc_a2bp_runtime_settings (setting_key, setting_value, is_secret, updated_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_secret = VALUES(is_secret), updated_at = VALUES(updated_at)'
        );
        $now = gmdate('Y-m-d H:i:s');
        foreach ($values as $key => $value) {
            if (!preg_match('/^[A-Z0-9_]+$/', $key)) {
                continue;
            }
            $statement->execute([$key, $value, in_array($key, $secretKeys, true) ? 1 : 0, $now]);
        }
    }
}

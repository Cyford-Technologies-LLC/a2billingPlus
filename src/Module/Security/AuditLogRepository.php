<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Security;

final class AuditLogRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
        $this->ensureTable();
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function record(string $actor, string $action, string $entityType, string $entityId, array $metadata = []): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO cc_a2bp_audit_log (actor, action, entity_type, entity_id, metadata_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $actor,
            $action,
            $entityType,
            $entityId,
            json_encode($metadata, JSON_UNESCAPED_SLASHES),
            gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function ensureTable(): void
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS cc_a2bp_audit_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    actor TEXT NOT NULL,
                    action TEXT NOT NULL,
                    entity_type TEXT NOT NULL,
                    entity_id TEXT NOT NULL,
                    metadata_json TEXT NOT NULL,
                    created_at TEXT NOT NULL
                )'
            );
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS cc_a2bp_audit_log (
                id BIGINT NOT NULL AUTO_INCREMENT,
                actor VARCHAR(128) NOT NULL,
                action VARCHAR(128) NOT NULL,
                entity_type VARCHAR(64) NOT NULL,
                entity_id VARCHAR(128) NOT NULL,
                metadata_json JSON NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_a2bp_audit_entity_created (entity_type, entity_id, created_at),
                KEY idx_a2bp_audit_actor_created (actor, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}

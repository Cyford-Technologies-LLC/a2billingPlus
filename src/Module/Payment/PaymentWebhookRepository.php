<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Payment;

final class PaymentWebhookRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
        $this->ensureTable();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function record(
        string $provider,
        string $eventType,
        string $eventId,
        array $payload,
        bool $processed,
        string $message
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO cc_a2bp_payment_webhook_event
                (provider, event_type, event_id, payload_json, processed, message, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $provider,
            $eventType,
            $eventId,
            json_encode($payload, JSON_UNESCAPED_SLASHES),
            $processed ? 1 : 0,
            $message,
            gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function hasEvent(string $provider, string $eventId): bool
    {
        if ($eventId === '') {
            return false;
        }

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM cc_a2bp_payment_webhook_event WHERE provider = ? AND event_id = ?'
        );
        $statement->execute([$provider, $eventId]);

        return (int)$statement->fetchColumn() > 0;
    }

    private function ensureTable(): void
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS cc_a2bp_payment_webhook_event (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    provider TEXT NOT NULL,
                    event_type TEXT NOT NULL,
                    event_id TEXT NOT NULL,
                    payload_json TEXT NOT NULL,
                    processed INTEGER NOT NULL DEFAULT 0,
                    message TEXT NOT NULL,
                    created_at TEXT NOT NULL
                )'
            );
            $this->pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uniq_payment_webhook_event ON cc_a2bp_payment_webhook_event (provider, event_id)');
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS cc_a2bp_payment_webhook_event (
                id BIGINT NOT NULL AUTO_INCREMENT,
                provider VARCHAR(64) NOT NULL,
                event_type VARCHAR(128) NOT NULL,
                event_id VARCHAR(128) NOT NULL,
                payload_json TEXT NOT NULL,
                processed TINYINT(1) NOT NULL DEFAULT 0,
                message VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_payment_webhook_event (provider, event_id),
                KEY idx_payment_webhook_event_type_created (provider, event_type, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}

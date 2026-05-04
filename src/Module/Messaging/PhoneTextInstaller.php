<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Messaging;

/**
 * Installs the Phone & Text integration schema.
 * Run once during deployment via: php bin/install-phone-text.php
 */
final class PhoneTextInstaller
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return array{success:bool, steps:list<string>}
     */
    public function install(): array
    {
        $steps = [];

        $this->runStep($steps, 'Create cc_sms_message table', function (): void {
            $this->pdo->exec($this->smsMigration());
        });

        $this->runStep($steps, 'Create cc_did_assignment table', function (): void {
            $this->pdo->exec($this->didAssignmentMigration());
        });

        $this->runStep($steps, 'Ensure cc_vectavoip_did_inventory table', function (): void {
            $this->pdo->exec($this->didInventoryMigration());
        });

        return ['success' => true, 'steps' => $steps];
    }

    /**
     * @return array{success:bool, steps:list<string>}
     */
    public function uninstall(): array
    {
        $steps = [];

        $this->runStep($steps, 'Drop cc_sms_message table', function (): void {
            $this->pdo->exec('DROP TABLE IF EXISTS cc_sms_message');
        });

        $this->runStep($steps, 'Drop cc_did_assignment table', function (): void {
            $this->pdo->exec('DROP TABLE IF EXISTS cc_did_assignment');
        });

        return ['success' => true, 'steps' => $steps];
    }

    /**
     * @param list<string> $steps
     */
    private function runStep(array &$steps, string $label, callable $fn): void
    {
        try {
            $fn();
            $steps[] = "[OK] {$label}";
        } catch (\Throwable $e) {
            $steps[] = "[WARN] {$label}: " . $e->getMessage();
        }
    }

    private function smsMigration(): string
    {
        return "CREATE TABLE IF NOT EXISTS cc_sms_message (
            id BIGINT NOT NULL AUTO_INCREMENT,
            customer_id BIGINT NOT NULL,
            from_number VARCHAR(32) NOT NULL DEFAULT '',
            to_number VARCHAR(32) NOT NULL DEFAULT '',
            body TEXT NOT NULL,
            direction ENUM('outbound','inbound') NOT NULL DEFAULT 'outbound',
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            gateway_message_id VARCHAR(128) NOT NULL DEFAULT '',
            error_message VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_sms_message_customer_id (customer_id),
            KEY idx_sms_message_from_number (from_number),
            KEY idx_sms_message_to_number (to_number),
            KEY idx_sms_message_status (status),
            KEY idx_sms_message_direction (direction)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    private function didAssignmentMigration(): string
    {
        return "CREATE TABLE IF NOT EXISTS cc_did_assignment (
            id BIGINT NOT NULL AUTO_INCREMENT,
            customer_id BIGINT NOT NULL,
            did VARCHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            sms_enabled TINYINT(1) NOT NULL DEFAULT 1,
            voice_enabled TINYINT(1) NOT NULL DEFAULT 1,
            provider_reference VARCHAR(128) NOT NULL DEFAULT '',
            webhook_url VARCHAR(512) NOT NULL DEFAULT '',
            assigned_at DATETIME NOT NULL,
            released_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_did_assignment_did_status (did, status),
            KEY idx_did_assignment_customer_id (customer_id),
            KEY idx_did_assignment_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    private function didInventoryMigration(): string
    {
        return "CREATE TABLE IF NOT EXISTS cc_vectavoip_did_inventory (
            id BIGINT NOT NULL AUTO_INCREMENT,
            did VARCHAR(64) NOT NULL,
            country VARCHAR(64) NOT NULL DEFAULT '',
            region VARCHAR(64) NOT NULL DEFAULT '',
            monthly_rate DECIMAL(15,5) NOT NULL DEFAULT 0.00000,
            setup_rate DECIMAL(15,5) NOT NULL DEFAULT 0.00000,
            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            status VARCHAR(32) NOT NULL DEFAULT 'available',
            provider_reference VARCHAR(128) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_vectavoip_did_inventory_did (did),
            KEY idx_vectavoip_did_inventory_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }
}

<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\Twilio;

use A2BillingPlus\Module\Telephony\CoreTrunkProjectionService;

final class TwilioProvisioningService
{
    public function __construct(private readonly \PDO $pdo)
    {
        $this->ensureInventoryTable();
    }

    /**
     * @param list<array<string, mixed>> $numbers
     * @return array{success:bool, upserted:int, message:string}
     */
    public function syncOwnedNumbers(array $numbers): array
    {
        $upserted = 0;
        foreach ($numbers as $number) {
            $phoneNumber = $this->stringValue($number, 'phone_number');
            if ($phoneNumber === '') {
                continue;
            }

            $this->upsertInventory([
                'did' => $phoneNumber,
                'country' => $this->stringValue($number, 'country_code'),
                'region' => '',
                'monthly_rate' => '0.00000',
                'setup_rate' => '0.00000',
                'currency' => 'USD',
                'status' => 'available',
                'provider_code' => 'twilio',
                'provider_reference' => $this->stringValue($number, 'sid'),
                'provider_trunk_reference' => $this->stringValue($number, 'trunk_sid'),
                'provider_trunk_name' => $this->stringValue($number, 'trunk_name'),
                'order_reference' => '',
            ]);
            $upserted++;
        }

        return [
            'success' => true,
            'upserted' => $upserted,
            'message' => 'Twilio owned numbers synchronized into local inventory.',
        ];
    }

    /**
     * @param array<string, mixed> $trunk
     * @return array{success:bool, provider_id:int, trunk_id:int, message:string}
     */
    public function materializeTrunk(array $trunk): array
    {
        $providerId = $this->ensureProvider();
        $trunkId = $this->ensureLocalTrunk($providerId, $trunk);

        return [
            'success' => true,
            'provider_id' => $providerId,
            'trunk_id' => $trunkId,
            'message' => 'Twilio trunk was created and linked locally.',
        ];
    }

    private function ensureProvider(): int
    {
        return (new CoreTrunkProjectionService($this->pdo))->ensureProvider(
            'Twilio',
            'Twilio automatically provisioned provider.'
        );
    }

    /**
     * @param array<string, mixed> $trunk
     */
    private function ensureLocalTrunk(int $providerId, array $trunk): int
    {
        $trunkSid = $this->stringValue($trunk, 'sid');
        $friendlyName = $this->stringValue($trunk, 'friendly_name', 'Twilio Trunk');
        $parameter = $trunkSid !== '' ? 'twilio_trunk:' . $trunkSid : 'twilio_trunk';
        $trunkCode = $this->trunkCodeFor($friendlyName, $trunkSid);

        return (new CoreTrunkProjectionService($this->pdo))->upsertBySyncKey([
            'provider_id' => $providerId,
            'trunkcode' => $trunkCode,
            'providertech' => $this->trunkTechnology(),
            'providerip' => $this->trunkHostFor($trunk),
            'addparameter' => $parameter,
            'maxuse' => -1,
            'status' => 1,
            'if_max_use' => 0,
        ]);
    }

    /**
     * @param array<string, string> $did
     */
    private function upsertInventory(array $did): void
    {
        $columns = [
            'did',
            'country',
            'region',
            'monthly_rate',
            'setup_rate',
            'currency',
            'status',
            'provider_code',
            'provider_reference',
            'provider_trunk_reference',
            'provider_trunk_name',
            'order_reference',
            'created_at',
            'updated_at',
        ];
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->prepare(
                'INSERT INTO cc_vectavoip_did_inventory
                    (' . implode(', ', $columns) . ')
                 VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON CONFLICT(did) DO UPDATE SET
                    country = excluded.country,
                    region = excluded.region,
                    monthly_rate = excluded.monthly_rate,
                    setup_rate = excluded.setup_rate,
                    currency = excluded.currency,
                    status = excluded.status,
                    provider_code = excluded.provider_code,
                    provider_reference = excluded.provider_reference,
                    provider_trunk_reference = excluded.provider_trunk_reference,
                    provider_trunk_name = excluded.provider_trunk_name,
                    order_reference = excluded.order_reference,
                    updated_at = excluded.updated_at'
            );
        } else {
            $statement = $this->pdo->prepare(
                'INSERT INTO cc_vectavoip_did_inventory
                    (' . implode(', ', $columns) . ')
                 VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    country = VALUES(country),
                    region = VALUES(region),
                    monthly_rate = VALUES(monthly_rate),
                    setup_rate = VALUES(setup_rate),
                    currency = VALUES(currency),
                    status = VALUES(status),
                    provider_code = VALUES(provider_code),
                    provider_reference = VALUES(provider_reference),
                    provider_trunk_reference = VALUES(provider_trunk_reference),
                    provider_trunk_name = VALUES(provider_trunk_name),
                    order_reference = VALUES(order_reference),
                    updated_at = VALUES(updated_at)'
            );
        }

        $now = gmdate('Y-m-d H:i:s');
        $statement->execute([
            $did['did'],
            $did['country'],
            $did['region'],
            $did['monthly_rate'],
            $did['setup_rate'],
            $did['currency'],
            $did['status'],
            $did['provider_code'],
            $did['provider_reference'],
            $did['provider_trunk_reference'],
            $did['provider_trunk_name'],
            $did['order_reference'],
            $now,
            $now,
        ]);
    }

    private function ensureInventoryTable(): void
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS cc_vectavoip_did_inventory (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    did TEXT NOT NULL UNIQUE,
                    country TEXT NOT NULL DEFAULT \'\',
                    region TEXT NOT NULL DEFAULT \'\',
                    monthly_rate TEXT NOT NULL DEFAULT \'0.00000\',
                    setup_rate TEXT NOT NULL DEFAULT \'0.00000\',
                    currency TEXT NOT NULL DEFAULT \'USD\',
                    status TEXT NOT NULL DEFAULT \'available\',
                    provider_code TEXT NOT NULL DEFAULT \'\',
                    provider_reference TEXT NOT NULL DEFAULT \'\',
                    provider_trunk_reference TEXT NOT NULL DEFAULT \'\',
                    provider_trunk_name TEXT NOT NULL DEFAULT \'\',
                    order_reference TEXT NOT NULL DEFAULT \'\',
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )'
            );
            $this->ensureSqliteColumn('cc_vectavoip_did_inventory', 'provider_code', "TEXT NOT NULL DEFAULT ''");
            $this->ensureSqliteColumn('cc_vectavoip_did_inventory', 'provider_reference', "TEXT NOT NULL DEFAULT ''");
            $this->ensureSqliteColumn('cc_vectavoip_did_inventory', 'provider_trunk_reference', "TEXT NOT NULL DEFAULT ''");
            $this->ensureSqliteColumn('cc_vectavoip_did_inventory', 'provider_trunk_name', "TEXT NOT NULL DEFAULT ''");
            $this->ensureSqliteColumn('cc_vectavoip_did_inventory', 'order_reference', "TEXT NOT NULL DEFAULT ''");
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS cc_vectavoip_did_inventory (
                id BIGINT NOT NULL AUTO_INCREMENT,
                did VARCHAR(64) NOT NULL,
                country VARCHAR(64) NOT NULL DEFAULT \'\',
                region VARCHAR(64) NOT NULL DEFAULT \'\',
                monthly_rate DECIMAL(15,5) NOT NULL DEFAULT 0.00000,
                setup_rate DECIMAL(15,5) NOT NULL DEFAULT 0.00000,
                currency VARCHAR(3) NOT NULL DEFAULT \'USD\',
                status VARCHAR(32) NOT NULL DEFAULT \'available\',
                provider_code VARCHAR(32) NOT NULL DEFAULT \'\',
                provider_reference VARCHAR(128) NOT NULL DEFAULT \'\',
                provider_trunk_reference VARCHAR(128) NOT NULL DEFAULT \'\',
                provider_trunk_name VARCHAR(128) NOT NULL DEFAULT \'\',
                order_reference VARCHAR(128) NOT NULL DEFAULT \'\',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_vectavoip_did_inventory_did (did),
                KEY idx_vectavoip_did_inventory_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $this->ensureMysqlColumn('cc_vectavoip_did_inventory', 'provider_code', "VARCHAR(32) NOT NULL DEFAULT ''");
        $this->ensureMysqlColumn('cc_vectavoip_did_inventory', 'provider_reference', "VARCHAR(128) NOT NULL DEFAULT ''");
        $this->ensureMysqlColumn('cc_vectavoip_did_inventory', 'provider_trunk_reference', "VARCHAR(128) NOT NULL DEFAULT ''");
        $this->ensureMysqlColumn('cc_vectavoip_did_inventory', 'provider_trunk_name', "VARCHAR(128) NOT NULL DEFAULT ''");
        $this->ensureMysqlColumn('cc_vectavoip_did_inventory', 'order_reference', "VARCHAR(128) NOT NULL DEFAULT ''");
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? $default;
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    private function trunkCodeFor(string $value, string $trunkSid = ''): string
    {
        if ($trunkSid !== '') {
            $safeSid = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $trunkSid) ?? '');
            if ($safeSid !== '') {
                return substr('TW' . $safeSid, 0, 20);
            }
        }

        $safe = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $value) ?? 'TWILIO');
        $safe = $safe !== '' ? $safe : 'TWILIO';
        return substr($safe, 0, 20);
    }

    /**
     * @param array<string, mixed> $trunk
     */
    private function trunkHostFor(array $trunk): string
    {
        foreach ([
            'domain_name',
            'domainName',
            'from_domain',
            'fromDomain',
            'sip_domain',
            'sipDomain',
            'termination_uri',
            'terminationUri',
            'origination_uri',
            'originationUri',
            'uri',
        ] as $field) {
            $value = $this->stringValue($trunk, $field);
            if ($value === '') {
                continue;
            }

            $host = $this->hostFromUri($value);
            if ($host !== '') {
                return $host;
            }

            return $value;
        }

        if (preg_match('/^BY[0-9A-Fa-f]{32}$/', $this->stringValue($trunk, 'sid')) === 1) {
            return 'sip.twilio.com';
        }

        return '';
    }

    private function hostFromUri(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $parsed = parse_url($value);
        if (is_array($parsed) && is_string($parsed['host'] ?? null) && $parsed['host'] !== '') {
            return $parsed['host'];
        }

        if (str_contains($value, ':') && !str_contains($value, '.')) {
            return '';
        }

        return preg_replace('#^[A-Za-z]+:#', '', $value) ?? '';
    }

    private function trunkTechnology(): string
    {
        $configured = strtoupper(trim((string) getenv('TWILIO_TRUNK_TECHNOLOGY')));
        if (in_array($configured, ['SIP', 'PJSIP', 'IAX2'], true)) {
            return $configured;
        }

        $driver = strtolower(trim((string) getenv('A2BP_ASTERISK_CHANNEL_DRIVER')));
        return match ($driver) {
            'sip', 'chan_sip' => 'SIP',
            'iax', 'iax2' => 'IAX2',
            default => 'PJSIP',
        };
    }

    private function ensureSqliteColumn(string $table, string $column, string $definition): void
    {
        $statement = $this->pdo->query('PRAGMA table_info(' . $table . ')');
        $rows = $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
        foreach ($rows as $row) {
            if ((string) ($row['name'] ?? '') === $column) {
                return;
            }
        }

        $this->pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
    }

    private function ensureMysqlColumn(string $table, string $column, string $definition): void
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $statement->execute([$table, $column]);
        if ((int) $statement->fetchColumn() > 0) {
            return;
        }

        $this->pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
    }
}

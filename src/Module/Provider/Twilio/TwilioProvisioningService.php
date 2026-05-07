<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\Twilio;

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
        $statement = $this->pdo->prepare('SELECT id FROM cc_provider WHERE provider_name = ? LIMIT 1');
        $statement->execute(['Twilio']);
        $id = $statement->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        $insert = $this->pdo->prepare('INSERT INTO cc_provider (provider_name, description) VALUES (?, ?)');
        $insert->execute(['Twilio', 'Twilio automatically provisioned provider.']);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $trunk
     */
    private function ensureLocalTrunk(int $providerId, array $trunk): int
    {
        $trunkSid = $this->stringValue($trunk, 'sid');
        $friendlyName = $this->stringValue($trunk, 'friendly_name', 'Twilio Trunk');
        $domainName = $this->stringValue($trunk, 'domain_name');
        $trunkCode = $this->trunkCodeFor($friendlyName);
        $parameter = $trunkSid !== '' ? 'twilio_trunk:' . $trunkSid : 'twilio_trunk';

        $statement = $this->pdo->prepare('SELECT id_trunk FROM cc_trunk WHERE trunkcode = ? LIMIT 1');
        $statement->execute([$trunkCode]);
        $id = $statement->fetchColumn();
        if ($id !== false) {
            $update = $this->pdo->prepare(
                'UPDATE cc_trunk
                 SET providerip = ?, status = ?, id_provider = ?, addparameter = ?
                 WHERE id_trunk = ?'
            );
            $update->execute([$domainName, 1, $providerId, $parameter, (int) $id]);
            return (int) $id;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO cc_trunk
                (trunkcode, trunkprefix, providertech, providerip, removeprefix, failover_trunk, addparameter,
                 id_provider, inuse, maxuse, status, if_max_use)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $trunkCode,
            '',
            'SIP',
            $domainName,
            '',
            0,
            $parameter,
            $providerId,
            0,
            -1,
            1,
            0,
        ]);

        return (int) $this->pdo->lastInsertId();
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
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? $default;
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    private function trunkCodeFor(string $value): string
    {
        $safe = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $value) ?? 'TWILIO');
        $safe = $safe !== '' ? $safe : 'TWILIO';
        return substr($safe, 0, 20);
    }
}

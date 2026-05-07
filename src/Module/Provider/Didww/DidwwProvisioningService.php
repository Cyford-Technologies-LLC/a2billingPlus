<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\Didww;

final class DidwwProvisioningService
{
    public function __construct(private readonly \PDO $pdo)
    {
        $this->ensureInventoryTable();
    }

    /**
     * @param list<array<string, mixed>> $dids
     * @return array{success:bool, upserted:int, message:string}
     */
    public function syncOwnedDids(array $dids): array
    {
        $upserted = 0;
        foreach ($dids as $did) {
            $id = $this->stringValue($did, 'id');
            $number = $this->stringValue($did, 'number');
            if ($number === '') {
                continue;
            }

            $this->upsertDid([
                'did' => $number,
                'country' => '',
                'region' => '',
                'monthly_rate' => '0.00000',
                'setup_rate' => '0.00000',
                'currency' => 'USD',
                'status' => $this->statusForDid($did),
                'provider_reference' => $id !== '' ? 'didww:' . $id : 'didww',
            ]);
            $upserted++;
        }

        return [
            'success' => true,
            'upserted' => $upserted,
            'message' => 'DIDWW owned DIDs synchronized into local inventory.',
        ];
    }

    /**
     * @param array<string, mixed> $remoteTrunk
     * @param array<string, mixed> $localPayload
     * @return array{success:bool, provider_id:int, trunk_id:int, message:string}
     */
    public function materializeInboundTrunk(array $remoteTrunk, array $localPayload): array
    {
        $providerId = $this->ensureProvider();
        $trunkId = $this->ensureLocalTrunk($providerId, $remoteTrunk, $localPayload);

        return [
            'success' => true,
            'provider_id' => $providerId,
            'trunk_id' => $trunkId,
            'message' => 'DIDWW inbound trunk was created and linked locally.',
        ];
    }

    private function ensureProvider(): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM cc_provider WHERE provider_name = ? LIMIT 1');
        $statement->execute(['DIDWW']);
        $id = $statement->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        $insert = $this->pdo->prepare('INSERT INTO cc_provider (provider_name, description) VALUES (?, ?)');
        $insert->execute(['DIDWW', 'DIDWW automatically provisioned provider.']);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $remoteTrunk
     * @param array<string, mixed> $localPayload
     */
    private function ensureLocalTrunk(int $providerId, array $remoteTrunk, array $localPayload): int
    {
        $remoteId = $this->stringValue($remoteTrunk, 'id');
        $attributes = is_array($remoteTrunk['attributes'] ?? null) ? $remoteTrunk['attributes'] : [];
        $name = $this->stringValue($attributes, 'name', $this->stringValue($localPayload, 'name', 'DIDWW Inbound'));
        $capacity = max(1, (int) $this->stringValue($attributes, 'capacity_limit', '10'));
        $configuration = is_array($attributes['configuration'] ?? null) ? $attributes['configuration'] : [];
        $configurationAttributes = is_array($configuration['attributes'] ?? null) ? $configuration['attributes'] : [];
        $host = $this->stringValue($configurationAttributes, 'host', $this->stringValue($localPayload, 'host', ''));
        $trunkCode = $this->trunkCodeFor($name);
        $parameter = $remoteId !== '' ? 'didww_trunk:' . $remoteId : 'didww_trunk';

        $statement = $this->pdo->prepare('SELECT id_trunk FROM cc_trunk WHERE trunkcode = ? LIMIT 1');
        $statement->execute([$trunkCode]);
        $id = $statement->fetchColumn();
        if ($id !== false) {
            $update = $this->pdo->prepare(
                'UPDATE cc_trunk
                 SET providerip = ?, maxuse = ?, status = ?, id_provider = ?, addparameter = ?
                 WHERE id_trunk = ?'
            );
            $update->execute([$host, $capacity, 1, $providerId, $parameter, (int) $id]);
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
            $host,
            '',
            0,
            $parameter,
            $providerId,
            0,
            $capacity,
            1,
            0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, string> $did
     */
    private function upsertDid(array $did): void
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->prepare(
                'INSERT INTO cc_vectavoip_did_inventory
                    (did, country, region, monthly_rate, setup_rate, currency, status, provider_reference, created_at, updated_at)
                 VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON CONFLICT(did) DO UPDATE SET
                    country = excluded.country,
                    region = excluded.region,
                    monthly_rate = excluded.monthly_rate,
                    setup_rate = excluded.setup_rate,
                    currency = excluded.currency,
                    status = excluded.status,
                    provider_reference = excluded.provider_reference,
                    updated_at = excluded.updated_at'
            );
        } else {
            $statement = $this->pdo->prepare(
                'INSERT INTO cc_vectavoip_did_inventory
                    (did, country, region, monthly_rate, setup_rate, currency, status, provider_reference, created_at, updated_at)
                 VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    country = VALUES(country),
                    region = VALUES(region),
                    monthly_rate = VALUES(monthly_rate),
                    setup_rate = VALUES(setup_rate),
                    currency = VALUES(currency),
                    status = VALUES(status),
                    provider_reference = VALUES(provider_reference),
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
            $did['provider_reference'],
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
                    provider_reference TEXT NOT NULL DEFAULT \'\',
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
                provider_reference VARCHAR(128) NOT NULL DEFAULT \'\',
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

    /**
     * @param array<string, mixed> $did
     */
    private function statusForDid(array $did): string
    {
        if ($this->stringValue($did, 'terminated') === 'Yes') {
            return 'terminated';
        }
        if ($this->stringValue($did, 'blocked') === 'Yes') {
            return 'blocked';
        }
        if ($this->stringValue($did, 'awaiting_registration') === 'Yes') {
            return 'pending';
        }

        return 'available';
    }

    private function trunkCodeFor(string $value): string
    {
        $safe = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $value) ?? 'DIDWW');
        $safe = $safe !== '' ? $safe : 'DIDWW';
        return substr($safe, 0, 20);
    }
}

<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\VectaVoIP;

final class VectaVoIPProvisioningService
{
    public function __construct(private readonly \PDO $pdo)
    {
        $this->ensureDidInventoryTable();
        $this->ensureDidRequestTable();
    }

    /**
     * @return array<string, mixed>
     */
    public function provisionDefaults(): array
    {
        $this->pdo->beginTransaction();
        try {
            $providerId = $this->ensureProvider();
            $trunkId = $this->ensureTrunk($providerId);
            $ratecardId = $this->ensureRatecard($trunkId);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return [
            'success' => true,
            'provider_id' => $providerId,
            'trunk_id' => $trunkId,
            'ratecard_id' => $ratecardId,
            'message' => 'VectaVoIP provider defaults are provisioned.',
        ];
    }

    /**
     * @param list<array<string, mixed>> $dids
     * @return array{success:bool, upserted:int, message:string}
     */
    public function syncDidInventory(array $dids): array
    {
        $upserted = 0;
        foreach ($dids as $did) {
            $number = $this->stringValue($did, 'did');
            if ($number === '') {
                continue;
            }

            $this->upsertDid([
                'did' => $number,
                'country' => $this->stringValue($did, 'country'),
                'region' => $this->stringValue($did, 'region'),
                'monthly_rate' => $this->decimalValue($did, 'monthly_rate'),
                'setup_rate' => $this->decimalValue($did, 'setup_rate'),
                'currency' => $this->stringValue($did, 'currency', 'USD'),
                'status' => $this->stringValue($did, 'status', 'available'),
                'provider_reference' => $this->stringValue($did, 'provider_reference'),
            ]);
            $upserted++;
        }

        return [
            'success' => true,
            'upserted' => $upserted,
            'message' => 'VectaVoIP DID inventory synchronized.',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function applyPackageProvisioning(array $payload): array
    {
        $packageCode = $this->stringValue($payload, 'selected_package');
        if ($packageCode === '') {
            throw new \InvalidArgumentException('selected_package is required.');
        }

        $didCount = max(0, (int)$this->stringValue($payload, 'package_did_count', '0'));
        $channels = max(1, (int)$this->stringValue($payload, 'package_channels', '1'));
        $trunkLabel = $this->stringValue($payload, 'package_trunk_label', 'VectaVoIP ' . strtoupper($packageCode));
        $ratecardId = (int)$this->stringValue($payload, 'package_ratecard_id');
        $accountNumber = $this->stringValue($payload, 'account_number');
        $portalUsername = $this->stringValue($payload, 'portal_username');
        $apiSecret = $this->stringValue($payload, 'api_secret');
        $registeredIp = $this->stringValue($payload, 'registered_ip');

        $this->pdo->beginTransaction();
        try {
            $providerId = $this->ensureProvider();
            $trunkId = $this->ensurePackageTrunk($providerId, $trunkLabel, $channels);
            $resolvedRatecardId = $this->ensurePackageRatecard($trunkId, $packageCode, $ratecardId);
            $didRequestId = $this->recordDidRequest([
                'package_code' => $packageCode,
                'did_count' => $didCount,
                'sms_enabled' => $this->booleanInt($payload, 'package_sms_enabled'),
                'e911_enabled' => $this->booleanInt($payload, 'package_911_enabled'),
                'ratecard_id' => $resolvedRatecardId,
                'trunk_id' => $trunkId,
                'account_number' => $accountNumber,
                'registered_ip' => $registeredIp,
                'notes' => $this->stringValue($payload, 'package_notes'),
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $pjsipResult = null;
        if ($portalUsername !== '' && $apiSecret !== '') {
            $pjsip = new \A2BillingPlus\Module\Telephony\PjsipProvisioningService($this->pdo);
            $pjsipResult = $pjsip->provisionTrunk([
                'trunkcode' => $this->trunkCodeFor($packageCode),
                'host' => 'sip.vectavoip.com',
                'username' => $accountNumber !== '' ? $accountNumber : $portalUsername,
                'secret' => $apiSecret,
                'allow' => 'ulaw,alaw',
            ], 'vectavoip-package');
        }

        return [
            'success' => true,
            'provider_id' => $providerId,
            'trunk_id' => $trunkId,
            'ratecard_id' => $resolvedRatecardId,
            'did_request_id' => $didRequestId,
            'pjsip_endpoint' => $pjsipResult['body']['endpoint']['endpoint_id'] ?? '',
            'message' => 'VectaVoIP package provisioning applied.',
        ];
    }

    private function ensureProvider(): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM cc_provider WHERE provider_name = ? LIMIT 1');
        $statement->execute(['VectaVoIP']);
        $id = $statement->fetchColumn();
        if ($id !== false) {
            return (int)$id;
        }

        $insert = $this->pdo->prepare('INSERT INTO cc_provider (provider_name, description) VALUES (?, ?)');
        $insert->execute(['VectaVoIP', 'VectaVoIP automatically provisioned provider.']);

        return (int)$this->pdo->lastInsertId();
    }

    private function ensureTrunk(int $providerId): int
    {
        $statement = $this->pdo->prepare('SELECT id_trunk FROM cc_trunk WHERE trunkcode = ? LIMIT 1');
        $statement->execute(['VECTAVOIP']);
        $id = $statement->fetchColumn();
        if ($id !== false) {
            return (int)$id;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO cc_trunk
                (trunkcode, trunkprefix, providertech, providerip, removeprefix, failover_trunk, addparameter,
                 id_provider, inuse, maxuse, status, if_max_use)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            'VECTAVOIP',
            '',
            'SIP',
            'sip.vectavoip.com',
            '',
            0,
            '',
            $providerId,
            0,
            -1,
            1,
            0,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function ensurePackageTrunk(int $providerId, string $trunkLabel, int $channels): int
    {
        $trunkCode = $this->trunkCodeFor($trunkLabel);
        $statement = $this->pdo->prepare('SELECT id_trunk FROM cc_trunk WHERE trunkcode = ? LIMIT 1');
        $statement->execute([$trunkCode]);
        $id = $statement->fetchColumn();
        if ($id !== false) {
            $update = $this->pdo->prepare(
                'UPDATE cc_trunk
                 SET providerip = ?, maxuse = ?, status = ?, id_provider = ?, addparameter = ?
                 WHERE id_trunk = ?'
            );
            $update->execute([
                'sip.vectavoip.com',
                $channels,
                1,
                $providerId,
                $trunkLabel,
                (int)$id,
            ]);
            return (int)$id;
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
            'PJSIP',
            'sip.vectavoip.com',
            '',
            0,
            $trunkLabel,
            $providerId,
            0,
            $channels,
            1,
            0,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function ensureRatecard(int $trunkId): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM cc_tariffplan WHERE iduser = 0 AND tariffname = ? LIMIT 1');
        $statement->execute(['VectaVoIP Retail']);
        $id = $statement->fetchColumn();
        if ($id !== false) {
            return (int)$id;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO cc_tariffplan
                (iduser, tariffname, description, id_trunk, dnidprefix, calleridprefix)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            0,
            'VectaVoIP Retail',
            'Default VectaVoIP retail ratecard.',
            $trunkId,
            'all',
            'all',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function ensurePackageRatecard(int $trunkId, string $packageCode, int $requestedRatecardId): int
    {
        if ($requestedRatecardId > 0) {
            $update = $this->pdo->prepare('UPDATE cc_tariffplan SET id_trunk = ? WHERE id = ?');
            $update->execute([$trunkId, $requestedRatecardId]);
            return $requestedRatecardId;
        }

        $name = 'VectaVoIP ' . strtoupper($packageCode);
        $statement = $this->pdo->prepare('SELECT id FROM cc_tariffplan WHERE iduser = 0 AND tariffname = ? LIMIT 1');
        $statement->execute([$name]);
        $id = $statement->fetchColumn();
        if ($id !== false) {
            $update = $this->pdo->prepare('UPDATE cc_tariffplan SET id_trunk = ? WHERE id = ?');
            $update->execute([$trunkId, (int)$id]);
            return (int)$id;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO cc_tariffplan
                (iduser, tariffname, description, id_trunk, dnidprefix, calleridprefix)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            0,
            $name,
            'VectaVoIP package ratecard for ' . strtoupper($packageCode) . '.',
            $trunkId,
            'all',
            'all',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function recordDidRequest(array $payload): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $insert = $this->pdo->prepare(
            'INSERT INTO cc_vectavoip_did_requests
                (package_code, did_count, sms_enabled, e911_enabled, ratecard_id, trunk_id, account_number, registered_ip, notes, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $payload['package_code'],
            $payload['did_count'],
            $payload['sms_enabled'],
            $payload['e911_enabled'],
            $payload['ratecard_id'],
            $payload['trunk_id'],
            $payload['account_number'],
            $payload['registered_ip'],
            $payload['notes'],
            'requested',
            $now,
            $now,
        ]);

        return (int)$this->pdo->lastInsertId();
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

    private function ensureDidInventoryTable(): void
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

    private function ensureDidRequestTable(): void
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS cc_vectavoip_did_requests (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    package_code TEXT NOT NULL,
                    did_count INTEGER NOT NULL DEFAULT 0,
                    sms_enabled INTEGER NOT NULL DEFAULT 0,
                    e911_enabled INTEGER NOT NULL DEFAULT 0,
                    ratecard_id INTEGER NOT NULL DEFAULT 0,
                    trunk_id INTEGER NOT NULL DEFAULT 0,
                    account_number TEXT NOT NULL DEFAULT \'\',
                    registered_ip TEXT NOT NULL DEFAULT \'\',
                    notes TEXT NOT NULL DEFAULT \'\',
                    status TEXT NOT NULL DEFAULT \'requested\',
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )'
            );
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS cc_vectavoip_did_requests (
                id BIGINT NOT NULL AUTO_INCREMENT,
                package_code VARCHAR(64) NOT NULL,
                did_count INT NOT NULL DEFAULT 0,
                sms_enabled TINYINT(1) NOT NULL DEFAULT 0,
                e911_enabled TINYINT(1) NOT NULL DEFAULT 0,
                ratecard_id BIGINT NOT NULL DEFAULT 0,
                trunk_id BIGINT NOT NULL DEFAULT 0,
                account_number VARCHAR(64) NOT NULL DEFAULT \'\',
                registered_ip VARCHAR(64) NOT NULL DEFAULT \'\',
                notes TEXT NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'requested\',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_vectavoip_did_requests_status (status),
                KEY idx_vectavoip_did_requests_package (package_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? $default;
        return is_scalar($value) ? trim((string)$value) : $default;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function decimalValue(array $values, string $key): string
    {
        $value = $values[$key] ?? '0.00000';
        return is_numeric($value) ? number_format((float)$value, 5, '.', '') : '0.00000';
    }

    /**
     * @param array<string,mixed> $values
     */
    private function booleanInt(array $values, string $key): int
    {
        $value = $values[$key] ?? '';
        return in_array((string)$value, ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
    }

    private function trunkCodeFor(string $value): string
    {
        $safe = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $value) ?? 'VECTAVOIP');
        $safe = $safe !== '' ? $safe : 'VECTAVOIP';
        return substr($safe, 0, 20);
    }
}

<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Telephony;

final class CoreTrunkProjectionService
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function ensureProvider(string $providerName, string $description): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM cc_provider WHERE provider_name = ? LIMIT 1');
        $statement->execute([$providerName]);
        $id = $statement->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        $insert = $this->pdo->prepare('INSERT INTO cc_provider (provider_name, description) VALUES (?, ?)');
        $insert->execute([$providerName, $description]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array{
     *   provider_id:int,
     *   trunkcode:string,
     *   providertech:string,
     *   providerip:string,
     *   addparameter:string,
     *   trunkprefix?:string,
     *   removeprefix?:string,
     *   failover_trunk?:int,
     *   inuse?:int,
     *   maxuse?:int,
     *   status?:int,
     *   if_max_use?:int
     * } $payload
     */
    public function upsertBySyncKey(array $payload): int
    {
        $syncKey = trim((string) ($payload['addparameter'] ?? ''));
        if ($syncKey === '') {
            throw new \InvalidArgumentException('addparameter sync key is required.');
        }

        $statement = $this->pdo->prepare('SELECT id_trunk FROM cc_trunk WHERE addparameter = ? LIMIT 1');
        $statement->execute([$syncKey]);
        $id = $statement->fetchColumn();
        if ($id !== false) {
            $update = $this->pdo->prepare(
                'UPDATE cc_trunk
                 SET trunkcode = ?, trunkprefix = ?, providertech = ?, providerip = ?, removeprefix = ?,
                     failover_trunk = ?, id_provider = ?, inuse = ?, maxuse = ?, status = ?, if_max_use = ?, addparameter = ?
                 WHERE id_trunk = ?'
            );
            $update->execute([
                $payload['trunkcode'],
                $payload['trunkprefix'] ?? '',
                strtoupper((string) $payload['providertech']),
                $payload['providerip'],
                $payload['removeprefix'] ?? '',
                (int) ($payload['failover_trunk'] ?? 0),
                (int) $payload['provider_id'],
                (int) ($payload['inuse'] ?? 0),
                (int) ($payload['maxuse'] ?? -1),
                (int) ($payload['status'] ?? 1),
                (int) ($payload['if_max_use'] ?? 0),
                $syncKey,
                (int) $id,
            ]);
            return (int) $id;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO cc_trunk
                (trunkcode, trunkprefix, providertech, providerip, removeprefix, failover_trunk, addparameter,
                 id_provider, inuse, maxuse, status, if_max_use)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $payload['trunkcode'],
            $payload['trunkprefix'] ?? '',
            strtoupper((string) $payload['providertech']),
            $payload['providerip'],
            $payload['removeprefix'] ?? '',
            (int) ($payload['failover_trunk'] ?? 0),
            $syncKey,
            (int) $payload['provider_id'],
            (int) ($payload['inuse'] ?? 0),
            (int) ($payload['maxuse'] ?? -1),
            (int) ($payload['status'] ?? 1),
            (int) ($payload['if_max_use'] ?? 0),
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}

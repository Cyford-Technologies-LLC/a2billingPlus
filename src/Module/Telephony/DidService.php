<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Telephony;

use A2BillingPlus\Module\Security\AuditLogRepository;

final class DidService
{
    public function __construct(
        private readonly DidRepository $repository,
        private readonly \PDO $pdo,
        private readonly ?AuditLogRepository $auditLog = null
    ) {
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function list(int $limit, int $offset, ?int $customerId = null, ?int $reserved = null, ?int $activated = null): array
    {
        return $this->repository->list($limit, $offset, $customerId, $reserved, $activated);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function detail(int $id): ?array
    {
        $did = $this->repository->find($id);
        if ($did === null) {
            return null;
        }

        $did['destinations'] = $this->repository->destinations($id);
        return $did;
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function assign(int $didId, int $customerId, string $actor): array
    {
        $did = $this->repository->find($didId);
        if ($did === null) {
            return $this->error(404, 'did_not_found', 'DID was not found.', 'id');
        }
        if ($customerId <= 0) {
            return $this->error(422, 'did_validation_failed', 'customer_id must be a positive integer.', 'customer_id');
        }
        if ((int)($did['reserved'] ?? 0) === 1 && (int)($did['iduser'] ?? 0) !== $customerId) {
            return $this->error(409, 'did_already_reserved', 'DID is already reserved for another customer.', 'id');
        }

        $this->inTransaction(function () use ($didId, $customerId): void {
            $this->repository->assign($didId, $customerId);
        });
        $updated = $this->detail($didId);
        $this->audit($actor, 'did.assign', $didId, ['customer_id' => $customerId, 'did' => $updated['did'] ?? '']);

        return ['status' => 200, 'body' => ['success' => true, 'did' => $updated]];
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function release(int $didId, string $actor): array
    {
        $did = $this->repository->find($didId);
        if ($did === null) {
            return $this->error(404, 'did_not_found', 'DID was not found.', 'id');
        }

        $customerId = (int)($did['iduser'] ?? 0);
        $this->inTransaction(function () use ($didId): void {
            $this->repository->release($didId);
        });
        $updated = $this->detail($didId);
        $this->audit($actor, 'did.release', $didId, ['customer_id' => $customerId, 'did' => $did['did'] ?? '']);

        return ['status' => 200, 'body' => ['success' => true, 'did' => $updated]];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function updateRouting(int $didId, array $payload, string $actor): array
    {
        $did = $this->repository->find($didId);
        if ($did === null) {
            return $this->error(404, 'did_not_found', 'DID was not found.', 'id');
        }

        $customerId = $this->intValue($payload, 'customer_id') ?? (int)($did['iduser'] ?? 0);
        if ($customerId <= 0) {
            return $this->error(422, 'did_validation_failed', 'customer_id must be a positive integer.', 'customer_id');
        }
        if ((int)($did['iduser'] ?? 0) !== $customerId) {
            return $this->error(403, 'did_ownership_failed', 'DID is not assigned to this customer.', 'customer_id');
        }

        $destinations = $this->normalizeDestinations($payload['destinations'] ?? null);
        if (!is_array($destinations)) {
            return $this->error(422, 'did_validation_failed', 'destinations must contain one to ten valid destination rows.', 'destinations');
        }

        $this->inTransaction(function () use ($didId, $customerId, $destinations): void {
            $this->repository->replaceDestinations($didId, $customerId, $destinations);
        });
        $updated = $this->detail($didId);
        $this->audit($actor, 'did.routing.update', $didId, ['customer_id' => $customerId, 'destinations' => count($destinations)]);

        return ['status' => 200, 'body' => ['success' => true, 'did' => $updated]];
    }

    /**
     * @param mixed $payload
     * @return list<array{destination:string,priority:int,voip_call:int,activated:int,validated:int}>|null
     */
    private function normalizeDestinations(mixed $payload): ?array
    {
        if (!is_array($payload) || $payload === [] || count($payload) > 10) {
            return null;
        }

        $normalized = [];
        foreach (array_values($payload) as $index => $row) {
            if (!is_array($row)) {
                return null;
            }
            $destination = is_scalar($row['destination'] ?? null) ? trim((string)$row['destination']) : '';
            if ($destination === '' || strlen($destination) > 100 || preg_match('/^[+*#A-Za-z0-9_.@:-]+$/', $destination) !== 1) {
                return null;
            }

            $priority = $this->intValue($row, 'priority') ?? ($index + 1);
            if ($priority < 1 || $priority > 100) {
                return null;
            }

            $normalized[] = [
                'destination' => $destination,
                'priority' => $priority,
                'voip_call' => $this->binaryValue($row, 'voip_call', 1),
                'activated' => $this->binaryValue($row, 'activated', 1),
                'validated' => $this->binaryValue($row, 'validated', 1),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function intValue(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?[0-9]+$/', $value) === 1) {
            return (int)$value;
        }

        return null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function binaryValue(array $payload, string $key, int $default): int
    {
        $value = $this->intValue($payload, $key);
        return $value === 0 ? 0 : ($value === 1 ? 1 : $default);
    }

    private function audit(string $actor, string $action, int $didId, array $metadata): void
    {
        $this->auditLog?->record($actor, $action, 'cc_did', (string)$didId, $metadata);
    }

    private function inTransaction(callable $callback): void
    {
        $this->pdo->beginTransaction();
        try {
            $callback();
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    private function error(int $status, string $code, string $message, string $field): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'code' => $code, 'message' => $message, 'field' => $field]];
    }
}

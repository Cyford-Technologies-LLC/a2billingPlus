<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Telephony;

use A2BillingPlus\Module\Security\AuditLogRepository;

final class TelephonyAccountService
{
    public function __construct(
        private readonly TelephonyAccountRepository $repository,
        private readonly ?AuditLogRepository $auditLog = null,
        private readonly ?PjsipProvisioningService $pjsipProvisioning = null,
        private readonly string $channelDriver = 'chan_sip',
        private readonly bool $realtimeEnabled = false
    ) {
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function list(string $technology, int $limit, int $offset, ?int $customerId = null): array
    {
        return $this->repository->list($this->normalizeTechnology($technology), $limit, $offset, $customerId);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function detail(string $technology, int $id): ?array
    {
        return $this->repository->find($this->normalizeTechnology($technology), $id);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function create(string $technology, array $payload, string $actor): array
    {
        $technology = $this->normalizeTechnology($technology);
        $validation = $this->validate($payload, false);
        if ($validation !== null) {
            return $validation;
        }

        $pdo = $this->repository->pdo();
        $transactional = $this->shouldSyncPjsip($technology);
        if ($transactional && !$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }

        try {
            $account = $this->repository->create($technology, $this->normalize($payload));
            $this->syncPjsipIfNeeded($technology, (int)($account['id'] ?? 0), $actor);
            if ($transactional && $pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($transactional && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return $this->error(500, 'telephony_account_provisioning_failed', $exception->getMessage(), 'technology');
        }

        $this->audit($actor, 'telephony_account.create', $technology, $account);

        return ['status' => 201, 'body' => ['success' => true, 'account' => $account]];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function update(string $technology, int $id, array $payload, string $actor): array
    {
        $technology = $this->normalizeTechnology($technology);
        if ($this->repository->find($technology, $id) === null) {
            return $this->error(404, 'telephony_account_not_found', 'Telephony account was not found.', 'id');
        }

        $validation = $this->validate($payload, true);
        if ($validation !== null) {
            return $validation;
        }

        $pdo = $this->repository->pdo();
        $transactional = $this->shouldSyncPjsip($technology);
        if ($transactional && !$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }

        try {
            $account = $this->repository->update($technology, $id, $this->normalize($payload));
            $this->syncPjsipIfNeeded($technology, $id, $actor);
            if ($transactional && $pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($transactional && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return $this->error(500, 'telephony_account_provisioning_failed', $exception->getMessage(), 'technology');
        }

        $this->audit($actor, 'telephony_account.update', $technology, $account ?? ['id' => $id]);

        return ['status' => 200, 'body' => ['success' => true, 'account' => $account]];
    }

    private function normalizeTechnology(string $technology): string
    {
        $value = strtolower(trim($technology));
        if (!in_array($value, ['sip', 'iax'], true)) {
            throw new \InvalidArgumentException('technology must be sip or iax.');
        }

        return $value;
    }

    private function shouldSyncPjsip(string $technology): bool
    {
        return $technology === 'sip'
            && $this->pjsipProvisioning !== null
            && strtolower($this->channelDriver) === 'pjsip'
            && $this->realtimeEnabled;
    }

    private function syncPjsipIfNeeded(string $technology, int $id, string $actor): void
    {
        if (!$this->shouldSyncPjsip($technology) || $id <= 0) {
            return;
        }

        $account = $this->repository->findProvisioningSource($technology, $id);
        if ($account === null) {
            throw new \RuntimeException('Provisioning source account was not found.');
        }

        $result = $this->pjsipProvisioning->syncLegacySipAccount($account, $actor);
        if (($result['body']['success'] ?? false) !== true) {
            throw new \RuntimeException((string)($result['body']['message'] ?? 'PJSIP provisioning failed.'));
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return null|array{status:int,body:array<string,mixed>}
     */
    private function validate(array $payload, bool $partial): ?array
    {
        foreach (['id_cc_card', 'username', 'secret'] as $field) {
            if (!$partial && !array_key_exists($field, $payload)) {
                return $this->error(422, 'telephony_account_validation_failed', $field . ' is required.', $field);
            }
        }
        if (array_key_exists('id_cc_card', $payload) && ($this->intValue($payload, 'id_cc_card') ?? 0) <= 0) {
            return $this->error(422, 'telephony_account_validation_failed', 'id_cc_card must be a positive integer.', 'id_cc_card');
        }
        foreach (['username', 'name', 'accountcode', 'regexten', 'callerid', 'context', 'host', 'type', 'secret'] as $field) {
            if (array_key_exists($field, $payload)) {
                $value = $this->stringValue($payload, $field);
                if ($value === '' || strlen($value) > 80) {
                    return $this->error(422, 'telephony_account_validation_failed', $field . ' must be 1 to 80 characters.', $field);
                }
            }
        }
        if (array_key_exists('type', $payload) && !in_array($this->stringValue($payload, 'type'), ['friend', 'peer', 'user'], true)) {
            return $this->error(422, 'telephony_account_validation_failed', 'type must be friend, peer, or user.', 'type');
        }

        return null;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function normalize(array $payload): array
    {
        $data = [];
        foreach (['username', 'name', 'accountcode', 'regexten', 'callerid', 'context', 'host', 'port', 'qualify', 'secret', 'type', 'disallow', 'allow', 'trunk', 'defaultuser', 'cid_number'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $this->stringValue($payload, $field);
            }
        }
        if (array_key_exists('id_cc_card', $payload)) {
            $data['id_cc_card'] = $this->intValue($payload, 'id_cc_card');
        }

        return $data;
    }

    private function audit(string $actor, string $action, string $technology, array $account): void
    {
        $this->auditLog?->record($actor, $action, $this->repository->table($technology), (string)($account['id'] ?? ''), [
            'technology' => $technology,
            'id_cc_card' => $account['id_cc_card'] ?? null,
            'username' => $account['username'] ?? '',
        ]);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';
        return is_scalar($value) ? trim((string)$value) : '';
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
        if (is_string($value) && preg_match('/^[0-9]+$/', $value) === 1) {
            return (int)$value;
        }

        return null;
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    private function error(int $status, string $code, string $message, string $field): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'code' => $code, 'message' => $message, 'field' => $field]];
    }
}

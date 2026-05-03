<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Telephony;

use A2BillingPlus\Module\Security\AuditLogRepository;

final class TrunkService
{
    private const TECHNOLOGIES = ['SIP', 'IAX2', 'PJSIP'];

    public function __construct(
        private readonly TrunkRepository $repository,
        private readonly ?AuditLogRepository $auditLog = null
    ) {
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function list(int $limit, int $offset, ?int $status = null): array
    {
        return $this->repository->list($limit, $offset, $status);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function detail(int $id): ?array
    {
        return $this->repository->find($id);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function create(array $payload, string $actor): array
    {
        $validation = $this->validate($payload, false);
        if ($validation !== null) {
            return $validation;
        }

        $trunk = $this->repository->create($this->normalize($payload));
        $this->audit($actor, 'trunk.create', $trunk);

        return ['status' => 201, 'body' => ['success' => true, 'trunk' => $trunk]];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function update(int $id, array $payload, string $actor): array
    {
        if ($this->repository->find($id) === null) {
            return $this->error(404, 'trunk_not_found', 'Trunk was not found.', 'id');
        }

        $validation = $this->validate($payload, true);
        if ($validation !== null) {
            return $validation;
        }

        $trunk = $this->repository->update($id, $this->normalize($payload));
        $this->audit($actor, 'trunk.update', $trunk ?? ['id_trunk' => $id]);

        return ['status' => 200, 'body' => ['success' => true, 'trunk' => $trunk]];
    }

    /**
     * @param array<string,mixed> $payload
     * @return null|array{status:int,body:array<string,mixed>}
     */
    private function validate(array $payload, bool $partial): ?array
    {
        foreach (['trunkcode', 'providertech', 'providerip'] as $field) {
            if (!$partial && $this->stringValue($payload, $field) === '') {
                return $this->error(422, 'trunk_validation_failed', $field . ' is required.', $field);
            }
        }
        if (array_key_exists('providertech', $payload)) {
            $tech = strtoupper($this->stringValue($payload, 'providertech'));
            if (!in_array($tech, self::TECHNOLOGIES, true)) {
                return $this->error(422, 'trunk_validation_failed', 'providertech must be SIP, IAX2, or PJSIP.', 'providertech');
            }
        }
        foreach (['trunkcode' => 50, 'trunkprefix' => 20, 'providerip' => 80, 'removeprefix' => 20, 'addparameter' => 120] as $field => $max) {
            if (array_key_exists($field, $payload) && strlen($this->stringValue($payload, $field)) > $max) {
                return $this->error(422, 'trunk_validation_failed', $field . ' must be ' . $max . ' characters or fewer.', $field);
            }
        }
        foreach (['status', 'maxuse', 'failover_trunk', 'id_provider', 'if_max_use'] as $field) {
            if (array_key_exists($field, $payload) && $this->intValue($payload, $field) === null) {
                return $this->error(422, 'trunk_validation_failed', $field . ' must be an integer.', $field);
            }
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
        foreach (['trunkcode', 'trunkprefix', 'providertech', 'providerip', 'removeprefix', 'addparameter'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $field === 'providertech' ? strtoupper($this->stringValue($payload, $field)) : $this->stringValue($payload, $field);
            }
        }
        foreach (['status', 'maxuse', 'failover_trunk', 'id_provider', 'if_max_use'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $this->intValue($payload, $field);
            }
        }

        return $data;
    }

    private function audit(string $actor, string $action, array $trunk): void
    {
        if ($this->auditLog === null) {
            return;
        }

        $this->auditLog->record($actor, $action, 'cc_trunk', (string)($trunk['id_trunk'] ?? ''), [
            'trunkcode' => $trunk['trunkcode'] ?? '',
            'providertech' => $trunk['providertech'] ?? '',
            'status' => $trunk['status'] ?? null,
        ]);
    }

    private function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';
        return is_scalar($value) ? trim((string)$value) : '';
    }

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
     * @return array{status:int,body:array<string,mixed>}
     */
    private function error(int $status, string $code, string $message, string $field): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'code' => $code, 'message' => $message, 'field' => $field]];
    }
}

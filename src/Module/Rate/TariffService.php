<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Rate;

use A2BillingPlus\Module\Security\AuditLogRepository;

final class TariffService
{
    public function __construct(
        private readonly TariffRepository $repository,
        private readonly ?AuditLogRepository $auditLog = null
    ) {
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function list(string $resource, int $limit, int $offset, string $search = ''): array
    {
        return $this->repository->list($resource, $limit, $offset, $search);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function detail(string $resource, int $id): ?array
    {
        return $this->repository->find($resource, $id);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function create(string $resource, array $payload, string $actor): array
    {
        $validation = $this->validate($resource, $payload, false);
        if ($validation !== null) {
            return $validation;
        }

        $row = $this->repository->create($resource, $this->normalize($resource, $payload));
        $this->audit($actor, $resource . '.create', $row);

        return ['status' => 201, 'body' => ['success' => true, 'item' => $row]];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function update(string $resource, int $id, array $payload, string $actor): array
    {
        if ($this->repository->find($resource, $id) === null) {
            return $this->error(404, 'tariff_not_found', 'Tariff resource was not found.', 'id');
        }

        $validation = $this->validate($resource, $payload, true);
        if ($validation !== null) {
            return $validation;
        }

        $row = $this->repository->update($resource, $id, $this->normalize($resource, $payload));
        $this->audit($actor, $resource . '.update', $row ?? ['id' => $id]);

        return ['status' => 200, 'body' => ['success' => true, 'item' => $row]];
    }

    /**
     * @param array<string,mixed> $payload
     * @return null|array{status:int,body:array<string,mixed>}
     */
    private function validate(string $resource, array $payload, bool $partial): ?array
    {
        $nameField = $resource === 'tariff-plans' ? 'tariffname' : 'tariffgroupname';
        if (!$partial && $this->stringValue($payload, $nameField) === '') {
            return $this->error(422, 'tariff_validation_failed', $nameField . ' is required.', $nameField);
        }
        if (array_key_exists($nameField, $payload) && strlen($this->stringValue($payload, $nameField)) > 50) {
            return $this->error(422, 'tariff_validation_failed', $nameField . ' must be 50 characters or fewer.', $nameField);
        }
        if ($resource === 'tariff-groups' && (!$partial || array_key_exists('idtariffplan', $payload))) {
            $planId = $this->intValue($payload, 'idtariffplan');
            if ($planId === null || $planId <= 0) {
                return $this->error(422, 'tariff_validation_failed', 'idtariffplan must be a positive integer.', 'idtariffplan');
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function normalize(string $resource, array $payload): array
    {
        $fields = $resource === 'tariff-plans'
            ? ['iduser', 'tariffname', 'description', 'id_trunk', 'idowner', 'dnidprefix', 'calleridprefix']
            : ['iduser', 'idtariffplan', 'tariffgroupname', 'lcrtype', 'removeinterprefix', 'id_cc_package_offer'];
        $data = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            $data[$field] = in_array($field, ['iduser', 'id_trunk', 'idowner', 'idtariffplan', 'lcrtype', 'removeinterprefix', 'id_cc_package_offer'], true)
                ? $this->intValue($payload, $field)
                : $this->stringValue($payload, $field);
        }

        return $data;
    }

    private function audit(string $actor, string $action, array $row): void
    {
        if ($this->auditLog === null) {
            return;
        }

        $this->auditLog->record($actor, $action, 'rate', (string)($row['id'] ?? ''), $row);
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

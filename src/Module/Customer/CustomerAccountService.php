<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Customer;

use A2BillingPlus\Module\Security\AuditLogRepository;

final class CustomerAccountService
{
    public function __construct(
        private readonly CustomerAccountRepository $repository,
        private readonly ?AuditLogRepository $auditLog = null
    ) {
    }

    /**
     * @return array{items:list<array<string, mixed>>,columns:list<string>}
     */
    public function search(CustomerSearchCriteria $criteria): array
    {
        return $this->repository->search($criteria);
    }

    /**
     * @return array{total:int,active:int,blocked:int}
     */
    public function summary(CustomerSearchCriteria $criteria): array
    {
        return $this->repository->summary($criteria);
    }

    /**
     * @return list<array{id:string,name:string}>
     */
    public function groups(): array
    {
        return $this->repository->groups();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByExternalId(string $externalId): ?array
    {
        return $this->repository->findByExternalId($externalId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function changeStatus(int $id, int $status, string $actor): ?array
    {
        $customer = $this->repository->updateStatus($id, $status);
        if ($customer !== null && $this->auditLog !== null) {
            $this->auditLog->record($actor, 'customer.status.update', 'cc_card', (string)$id, [
                'status' => $status,
            ]);
        }

        return $customer;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function create(array $payload, string $actor): array
    {
        $validation = $this->validate($payload, null);
        if (!$validation->valid) {
            return $this->error(422, 'customer_validation_failed', $validation->message, $validation->field);
        }

        $customer = $this->repository->create($validation->data);
        if ($this->auditLog !== null) {
            $this->auditLog->record($actor, 'customer.create', 'cc_card', (string)($customer['id'] ?? ''), [
                'username' => $customer['username'] ?? '',
                'id_group' => $customer['id_group'] ?? null,
            ]);
        }

        return [
            'status' => 201,
            'body' => [
                'success' => true,
                'customer' => $customer,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function update(int $id, array $payload, string $actor): array
    {
        if ($this->repository->findById($id) === null) {
            return $this->error(404, 'customer_not_found', 'Customer was not found.', 'id');
        }

        $validation = $this->validate($payload, $id);
        if (!$validation->valid) {
            return $this->error(422, 'customer_validation_failed', $validation->message, $validation->field);
        }

        $customer = $this->repository->update($id, $validation->data);
        if ($customer !== null && $this->auditLog !== null) {
            $this->auditLog->record($actor, 'customer.update', 'cc_card', (string)$id, [
                'fields' => array_keys($validation->data),
            ]);
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'customer' => $customer,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validate(array $payload, ?int $existingId): CustomerAccountValidationResult
    {
        $required = $existingId === null ? ['username', 'useralias', 'firstname', 'lastname', 'email'] : [];
        foreach ($required as $field) {
            if ($this->stringValue($payload, $field) === '') {
                return new CustomerAccountValidationResult(false, message: $field . ' is required.', field: $field);
            }
        }

        $data = [];
        foreach (['external_id', 'username', 'useralias', 'firstname', 'lastname', 'email', 'address', 'city', 'state', 'country', 'zipcode', 'phone', 'company_name', 'company_website'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $this->stringValue($payload, $field);
            }
        }

        if (($data['external_id'] ?? '') !== '' && strlen($data['external_id']) > 128) {
            return new CustomerAccountValidationResult(false, message: 'external_id must be 128 characters or fewer.', field: 'external_id');
        }

        foreach (['username' => 50, 'useralias' => 50, 'firstname' => 50, 'lastname' => 50, 'email' => 70] as $field => $max) {
            if (($data[$field] ?? '') !== '' && strlen((string)$data[$field]) > $max) {
                return new CustomerAccountValidationResult(false, message: $field . ' must be ' . $max . ' characters or fewer.', field: $field);
            }
        }

        foreach (['username', 'useralias'] as $field) {
            if (($data[$field] ?? '') !== '' && preg_match('/^[A-Za-z0-9_.@-]+$/', (string)$data[$field]) !== 1) {
                return new CustomerAccountValidationResult(false, message: $field . ' may only contain letters, numbers, dot, underscore, at, or dash.', field: $field);
            }
        }

        if (($data['email'] ?? '') !== '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            return new CustomerAccountValidationResult(false, message: 'email must be a valid email address.', field: 'email');
        }

        foreach (['username', 'useralias', 'email'] as $field) {
            if (($data[$field] ?? '') !== '' && $this->repository->valueExists($field, (string)$data[$field], $existingId)) {
                return new CustomerAccountValidationResult(false, message: $field . ' must be unique.', field: $field);
            }
        }

        if (array_key_exists('currency', $payload)) {
            $currency = strtoupper($this->stringValue($payload, 'currency'));
            if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                return new CustomerAccountValidationResult(false, message: 'currency must be a three-letter ISO code.', field: 'currency');
            }
            $data['currency'] = $currency;
        } elseif ($existingId === null) {
            $data['currency'] = 'USD';
        }

        foreach (['status', 'id_group'] as $field) {
            if (array_key_exists($field, $payload)) {
                $value = $this->intValue($payload, $field);
                if ($value === null || ($field === 'status' && !in_array($value, [0, 1], true)) || ($field === 'id_group' && $value <= 0)) {
                    return new CustomerAccountValidationResult(false, message: $field . ' is invalid.', field: $field);
                }
                $data[$field] = $value;
            }
        }
        if ($existingId === null) {
            $data['status'] ??= 1;
            $data['id_group'] ??= 1;
            $data['activated'] = '1';
        }

        return new CustomerAccountValidationResult(true, $data);
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
        return [
            'status' => $status,
            'body' => [
                'success' => false,
                'code' => $code,
                'message' => $message,
                'field' => $field,
            ],
        ];
    }
}

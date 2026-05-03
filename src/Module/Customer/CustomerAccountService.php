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
     * @return array<string, mixed>|null
     */
    public function detail(int $id): ?array
    {
        return $this->repository->findById($id);
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
}

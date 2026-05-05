<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Customer;

final class AdminCustomerWorkspaceService
{
    public function __construct(private readonly CustomerAccountService $service)
    {
    }

    /**
     * @return array{
     *     filters:array{search:string,status:string,limit:int,offset:int},
     *     summary:array{total:int,active:int,blocked:int},
     *     customers:array{items:list<array<string,mixed>>,columns:list<string>},
     *     groups:list<array{id:string,name:string}>
     * }
     */
    public function workspace(string $search, string $status, int $limit = 25, int $offset = 0): array
    {
        $search = trim($search);
        $status = trim($status);
        $statusValue = $this->normalizeStatus($status);
        $criteria = new CustomerSearchCriteria($limit, $offset, $search, $statusValue);

        return [
            'filters' => [
                'search' => $search,
                'status' => $status,
                'limit' => $limit,
                'offset' => $offset,
            ],
            'summary' => $this->service->summary($criteria),
            'customers' => $this->service->search($criteria),
            'groups' => $this->service->groups(),
        ];
    }

    private function normalizeStatus(string $status): ?int
    {
        if ($status === '') {
            return null;
        }

        if ($status === '0' || $status === '1') {
            return (int)$status;
        }

        throw new \InvalidArgumentException('Status filter must be empty, 0, or 1.');
    }
}

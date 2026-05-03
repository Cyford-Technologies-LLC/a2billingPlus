<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Customer;

final class CustomerAccountService
{
    public function __construct(private readonly CustomerAccountRepository $repository)
    {
    }

    /**
     * @return array{items:list<array<string, mixed>>,columns:list<string>}
     */
    public function search(CustomerSearchCriteria $criteria): array
    {
        return $this->repository->search($criteria);
    }
}

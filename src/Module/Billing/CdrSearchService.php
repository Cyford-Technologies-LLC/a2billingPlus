<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Billing;

final class CdrSearchService
{
    public function __construct(private readonly CdrRepository $repository)
    {
    }

    /**
     * @return array{items:list<array<string, mixed>>,columns:list<string>}
     */
    public function search(CdrSearchCriteria $criteria): array
    {
        return $this->repository->search($criteria);
    }
}

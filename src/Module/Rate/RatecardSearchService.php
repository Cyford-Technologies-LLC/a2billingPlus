<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Rate;

final class RatecardSearchService
{
    public function __construct(private readonly RatecardRepository $repository)
    {
    }

    /**
     * @return array{items:list<array<string, mixed>>,columns:list<string>}
     */
    public function search(RatecardSearchCriteria $criteria): array
    {
        return $this->repository->search($criteria);
    }
}

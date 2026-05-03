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

    /**
     * @return array<string, mixed>|null
     */
    public function detail(int $id): ?array
    {
        return $this->repository->find($id);
    }

    /**
     * @return array{items:list<array<string, mixed>>,columns:list<string>}
     */
    public function destinations(string $search, int $limit, int $offset): array
    {
        return $this->repository->destinations($search, $limit, $offset);
    }
}

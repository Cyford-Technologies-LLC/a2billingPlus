<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Rate;

final class PackageService
{
    public function __construct(private readonly PackageRepository $repository)
    {
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function list(int $limit, int $offset, string $search = ''): array
    {
        return $this->repository->list($limit, $offset, $search);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function detail(int $id): ?array
    {
        return $this->repository->find($id);
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function rates(int $packageId, int $limit, int $offset): array
    {
        return $this->repository->rates($packageId, $limit, $offset);
    }
}

<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Invoice;

final class ReceiptService
{
    public function __construct(private readonly ReceiptRepository $repository)
    {
    }

    /**
     * @return array{items:list<array<string, mixed>>,columns:list<string>}
     */
    public function search(ReceiptSearchCriteria $criteria): array
    {
        return $this->repository->search($criteria);
    }
}

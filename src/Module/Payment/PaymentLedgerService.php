<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Payment;

final class PaymentLedgerService
{
    public function __construct(private readonly PaymentLedgerRepository $repository)
    {
    }

    /**
     * @return array{items:list<array<string, mixed>>,columns:list<string>}
     */
    public function search(PaymentSearchCriteria $criteria): array
    {
        return $this->repository->search($criteria);
    }
}

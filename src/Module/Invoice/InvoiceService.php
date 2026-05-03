<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Invoice;

final class InvoiceService
{
    public function __construct(private readonly InvoiceRepository $repository)
    {
    }

    /**
     * @return array{items:list<array<string, mixed>>,columns:list<string>}
     */
    public function search(InvoiceSearchCriteria $criteria): array
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
     * @return array<string, mixed>|null
     */
    public function customerDetail(int $id, int $customerId): ?array
    {
        $invoice = $this->detail($id);
        if ($invoice === null || (int)($invoice['id_card'] ?? 0) !== $customerId) {
            return null;
        }

        return $invoice;
    }
}

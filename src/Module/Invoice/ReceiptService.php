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
        $receipt = $this->detail($id);
        if ($receipt === null || (int)($receipt['id_card'] ?? 0) !== $customerId) {
            return null;
        }

        return $receipt;
    }

    /**
     * @param array<string, mixed> $receipt
     * @return array<string, mixed>
     */
    public function downloadMetadata(array $receipt): array
    {
        $id = (int)($receipt['id'] ?? 0);

        return [
            'id' => $id,
            'filename' => 'receipt-' . $id . '.pdf',
            'content_type' => 'application/pdf',
            'available' => false,
            'message' => 'Receipt PDF generation is not module-backed yet.',
        ];
    }
}

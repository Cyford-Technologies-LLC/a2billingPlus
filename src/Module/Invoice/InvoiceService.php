<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Invoice;

final class InvoiceService
{
    public function __construct(
        private readonly InvoiceRepository $repository,
        private readonly ?InvoiceTaxSummaryRepository $taxSummaryRepository = null
    ) {
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

    /**
     * @param array<string, mixed> $invoice
     * @return array<string, mixed>
     */
    public function downloadMetadata(array $invoice): array
    {
        $reference = trim((string)($invoice['reference'] ?? ('invoice-' . (string)($invoice['id'] ?? ''))));

        return [
            'id' => (int)($invoice['id'] ?? 0),
            'reference' => $reference,
            'filename' => $reference . '.pdf',
            'content_type' => 'application/pdf',
            'available' => false,
            'message' => 'Invoice PDF generation is not module-backed yet.',
        ];
    }

    /**
     * @return array{subtotal:string,tax_total:string,total:string,rates:list<array{vat_rate:string,subtotal:string,tax_total:string,total:string}>}
     */
    public function taxSummary(int $invoiceId): array
    {
        return $this->taxSummaryRepository?->summarizeInvoice($invoiceId) ?? [
            'subtotal' => '0.00000',
            'tax_total' => '0.00000',
            'total' => '0.00000',
            'rates' => [],
        ];
    }
}

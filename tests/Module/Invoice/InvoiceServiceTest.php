<?php

declare(strict_types=1);

use A2BillingPlus\Module\Invoice\InvoiceRepository;
use A2BillingPlus\Module\Invoice\InvoiceSearchCriteria;
use A2BillingPlus\Module\Invoice\InvoiceService;
use A2BillingPlus\Module\Invoice\InvoiceTaxSummaryRepository;
use PHPUnit\Framework\TestCase;

final class InvoiceServiceTest extends TestCase
{
    public function testSearchFiltersByCustomerStatusPaidStatusAndDate(): void
    {
        $service = new InvoiceService(new InvoiceRepository($this->pdo()));

        $result = $service->search(new InvoiceSearchCriteria(10, 0, '2026-05-01 00:00:00', '2026-05-02 00:00:00', 1, 0, 1));

        $this->assertCount(1, $result['items']);
        $this->assertSame('INV-1', $result['items'][0]['reference']);
        $this->assertSame('May invoice', $result['items'][0]['title']);
    }

    public function testSearchHonorsLimitAndOffset(): void
    {
        $service = new InvoiceService(new InvoiceRepository($this->pdo()));

        $result = $service->search(new InvoiceSearchCriteria(1, 1));

        $this->assertCount(1, $result['items']);
        $this->assertSame('INV-2', $result['items'][0]['reference']);
    }

    public function testCustomerDetailRequiresOwnership(): void
    {
        $service = new InvoiceService(new InvoiceRepository($this->pdo()));

        $this->assertSame('INV-1', $service->customerDetail(1, 1)['reference']);
        $this->assertNull($service->customerDetail(3, 1));
    }

    public function testBuildsInvoiceDownloadMetadata(): void
    {
        $service = new InvoiceService(new InvoiceRepository($this->pdo()));

        $metadata = $service->downloadMetadata($service->detail(1));

        $this->assertSame('INV-1.pdf', $metadata['filename']);
        $this->assertSame('application/pdf', $metadata['content_type']);
        $this->assertFalse($metadata['available']);
    }

    public function testBuildsTaxSummaryFromInvoiceItems(): void
    {
        $pdo = $this->pdo();
        $service = new InvoiceService(new InvoiceRepository($pdo), new InvoiceTaxSummaryRepository($pdo));

        $summary = $service->taxSummary(1);

        $this->assertSame('30.00000', $summary['subtotal']);
        $this->assertSame('4.00000', $summary['tax_total']);
        $this->assertSame('34.00000', $summary['total']);
        $this->assertCount(2, $summary['rates']);
        $this->assertSame('0.00000', $summary['rates'][0]['vat_rate']);
        $this->assertSame('20.00000', $summary['rates'][1]['vat_rate']);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cc_invoice (
                id INTEGER PRIMARY KEY,
                reference TEXT,
                id_card INTEGER,
                date TEXT,
                paid_status INTEGER,
                status INTEGER,
                title TEXT,
                description TEXT
            )'
        );
        $pdo->exec(
            'CREATE TABLE cc_invoice_item (
                id INTEGER PRIMARY KEY,
                id_invoice INTEGER,
                date TEXT,
                price TEXT,
                VAT TEXT,
                description TEXT
            )'
        );
        $pdo->exec("INSERT INTO cc_invoice (id, reference, id_card, date, paid_status, status, title, description) VALUES (3, 'INV-3', 2, '2026-05-03 10:00:00', 0, 0, 'Other invoice', 'Other customer')");
        $pdo->exec("INSERT INTO cc_invoice (id, reference, id_card, date, paid_status, status, title, description) VALUES (2, 'INV-2', 1, '2026-05-02 10:00:00', 0, 0, 'Second invoice', 'Open invoice')");
        $pdo->exec("INSERT INTO cc_invoice (id, reference, id_card, date, paid_status, status, title, description) VALUES (1, 'INV-1', 1, '2026-05-01 10:00:00', 1, 0, 'May invoice', 'Paid invoice')");
        $pdo->exec("INSERT INTO cc_invoice_item (id, id_invoice, date, price, VAT, description) VALUES (1, 1, '2026-05-01 10:00:00', '10.00000', '20.00', 'Calls')");
        $pdo->exec("INSERT INTO cc_invoice_item (id, id_invoice, date, price, VAT, description) VALUES (2, 1, '2026-05-01 10:00:00', '10.00000', '20.00', 'More calls')");
        $pdo->exec("INSERT INTO cc_invoice_item (id, id_invoice, date, price, VAT, description) VALUES (3, 1, '2026-05-01 10:00:00', '10.00000', '0.00', 'Credit')");

        return $pdo;
    }
}

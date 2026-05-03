<?php

declare(strict_types=1);

use A2BillingPlus\Module\Invoice\InvoiceRepository;
use A2BillingPlus\Module\Invoice\InvoiceSearchCriteria;
use A2BillingPlus\Module\Invoice\InvoiceService;
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
        $pdo->exec("INSERT INTO cc_invoice (id, reference, id_card, date, paid_status, status, title, description) VALUES (3, 'INV-3', 2, '2026-05-03 10:00:00', 0, 0, 'Other invoice', 'Other customer')");
        $pdo->exec("INSERT INTO cc_invoice (id, reference, id_card, date, paid_status, status, title, description) VALUES (2, 'INV-2', 1, '2026-05-02 10:00:00', 0, 0, 'Second invoice', 'Open invoice')");
        $pdo->exec("INSERT INTO cc_invoice (id, reference, id_card, date, paid_status, status, title, description) VALUES (1, 'INV-1', 1, '2026-05-01 10:00:00', 1, 0, 'May invoice', 'Paid invoice')");

        return $pdo;
    }
}

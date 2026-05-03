<?php

declare(strict_types=1);

use A2BillingPlus\Module\Invoice\ReceiptRepository;
use A2BillingPlus\Module\Invoice\ReceiptSearchCriteria;
use A2BillingPlus\Module\Invoice\ReceiptService;
use PHPUnit\Framework\TestCase;

final class ReceiptServiceTest extends TestCase
{
    public function testSearchFiltersByCustomerStatusAndDate(): void
    {
        $service = new ReceiptService(new ReceiptRepository($this->pdo()));

        $result = $service->search(new ReceiptSearchCriteria(10, 0, '2026-05-01 00:00:00', '2026-05-02 00:00:00', 1, 0));

        $this->assertCount(1, $result['items']);
        $this->assertSame('Receipt 1', $result['items'][0]['title']);
        $this->assertSame('Payment receipt', $result['items'][0]['description']);
    }

    public function testSearchHonorsLimitAndOffset(): void
    {
        $service = new ReceiptService(new ReceiptRepository($this->pdo()));

        $result = $service->search(new ReceiptSearchCriteria(1, 1));

        $this->assertCount(1, $result['items']);
        $this->assertSame('Receipt 2', $result['items'][0]['title']);
    }

    public function testCustomerDetailRequiresOwnership(): void
    {
        $service = new ReceiptService(new ReceiptRepository($this->pdo()));

        $this->assertSame('Receipt 1', $service->customerDetail(1, 1)['title']);
        $this->assertNull($service->customerDetail(3, 1));
    }

    public function testBuildsReceiptDownloadMetadata(): void
    {
        $service = new ReceiptService(new ReceiptRepository($this->pdo()));

        $metadata = $service->downloadMetadata($service->detail(1));

        $this->assertSame('receipt-1.pdf', $metadata['filename']);
        $this->assertSame('application/pdf', $metadata['content_type']);
        $this->assertFalse($metadata['available']);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cc_receipt (
                id INTEGER PRIMARY KEY,
                id_card INTEGER,
                date TEXT,
                title TEXT,
                description TEXT,
                status INTEGER
            )'
        );
        $pdo->exec("INSERT INTO cc_receipt (id, id_card, date, title, description, status) VALUES (3, 2, '2026-05-03 10:00:00', 'Receipt 3', 'Other customer', 0)");
        $pdo->exec("INSERT INTO cc_receipt (id, id_card, date, title, description, status) VALUES (2, 1, '2026-05-02 10:00:00', 'Receipt 2', 'Second receipt', 1)");
        $pdo->exec("INSERT INTO cc_receipt (id, id_card, date, title, description, status) VALUES (1, 1, '2026-05-01 10:00:00', 'Receipt 1', 'Payment receipt', 0)");

        return $pdo;
    }
}

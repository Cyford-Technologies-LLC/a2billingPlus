<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiCustomerContextAuthenticator;
use A2BillingPlus\Api\CustomerDocumentController;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;
use PHPUnit\Framework\TestCase;

final class CustomerDocumentControllerTest extends TestCase
{
    public function testListsOnlySignedCustomerInvoices(): void
    {
        $response = $this->controller()->handle('customer-invoices', new JsonRequest('GET', [], [], $this->headers(1)));
        $payload = $response->getPayload();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $payload['data']['invoices']);
        $this->assertSame(1, $payload['meta']['customer_id']);
    }

    public function testInvoiceDetailRequiresOwnership(): void
    {
        $controller = $this->controller();

        $owned = $controller->handle('customer-invoices', new JsonRequest('GET', ['id' => '1'], [], $this->headers(1)));
        $other = $controller->handle('customer-invoices', new JsonRequest('GET', ['id' => '3'], [], $this->headers(1)));

        $this->assertSame(200, $owned->getStatusCode());
        $this->assertSame('INV-1', $owned->getPayload()['data']['invoice']['reference']);
        $this->assertSame(404, $other->getStatusCode());
    }

    public function testListsOnlySignedCustomerReceipts(): void
    {
        $response = $this->controller()->handle('customer-receipts', new JsonRequest('GET', [], [], $this->headers(1)));
        $payload = $response->getPayload();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $payload['data']['receipts']);
        $this->assertSame(1, $payload['meta']['customer_id']);
    }

    public function testReceiptDetailRequiresOwnership(): void
    {
        $controller = $this->controller();

        $owned = $controller->handle('customer-receipts', new JsonRequest('GET', ['id' => '1'], [], $this->headers(1)));
        $other = $controller->handle('customer-receipts', new JsonRequest('GET', ['id' => '3'], [], $this->headers(1)));

        $this->assertSame(200, $owned->getStatusCode());
        $this->assertSame('Receipt 1', $owned->getPayload()['data']['receipt']['title']);
        $this->assertSame(404, $other->getStatusCode());
    }

    public function testRejectsUnsignedDocumentAccess(): void
    {
        $response = $this->controller()->handle('customer-invoices', new JsonRequest('GET'));

        $this->assertSame(401, $response->getStatusCode());
    }

    private function controller(): CustomerDocumentController
    {
        $pdo = $this->pdo();

        return new CustomerDocumentController(
            new ApiCustomerContextAuthenticator(new AppConfig(['A2BP_CUSTOMER_API_SECRET' => 'customer-secret'])),
            fn (): PDO => $pdo
        );
    }

    /**
     * @return array<string,string>
     */
    private function headers(int $customerId): array
    {
        return [
            'X-A2BP-Customer-Id' => (string)$customerId,
            'X-A2BP-Customer-Signature' => hash_hmac('sha256', (string)$customerId, 'customer-secret'),
        ];
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE cc_invoice (id INTEGER PRIMARY KEY, reference TEXT, id_card INTEGER, date TEXT, paid_status INTEGER, status INTEGER, title TEXT, description TEXT)');
        $pdo->exec('CREATE TABLE cc_receipt (id INTEGER PRIMARY KEY, id_card INTEGER, date TEXT, title TEXT, description TEXT, status INTEGER)');
        $pdo->exec("INSERT INTO cc_invoice (id, reference, id_card, date, paid_status, status, title, description) VALUES (3, 'INV-3', 2, '2026-05-03', 0, 0, 'Other invoice', 'Other customer')");
        $pdo->exec("INSERT INTO cc_invoice (id, reference, id_card, date, paid_status, status, title, description) VALUES (2, 'INV-2', 1, '2026-05-02', 0, 0, 'Second invoice', 'Open invoice')");
        $pdo->exec("INSERT INTO cc_invoice (id, reference, id_card, date, paid_status, status, title, description) VALUES (1, 'INV-1', 1, '2026-05-01', 1, 0, 'May invoice', 'Paid invoice')");
        $pdo->exec("INSERT INTO cc_receipt (id, id_card, date, title, description, status) VALUES (3, 2, '2026-05-03', 'Receipt 3', 'Other customer', 0)");
        $pdo->exec("INSERT INTO cc_receipt (id, id_card, date, title, description, status) VALUES (2, 1, '2026-05-02', 'Receipt 2', 'Second receipt', 1)");
        $pdo->exec("INSERT INTO cc_receipt (id, id_card, date, title, description, status) VALUES (1, 1, '2026-05-01', 'Receipt 1', 'Payment receipt', 0)");

        return $pdo;
    }
}

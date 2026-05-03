<?php

declare(strict_types=1);

use A2BillingPlus\Module\Payment\PaymentLedgerRepository;
use A2BillingPlus\Module\Payment\PaymentLedgerService;
use A2BillingPlus\Module\Payment\PaymentSearchCriteria;
use PHPUnit\Framework\TestCase;

final class PaymentLedgerServiceTest extends TestCase
{
    public function testSearchFiltersByDateAndCustomer(): void
    {
        $service = new PaymentLedgerService(new PaymentLedgerRepository($this->pdo()));

        $result = $service->search(new PaymentSearchCriteria(10, 0, '2026-05-01 00:00:00', '2026-05-02 00:00:00', 1));

        $this->assertCount(1, $result['items']);
        $this->assertSame('10.00', $result['items'][0]['payment']);
        $this->assertSame('Stripe test', $result['items'][0]['description']);
    }

    public function testSearchHonorsLimitAndOffset(): void
    {
        $service = new PaymentLedgerService(new PaymentLedgerRepository($this->pdo()));

        $result = $service->search(new PaymentSearchCriteria(1, 1));

        $this->assertCount(1, $result['items']);
        $this->assertSame('20.00', $result['items'][0]['payment']);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE cc_logpayment (id INTEGER PRIMARY KEY, date TEXT, payment TEXT, card_id INTEGER, reseller_id INTEGER, description TEXT, added_refill TEXT)');
        $pdo->exec("INSERT INTO cc_logpayment (id, date, payment, card_id, reseller_id, description, added_refill) VALUES (3, '2026-05-03 10:00:00', '30.00', 2, 0, 'Other customer', '30.00')");
        $pdo->exec("INSERT INTO cc_logpayment (id, date, payment, card_id, reseller_id, description, added_refill) VALUES (2, '2026-05-02 10:00:00', '20.00', 1, 0, 'Second payment', '20.00')");
        $pdo->exec("INSERT INTO cc_logpayment (id, date, payment, card_id, reseller_id, description, added_refill) VALUES (1, '2026-05-01 10:00:00', '10.00', 1, 0, 'Stripe test', '10.00')");

        return $pdo;
    }
}

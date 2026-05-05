<?php

declare(strict_types=1);

use A2BillingPlus\Module\Payment\AdminPaymentWorkspaceService;
use A2BillingPlus\Module\Payment\PaymentLedgerRepository;
use A2BillingPlus\Module\Payment\PaymentLedgerService;
use A2BillingPlus\Module\Payment\PaymentReconciliationService;
use PHPUnit\Framework\TestCase;

final class AdminPaymentWorkspaceServiceTest extends TestCase
{
    public function testBuildsWorkspaceWithSummaryAndLedger(): void
    {
        $workspace = $this->service()->workspace('2026-05-01', '2026-05-03', '1', 10);

        self::assertSame('2026-05-01 00:00:00', $workspace['filters']['from']);
        self::assertSame('2026-05-03 23:59:59', $workspace['filters']['to']);
        self::assertSame(2, $workspace['summary']['payments']);
        self::assertSame('30.00000', $workspace['summary']['total_paid']);
        self::assertCount(2, $workspace['ledger']['items']);
    }

    public function testRejectsInvalidCustomerFilter(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->workspace('2026-05-01', '2026-05-03', 'abc', 10);
    }

    private function service(): AdminPaymentWorkspaceService
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE cc_logpayment (id INTEGER PRIMARY KEY, date TEXT, payment TEXT, card_id INTEGER, reseller_id INTEGER, description TEXT, added_refill TEXT)');
        $pdo->exec("INSERT INTO cc_logpayment (id, date, payment, card_id, reseller_id, description, added_refill) VALUES (1, '2026-05-01 10:00:00', '10.00', 1, 0, 'Stripe test', '9.50')");
        $pdo->exec("INSERT INTO cc_logpayment (id, date, payment, card_id, reseller_id, description, added_refill) VALUES (2, '2026-05-02 10:00:00', '20.00', 1, 0, 'Second payment', '20.50')");
        $pdo->exec("INSERT INTO cc_logpayment (id, date, payment, card_id, reseller_id, description, added_refill) VALUES (3, '2026-05-04 10:00:00', '30.00', 2, 0, 'Outside range', '30.00')");

        return new AdminPaymentWorkspaceService(
            new PaymentLedgerService(new PaymentLedgerRepository($pdo)),
            new PaymentReconciliationService($pdo)
        );
    }
}

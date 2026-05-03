<?php

declare(strict_types=1);

use A2BillingPlus\Module\Payment\PaymentReconciliationService;
use PHPUnit\Framework\TestCase;

final class PaymentReconciliationServiceTest extends TestCase
{
    public function testSummarizesPaidVersusRefilledTotals(): void
    {
        $summary = (new PaymentReconciliationService($this->pdo()))->summarize(
            '2026-05-01 00:00:00',
            '2026-05-03 00:00:00'
        );

        $this->assertSame(2, $summary['payments']);
        $this->assertSame('30.00000', $summary['total_paid']);
        $this->assertSame('29.50000', $summary['total_refilled']);
        $this->assertSame('0.50000', $summary['difference']);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE cc_logpayment (id INTEGER PRIMARY KEY, date TEXT, payment TEXT, added_refill TEXT)');
        $pdo->exec("INSERT INTO cc_logpayment (id, date, payment, added_refill) VALUES (1, '2026-05-01 10:00:00', '10.00', '9.50')");
        $pdo->exec("INSERT INTO cc_logpayment (id, date, payment, added_refill) VALUES (2, '2026-05-02 10:00:00', '20.00', '20.00')");
        $pdo->exec("INSERT INTO cc_logpayment (id, date, payment, added_refill) VALUES (3, '2026-05-03 10:00:00', '30.00', '30.00')");

        return $pdo;
    }
}

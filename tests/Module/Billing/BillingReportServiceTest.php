<?php

declare(strict_types=1);

use A2BillingPlus\Module\Billing\BillingReportService;
use PHPUnit\Framework\TestCase;

final class BillingReportServiceTest extends TestCase
{
    public function testCalculatesAsrAndAloc(): void
    {
        $service = new BillingReportService($this->pdo());
        $summary = $service->callQualitySummary('2026-05-01 00:00:00', '2026-05-02 00:00:00');

        $this->assertSame(3, $summary['attempts']);
        $this->assertSame(2, $summary['answered_calls']);
        $this->assertSame('66.67', $summary['asr_percent']);
        $this->assertSame('90.00', $summary['aloc_seconds']);
    }

    public function testBuildsDailyQualityRowsWithTimezoneValidation(): void
    {
        $service = new BillingReportService($this->pdo());
        $rows = $service->dailyCallQuality('2026-05-01 00:00:00', '2026-05-03 00:00:00', 'America/New_York');

        $this->assertCount(2, $rows);
        $this->assertSame('2026-04-30', $rows[0]['report_date']);
        $this->assertSame('66.67', $rows[0]['asr_percent']);
        $this->assertSame('100.00', $rows[1]['asr_percent']);
    }

    public function testCalculatesProviderReconciliation(): void
    {
        $service = new BillingReportService($this->pdo());
        $summary = $service->reconciliationSummary('2026-05-01 00:00:00', '2026-05-03 00:00:00');

        $this->assertSame(4, $summary['calls']);
        $this->assertSame('0.30000', $summary['customer_revenue']);
        $this->assertSame('0.17000', $summary['provider_cost']);
        $this->assertSame('0.13000', $summary['gross_margin']);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cc_call (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                starttime TEXT,
                sessiontime INTEGER,
                terminatecauseid INTEGER,
                sessionbill TEXT,
                buycost TEXT
            )'
        );
        $pdo->exec("INSERT INTO cc_call (starttime, sessiontime, terminatecauseid, sessionbill, buycost) VALUES ('2026-05-01 01:00:00', 60, 1, '0.10000', '0.06000')");
        $pdo->exec("INSERT INTO cc_call (starttime, sessiontime, terminatecauseid, sessionbill, buycost) VALUES ('2026-05-01 02:00:00', 120, 1, '0.18000', '0.10000')");
        $pdo->exec("INSERT INTO cc_call (starttime, sessiontime, terminatecauseid, sessionbill, buycost) VALUES ('2026-05-01 03:00:00', 0, 3, '0.00000', '0.00000')");
        $pdo->exec("INSERT INTO cc_call (starttime, sessiontime, terminatecauseid, sessionbill, buycost) VALUES ('2026-05-02 01:00:00', 30, 1, '0.02000', '0.01000')");

        return $pdo;
    }
}

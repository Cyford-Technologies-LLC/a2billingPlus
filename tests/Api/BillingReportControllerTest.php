<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Api\BillingReportController;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;
use PHPUnit\Framework\TestCase;

final class BillingReportControllerTest extends TestCase
{
    public function testReturnsCallQualityReport(): void
    {
        $response = $this->controller()->handle($this->request([
            'report' => 'call_quality',
            'from' => '2026-05-01',
            'to' => '2026-05-02',
        ]));

        $payload = $response->getPayload();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(3, $payload['data']['call_quality']['attempts']);
        $this->assertSame('66.67', $payload['data']['call_quality']['asr_percent']);
    }

    public function testReturnsDailyCallQualityReport(): void
    {
        $response = $this->controller()->handle($this->request([
            'report' => 'daily_call_quality',
            'from' => '2026-05-01',
            'to' => '2026-05-03',
            'timezone' => 'America/New_York',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('2026-04-30', $response->getPayload()['data']['daily_call_quality'][0]['report_date']);
    }

    public function testReturnsReconciliationReport(): void
    {
        $response = $this->controller()->handle($this->request([
            'report' => 'reconciliation',
            'from' => '2026-05-01',
            'to' => '2026-05-03',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('0.13000', $response->getPayload()['data']['reconciliation']['gross_margin']);
    }

    public function testRejectsUnknownReport(): void
    {
        $response = $this->controller()->handle($this->request([
            'report' => 'unknown',
            'from' => '2026-05-01',
            'to' => '2026-05-03',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('invalid_report', $response->getPayload()['error']['code']);
    }

    private function controller(): BillingReportController
    {
        $pdo = $this->pdo();

        return new BillingReportController(
            new ApiServiceKeyAuthenticator(new AppConfig(['A2BP_API_SERVICE_KEY' => 'secret-key'])),
            fn (): PDO => $pdo
        );
    }

    /**
     * @param array<string,mixed> $query
     */
    private function request(array $query): JsonRequest
    {
        return new JsonRequest('GET', $query, [], [
            'Authorization' => 'Bearer secret-key',
        ]);
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

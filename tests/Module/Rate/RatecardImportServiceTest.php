<?php

declare(strict_types=1);

use A2BillingPlus\Module\Rate\RatecardImportService;
use A2BillingPlus\Module\Rate\RatecardRowMapper;
use PHPUnit\Framework\TestCase;

final class RatecardImportServiceTest extends TestCase
{
    public function testMapsProviderRowsToRatecardRows(): void
    {
        $mapper = new RatecardRowMapper();
        $row = $mapper->map([
            'destination' => 'United States',
            'prefix' => '1',
            'rate' => '0.01',
            'increment' => 60,
        ], 12, 'VectaVoIP:retail');

        $this->assertSame(12, $row['idtariffplan']);
        $this->assertSame('1', $row['dialprefix']);
        $this->assertSame('0.01000', $row['rateinitial']);
        $this->assertSame(60, $row['billingblock']);
        $this->assertSame('VectaVoIP:retail', $row['tag']);
    }

    public function testImportsRowsIntoRatecardTable(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cc_ratecard (
                idtariffplan INTEGER,
                dialprefix TEXT,
                destination INTEGER,
                buyrate TEXT,
                buyrateinitblock INTEGER,
                buyrateincrement INTEGER,
                rateinitial TEXT,
                initblock INTEGER,
                billingblock INTEGER,
                tag TEXT
            )'
        );

        $service = new RatecardImportService($pdo);
        $summary = $service->importRows([
            ['destination' => 'United States', 'prefix' => '1', 'rate' => '0.0100', 'increment' => 60],
            ['destination' => 'Broken', 'prefix' => '', 'rate' => '0.0200'],
        ], 7, 'VectaVoIP:retail', false);

        $this->assertTrue($summary->isSuccessful());
        $this->assertSame(1, $summary->getImportedRows());
        $this->assertSame(1, $summary->getSkippedRows());
        $this->assertSame('1', $pdo->query('SELECT dialprefix FROM cc_ratecard')->fetchColumn());
    }
}

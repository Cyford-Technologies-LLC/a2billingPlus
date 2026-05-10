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
        $this->assertSame('1', $row['destination']);
        $this->assertSame('United States', $row['destination_name']);
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
                id INTEGER PRIMARY KEY AUTOINCREMENT,
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

    public function testSkipsDuplicateRowsUnlessUpdateExistingIsEnabled(): void
    {
        $pdo = $this->ratecardPdo();
        $service = new RatecardImportService($pdo);

        $rows = [
            ['destination' => 'United States', 'prefix' => '1', 'rate' => '0.0100', 'increment' => 60],
        ];

        $first = $service->importRows($rows, 7, 'VectaVoIP:retail', false);
        $second = $service->importRows($rows, 7, 'VectaVoIP:retail', false);

        $this->assertSame(1, $first->getImportedRows());
        $this->assertSame(0, $second->getImportedRows());
        $this->assertSame(1, $second->getSkippedRows());
        $this->assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM cc_ratecard')->fetchColumn());

        $updated = $service->importRows([
            ['destination' => 'United States', 'prefix' => '1', 'rate' => '0.0200', 'increment' => 30],
        ], 7, 'VectaVoIP:retail', false, true);

        $this->assertSame(1, $updated->getImportedRows());
        $this->assertSame('0.02000', $pdo->query('SELECT rateinitial FROM cc_ratecard')->fetchColumn());
        $this->assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM cc_ratecard')->fetchColumn());
    }

    public function testImportsRowsWhenLegacyMusiconholdColumnHasNoDefault(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cc_ratecard (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                idtariffplan INTEGER,
                dialprefix TEXT,
                destination INTEGER,
                buyrate TEXT,
                buyrateinitblock INTEGER,
                buyrateincrement INTEGER,
                rateinitial TEXT,
                initblock INTEGER,
                billingblock INTEGER,
                tag TEXT,
                musiconhold TEXT NOT NULL
            )'
        );

        $service = new RatecardImportService($pdo);
        $summary = $service->importRows([
            ['destination' => 'United States', 'prefix' => '1', 'rate' => '0.0100', 'increment' => 60],
        ], 7, 'VectaVoIP:retail', false);

        $this->assertTrue($summary->isSuccessful());
        $this->assertSame(1, $summary->getImportedRows());
        $this->assertSame('', $pdo->query('SELECT musiconhold FROM cc_ratecard')->fetchColumn());
    }

    public function testImportsDestinationIntoPrefixTable(): void
    {
        $pdo = $this->ratecardPdo();
        $pdo->exec('CREATE TABLE cc_prefix (prefix INTEGER PRIMARY KEY, destination TEXT NOT NULL)');

        $service = new RatecardImportService($pdo);
        $summary = $service->importRows([
            ['destination' => 'United States Toll-Free', 'prefix' => '1800', 'rate' => '0.0100', 'increment' => 60],
        ], 7, 'VectaVoIP:retail', false);

        $this->assertTrue($summary->isSuccessful());
        $this->assertSame('1800', $pdo->query('SELECT destination FROM cc_ratecard')->fetchColumn());
        $this->assertSame('United States Toll-Free', $pdo->query('SELECT destination FROM cc_prefix WHERE prefix = 1800')->fetchColumn());
    }

    private function ratecardPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cc_ratecard (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
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

        return $pdo;
    }
}

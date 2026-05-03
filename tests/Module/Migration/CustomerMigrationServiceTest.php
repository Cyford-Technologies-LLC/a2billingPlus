<?php

declare(strict_types=1);

use A2BillingPlus\Module\Migration\CustomerMigrationService;
use PHPUnit\Framework\TestCase;

final class CustomerMigrationServiceTest extends TestCase
{
    public function testDryRunCountsInsertsAndUpdates(): void
    {
        $source = $this->pdoWithCardTable();
        $target = $this->pdoWithCardTable();

        $source->exec("INSERT INTO cc_card (id, username, useralias, credit) VALUES (1, '1001', '1001', '1.25')");
        $source->exec("INSERT INTO cc_card (id, username, useralias, credit) VALUES (2, '1002', '1002', '2.50')");
        $target->exec("INSERT INTO cc_card (id, username, useralias, credit) VALUES (2, '1002', '1002', '0.00')");

        $summary = (new CustomerMigrationService($source, $target))->migrateCustomers(true);

        $this->assertTrue($summary->isSuccessful());
        $this->assertSame(2, $summary->getScannedRows());
        $this->assertSame(1, $summary->getInsertedRows());
        $this->assertSame(1, $summary->getUpdatedRows());
        $this->assertSame(1, (int)$target->query('SELECT COUNT(*) FROM cc_card')->fetchColumn());
    }

    public function testMigratesCustomersIntoTarget(): void
    {
        $source = $this->pdoWithCardTable();
        $target = $this->pdoWithCardTable();

        $source->exec("INSERT INTO cc_card (id, username, useralias, credit) VALUES (1, '1001', '1001', '1.25')");
        $target->exec("INSERT INTO cc_card (id, username, useralias, credit) VALUES (1, 'old', 'old', '0.00')");

        $summary = (new CustomerMigrationService($source, $target))->migrateCustomers(false);

        $this->assertTrue($summary->isSuccessful());
        $this->assertSame(0, $summary->getInsertedRows());
        $this->assertSame(1, $summary->getUpdatedRows());
        $this->assertSame('1001', $target->query('SELECT username FROM cc_card WHERE id = 1')->fetchColumn());
        $this->assertSame('1.25', $target->query('SELECT credit FROM cc_card WHERE id = 1')->fetchColumn());
    }

    public function testMigratesSipAndIaxSettings(): void
    {
        $source = $this->pdoWithCardTable();
        $target = $this->pdoWithCardTable();
        $this->createVoipTables($source);
        $this->createVoipTables($target);

        $source->exec("INSERT INTO cc_sip_buddies (id, id_cc_card, name, accountcode, secret) VALUES (1, 10, '1001', '1001', 'sip-secret')");
        $source->exec("INSERT INTO cc_iax_buddies (id, id_cc_card, name, accountcode, secret) VALUES (2, 10, '1001-iax', '1001', 'iax-secret')");

        $summary = (new CustomerMigrationService($source, $target))->migrateVoipSettings(false);

        $this->assertTrue($summary->isSuccessful());
        $this->assertSame(2, $summary->getScannedRows());
        $this->assertSame(2, $summary->getInsertedRows());
        $this->assertSame('sip-secret', $target->query('SELECT secret FROM cc_sip_buddies WHERE name = "1001"')->fetchColumn());
        $this->assertSame('iax-secret', $target->query('SELECT secret FROM cc_iax_buddies WHERE name = "1001-iax"')->fetchColumn());
    }

    public function testMigratesCdrsByDateWindow(): void
    {
        $source = $this->pdoWithCardTable();
        $target = $this->pdoWithCardTable();
        $this->createCdrTable($source);
        $this->createCdrTable($target);

        $source->exec("INSERT INTO cc_call (id, uniqueid, starttime, sessiontime, calledstation, sessionbill) VALUES (1, 'old', '2026-04-30 23:00:00', 10, '100', '0.01')");
        $source->exec("INSERT INTO cc_call (id, uniqueid, starttime, sessiontime, calledstation, sessionbill) VALUES (2, 'in-window', '2026-05-01 12:00:00', 60, '18005551212', '0.10')");
        $source->exec("INSERT INTO cc_call (id, uniqueid, starttime, sessiontime, calledstation, sessionbill) VALUES (3, 'new', '2026-05-03 00:00:00', 20, '101', '0.02')");

        $summary = (new CustomerMigrationService($source, $target))->migrateCdrs(false, 0, '2026-05-01 00:00:00', '2026-05-03 00:00:00');

        $this->assertTrue($summary->isSuccessful());
        $this->assertSame(1, $summary->getScannedRows());
        $this->assertSame(1, $summary->getInsertedRows());
        $this->assertSame('in-window', $target->query('SELECT uniqueid FROM cc_call')->fetchColumn());
    }

    public function testFormatsOperatorDryRunReport(): void
    {
        $summary = new \A2BillingPlus\Module\Migration\MigrationSummary(true, 10, 7, 2, 1, 'ok');
        $payload = (new \A2BillingPlus\Module\Migration\MigrationReportFormatter())->jsonPayload(['customers' => $summary], true, 'customers');

        $this->assertTrue($payload['success']);
        $this->assertTrue($payload['dry_run']);
        $this->assertSame(10, $payload['scanned_rows']);
        $this->assertSame(7, $payload['inserted_rows']);
        $this->assertSame('ok', $payload['results']['customers']['message']);
    }

    private function pdoWithCardTable(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cc_card (
                id INTEGER PRIMARY KEY,
                username TEXT NOT NULL,
                useralias TEXT NOT NULL,
                credit TEXT NOT NULL
            )'
        );

        return $pdo;
    }

    private function createVoipTables(PDO $pdo): void
    {
        foreach (['cc_sip_buddies', 'cc_iax_buddies'] as $tableName) {
            $pdo->exec(
                'CREATE TABLE ' . $tableName . ' (
                    id INTEGER PRIMARY KEY,
                    id_cc_card INTEGER NOT NULL,
                    name TEXT NOT NULL,
                    accountcode TEXT NOT NULL,
                    secret TEXT NOT NULL
                )'
            );
        }
    }

    private function createCdrTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE cc_call (
                id INTEGER PRIMARY KEY,
                uniqueid TEXT NOT NULL,
                starttime TEXT NOT NULL,
                sessiontime INTEGER NOT NULL,
                calledstation TEXT NOT NULL,
                sessionbill TEXT NOT NULL
            )'
        );
    }
}

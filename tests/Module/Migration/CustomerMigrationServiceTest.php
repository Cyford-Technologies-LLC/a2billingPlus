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
}

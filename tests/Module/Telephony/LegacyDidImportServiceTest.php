<?php

declare(strict_types=1);

use A2BillingPlus\Module\Telephony\LegacyDidImportService;
use PHPUnit\Framework\TestCase;

final class LegacyDidImportServiceTest extends TestCase
{
    public function testImportsMissingInventoryIntoLegacyDidTable(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE cc_did (id INTEGER PRIMARY KEY AUTOINCREMENT, id_cc_didgroup INTEGER NOT NULL DEFAULT 0, id_cc_country INTEGER NOT NULL DEFAULT 0, activated INTEGER NOT NULL DEFAULT 1, reserved INTEGER DEFAULT 0, iduser INTEGER NOT NULL DEFAULT 0, did TEXT NOT NULL UNIQUE, startingdate TEXT NOT NULL DEFAULT \'0000-00-00 00:00:00\', expirationdate TEXT NOT NULL DEFAULT \'0000-00-00 00:00:00\', description TEXT NULL, billingtype INTEGER DEFAULT 0, fixrate REAL NOT NULL DEFAULT 0, max_concurrent INTEGER NOT NULL DEFAULT 10)');
        $pdo->exec('CREATE TABLE cc_country (id INTEGER PRIMARY KEY AUTOINCREMENT, countrycode TEXT, countryname TEXT)');
        $pdo->exec("INSERT INTO cc_country (id, countrycode, countryname) VALUES (1, 'US', 'UNITED STATES')");

        $service = new LegacyDidImportService($pdo);
        $result = $service->importMissingFromInventory([
            ['did' => '+14046090653', 'country' => 'US', 'monthly_rate' => '1.25', 'provider_code' => 'twilio', 'provider_trunk_name' => 'VectaVoip'],
        ]);

        $this->assertSame(['imported' => 1, 'skipped' => 0], $result);
        $this->assertSame('+14046090653', $pdo->query("SELECT did FROM cc_did LIMIT 1")->fetchColumn());
        $this->assertSame('1.25', (string) $pdo->query("SELECT fixrate FROM cc_did WHERE did = '+14046090653'")->fetchColumn());
        $this->assertSame('1', (string) $pdo->query("SELECT id_cc_country FROM cc_did WHERE did = '+14046090653'")->fetchColumn());
    }

    public function testSkipsExistingLegacyDidRows(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE cc_did (id INTEGER PRIMARY KEY AUTOINCREMENT, id_cc_didgroup INTEGER NOT NULL DEFAULT 0, id_cc_country INTEGER NOT NULL DEFAULT 0, activated INTEGER NOT NULL DEFAULT 1, reserved INTEGER DEFAULT 0, iduser INTEGER NOT NULL DEFAULT 0, did TEXT NOT NULL UNIQUE, startingdate TEXT NOT NULL DEFAULT \'0000-00-00 00:00:00\', expirationdate TEXT NOT NULL DEFAULT \'0000-00-00 00:00:00\', description TEXT NULL, billingtype INTEGER DEFAULT 0, fixrate REAL NOT NULL DEFAULT 0, max_concurrent INTEGER NOT NULL DEFAULT 10)');
        $pdo->exec("INSERT INTO cc_did (did, description) VALUES ('+14046090653', 'Existing DID')");

        $service = new LegacyDidImportService($pdo);
        $result = $service->importMissingFromInventory([
            ['did' => '+14046090653', 'country' => 'US', 'monthly_rate' => '1.25', 'provider_code' => 'twilio'],
        ]);

        $this->assertSame(['imported' => 0, 'skipped' => 1], $result);
        $this->assertSame('Existing DID', $pdo->query("SELECT description FROM cc_did WHERE did = '+14046090653'")->fetchColumn());
    }

    public function testImportsAllCachedInventoryRowsIntoLegacyDidTable(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE cc_did (id INTEGER PRIMARY KEY AUTOINCREMENT, id_cc_didgroup INTEGER NOT NULL DEFAULT 0, id_cc_country INTEGER NOT NULL DEFAULT 0, activated INTEGER NOT NULL DEFAULT 1, reserved INTEGER DEFAULT 0, iduser INTEGER NOT NULL DEFAULT 0, did TEXT NOT NULL UNIQUE, startingdate TEXT NOT NULL DEFAULT \'0000-00-00 00:00:00\', expirationdate TEXT NOT NULL DEFAULT \'0000-00-00 00:00:00\', description TEXT NULL, billingtype INTEGER DEFAULT 0, fixrate REAL NOT NULL DEFAULT 0, max_concurrent INTEGER NOT NULL DEFAULT 10)');
        $pdo->exec('CREATE TABLE cc_vectavoip_did_inventory (id INTEGER PRIMARY KEY AUTOINCREMENT, did TEXT NOT NULL UNIQUE, country TEXT NOT NULL DEFAULT \'\', region TEXT NOT NULL DEFAULT \'\', monthly_rate TEXT NOT NULL DEFAULT \'0.00000\', provider_code TEXT NOT NULL DEFAULT \'\', provider_trunk_name TEXT NOT NULL DEFAULT \'\', provider_reference TEXT NOT NULL DEFAULT \'\', provider_trunk_reference TEXT NOT NULL DEFAULT \'\', order_reference TEXT NOT NULL DEFAULT \'\')');
        $pdo->exec("INSERT INTO cc_vectavoip_did_inventory (did, country, monthly_rate, provider_code, provider_trunk_name) VALUES ('+14046090653', 'US', '1.25', 'twilio', 'VectaVoip')");

        $service = new LegacyDidImportService($pdo);
        $result = $service->importAllCachedInventory();

        $this->assertSame(['imported' => 1, 'skipped' => 0], $result);
        $this->assertSame('+14046090653', $pdo->query("SELECT did FROM cc_did LIMIT 1")->fetchColumn());
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
}

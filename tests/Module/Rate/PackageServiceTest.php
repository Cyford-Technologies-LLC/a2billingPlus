<?php

declare(strict_types=1);

use A2BillingPlus\Module\Rate\PackageRepository;
use A2BillingPlus\Module\Rate\PackageService;
use PHPUnit\Framework\TestCase;

final class PackageServiceTest extends TestCase
{
    public function testListsAndSearchesPackages(): void
    {
        $service = new PackageService(new PackageRepository($this->pdo()));

        $result = $service->list(10, 0, 'Starter');

        $this->assertCount(1, $result['items']);
        $this->assertSame('Starter Package', $result['items'][0]['label']);
        $this->assertContains('freetimetocall', $result['columns']);
    }

    public function testLoadsPackageDetailAndAssignedRates(): void
    {
        $service = new PackageService(new PackageRepository($this->pdo()));

        $package = $service->detail(1);
        $rates = $service->rates(1, 10, 0);

        $this->assertSame('Starter Package', $package['label']);
        $this->assertCount(1, $rates['items']);
        $this->assertSame('1', $rates['items'][0]['dialprefix']);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cc_package_offer (
                id INTEGER PRIMARY KEY,
                creationdate TEXT,
                label TEXT,
                packagetype INTEGER,
                billingtype INTEGER,
                startday INTEGER,
                freetimetocall INTEGER
            )'
        );
        $pdo->exec(
            'CREATE TABLE cc_ratecard (
                id INTEGER PRIMARY KEY,
                idtariffplan INTEGER,
                dialprefix TEXT,
                destination TEXT,
                buyrate TEXT,
                rateinitial TEXT,
                initblock INTEGER,
                billingblock INTEGER,
                tag TEXT
            )'
        );
        $pdo->exec('CREATE TABLE cc_package_rate (package_id INTEGER, rate_id INTEGER, PRIMARY KEY (package_id, rate_id))');
        $pdo->exec("INSERT INTO cc_package_offer (id, creationdate, label, packagetype, billingtype, startday, freetimetocall) VALUES (1, '2026-05-03', 'Starter Package', 0, 0, 1, 600)");
        $pdo->exec("INSERT INTO cc_package_offer (id, creationdate, label, packagetype, billingtype, startday, freetimetocall) VALUES (2, '2026-05-03', 'Wholesale Package', 1, 0, 1, 1200)");
        $pdo->exec("INSERT INTO cc_ratecard (id, idtariffplan, dialprefix, destination, buyrate, rateinitial, initblock, billingblock, tag) VALUES (1, 7, '1', 'United States', '0.0050', '0.0100', 60, 60, 'VectaVoIP:retail')");
        $pdo->exec('INSERT INTO cc_package_rate (package_id, rate_id) VALUES (1, 1)');

        return $pdo;
    }
}

<?php

declare(strict_types=1);

use A2BillingPlus\Module\Rate\AdminRateWorkspaceService;
use A2BillingPlus\Module\Rate\RatecardRepository;
use A2BillingPlus\Module\Rate\RatecardSearchService;
use A2BillingPlus\Module\Rate\TariffRepository;
use A2BillingPlus\Module\Rate\TariffService;
use PHPUnit\Framework\TestCase;

final class AdminRateWorkspaceServiceTest extends TestCase
{
    public function testWorkspaceCombinesRatesTariffsAndDestinations(): void
    {
        $service = $this->service();

        $workspace = $service->workspace('1', '7', 'VectaVoIP:retail', 'United', 25);

        $this->assertSame('1', $workspace['filters']['prefix']);
        $this->assertSame('7', $workspace['filters']['tariff_plan_id']);
        $this->assertSame('VectaVoIP:retail', $workspace['filters']['tag']);
        $this->assertSame(1, $workspace['summary']['rate_rows']);
        $this->assertSame(2, $workspace['summary']['tariff_plans']);
        $this->assertSame(1, $workspace['summary']['tariff_groups']);
        $this->assertSame(2, $workspace['summary']['destinations']);
        $this->assertSame('United States', $workspace['ratecards']['items'][0]['destination']);
        $this->assertContains('Retail', array_column($workspace['tariff_plans']['items'], 'tariffname'));
    }

    public function testWorkspaceNormalizesInvalidLimitAndTariffPlan(): void
    {
        $service = $this->service();

        $workspace = $service->workspace('', 'not-a-number', '', '', 500);

        $this->assertSame('', $workspace['filters']['tariff_plan_id']);
        $this->assertSame(25, $workspace['filters']['limit']);
        $this->assertSame(2, $workspace['summary']['rate_rows']);
    }

    private function service(): AdminRateWorkspaceService
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
        $pdo->exec(
            'CREATE TABLE cc_tariffplan (
                id INTEGER PRIMARY KEY,
                iduser INTEGER,
                tariffname TEXT,
                creationdate TEXT,
                description TEXT,
                id_trunk INTEGER,
                idowner INTEGER,
                dnidprefix TEXT,
                calleridprefix TEXT
            )'
        );
        $pdo->exec(
            'CREATE TABLE cc_tariffgroup (
                id INTEGER PRIMARY KEY,
                iduser INTEGER,
                idtariffplan INTEGER,
                tariffgroupname TEXT,
                lcrtype INTEGER,
                creationdate TEXT,
                removeinterprefix INTEGER,
                id_cc_package_offer INTEGER
            )'
        );
        $pdo->exec("INSERT INTO cc_ratecard (id, idtariffplan, dialprefix, destination, buyrate, rateinitial, initblock, billingblock, tag) VALUES (2, 7, '44', 'United Kingdom', '0.0100', '0.0180', 60, 60, 'VectaVoIP:retail')");
        $pdo->exec("INSERT INTO cc_ratecard (id, idtariffplan, dialprefix, destination, buyrate, rateinitial, initblock, billingblock, tag) VALUES (1, 7, '1', 'United States', '0.0050', '0.0100', 60, 60, 'VectaVoIP:retail')");
        $pdo->exec("INSERT INTO cc_tariffplan (id, iduser, tariffname, creationdate, description, id_trunk, idowner, dnidprefix, calleridprefix) VALUES (7, 0, 'Retail', '2026-05-05 00:00:00', 'Retail rates', 1, 0, 'all', 'all')");
        $pdo->exec("INSERT INTO cc_tariffplan (id, iduser, tariffname, creationdate, description, id_trunk, idowner, dnidprefix, calleridprefix) VALUES (8, 0, 'Wholesale', '2026-05-05 00:00:00', 'Wholesale rates', 1, 0, 'all', 'all')");
        $pdo->exec("INSERT INTO cc_tariffgroup (id, iduser, idtariffplan, tariffgroupname, lcrtype, creationdate, removeinterprefix, id_cc_package_offer) VALUES (1, 0, 7, 'Default Group', 0, '2026-05-05 00:00:00', 0, -1)");

        return new AdminRateWorkspaceService(
            new RatecardSearchService(new RatecardRepository($pdo)),
            new TariffService(new TariffRepository($pdo))
        );
    }
}

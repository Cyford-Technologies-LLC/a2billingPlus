<?php

declare(strict_types=1);

use A2BillingPlus\Module\Rate\RatecardRepository;
use A2BillingPlus\Module\Rate\RatecardSearchCriteria;
use A2BillingPlus\Module\Rate\RatecardSearchService;
use PHPUnit\Framework\TestCase;

final class RatecardSearchServiceTest extends TestCase
{
    public function testSearchFiltersByPrefixTariffPlanAndTag(): void
    {
        $service = new RatecardSearchService(new RatecardRepository($this->pdo()));

        $result = $service->search(new RatecardSearchCriteria(10, 0, '1', 7, 'VectaVoIP:retail'));

        $this->assertCount(1, $result['items']);
        $this->assertSame('1', $result['items'][0]['dialprefix']);
        $this->assertSame('0.0100', $result['items'][0]['rateinitial']);
    }

    public function testSearchHonorsLimitAndOffset(): void
    {
        $service = new RatecardSearchService(new RatecardRepository($this->pdo()));

        $result = $service->search(new RatecardSearchCriteria(1, 1));

        $this->assertCount(1, $result['items']);
        $this->assertSame('44', $result['items'][0]['dialprefix']);
    }

    public function testLoadsRatecardDetailById(): void
    {
        $service = new RatecardSearchService(new RatecardRepository($this->pdo()));

        $rate = $service->detail(3);

        $this->assertIsArray($rate);
        $this->assertSame('United States', $rate['destination']);
        $this->assertSame('1', $rate['dialprefix']);
    }

    public function testDestinationLookupReturnsDistinctDestinationRows(): void
    {
        $service = new RatecardSearchService(new RatecardRepository($this->pdo()));

        $result = $service->destinations('United', 10, 0);

        $this->assertCount(2, $result['items']);
        $this->assertSame('United Kingdom', $result['items'][0]['destination']);
        $this->assertSame('United States', $result['items'][1]['destination']);
    }

    private function pdo(): PDO
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
        $pdo->exec("INSERT INTO cc_ratecard (id, idtariffplan, dialprefix, destination, buyrate, rateinitial, initblock, billingblock, tag) VALUES (3, 7, '1', 'United States', '0.0050', '0.0100', 60, 60, 'VectaVoIP:retail')");
        $pdo->exec("INSERT INTO cc_ratecard (id, idtariffplan, dialprefix, destination, buyrate, rateinitial, initblock, billingblock, tag) VALUES (2, 7, '44', 'United Kingdom', '0.0100', '0.0180', 60, 60, 'VectaVoIP:retail')");
        $pdo->exec("INSERT INTO cc_ratecard (id, idtariffplan, dialprefix, destination, buyrate, rateinitial, initblock, billingblock, tag) VALUES (1, 8, '1', 'Canada', '0.0060', '0.0125', 60, 60, 'other')");

        return $pdo;
    }
}

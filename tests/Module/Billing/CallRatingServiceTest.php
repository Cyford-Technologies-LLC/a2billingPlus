<?php

declare(strict_types=1);

use A2BillingPlus\Module\Billing\CallRatingRequest;
use A2BillingPlus\Module\Billing\CallRatingService;
use PHPUnit\Framework\TestCase;

final class CallRatingServiceTest extends TestCase
{
    public function testRatesRegressionFixtures(): void
    {
        $fixtures = json_decode((string)file_get_contents(__DIR__ . '/../../Fixtures/rating_scenarios.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($fixtures);

        foreach ($fixtures as $fixture) {
            $pdo = $this->pdo();
            $this->insertRate($pdo, $fixture['rate']);
            $service = new CallRatingService($pdo);

            $result = $service->rate(new CallRatingRequest(
                $fixture['destination'],
                (int)$fixture['duration_seconds']
            ));

            $this->assertTrue($result->isRated(), $fixture['name']);
            $this->assertSame($fixture['expected']['dialprefix'], $result->getDialPrefix(), $fixture['name']);
            $this->assertSame($fixture['expected']['billable_seconds'], $result->getBillableSeconds(), $fixture['name']);
            $this->assertSame($fixture['expected']['customer_cost'], $result->getCustomerCost(), $fixture['name']);
            $this->assertSame($fixture['expected']['provider_cost'], $result->getProviderCost(), $fixture['name']);
        }
    }

    public function testUsesLongestPrefixMatch(): void
    {
        $pdo = $this->pdo();
        $this->insertRate($pdo, ['dialprefix' => '1', 'rateinitial' => '0.01000']);
        $this->insertRate($pdo, ['dialprefix' => '1800', 'rateinitial' => '0.00300']);

        $result = (new CallRatingService($pdo))->rate(new CallRatingRequest('18005551212', 60));

        $this->assertTrue($result->isRated());
        $this->assertSame('1800', $result->getDialPrefix());
        $this->assertSame('0.00300', $result->getCustomerCost());
    }

    public function testRejectsUnratedDestination(): void
    {
        $result = (new CallRatingService($this->pdo()))->rate(new CallRatingRequest('999', 60));

        $this->assertFalse($result->isRated());
        $this->assertSame('No matching rate was found.', $result->getMessage());
    }

    /**
     * @param array<string, mixed> $rate
     */
    private function insertRate(PDO $pdo, array $rate): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO cc_ratecard (
                idtariffplan, dialprefix, rateinitial, buyrate, initblock, billingblock,
                connectcharge, mincharge, buyrateconnectcharge, buyratemincharge
            ) VALUES (
                1, :dialprefix, :rateinitial, :buyrate, :initblock, :billingblock,
                :connectcharge, :mincharge, :buyrateconnectcharge, :buyratemincharge
            )'
        );
        $statement->execute([
            'dialprefix' => (string)$rate['dialprefix'],
            'rateinitial' => (string)($rate['rateinitial'] ?? '0.00000'),
            'buyrate' => (string)($rate['buyrate'] ?? '0.00000'),
            'initblock' => (int)($rate['initblock'] ?? 60),
            'billingblock' => (int)($rate['billingblock'] ?? 60),
            'connectcharge' => (string)($rate['connectcharge'] ?? '0.00000'),
            'mincharge' => (string)($rate['mincharge'] ?? '0.00000'),
            'buyrateconnectcharge' => (string)($rate['buyrateconnectcharge'] ?? '0.00000'),
            'buyratemincharge' => (string)($rate['buyratemincharge'] ?? '0.00000'),
        ]);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cc_ratecard (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                idtariffplan INTEGER,
                dialprefix TEXT,
                rateinitial TEXT,
                buyrate TEXT,
                initblock INTEGER,
                billingblock INTEGER,
                connectcharge TEXT,
                mincharge TEXT,
                buyrateconnectcharge TEXT,
                buyratemincharge TEXT
            )'
        );

        return $pdo;
    }
}

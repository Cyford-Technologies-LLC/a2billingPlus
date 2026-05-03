<?php

declare(strict_types=1);

use A2BillingPlus\Module\Billing\CdrRepository;
use A2BillingPlus\Module\Billing\CdrSearchCriteria;
use A2BillingPlus\Module\Billing\CdrSearchService;
use PHPUnit\Framework\TestCase;

final class CdrSearchServiceTest extends TestCase
{
    public function testSearchFiltersByDateCustomerAndCalledStation(): void
    {
        $service = new CdrSearchService(new CdrRepository($this->pdo()));

        $result = $service->search(new CdrSearchCriteria(10, 0, '2026-05-01 00:00:00', '2026-05-02 00:00:00', 1, '1800'));

        $this->assertCount(1, $result['items']);
        $this->assertSame('s1', $result['items'][0]['sessionid']);
        $this->assertSame('18005551212', $result['items'][0]['calledstation']);
    }

    public function testSearchHonorsLimitAndOffset(): void
    {
        $service = new CdrSearchService(new CdrRepository($this->pdo()));

        $result = $service->search(new CdrSearchCriteria(1, 1));

        $this->assertCount(1, $result['items']);
        $this->assertSame('s2', $result['items'][0]['sessionid']);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cc_call (
                id INTEGER PRIMARY KEY,
                sessionid TEXT,
                uniqueid TEXT,
                starttime TEXT,
                stoptime TEXT,
                sessiontime INTEGER,
                calledstation TEXT,
                sessionbill TEXT,
                buycost TEXT,
                terminatecauseid INTEGER,
                id_card INTEGER
            )'
        );
        $pdo->exec("INSERT INTO cc_call (id, sessionid, uniqueid, starttime, stoptime, sessiontime, calledstation, sessionbill, buycost, terminatecauseid, id_card) VALUES (3, 's3', 'u3', '2026-05-02 10:00:00', '2026-05-02 10:01:00', 60, '441234', '0.0180', '0.0100', 1, 2)");
        $pdo->exec("INSERT INTO cc_call (id, sessionid, uniqueid, starttime, stoptime, sessiontime, calledstation, sessionbill, buycost, terminatecauseid, id_card) VALUES (2, 's2', 'u2', '2026-05-01 11:00:00', '2026-05-01 11:01:00', 60, '18885551212', '0.0100', '0.0050', 1, 1)");
        $pdo->exec("INSERT INTO cc_call (id, sessionid, uniqueid, starttime, stoptime, sessiontime, calledstation, sessionbill, buycost, terminatecauseid, id_card) VALUES (1, 's1', 'u1', '2026-05-01 10:00:00', '2026-05-01 10:01:00', 60, '18005551212', '0.0100', '0.0050', 1, 1)");

        return $pdo;
    }
}

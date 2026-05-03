<?php

declare(strict_types=1);

use A2BillingPlus\Module\Security\AuditLogRepository;
use A2BillingPlus\Module\Telephony\DidRepository;
use A2BillingPlus\Module\Telephony\DidService;
use PHPUnit\Framework\TestCase;

final class DidServiceTest extends TestCase
{
    public function testListsAndLoadsDidWithDestinations(): void
    {
        $service = $this->service($this->pdo());

        $list = $service->list(10, 0, null, 1, 1);
        $detail = $service->detail(1);

        $this->assertCount(1, $list['items']);
        $this->assertSame('+15551234567', $detail['did']);
        $this->assertSame('1001', $detail['destinations'][0]['destination']);
    }

    public function testAssignsDidAndWritesUsageAudit(): void
    {
        $pdo = $this->pdo();
        $service = $this->service($pdo);

        $result = $service->assign(2, 42, 'admin:root');

        $this->assertSame(200, $result['status']);
        $this->assertSame(42, (int)$result['body']['did']['iduser']);
        $this->assertSame(1, (int)$result['body']['did']['reserved']);
        $this->assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM cc_did_use WHERE id_did = 2 AND id_cc_card = 42 AND activated = 1')->fetchColumn());
        $this->assertSame('did.assign', $pdo->query('SELECT action FROM cc_a2bp_audit_log')->fetchColumn());
    }

    public function testRejectsAssignmentOwnedByAnotherCustomer(): void
    {
        $result = $this->service($this->pdo())->assign(1, 99, 'admin:root');

        $this->assertSame(409, $result['status']);
        $this->assertSame('did_already_reserved', $result['body']['code']);
    }

    public function testUpdatesRoutingForAssignedOwner(): void
    {
        $pdo = $this->pdo();
        $service = $this->service($pdo);

        $result = $service->updateRouting(1, [
            'customer_id' => 10,
            'destinations' => [
                ['destination' => 'sip:desk@example.test', 'priority' => 1, 'voip_call' => 1],
                ['destination' => '+15559876543', 'priority' => 2, 'voip_call' => 0],
            ],
        ], 'admin:root');

        $this->assertSame(200, $result['status']);
        $this->assertCount(2, $result['body']['did']['destinations']);
        $this->assertSame('sip:desk@example.test', $result['body']['did']['destinations'][0]['destination']);
        $this->assertSame('did.routing.update', $pdo->query('SELECT action FROM cc_a2bp_audit_log')->fetchColumn());
    }

    public function testRejectsRoutingForWrongCustomer(): void
    {
        $result = $this->service($this->pdo())->updateRouting(1, [
            'customer_id' => 42,
            'destinations' => [
                ['destination' => '1001'],
            ],
        ], 'admin:root');

        $this->assertSame(403, $result['status']);
        $this->assertSame('did_ownership_failed', $result['body']['code']);
    }

    public function testRejectsInvalidRoutingDestination(): void
    {
        $result = $this->service($this->pdo())->updateRouting(1, [
            'customer_id' => 10,
            'destinations' => [
                ['destination' => 'bad destination with spaces'],
            ],
        ], 'admin:root');

        $this->assertSame(422, $result['status']);
    }

    public function testReleaseClearsOwnerUsageAndDestinations(): void
    {
        $pdo = $this->pdo();
        $service = $this->service($pdo);

        $result = $service->release(1, 'admin:root');

        $this->assertSame(200, $result['status']);
        $this->assertSame(0, (int)$result['body']['did']['iduser']);
        $this->assertSame(0, (int)$result['body']['did']['reserved']);
        $this->assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM cc_did_destination WHERE id_cc_did = 1')->fetchColumn());
        $this->assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM cc_did_use WHERE id_did = 1 AND activated = 0')->fetchColumn());
    }

    private function service(PDO $pdo): DidService
    {
        return new DidService(new DidRepository($pdo), $pdo, new AuditLogRepository($pdo));
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cc_did (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_cc_didgroup INTEGER,
                id_cc_country INTEGER,
                activated INTEGER,
                reserved INTEGER,
                iduser INTEGER,
                did TEXT,
                creationdate TEXT,
                startingdate TEXT,
                expirationdate TEXT,
                description TEXT,
                billingtype INTEGER,
                fixrate REAL,
                connection_charge REAL,
                selling_rate REAL,
                max_concurrent INTEGER
            )'
        );
        $pdo->exec(
            'CREATE TABLE cc_did_use (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_cc_card INTEGER,
                id_did INTEGER,
                reservationdate TEXT,
                releasedate TEXT,
                activated INTEGER,
                month_payed INTEGER,
                reminded INTEGER
            )'
        );
        $pdo->exec(
            'CREATE TABLE cc_did_destination (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                destination TEXT,
                priority INTEGER,
                id_cc_card INTEGER,
                id_cc_did INTEGER,
                creationdate TEXT,
                activated INTEGER,
                secondusedreal INTEGER,
                voip_call INTEGER,
                validated INTEGER
            )'
        );
        $pdo->exec("INSERT INTO cc_did (id, id_cc_didgroup, id_cc_country, activated, reserved, iduser, did, creationdate, billingtype, fixrate) VALUES (1, 1, 1, 1, 1, 10, '+15551234567', '2026-05-03', 1, 1.50)");
        $pdo->exec("INSERT INTO cc_did (id, id_cc_didgroup, id_cc_country, activated, reserved, iduser, did, creationdate, billingtype, fixrate) VALUES (2, 1, 1, 1, 0, 0, '+15557654321', '2026-05-03', 1, 1.50)");
        $pdo->exec("INSERT INTO cc_did_use (id_cc_card, id_did, reservationdate, activated, month_payed, reminded) VALUES (10, 1, '2026-05-03', 1, 1, 0)");
        $pdo->exec("INSERT INTO cc_did_destination (destination, priority, id_cc_card, id_cc_did, creationdate, activated, secondusedreal, voip_call, validated) VALUES ('1001', 1, 10, 1, '2026-05-03', 1, 0, 1, 1)");

        return $pdo;
    }
}

<?php

declare(strict_types=1);

use A2BillingPlus\Module\Security\AuditLogRepository;
use A2BillingPlus\Module\Telephony\TrunkRepository;
use A2BillingPlus\Module\Telephony\TrunkService;
use PHPUnit\Framework\TestCase;

final class TrunkServiceTest extends TestCase
{
    public function testListsAndLoadsTrunks(): void
    {
        $service = new TrunkService(new TrunkRepository($this->pdo()));

        $list = $service->list(10, 0, 1);
        $detail = $service->detail(1);

        $this->assertCount(1, $list['items']);
        $this->assertSame('DEFAULT', $detail['trunkcode']);
    }

    public function testCreatesTrunkAndAudits(): void
    {
        $pdo = $this->pdo();
        $service = new TrunkService(new TrunkRepository($pdo), new AuditLogRepository($pdo));

        $result = $service->create([
            'trunkcode' => 'VECTA',
            'providertech' => 'pjsip',
            'providerip' => 'sip.vectavoip.com',
            'trunkprefix' => '1',
        ], 'admin:root');

        $this->assertSame(201, $result['status']);
        $this->assertSame('PJSIP', $result['body']['trunk']['providertech']);
        $this->assertSame('trunk.create', $pdo->query('SELECT action FROM cc_a2bp_audit_log')->fetchColumn());
    }

    public function testUpdatesTrunk(): void
    {
        $service = new TrunkService(new TrunkRepository($this->pdo()));

        $result = $service->update(1, [
            'providerip' => 'updated.example.test',
            'status' => 0,
        ], 'admin:root');

        $this->assertSame(200, $result['status']);
        $this->assertSame('updated.example.test', $result['body']['trunk']['providerip']);
        $this->assertSame(0, (int)$result['body']['trunk']['status']);
    }

    public function testRejectsInvalidTechnology(): void
    {
        $service = new TrunkService(new TrunkRepository($this->pdo()));

        $result = $service->create([
            'trunkcode' => 'BAD',
            'providertech' => 'skinny',
            'providerip' => 'example.test',
        ], 'admin:root');

        $this->assertSame(422, $result['status']);
        $this->assertSame('providertech', $result['body']['field']);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cc_trunk (
                id_trunk INTEGER PRIMARY KEY AUTOINCREMENT,
                trunkcode TEXT,
                trunkprefix TEXT,
                providertech TEXT,
                providerip TEXT,
                removeprefix TEXT,
                creationdate TEXT,
                failover_trunk INTEGER,
                addparameter TEXT,
                id_provider INTEGER,
                inuse INTEGER,
                maxuse INTEGER,
                status INTEGER,
                if_max_use INTEGER
            )'
        );
        $pdo->exec("INSERT INTO cc_trunk (id_trunk, trunkcode, trunkprefix, providertech, providerip, removeprefix, creationdate, failover_trunk, addparameter, id_provider, inuse, maxuse, status, if_max_use) VALUES (1, 'DEFAULT', '011', 'IAX2', 'examplehost', '', '2026-05-03', 0, '', NULL, 0, -1, 1, 0)");

        return $pdo;
    }
}

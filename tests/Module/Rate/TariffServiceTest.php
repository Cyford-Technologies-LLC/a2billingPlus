<?php

declare(strict_types=1);

use A2BillingPlus\Module\Rate\TariffRepository;
use A2BillingPlus\Module\Rate\TariffService;
use A2BillingPlus\Module\Security\AuditLogRepository;
use PHPUnit\Framework\TestCase;

final class TariffServiceTest extends TestCase
{
    public function testListsAndLoadsTariffPlans(): void
    {
        $service = new TariffService(new TariffRepository($this->pdo()));

        $list = $service->list('tariff-plans', 10, 0, 'Retail');
        $detail = $service->detail('tariff-plans', 1);

        $this->assertCount(1, $list['items']);
        $this->assertSame('Retail', $list['items'][0]['tariffname']);
        $this->assertSame('Retail plan', $detail['description']);
    }

    public function testCreatesTariffPlanAndAudits(): void
    {
        $pdo = $this->pdo();
        $service = new TariffService(new TariffRepository($pdo), new AuditLogRepository($pdo));

        $result = $service->create('tariff-plans', [
            'tariffname' => 'Wholesale',
            'description' => 'Wholesale plan',
        ], 'admin:root');

        $this->assertSame(201, $result['status']);
        $this->assertSame('Wholesale', $result['body']['item']['tariffname']);
        $this->assertSame('tariff-plans.create', $pdo->query('SELECT action FROM cc_a2bp_audit_log')->fetchColumn());
    }

    public function testUpdatesTariffGroup(): void
    {
        $service = new TariffService(new TariffRepository($this->pdo()));

        $result = $service->update('tariff-groups', 1, [
            'tariffgroupname' => 'Updated Group',
            'idtariffplan' => 1,
        ], 'admin:root');

        $this->assertSame(200, $result['status']);
        $this->assertSame('Updated Group', $result['body']['item']['tariffgroupname']);
    }

    public function testRejectsInvalidTariffGroupPlan(): void
    {
        $service = new TariffService(new TariffRepository($this->pdo()));

        $result = $service->create('tariff-groups', [
            'tariffgroupname' => 'Bad Group',
            'idtariffplan' => 0,
        ], 'admin:root');

        $this->assertSame(422, $result['status']);
        $this->assertSame('idtariffplan', $result['body']['field']);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE cc_tariffplan (id INTEGER PRIMARY KEY AUTOINCREMENT, iduser INTEGER, tariffname TEXT, creationdate TEXT, description TEXT, id_trunk INTEGER, idowner INTEGER, dnidprefix TEXT, calleridprefix TEXT)');
        $pdo->exec('CREATE TABLE cc_tariffgroup (id INTEGER PRIMARY KEY AUTOINCREMENT, iduser INTEGER, idtariffplan INTEGER, tariffgroupname TEXT, lcrtype INTEGER, creationdate TEXT, removeinterprefix INTEGER, id_cc_package_offer INTEGER)');
        $pdo->exec("INSERT INTO cc_tariffplan (id, iduser, tariffname, creationdate, description, id_trunk, idowner, dnidprefix, calleridprefix) VALUES (1, 0, 'Retail', '2026-05-03', 'Retail plan', 0, 0, 'all', 'all')");
        $pdo->exec("INSERT INTO cc_tariffgroup (id, iduser, idtariffplan, tariffgroupname, lcrtype, creationdate, removeinterprefix, id_cc_package_offer) VALUES (1, 0, 1, 'Default Group', 0, '2026-05-03', 0, -1)");

        return $pdo;
    }
}

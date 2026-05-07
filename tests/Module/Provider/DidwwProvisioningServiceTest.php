<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\Didww\DidwwProvisioningService;
use PHPUnit\Framework\TestCase;

final class DidwwProvisioningServiceTest extends TestCase
{
    public function testSyncOwnedDidsUpsertsIntoLocalInventory(): void
    {
        $pdo = $this->pdo();
        $service = new DidwwProvisioningService($pdo);

        $result = $service->syncOwnedDids([
            ['id' => 'did-1', 'number' => '+12125550100', 'blocked' => 'No', 'awaiting_registration' => 'No', 'terminated' => 'No', 'voice_in_trunk_reference' => 'trunk-1', 'voice_in_trunk' => 'Main DIDWW Trunk', 'order_reference' => 'ORD-1'],
            ['id' => 'did-2', 'number' => '+12125550101', 'blocked' => 'Yes', 'awaiting_registration' => 'No', 'terminated' => 'No', 'voice_in_trunk_reference' => 'trunk-2', 'voice_in_trunk' => 'Backup DIDWW Trunk', 'order_reference' => 'ORD-2'],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['upserted']);
        $this->assertSame(2, (int) $pdo->query("SELECT COUNT(*) FROM cc_vectavoip_did_inventory WHERE provider_reference LIKE 'didww:%'")->fetchColumn());
        $this->assertSame('blocked', $pdo->query("SELECT status FROM cc_vectavoip_did_inventory WHERE did = '+12125550101'")->fetchColumn());
        $this->assertSame('didww', $pdo->query("SELECT provider_code FROM cc_vectavoip_did_inventory WHERE did = '+12125550100'")->fetchColumn());
        $this->assertSame('Main DIDWW Trunk', $pdo->query("SELECT provider_trunk_name FROM cc_vectavoip_did_inventory WHERE did = '+12125550100'")->fetchColumn());
        $this->assertSame('ORD-2', $pdo->query("SELECT order_reference FROM cc_vectavoip_did_inventory WHERE did = '+12125550101'")->fetchColumn());
    }

    public function testMaterializeInboundTrunkCreatesProviderAndLocalTrunk(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE cc_provider (id INTEGER PRIMARY KEY AUTOINCREMENT, provider_name TEXT, description TEXT)');
        $pdo->exec('CREATE TABLE cc_trunk (id_trunk INTEGER PRIMARY KEY AUTOINCREMENT, trunkcode TEXT, trunkprefix TEXT, providertech TEXT, providerip TEXT, removeprefix TEXT, failover_trunk INTEGER, addparameter TEXT, id_provider INTEGER, inuse INTEGER, maxuse INTEGER, status INTEGER, if_max_use INTEGER)');

        $service = new DidwwProvisioningService($pdo);
        $result = $service->materializeInboundTrunk([
            'id' => 'trunk-1',
            'attributes' => [
                'name' => 'DIDWW Main',
                'capacity_limit' => 12,
                'configuration' => [
                    'attributes' => [
                        'host' => 'pbx.example.test',
                    ],
                ],
            ],
        ], ['name' => 'DIDWW Main', 'host' => 'pbx.example.test']);

        $this->assertTrue($result['success']);
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM cc_provider WHERE provider_name = 'DIDWW'")->fetchColumn());
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM cc_trunk WHERE providerip = 'pbx.example.test'")->fetchColumn());
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
}

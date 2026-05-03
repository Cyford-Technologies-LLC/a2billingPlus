<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPProvisioningService;
use PHPUnit\Framework\TestCase;

final class VectaVoIPProvisioningServiceTest extends TestCase
{
    public function testProvisionsProviderTrunkAndRatecard(): void
    {
        $pdo = $this->pdo();
        $service = new VectaVoIPProvisioningService($pdo);

        $result = $service->provisionDefaults();
        $again = $service->provisionDefaults();

        $this->assertTrue($result['success']);
        $this->assertSame($result['provider_id'], $again['provider_id']);
        $this->assertSame($result['trunk_id'], $again['trunk_id']);
        $this->assertSame($result['ratecard_id'], $again['ratecard_id']);
        $this->assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM cc_provider')->fetchColumn());
        $this->assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM cc_trunk')->fetchColumn());
        $this->assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM cc_tariffplan')->fetchColumn());
    }

    public function testSyncsDidInventory(): void
    {
        $pdo = $this->pdo();
        $service = new VectaVoIPProvisioningService($pdo);

        $result = $service->syncDidInventory([
            ['did' => '+15551234567', 'country' => 'US', 'region' => 'CA', 'monthly_rate' => '1.25', 'setup_rate' => '0.50'],
            ['did' => '+15557654321', 'country' => 'US', 'region' => 'NY'],
        ]);
        $update = $service->syncDidInventory([
            ['did' => '+15551234567', 'country' => 'US', 'region' => 'TX', 'status' => 'reserved'],
        ]);

        $this->assertSame(2, $result['upserted']);
        $this->assertSame(1, $update['upserted']);
        $this->assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM cc_vectavoip_did_inventory')->fetchColumn());
        $this->assertSame('reserved', $pdo->query("SELECT status FROM cc_vectavoip_did_inventory WHERE did = '+15551234567'")->fetchColumn());
    }

    public function testRollsBackPartialDefaultProvisioningFailure(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('DROP TABLE cc_tariffplan');
        $service = new VectaVoIPProvisioningService($pdo);

        try {
            $service->provisionDefaults();
            $this->fail('Expected provisioning to fail.');
        } catch (Throwable) {
            $this->assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM cc_provider')->fetchColumn());
            $this->assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM cc_trunk')->fetchColumn());
        }
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE cc_provider (id INTEGER PRIMARY KEY AUTOINCREMENT, provider_name TEXT UNIQUE, description TEXT)');
        $pdo->exec(
            'CREATE TABLE cc_trunk (
                id_trunk INTEGER PRIMARY KEY AUTOINCREMENT,
                trunkcode TEXT,
                trunkprefix TEXT,
                providertech TEXT,
                providerip TEXT,
                removeprefix TEXT,
                failover_trunk INTEGER,
                addparameter TEXT,
                id_provider INTEGER,
                inuse INTEGER,
                maxuse INTEGER,
                status INTEGER,
                if_max_use INTEGER
            )'
        );
        $pdo->exec(
            'CREATE TABLE cc_tariffplan (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                iduser INTEGER,
                tariffname TEXT,
                description TEXT,
                id_trunk INTEGER,
                dnidprefix TEXT,
                calleridprefix TEXT
            )'
        );

        return $pdo;
    }
}

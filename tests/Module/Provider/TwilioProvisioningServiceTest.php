<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\Twilio\TwilioProvisioningService;
use PHPUnit\Framework\TestCase;

final class TwilioProvisioningServiceTest extends TestCase
{
    public function testSyncOwnedNumbersUpsertsIntoLocalInventory(): void
    {
        $pdo = $this->pdo();
        $service = new TwilioProvisioningService($pdo);

        $result = $service->syncOwnedNumbers([
            ['sid' => 'PN1', 'phone_number' => '+12125550100', 'country_code' => 'US', 'trunk_sid' => 'TK1', 'trunk_name' => 'Main Twilio Trunk'],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['upserted']);
        $this->assertSame('twilio', $pdo->query("SELECT provider_code FROM cc_vectavoip_did_inventory WHERE did = '+12125550100'")->fetchColumn());
        $this->assertSame('TK1', $pdo->query("SELECT provider_trunk_reference FROM cc_vectavoip_did_inventory WHERE did = '+12125550100'")->fetchColumn());
    }

    public function testMaterializeTrunkCreatesProviderAndLocalTrunk(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE cc_provider (id INTEGER PRIMARY KEY AUTOINCREMENT, provider_name TEXT, description TEXT)');
        $pdo->exec('CREATE TABLE cc_trunk (id_trunk INTEGER PRIMARY KEY AUTOINCREMENT, trunkcode TEXT, trunkprefix TEXT, providertech TEXT, providerip TEXT, removeprefix TEXT, failover_trunk INTEGER, addparameter TEXT, id_provider INTEGER, inuse INTEGER, maxuse INTEGER, status INTEGER, if_max_use INTEGER)');

        $service = new TwilioProvisioningService($pdo);
        $result = $service->materializeTrunk([
            'sid' => 'TK1',
            'friendly_name' => 'Twilio Main',
            'domain_name' => 'example.pstn.twilio.com',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM cc_provider WHERE provider_name = 'Twilio'")->fetchColumn());
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM cc_trunk WHERE providerip = 'example.pstn.twilio.com'")->fetchColumn());
        $this->assertSame('twilio_trunk:TK1', $pdo->query("SELECT addparameter FROM cc_trunk WHERE providerip = 'example.pstn.twilio.com'")->fetchColumn());
    }

    public function testSyncOwnedNumbersUpgradesLegacyInventoryTable(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE cc_vectavoip_did_inventory (id INTEGER PRIMARY KEY AUTOINCREMENT, did TEXT NOT NULL UNIQUE, country TEXT NOT NULL DEFAULT \'\', region TEXT NOT NULL DEFAULT \'\', monthly_rate TEXT NOT NULL DEFAULT \'0.00000\', setup_rate TEXT NOT NULL DEFAULT \'0.00000\', currency TEXT NOT NULL DEFAULT \'USD\', status TEXT NOT NULL DEFAULT \'available\', created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');

        $service = new TwilioProvisioningService($pdo);
        $result = $service->syncOwnedNumbers([
            ['sid' => 'PN2', 'phone_number' => '+12125550101', 'country_code' => 'US', 'trunk_sid' => 'TK2', 'trunk_name' => 'Backup Twilio Trunk'],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('twilio', $pdo->query("SELECT provider_code FROM cc_vectavoip_did_inventory WHERE did = '+12125550101'")->fetchColumn());
        $this->assertSame('PN2', $pdo->query("SELECT provider_reference FROM cc_vectavoip_did_inventory WHERE did = '+12125550101'")->fetchColumn());
        $this->assertSame('TK2', $pdo->query("SELECT provider_trunk_reference FROM cc_vectavoip_did_inventory WHERE did = '+12125550101'")->fetchColumn());
    }

    public function testMaterializeTrunkDoesNotOverwriteUnrelatedTrunkWithSameFriendlyNameCode(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE cc_provider (id INTEGER PRIMARY KEY AUTOINCREMENT, provider_name TEXT, description TEXT)');
        $pdo->exec('CREATE TABLE cc_trunk (id_trunk INTEGER PRIMARY KEY AUTOINCREMENT, trunkcode TEXT, trunkprefix TEXT, providertech TEXT, providerip TEXT, removeprefix TEXT, failover_trunk INTEGER, addparameter TEXT, id_provider INTEGER, inuse INTEGER, maxuse INTEGER, status INTEGER, if_max_use INTEGER)');
        $pdo->exec("INSERT INTO cc_trunk (id_trunk, trunkcode, trunkprefix, providertech, providerip, removeprefix, failover_trunk, addparameter, id_provider, inuse, maxuse, status, if_max_use) VALUES (1, 'VECTAVOIP', '', 'SIP', 'sip.vectavoip.com', '', 0, '', 1, 0, -1, 1, 0)");

        $service = new TwilioProvisioningService($pdo);
        $result = $service->materializeTrunk([
            'sid' => 'BYbf0b89b0a20e0aa44f399c29c686ec5e',
            'friendly_name' => 'VectaVoip',
            'domain_name' => '',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('sip.vectavoip.com', $pdo->query("SELECT providerip FROM cc_trunk WHERE id_trunk = 1")->fetchColumn());
        $this->assertSame('', $pdo->query("SELECT addparameter FROM cc_trunk WHERE id_trunk = 1")->fetchColumn());
        $this->assertSame(2, (int) $pdo->query("SELECT COUNT(*) FROM cc_trunk")->fetchColumn());
        $this->assertSame('twilio_trunk:BYbf0b89b0a20e0aa44f399c29c686ec5e', $pdo->query("SELECT addparameter FROM cc_trunk WHERE id_trunk = 2")->fetchColumn());
        $this->assertSame('sip.twilio.com', $pdo->query("SELECT providerip FROM cc_trunk WHERE id_trunk = 2")->fetchColumn());
    }

    public function testMaterializeTrunkUsesFromDomainWhenTwilioProvidesIt(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE cc_provider (id INTEGER PRIMARY KEY AUTOINCREMENT, provider_name TEXT, description TEXT)');
        $pdo->exec('CREATE TABLE cc_trunk (id_trunk INTEGER PRIMARY KEY AUTOINCREMENT, trunkcode TEXT, trunkprefix TEXT, providertech TEXT, providerip TEXT, removeprefix TEXT, failover_trunk INTEGER, addparameter TEXT, id_provider INTEGER, inuse INTEGER, maxuse INTEGER, status INTEGER, if_max_use INTEGER)');

        $service = new TwilioProvisioningService($pdo);
        $result = $service->materializeTrunk([
            'sid' => 'BY11111111111111111111111111111111',
            'friendly_name' => 'Twilio BYOC',
            'from_domain' => 'customer.sip.us1.twilio.com',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('customer.sip.us1.twilio.com', $pdo->query("SELECT providerip FROM cc_trunk WHERE id_trunk = 1")->fetchColumn());
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
}

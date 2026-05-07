<?php

declare(strict_types=1);

use A2BillingPlus\Module\Telephony\CoreTrunkProjectionService;
use PHPUnit\Framework\TestCase;

final class CoreTrunkProjectionServiceTest extends TestCase
{
    public function testUpsertBySyncKeyCreatesAndUpdatesCoreTrunk(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE cc_provider (id INTEGER PRIMARY KEY AUTOINCREMENT, provider_name TEXT, description TEXT)');
        $pdo->exec('CREATE TABLE cc_trunk (id_trunk INTEGER PRIMARY KEY AUTOINCREMENT, trunkcode TEXT, trunkprefix TEXT, providertech TEXT, providerip TEXT, removeprefix TEXT, failover_trunk INTEGER, addparameter TEXT, id_provider INTEGER, inuse INTEGER, maxuse INTEGER, status INTEGER, if_max_use INTEGER)');

        $service = new CoreTrunkProjectionService($pdo);
        $providerId = $service->ensureProvider('Twilio', 'Twilio provider');
        $trunkId = $service->upsertBySyncKey([
            'provider_id' => $providerId,
            'trunkcode' => 'TWBY123',
            'providertech' => 'SIP',
            'providerip' => 'host-1',
            'addparameter' => 'twilio_trunk:BY123',
            'maxuse' => -1,
            'status' => 1,
            'if_max_use' => 0,
        ]);

        $updatedId = $service->upsertBySyncKey([
            'provider_id' => $providerId,
            'trunkcode' => 'TWBY123',
            'providertech' => 'SIP',
            'providerip' => 'host-2',
            'addparameter' => 'twilio_trunk:BY123',
            'maxuse' => 10,
            'status' => 1,
            'if_max_use' => 0,
        ]);

        $this->assertSame($trunkId, $updatedId);
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM cc_trunk WHERE addparameter = 'twilio_trunk:BY123'")->fetchColumn());
        $this->assertSame('host-2', $pdo->query("SELECT providerip FROM cc_trunk WHERE id_trunk = $trunkId")->fetchColumn());
        $this->assertSame('10', (string) $pdo->query("SELECT maxuse FROM cc_trunk WHERE id_trunk = $trunkId")->fetchColumn());
    }
}

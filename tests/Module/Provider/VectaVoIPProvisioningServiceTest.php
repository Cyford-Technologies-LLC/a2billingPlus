<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPProvisioningService;
use PHPUnit\Framework\TestCase;

final class VectaVoIPProvisioningServiceTest extends TestCase
{
    public function testApplyPackageProvisioningCreatesTelephonyArtifacts(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createBaseTables($pdo);

        $service = new VectaVoIPProvisioningService($pdo);
        $result = $service->applyPackageProvisioning([
            'selected_package' => 'business',
            'package_did_count' => '3',
            'package_channels' => '6',
            'package_sms_enabled' => '1',
            'package_911_enabled' => '0',
            'package_ratecard_id' => '',
            'package_trunk_label' => 'Business Primary',
            'package_notes' => 'Provision for test tenant',
            'account_number' => 'VV12345',
            'portal_username' => 'tenant-admin',
            'api_secret' => 'secret-123',
            'registered_ip' => '74.208.7.156',
        ]);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, (int)$result['provider_id']);
        $this->assertGreaterThan(0, (int)$result['trunk_id']);
        $this->assertGreaterThan(0, (int)$result['ratecard_id']);
        $this->assertGreaterThan(0, (int)$result['did_request_id']);
        $this->assertSame('trunk-business', $result['pjsip_endpoint']);

        $trunk = $pdo->query("SELECT trunkcode, providertech, providerip, maxuse, addparameter FROM cc_trunk")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('BUSINESSPRIMARY', $trunk['trunkcode']);
        $this->assertSame('PJSIP', $trunk['providertech']);
        $this->assertSame('sip.vectavoip.com', $trunk['providerip']);
        $this->assertSame(6, (int)$trunk['maxuse']);

        $ratecard = $pdo->query("SELECT tariffname, id_trunk FROM cc_tariffplan")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('VectaVoIP BUSINESS', $ratecard['tariffname']);
        $this->assertSame((int)$result['trunk_id'], (int)$ratecard['id_trunk']);

        $didRequest = $pdo->query("SELECT package_code, did_count, sms_enabled, e911_enabled, account_number, registered_ip, status FROM cc_vectavoip_did_requests")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('business', $didRequest['package_code']);
        $this->assertSame(3, (int)$didRequest['did_count']);
        $this->assertSame(1, (int)$didRequest['sms_enabled']);
        $this->assertSame(0, (int)$didRequest['e911_enabled']);
        $this->assertSame('VV12345', $didRequest['account_number']);
        $this->assertSame('74.208.7.156', $didRequest['registered_ip']);
        $this->assertSame('requested', $didRequest['status']);
    }

    public function testFulfillDidRequestMovesIssuedNumbersIntoInventory(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createBaseTables($pdo);

        $service = new VectaVoIPProvisioningService($pdo);
        $request = $service->applyPackageProvisioning([
            'selected_package' => 'starter',
            'package_did_count' => '2',
            'package_channels' => '2',
            'package_sms_enabled' => '0',
            'package_911_enabled' => '0',
            'package_ratecard_id' => '',
            'package_trunk_label' => 'Starter Primary',
            'package_notes' => '',
            'account_number' => 'VV20001',
            'portal_username' => 'starter-admin',
            'api_secret' => 'secret-234',
            'registered_ip' => '74.208.7.156',
        ]);

        $result = $service->fulfillDidRequest((int)$request['did_request_id'], ['+15550000001', '+15550000002']);

        $this->assertTrue($result['success']);
        $this->assertSame(2, (int)$result['fulfilled_count']);

        $requestRow = $pdo->query("SELECT status, notes FROM cc_vectavoip_did_requests WHERE id = " . (int)$request['did_request_id'])->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('fulfilled', $requestRow['status']);
        $this->assertStringContainsString('+15550000001', $requestRow['notes']);

        $inventoryCount = (int)$pdo->query("SELECT COUNT(*) FROM cc_vectavoip_did_inventory WHERE provider_reference = 'vectavoip-request:" . (int)$request['did_request_id'] . "'")->fetchColumn();
        $this->assertSame(2, $inventoryCount);
    }

    private function createBaseTables(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE cc_provider (id INTEGER PRIMARY KEY AUTOINCREMENT, provider_name TEXT NOT NULL, description TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE cc_trunk (
            id_trunk INTEGER PRIMARY KEY AUTOINCREMENT,
            trunkcode TEXT NOT NULL,
            trunkprefix TEXT NOT NULL DEFAULT "",
            providertech TEXT NOT NULL,
            providerip TEXT NOT NULL,
            removeprefix TEXT NOT NULL DEFAULT "",
            creationdate TEXT NOT NULL DEFAULT "",
            failover_trunk INTEGER NOT NULL DEFAULT 0,
            addparameter TEXT NOT NULL DEFAULT "",
            id_provider INTEGER NOT NULL DEFAULT 0,
            inuse INTEGER NOT NULL DEFAULT 0,
            maxuse INTEGER NOT NULL DEFAULT 0,
            status INTEGER NOT NULL DEFAULT 1,
            if_max_use INTEGER NOT NULL DEFAULT 0
        )');
        $pdo->exec('CREATE TABLE cc_tariffplan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            iduser INTEGER NOT NULL DEFAULT 0,
            tariffname TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT "",
            id_trunk INTEGER NOT NULL DEFAULT 0,
            dnidprefix TEXT NOT NULL DEFAULT "",
            calleridprefix TEXT NOT NULL DEFAULT ""
        )');
    }
}

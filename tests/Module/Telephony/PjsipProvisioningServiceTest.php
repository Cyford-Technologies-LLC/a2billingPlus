<?php

declare(strict_types=1);

use A2BillingPlus\Module\Security\AuditLogRepository;
use A2BillingPlus\Module\Telephony\PjsipProvisioningService;
use PHPUnit\Framework\TestCase;

final class PjsipProvisioningServiceTest extends TestCase
{
    public function testProvisionsCustomerDeviceWithoutReturningSecret(): void
    {
        $pdo = $this->pdo();
        $service = new PjsipProvisioningService($pdo, new AuditLogRepository($pdo));

        $result = $service->provisionCustomerDevice([
            'customer_id' => 10,
            'username' => '1001',
            'secret' => 'strong-device-secret',
        ], 'admin:root');

        $this->assertSame(201, $result['status']);
        $this->assertSame('1001', $result['body']['endpoint']['endpoint_id']);
        $this->assertArrayNotHasKey('secret', $result['body']['endpoint']);
        $this->assertSame('strong-device-secret', $pdo->query("SELECT password FROM ps_auths WHERE id = '1001-auth'")->fetchColumn());
        $this->assertSame('pjsip.customer_device.provision', $pdo->query('SELECT action FROM cc_a2bp_audit_log')->fetchColumn());
    }

    public function testProvisionsTrunkEndpoint(): void
    {
        $pdo = $this->pdo();
        $service = new PjsipProvisioningService($pdo, new AuditLogRepository($pdo));

        $result = $service->provisionTrunk([
            'trunkcode' => 'vectavoip',
            'host' => 'sip.vectavoip.com',
            'username' => 'acct',
            'secret' => 'strong-trunk-secret',
        ], 'admin:root');

        $this->assertSame(201, $result['status']);
        $this->assertSame('trunk-vectavoip', $result['body']['endpoint']['endpoint_id']);
        $this->assertSame('sip:sip.vectavoip.com', $pdo->query("SELECT contact FROM ps_aors WHERE id = 'trunk-vectavoip'")->fetchColumn());
    }

    public function testProvisionsTwilioEdgeIpAsSubnetIdentifyMatch(): void
    {
        $pdo = $this->pdo();
        $service = new PjsipProvisioningService($pdo, new AuditLogRepository($pdo));

        $result = $service->provisionTrunk([
            'trunkcode' => 'twbybf0b89b0a20e0aa4',
            'host' => '54.172.60.2',
        ], 'admin:root');

        $this->assertSame(201, $result['status']);
        $this->assertSame(
            '54.172.60.0/24',
            $pdo->query("SELECT `match` FROM ps_endpoint_id_ips WHERE id = 'trunk-twbybf0b89b0a20e0aa4'")->fetchColumn()
        );
    }

    public function testListsLoadsAndUpdatesProvisionedEndpoint(): void
    {
        $pdo = $this->pdo();
        $service = new PjsipProvisioningService($pdo, new AuditLogRepository($pdo));
        $service->provisionCustomerDevice([
            'customer_id' => 10,
            'username' => '1001',
            'secret' => 'strong-device-secret',
        ], 'admin:root');

        $list = $service->listEndpoints(10, 0, 'customer_device', 10);
        $detail = $service->endpointDetail('1001');
        $update = $service->updateEndpoint('1001', [
            'context' => 'from-internal',
            'allow' => 'ulaw',
            'max_contacts' => 2,
        ], 'admin:root');

        $this->assertCount(1, $list['items']);
        $this->assertSame('customer_device', $detail['endpoint_type']);
        $this->assertArrayNotHasKey('password', $detail);
        $this->assertSame(200, $update['status']);
        $this->assertSame('from-internal', $update['body']['endpoint']['context']);
        $this->assertSame(2, (int)$update['body']['endpoint']['max_contacts']);
    }

    public function testRejectsInvalidEndpointUpdate(): void
    {
        $result = (new PjsipProvisioningService($this->pdo()))->updateEndpoint('missing', [
            'context' => 'from-internal',
        ], 'admin:root');

        $this->assertSame(404, $result['status']);
    }

    public function testRejectsInvalidCustomerPayload(): void
    {
        $result = (new PjsipProvisioningService($this->pdo()))->provisionCustomerDevice([
            'customer_id' => 0,
            'username' => '1001',
        ], 'admin:root');

        $this->assertSame(422, $result['status']);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
}

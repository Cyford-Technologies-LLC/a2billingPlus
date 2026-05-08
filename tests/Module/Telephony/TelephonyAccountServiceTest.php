<?php

declare(strict_types=1);

use A2BillingPlus\Module\Security\AuditLogRepository;
use A2BillingPlus\Module\Telephony\PjsipProvisioningService;
use A2BillingPlus\Module\Telephony\TelephonyAccountRepository;
use A2BillingPlus\Module\Telephony\TelephonyAccountService;
use PHPUnit\Framework\TestCase;

final class TelephonyAccountServiceTest extends TestCase
{
    public function testListsAndLoadsSipAccountsWithoutSecrets(): void
    {
        $service = $this->service($this->pdo());

        $list = $service->list('sip', 10, 0, 10);
        $detail = $service->detail('sip', 1);

        $this->assertCount(1, $list['items']);
        $this->assertSame('1001', $detail['username']);
        $this->assertArrayNotHasKey('secret', $detail);
    }

    public function testCreatesAccountAndDoesNotReturnSecret(): void
    {
        $pdo = $this->pdo();
        $service = $this->service($pdo);

        $result = $service->create('sip', [
            'id_cc_card' => 10,
            'username' => '1002',
            'secret' => 'super-secret',
            'context' => 'a2billing',
        ], 'admin:root');

        $this->assertSame(201, $result['status']);
        $this->assertSame('1002', $result['body']['account']['username']);
        $this->assertArrayNotHasKey('secret', $result['body']['account']);
        $this->assertSame('super-secret', $pdo->query("SELECT secret FROM cc_sip_buddies WHERE username = '1002'")->fetchColumn());
        $this->assertSame('telephony_account.create', $pdo->query('SELECT action FROM cc_a2bp_audit_log')->fetchColumn());
    }

    public function testCreatesSipAccountAndMirrorsItIntoPjsipWhenEnabled(): void
    {
        $pdo = $this->pdo();
        $service = new TelephonyAccountService(
            new TelephonyAccountRepository($pdo),
            new AuditLogRepository($pdo),
            new PjsipProvisioningService($pdo, new AuditLogRepository($pdo)),
            'pjsip',
            true
        );

        $result = $service->create('sip', [
            'id_cc_card' => 10,
            'username' => '1004',
            'secret' => 'pjsip-secret',
            'context' => 'a2billing',
            'allow' => 'ulaw,alaw',
        ], 'admin:root');

        $this->assertSame(201, $result['status']);
        $this->assertSame('pjsip-secret', $pdo->query("SELECT password FROM ps_auths WHERE id = 'cust-10-1004-auth'")->fetchColumn());
        $this->assertSame('cust-10-1004', $pdo->query("SELECT endpoint_id FROM cc_a2bp_pjsip_endpoint_map WHERE endpoint_id = 'cust-10-1004'")->fetchColumn());
    }

    public function testUpdatesIaxAccount(): void
    {
        $service = $this->service($this->pdo());

        $result = $service->update('iax', 1, ['callerid' => 'Desk Phone'], 'admin:root');

        $this->assertSame(200, $result['status']);
        $this->assertSame('Desk Phone', $result['body']['account']['callerid']);
    }

    public function testRejectsInvalidTechnology(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service($this->pdo())->list('pjsip', 10, 0);
    }

    public function testRejectsMissingSecretOnCreate(): void
    {
        $result = $this->service($this->pdo())->create('sip', [
            'id_cc_card' => 10,
            'username' => '1003',
        ], 'admin:root');

        $this->assertSame(422, $result['status']);
        $this->assertSame('secret', $result['body']['field']);
    }

    private function service(PDO $pdo): TelephonyAccountService
    {
        return new TelephonyAccountService(new TelephonyAccountRepository($pdo), new AuditLogRepository($pdo));
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createBuddyTable($pdo, 'cc_sip_buddies');
        $this->createBuddyTable($pdo, 'cc_iax_buddies');
        $pdo->exec("INSERT INTO cc_sip_buddies (id, id_cc_card, name, accountcode, regexten, callerid, context, host, qualify, secret, type, username, disallow, allow) VALUES (1, 10, '1001', '1001', '1001', '1001', 'a2billing', 'dynamic', 'yes', 'hidden', 'friend', '1001', 'all', 'ulaw')");
        $pdo->exec("INSERT INTO cc_iax_buddies (id, id_cc_card, name, accountcode, regexten, callerid, context, host, qualify, secret, type, username, disallow, allow) VALUES (1, 10, '2001', '2001', '2001', '2001', 'a2billing', 'dynamic', 'yes', 'hidden', 'friend', '2001', 'all', 'ulaw')");

        return $pdo;
    }

    private function createBuddyTable(PDO $pdo, string $table): void
    {
        $pdo->exec(
            'CREATE TABLE ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_cc_card INTEGER,
                name TEXT,
                accountcode TEXT,
                regexten TEXT,
                callerid TEXT,
                context TEXT,
                host TEXT,
                port TEXT,
                qualify TEXT,
                secret TEXT,
                type TEXT,
                username TEXT,
                disallow TEXT,
                allow TEXT,
                regseconds INTEGER,
                ipaddr TEXT,
                trunk TEXT,
                defaultuser TEXT,
                cid_number TEXT
            )'
        );
    }
}

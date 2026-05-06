<?php

declare(strict_types=1);

use A2BillingPlus\Module\Telephony\AdminTelephonyWorkspaceService;
use A2BillingPlus\Module\Telephony\AsteriskConfigCheckService;
use A2BillingPlus\Module\Telephony\DidRepository;
use A2BillingPlus\Module\Telephony\DidService;
use A2BillingPlus\Module\Telephony\TelephonyAccountRepository;
use A2BillingPlus\Module\Telephony\TelephonyAccountService;
use A2BillingPlus\Module\Telephony\TrunkRepository;
use A2BillingPlus\Module\Telephony\TrunkService;
use PHPUnit\Framework\TestCase;

final class AdminTelephonyWorkspaceServiceTest extends TestCase
{
    public function testWorkspaceCombinesTelephonySections(): void
    {
        $service = $this->service();

        $workspace = $service->workspace(
            '10',
            '1',
            '1',
            '1',
            25,
            ['did' => true, 'trunk' => true, 'accounts' => true],
            [
                'version' => '20.2.1',
                'ami_user' => 'ami-user',
                'ami_password' => 'this-is-a-long-ami-password',
                'ari_user' => 'ari-user',
                'ari_password' => 'this-is-a-long-ari-password',
                'channel_driver' => 'pjsip',
                'realtime_enabled' => 'yes',
            ]
        );

        $this->assertSame('10', $workspace['filters']['customer_id']);
        $this->assertSame(1, $workspace['summary']['dids']);
        $this->assertSame(1, $workspace['summary']['trunks']);
        $this->assertSame(1, $workspace['summary']['sip_accounts']);
        $this->assertSame(1, $workspace['summary']['iax_accounts']);
        $this->assertSame(1, $workspace['summary']['vectavoip_requests']);
        $this->assertSame(1, $workspace['summary']['pjsip_trunks']);
        $this->assertTrue($workspace['summary']['asterisk_ready']);
        $this->assertSame('+15551234567', $workspace['dids']['items'][0]['did']);
        $this->assertSame('DEFAULT', $workspace['trunks']['items'][0]['trunkcode']);
        $this->assertSame('business', $workspace['vectavoip_requests']['items'][0]['package_code']);
        $this->assertSame('trunk-business', $workspace['pjsip_trunks']['items'][0]['endpoint_id']);
    }

    public function testWorkspaceRespectsCapabilitiesAndNormalizesBadFilters(): void
    {
        $service = $this->service();

        $workspace = $service->workspace(
            'bad',
            '9',
            '5',
            'nope',
            500,
            ['did' => false, 'trunk' => true, 'accounts' => false],
            [
                'version' => '18.0.0',
                'ami_user' => 'ami-user',
                'ami_password' => 'short',
                'ari_user' => 'ari-user',
                'ari_password' => 'short',
                'channel_driver' => 'sip',
                'realtime_enabled' => 'no',
            ]
        );

        $this->assertSame('', $workspace['filters']['customer_id']);
        $this->assertSame('', $workspace['filters']['trunk_status']);
        $this->assertSame('', $workspace['filters']['did_reserved']);
        $this->assertSame('', $workspace['filters']['did_activated']);
        $this->assertSame(25, $workspace['filters']['limit']);
        $this->assertCount(0, $workspace['dids']['items']);
        $this->assertCount(0, $workspace['sip_accounts']['items']);
        $this->assertFalse($workspace['summary']['asterisk_ready']);
    }

    private function service(): AdminTelephonyWorkspaceService
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
        $this->createBuddyTable($pdo, 'cc_sip_buddies');
        $this->createBuddyTable($pdo, 'cc_iax_buddies');
        $pdo->exec('CREATE TABLE cc_vectavoip_did_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, package_code TEXT, did_count INTEGER, sms_enabled INTEGER, e911_enabled INTEGER, ratecard_id INTEGER, trunk_id INTEGER, account_number TEXT, registered_ip TEXT, notes TEXT, status TEXT, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE ps_endpoints (id TEXT PRIMARY KEY, transport TEXT, aors TEXT, auth TEXT, context TEXT, disallow TEXT, allow TEXT, direct_media TEXT, rtp_symmetric TEXT, force_rport TEXT, rewrite_contact TEXT)');
        $pdo->exec('CREATE TABLE cc_a2bp_pjsip_endpoint_map (endpoint_id TEXT PRIMARY KEY, endpoint_type TEXT, owner_id INTEGER, label TEXT, created_at TEXT, updated_at TEXT)');

        $pdo->exec("INSERT INTO cc_did (id, id_cc_didgroup, id_cc_country, activated, reserved, iduser, did, creationdate, billingtype, fixrate) VALUES (1, 1, 1, 1, 1, 10, '+15551234567', '2026-05-05', 1, 1.50)");
        $pdo->exec("INSERT INTO cc_did_destination (destination, priority, id_cc_card, id_cc_did, creationdate, activated, secondusedreal, voip_call, validated) VALUES ('1001', 1, 10, 1, '2026-05-05', 1, 0, 1, 1)");
        $pdo->exec("INSERT INTO cc_trunk (id_trunk, trunkcode, trunkprefix, providertech, providerip, removeprefix, creationdate, failover_trunk, addparameter, id_provider, inuse, maxuse, status, if_max_use) VALUES (1, 'DEFAULT', '011', 'IAX2', 'examplehost', '', '2026-05-05', 0, '', NULL, 0, -1, 1, 0)");
        $pdo->exec("INSERT INTO cc_sip_buddies (id, id_cc_card, name, accountcode, regexten, callerid, context, host, qualify, secret, type, username, disallow, allow) VALUES (1, 10, '1001', '1001', '1001', '1001', 'a2billing', 'dynamic', 'yes', 'hidden', 'friend', '1001', 'all', 'ulaw')");
        $pdo->exec("INSERT INTO cc_iax_buddies (id, id_cc_card, name, accountcode, regexten, callerid, context, host, qualify, secret, type, username, disallow, allow) VALUES (1, 10, '2001', '2001', '2001', '2001', 'a2billing', 'dynamic', 'yes', 'hidden', 'friend', '2001', 'all', 'ulaw')");
        $pdo->exec("INSERT INTO cc_vectavoip_did_requests (id, package_code, did_count, sms_enabled, e911_enabled, ratecard_id, trunk_id, account_number, registered_ip, notes, status, created_at, updated_at) VALUES (1, 'business', 3, 1, 0, 5, 2, 'VV12345', '74.208.7.156', 'Provision test', 'requested', '2026-05-05', '2026-05-05')");
        $pdo->exec("INSERT INTO ps_endpoints (id, transport, aors, auth, context, disallow, allow, direct_media, rtp_symmetric, force_rport, rewrite_contact) VALUES ('trunk-business', 'transport-udp', 'trunk-business', 'trunk-business-auth', 'from-pstn', 'all', 'ulaw,alaw', 'no', 'yes', 'yes', 'yes')");
        $pdo->exec("INSERT INTO cc_a2bp_pjsip_endpoint_map (endpoint_id, endpoint_type, owner_id, label, created_at, updated_at) VALUES ('trunk-business', 'trunk', 0, 'BUSINESSPRIMARY', '2026-05-05', '2026-05-05')");

        return new AdminTelephonyWorkspaceService(
            new DidService(new DidRepository($pdo), $pdo),
            new TrunkService(new TrunkRepository($pdo)),
            new TelephonyAccountService(new TelephonyAccountRepository($pdo)),
            new AsteriskConfigCheckService(),
            $pdo
        );
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

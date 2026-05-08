<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Api\TelephonyAccountController;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;
use PHPUnit\Framework\TestCase;

final class TelephonyAccountControllerTest extends TestCase
{
    public function testListsAndLoadsAccounts(): void
    {
        $controller = $this->controller($this->pdo());

        $list = $controller->handle(new JsonRequest('GET', ['technology' => 'sip', 'customer_id' => '10'], [], $this->headers()));
        $detail = $controller->handle(new JsonRequest('GET', ['technology' => 'sip', 'id' => '1'], [], $this->headers()));

        $this->assertSame(200, $list->getStatusCode());
        $this->assertSame('1001', $detail->getPayload()['data']['account']['username']);
        $this->assertArrayNotHasKey('secret', $detail->getPayload()['data']['account']);
    }

    public function testCreatesAndUpdatesAccountThroughApi(): void
    {
        $pdo = $this->pdo();
        $controller = $this->controller($pdo);

        $create = $controller->handle(new JsonRequest('POST', ['technology' => 'sip'], [
            'account' => [
                'id_cc_card' => 10,
                'username' => '1002',
                'secret' => 'super-secret',
            ],
        ], $this->headers()));
        $id = (int)$create->getPayload()['data']['account']['id'];
        $update = $controller->handle(new JsonRequest('PUT', ['technology' => 'sip', 'id' => (string)$id], [
            'account' => ['callerid' => 'Desk Phone'],
        ], $this->headers()));

        $this->assertSame(201, $create->getStatusCode());
        $this->assertArrayNotHasKey('secret', $create->getPayload()['data']['account']);
        $this->assertSame(200, $update->getStatusCode());
        $this->assertSame('Desk Phone', $update->getPayload()['data']['account']['callerid']);
        $this->assertSame(4, (int)$pdo->query('SELECT COUNT(*) FROM cc_a2bp_audit_log')->fetchColumn());
    }

    public function testRejectsPjsipUntilProvisioningModuleExists(): void
    {
        $response = $this->controller($this->pdo())->handle(new JsonRequest('GET', ['technology' => 'pjsip'], [], $this->headers()));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('invalid_technology', $response->getPayload()['error']['code']);
    }

    public function testRequiresServiceKey(): void
    {
        $response = $this->controller($this->pdo())->handle(new JsonRequest('GET'));

        $this->assertSame(401, $response->getStatusCode());
    }

    private function controller(PDO $pdo): TelephonyAccountController
    {
        return new TelephonyAccountController(
            new ApiServiceKeyAuthenticator(new AppConfig(['A2BP_API_SERVICE_KEY' => 'secret-key'])),
            fn (): PDO => $pdo
        );
    }

    /**
     * @return array<string,string>
     */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer secret-key',
            'X-A2BP-Actor' => 'admin:root',
        ];
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createBuddyTable($pdo, 'cc_sip_buddies');
        $this->createBuddyTable($pdo, 'cc_iax_buddies');
        $pdo->exec("INSERT INTO cc_sip_buddies (id, id_cc_card, name, accountcode, regexten, callerid, context, host, qualify, secret, type, username, disallow, allow) VALUES (1, 10, '1001', '1001', '1001', '1001', 'a2billing', 'dynamic', 'yes', 'hidden', 'friend', '1001', 'all', 'ulaw')");

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

<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Api\RestApiController;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;
use PHPUnit\Framework\TestCase;

final class RestApiControllerTest extends TestCase
{
    public function testRejectsMissingAuthorizationHeader(): void
    {
        $controller = $this->controller('secret-key', $this->pdo());
        $response = $controller->handle('customers', new JsonRequest('GET'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('missing_authorization', $response->getPayload()['error']['code']);
    }

    public function testRejectsInvalidServiceKey(): void
    {
        $controller = $this->controller('secret-key', $this->pdo());
        $response = $controller->handle('customers', new JsonRequest('GET', [], [], [
            'Authorization' => 'Bearer wrong-key',
        ]));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('invalid_service_key', $response->getPayload()['error']['code']);
    }

    public function testRejectsUnconfiguredServiceKey(): void
    {
        $controller = $this->controller('', $this->pdo());
        $response = $controller->handle('customers', new JsonRequest('GET', [], [], [
            'Authorization' => 'Bearer secret-key',
        ]));

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('api_auth_not_configured', $response->getPayload()['error']['code']);
    }

    public function testRejectsInvalidLimit(): void
    {
        $controller = $this->controller('secret-key', $this->pdo());
        $response = $controller->handle('customers', new JsonRequest('GET', ['limit' => '101'], [], [
            'Authorization' => 'Bearer secret-key',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('invalid_limit', $response->getPayload()['error']['code']);
    }

    public function testRejectsInvalidCustomerStatusFilter(): void
    {
        $controller = $this->controller('secret-key', $this->pdo());
        $response = $controller->handle('customers', new JsonRequest('GET', ['status' => 'blocked'], [], [
            'Authorization' => 'Bearer secret-key',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('invalid_status', $response->getPayload()['error']['code']);
    }

    public function testListsCustomersWithStandardEnvelope(): void
    {
        $controller = $this->controller('secret-key', $this->pdo());
        $response = $controller->handle('customers', new JsonRequest('GET', ['limit' => '10'], [], [
            'Authorization' => 'Bearer secret-key',
        ]));

        $payload = $response->getPayload();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('v1', $payload['api_version']);
        $this->assertTrue($payload['success']);
        $this->assertSame('alice', $payload['data']['customers'][0]['username']);
        $this->assertSame('customers', $payload['meta']['resource']);
    }

    public function testListsCustomersThroughCustomerModuleFilters(): void
    {
        $controller = $this->controller('secret-key', $this->pdo());
        $response = $controller->handle('customers', new JsonRequest('GET', [
            'limit' => '10',
            'search' => 'alice',
            'status' => '1',
        ], [], [
            'Authorization' => 'Bearer secret-key',
        ]));

        $payload = $response->getPayload();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $payload['data']['customers']);
        $this->assertSame('alice', $payload['data']['customers'][0]['username']);
        $this->assertSame('alice', $payload['meta']['filters']['search']);
        $this->assertSame(1, $payload['meta']['filters']['status']);
    }

    public function testLoadsCustomerDetail(): void
    {
        $controller = $this->controller('secret-key', $this->pdo());
        $response = $controller->handle('customers', new JsonRequest('GET', ['id' => '1'], [], [
            'Authorization' => 'Bearer secret-key',
        ]));

        $payload = $response->getPayload();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('alice', $payload['data']['customer']['username']);
        $this->assertSame(1, $payload['meta']['id']);
    }

    public function testReturnsNotFoundForMissingCustomerDetail(): void
    {
        $controller = $this->controller('secret-key', $this->pdo());
        $response = $controller->handle('customers', new JsonRequest('GET', ['id' => '999'], [], [
            'Authorization' => 'Bearer secret-key',
        ]));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('customer_not_found', $response->getPayload()['error']['code']);
    }

    public function testUpdatesCustomerStatusAndAuditsActor(): void
    {
        $pdo = $this->pdo();
        $controller = $this->controller('secret-key', $pdo);
        $response = $controller->handle('customers', new JsonRequest('PATCH', [], [
            'id' => '1',
            'status' => '0',
        ], [
            'Authorization' => 'Bearer secret-key',
            'X-A2BP-Actor' => 'admin:root',
        ]));

        $payload = $response->getPayload();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, (int)$payload['data']['customer']['status']);
        $this->assertSame('status_update', $payload['meta']['action']);
        $this->assertSame('admin:root', $pdo->query('SELECT actor FROM cc_a2bp_audit_log')->fetchColumn());
    }

    public function testRejectsInvalidCustomerStatusUpdate(): void
    {
        $controller = $this->controller('secret-key', $this->pdo());
        $response = $controller->handle('customers', new JsonRequest('PATCH', [], [
            'id' => '1',
            'status' => '2',
        ], [
            'Authorization' => 'Bearer secret-key',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('invalid_status', $response->getPayload()['error']['code']);
    }

    public function testListsRatesThroughRateModuleFilters(): void
    {
        $controller = $this->controller('secret-key', $this->pdo());
        $response = $controller->handle('rates', new JsonRequest('GET', [
            'prefix' => '1',
            'tariff_plan_id' => '7',
            'tag' => 'VectaVoIP:retail',
        ], [], [
            'Authorization' => 'Bearer secret-key',
        ]));

        $payload = $response->getPayload();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $payload['data']['rates']);
        $this->assertSame('1', $payload['data']['rates'][0]['dialprefix']);
        $this->assertSame('1', $payload['meta']['filters']['prefix']);
        $this->assertSame(7, $payload['meta']['filters']['tariff_plan_id']);
    }

    public function testRejectsInvalidRatePrefixFilter(): void
    {
        $controller = $this->controller('secret-key', $this->pdo());
        $response = $controller->handle('rates', new JsonRequest('GET', ['prefix' => 'abc'], [], [
            'Authorization' => 'Bearer secret-key',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('invalid_prefix', $response->getPayload()['error']['code']);
    }

    public function testListsCdrsThroughBillingModuleFilters(): void
    {
        $controller = $this->controller('secret-key', $this->pdo());
        $response = $controller->handle('cdrs', new JsonRequest('GET', [
            'from' => '2026-05-03 00:00:00',
            'to' => '2026-05-04 00:00:00',
            'customer_id' => '1',
            'calledstation' => '1800',
        ], [], [
            'Authorization' => 'Bearer secret-key',
        ]));

        $payload = $response->getPayload();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $payload['data']['cdrs']);
        $this->assertSame('s1', $payload['data']['cdrs'][0]['sessionid']);
        $this->assertSame(1, $payload['meta']['filters']['customer_id']);
    }

    public function testRejectsInvalidCdrDateFilter(): void
    {
        $controller = $this->controller('secret-key', $this->pdo());
        $response = $controller->handle('cdrs', new JsonRequest('GET', ['from' => '05/03/2026'], [], [
            'Authorization' => 'Bearer secret-key',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('invalid_from', $response->getPayload()['error']['code']);
    }

    public function testListsPaymentsThroughPaymentModuleFilters(): void
    {
        $controller = $this->controller('secret-key', $this->pdo());
        $response = $controller->handle('payments', new JsonRequest('GET', [
            'from' => '2026-05-03',
            'to' => '2026-05-04',
            'customer_id' => '1',
        ], [], [
            'Authorization' => 'Bearer secret-key',
        ]));

        $payload = $response->getPayload();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $payload['data']['payments']);
        $this->assertSame('10.00', $payload['data']['payments'][0]['payment']);
        $this->assertSame(1, $payload['meta']['filters']['customer_id']);
    }

    public function testRejectsInvalidPaymentCustomerFilter(): void
    {
        $controller = $this->controller('secret-key', $this->pdo());
        $response = $controller->handle('payments', new JsonRequest('GET', ['customer_id' => 'zero'], [], [
            'Authorization' => 'Bearer secret-key',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('invalid_customer_id', $response->getPayload()['error']['code']);
    }

    public function testListsAllInitialResources(): void
    {
        $controller = $this->controller('secret-key', $this->pdo());

        foreach (RestApiController::RESOURCES as $resource) {
            $response = $controller->handle($resource, new JsonRequest('GET', [], [], [
                'Authorization' => 'Bearer secret-key',
            ]));

            $this->assertSame(200, $response->getStatusCode(), $resource);
            $this->assertArrayHasKey($resource, $response->getPayload()['data'], $resource);
        }
    }

    private function controller(string $serviceKey, PDO $pdo): RestApiController
    {
        return new RestApiController(
            new ApiServiceKeyAuthenticator(new AppConfig(['A2BP_API_SERVICE_KEY' => $serviceKey])),
            fn (): PDO => $pdo
        );
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE cc_card (id INTEGER PRIMARY KEY, username TEXT, useralias TEXT, firstname TEXT, lastname TEXT, credit TEXT, currency TEXT, status INTEGER, activated TEXT, id_group INTEGER, creationdate TEXT, email TEXT, uipass TEXT)');
        $pdo->exec('CREATE TABLE cc_ratecard (id INTEGER PRIMARY KEY, idtariffplan INTEGER, dialprefix TEXT, destination TEXT, buyrate TEXT, rateinitial TEXT, initblock INTEGER, billingblock INTEGER, tag TEXT)');
        $pdo->exec('CREATE TABLE cc_logpayment (id INTEGER PRIMARY KEY, date TEXT, payment TEXT, card_id INTEGER, description TEXT)');
        $pdo->exec('CREATE TABLE cc_call (id INTEGER PRIMARY KEY, sessionid TEXT, uniqueid TEXT, starttime TEXT, stoptime TEXT, sessiontime INTEGER, calledstation TEXT, sessionbill TEXT, buycost TEXT, terminatecauseid INTEGER, id_card INTEGER)');
        $pdo->exec('CREATE TABLE cc_provider (id INTEGER PRIMARY KEY, provider_name TEXT, description TEXT)');
        $pdo->exec('CREATE TABLE cc_invoice (id INTEGER PRIMARY KEY, id_card INTEGER, title TEXT, reference TEXT, paid_status INTEGER)');
        $pdo->exec("INSERT INTO cc_card (id, username, useralias, firstname, lastname, credit, currency, status, activated, id_group, creationdate, email, uipass) VALUES (1, 'alice', 'alice-a', 'Alice', 'Able', '10.00', 'USD', 1, '1', 1, '2026-05-03', 'alice@example.test', 'secret')");
        $pdo->exec("INSERT INTO cc_ratecard (id, idtariffplan, dialprefix, destination, buyrate, rateinitial, initblock, billingblock, tag) VALUES (1, 7, '1', 'United States', '0.0100', '0.0200', 60, 60, 'VectaVoIP:retail')");
        $pdo->exec("INSERT INTO cc_logpayment (id, date, payment, card_id, description) VALUES (1, '2026-05-03', '10.00', 1, 'top up')");
        $pdo->exec("INSERT INTO cc_call (id, sessionid, uniqueid, starttime, stoptime, sessiontime, calledstation, sessionbill, buycost, terminatecauseid, id_card) VALUES (1, 's1', 'u1', '2026-05-03 10:00:00', '2026-05-03 10:01:00', 60, '18005551212', '0.01', '0.005', 1, 1)");
        $pdo->exec("INSERT INTO cc_provider (id, provider_name, description) VALUES (1, 'VectaVoIP', 'default provider')");
        $pdo->exec("INSERT INTO cc_invoice (id, id_card, title, reference, paid_status) VALUES (1, 1, 'Invoice', 'INV-1', 0)");

        return $pdo;
    }
}

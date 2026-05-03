<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiCustomerContextAuthenticator;
use A2BillingPlus\Api\CustomerSelfServiceController;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;
use PHPUnit\Framework\TestCase;

final class CustomerSelfServiceControllerTest extends TestCase
{
    public function testReturnsOnlySignedCustomerProfileBalanceAndStatus(): void
    {
        $controller = $this->controller($this->pdo());
        $response = $controller->handle(new JsonRequest('GET', [], [], $this->headers(1)));
        $payload = $response->getPayload();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('alice', $payload['data']['profile']['username']);
        $this->assertSame('10.00', $payload['data']['balance']['credit']);
        $this->assertSame(1, (int)$payload['data']['status']['status']);
        $this->assertSame(1, $payload['meta']['customer_id']);
        $this->assertArrayNotHasKey('uipass', $payload['data']['profile']);
    }

    public function testUpdatesOnlySignedCustomerContactFields(): void
    {
        $pdo = $this->pdo();
        $controller = $this->controller($pdo);
        $response = $controller->handle(new JsonRequest('PUT', [], [
            'customer' => [
                'email' => 'alice.updated@example.test',
                'phone' => '5551212',
                'status' => 0,
                'id_group' => 9,
            ],
        ], $this->headers(1)));
        $payload = $response->getPayload();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('alice.updated@example.test', $payload['data']['profile']['email']);
        $this->assertSame('5551212', $payload['data']['profile']['phone']);
        $this->assertSame(1, (int)$payload['data']['profile']['status']);
        $this->assertSame(1, (int)$payload['data']['profile']['id_group']);
        $this->assertSame('customer:1', $pdo->query('SELECT actor FROM cc_a2bp_audit_log')->fetchColumn());
    }

    public function testRejectsUnsignedCustomerProfileAccess(): void
    {
        $response = $this->controller($this->pdo())->handle(new JsonRequest('GET'));

        $this->assertSame(401, $response->getStatusCode());
    }

    private function controller(PDO $pdo): CustomerSelfServiceController
    {
        return new CustomerSelfServiceController(
            new ApiCustomerContextAuthenticator(new AppConfig(['A2BP_CUSTOMER_API_SECRET' => 'customer-secret'])),
            fn (): PDO => $pdo
        );
    }

    /**
     * @return array<string, string>
     */
    private function headers(int $customerId): array
    {
        return [
            'X-A2BP-Customer-Id' => (string)$customerId,
            'X-A2BP-Customer-Signature' => hash_hmac('sha256', (string)$customerId, 'customer-secret'),
        ];
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cc_card (
                id INTEGER PRIMARY KEY,
                username TEXT,
                useralias TEXT,
                firstname TEXT,
                lastname TEXT,
                email TEXT,
                phone TEXT,
                credit TEXT,
                currency TEXT,
                status INTEGER,
                activated TEXT,
                id_group INTEGER,
                creationdate TEXT,
                uipass TEXT
            )'
        );
        $pdo->exec("INSERT INTO cc_card (id, username, useralias, firstname, lastname, email, phone, credit, currency, status, activated, id_group, creationdate, uipass) VALUES (1, 'alice', 'alice-a', 'Alice', 'Able', 'alice@example.test', '', '10.00', 'USD', 1, '1', 1, '2026-05-03', 'secret')");
        $pdo->exec("INSERT INTO cc_card (id, username, useralias, firstname, lastname, email, phone, credit, currency, status, activated, id_group, creationdate, uipass) VALUES (2, 'bob', 'bob-b', 'Bob', 'Blocked', 'bob@example.test', '', '0.00', 'USD', 0, '1', 1, '2026-05-03', 'secret')");

        return $pdo;
    }
}

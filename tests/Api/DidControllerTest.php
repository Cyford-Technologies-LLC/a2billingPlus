<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Api\DidController;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;
use PHPUnit\Framework\TestCase;

final class DidControllerTest extends TestCase
{
    public function testListsAndLoadsDids(): void
    {
        $controller = $this->controller($this->pdo());

        $list = $controller->handle(new JsonRequest('GET', ['reserved' => '1'], [], $this->headers()));
        $detail = $controller->handle(new JsonRequest('GET', ['id' => '1'], [], $this->headers()));

        $this->assertSame(200, $list->getStatusCode());
        $this->assertSame('+15551234567', $detail->getPayload()['data']['did']['did']);
    }

    public function testAssignsReleasesAndRoutesDidThroughApi(): void
    {
        $pdo = $this->pdo();
        $controller = $this->controller($pdo);

        $assign = $controller->handle(new JsonRequest('POST', [], [
            'assignment' => ['id' => 2, 'customer_id' => 42],
        ], $this->headers()));
        $route = $controller->handle(new JsonRequest('PUT', [], [
            'routing' => [
                'id' => 2,
                'customer_id' => 42,
                'destinations' => [
                    ['destination' => '1002', 'priority' => 1],
                ],
            ],
        ], $this->headers()));
        $release = $controller->handle(new JsonRequest('DELETE', ['id' => '2'], [], $this->headers()));

        $this->assertSame(200, $assign->getStatusCode());
        $this->assertSame(200, $route->getStatusCode());
        $this->assertSame('1002', $route->getPayload()['data']['did']['destinations'][0]['destination']);
        $this->assertSame(200, $release->getStatusCode());
        $this->assertSame(0, (int)$release->getPayload()['data']['did']['reserved']);
        $this->assertSame(3, (int)$pdo->query('SELECT COUNT(*) FROM cc_a2bp_audit_log')->fetchColumn());
    }

    public function testRejectsInvalidDidFilter(): void
    {
        $response = $this->controller($this->pdo())->handle(new JsonRequest('GET', ['reserved' => 'maybe'], [], $this->headers()));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('invalid_filter', $response->getPayload()['error']['code']);
    }

    public function testRequiresServiceKey(): void
    {
        $response = $this->controller($this->pdo())->handle(new JsonRequest('GET'));

        $this->assertSame(401, $response->getStatusCode());
    }

    private function controller(PDO $pdo): DidController
    {
        return new DidController(
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
                fixrate REAL
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
        $pdo->exec("INSERT INTO cc_did (id, id_cc_didgroup, id_cc_country, activated, reserved, iduser, did, creationdate, billingtype, fixrate) VALUES (1, 1, 1, 1, 1, 10, '+15551234567', '2026-05-03', 1, 1.50)");
        $pdo->exec("INSERT INTO cc_did (id, id_cc_didgroup, id_cc_country, activated, reserved, iduser, did, creationdate, billingtype, fixrate) VALUES (2, 1, 1, 1, 0, 0, '+15557654321', '2026-05-03', 1, 1.50)");
        $pdo->exec("INSERT INTO cc_did_destination (destination, priority, id_cc_card, id_cc_did, creationdate, activated, secondusedreal, voip_call, validated) VALUES ('1001', 1, 10, 1, '2026-05-03', 1, 0, 1, 1)");

        return $pdo;
    }
}

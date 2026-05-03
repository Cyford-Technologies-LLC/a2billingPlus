<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Api\TrunkController;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;
use PHPUnit\Framework\TestCase;

final class TrunkControllerTest extends TestCase
{
    public function testListsAndLoadsTrunks(): void
    {
        $controller = $this->controller($this->pdo());

        $list = $controller->handle(new JsonRequest('GET', ['status' => '1'], [], $this->headers()));
        $detail = $controller->handle(new JsonRequest('GET', ['id' => '1'], [], $this->headers()));

        $this->assertSame(200, $list->getStatusCode());
        $this->assertSame('DEFAULT', $detail->getPayload()['data']['trunk']['trunkcode']);
    }

    public function testCreatesAndUpdatesTrunkThroughApi(): void
    {
        $pdo = $this->pdo();
        $controller = $this->controller($pdo);

        $create = $controller->handle(new JsonRequest('POST', [], [
            'trunk' => [
                'trunkcode' => 'VECTA',
                'providertech' => 'PJSIP',
                'providerip' => 'sip.vectavoip.com',
            ],
        ], $this->headers()));
        $id = (int)$create->getPayload()['data']['trunk']['id_trunk'];
        $update = $controller->handle(new JsonRequest('PUT', [], [
            'id' => (string)$id,
            'trunk' => [
                'status' => 0,
            ],
        ], $this->headers()));

        $this->assertSame(201, $create->getStatusCode());
        $this->assertSame(200, $update->getStatusCode());
        $this->assertSame(0, (int)$update->getPayload()['data']['trunk']['status']);
        $this->assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM cc_a2bp_audit_log')->fetchColumn());
    }

    public function testRejectsInvalidTrunkPayload(): void
    {
        $response = $this->controller($this->pdo())->handle(new JsonRequest('POST', [], [
            'trunk' => [
                'trunkcode' => 'BAD',
                'providertech' => 'skinny',
                'providerip' => 'example.test',
            ],
        ], $this->headers()));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('trunk_validation_failed', $response->getPayload()['error']['code']);
    }

    private function controller(PDO $pdo): TrunkController
    {
        return new TrunkController(
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
        $pdo->exec("INSERT INTO cc_trunk (id_trunk, trunkcode, trunkprefix, providertech, providerip, removeprefix, creationdate, failover_trunk, addparameter, id_provider, inuse, maxuse, status, if_max_use) VALUES (1, 'DEFAULT', '011', 'IAX2', 'examplehost', '', '2026-05-03', 0, '', NULL, 0, -1, 1, 0)");

        return $pdo;
    }
}

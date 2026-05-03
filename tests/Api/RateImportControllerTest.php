<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Api\RateImportController;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;
use PHPUnit\Framework\TestCase;

final class RateImportControllerTest extends TestCase
{
    public function testDryRunDoesNotInsertRowsButRecordsAudit(): void
    {
        $pdo = $this->pdo();
        $response = $this->controller($pdo)->handle($this->request([
            'tariff_plan_id' => 7,
            'tag' => 'manual:test',
            'dry_run' => '1',
            'rows' => [
                ['destination' => 'United States', 'prefix' => '1', 'rate' => '0.0100', 'increment' => 60],
            ],
        ]));

        $payload = $response->getPayload();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $payload['data']['import']['imported_rows']);
        $this->assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM cc_ratecard')->fetchColumn());
        $this->assertSame('rate.import.apply', $pdo->query('SELECT action FROM cc_a2bp_audit_log')->fetchColumn());
    }

    public function testImportsRowsAndSkipsDuplicatesUnlessUpdateExistingIsEnabled(): void
    {
        $pdo = $this->pdo();
        $controller = $this->controller($pdo);
        $body = [
            'tariff_plan_id' => 7,
            'tag' => 'manual:test',
            'dry_run' => '0',
            'rows' => [
                ['destination' => 'United States', 'prefix' => '1', 'rate' => '0.0100', 'increment' => 60],
            ],
        ];

        $first = $controller->handle($this->request($body));
        $second = $controller->handle($this->request($body));
        $update = $controller->handle($this->request($body + ['update_existing' => '1']));

        $this->assertSame(1, $first->getPayload()['data']['import']['imported_rows']);
        $this->assertSame(0, $second->getPayload()['data']['import']['imported_rows']);
        $this->assertSame(1, $second->getPayload()['data']['import']['skipped_rows']);
        $this->assertSame(1, $update->getPayload()['data']['import']['imported_rows']);
        $this->assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM cc_ratecard')->fetchColumn());
    }

    public function testRejectsMissingRows(): void
    {
        $response = $this->controller($this->pdo())->handle($this->request([
            'tariff_plan_id' => 7,
            'tag' => 'manual:test',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('invalid_rows', $response->getPayload()['error']['code']);
    }

    private function controller(PDO $pdo): RateImportController
    {
        return new RateImportController(
            new ApiServiceKeyAuthenticator(new AppConfig(['A2BP_API_SERVICE_KEY' => 'secret-key'])),
            fn (): PDO => $pdo
        );
    }

    /**
     * @param array<string,mixed> $body
     */
    private function request(array $body): JsonRequest
    {
        return new JsonRequest('POST', [], $body, [
            'Authorization' => 'Bearer secret-key',
            'X-A2BP-Actor' => 'admin:root',
        ]);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cc_ratecard (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                idtariffplan INTEGER,
                dialprefix TEXT,
                destination INTEGER,
                buyrate TEXT,
                buyrateinitblock INTEGER,
                buyrateincrement INTEGER,
                rateinitial TEXT,
                initblock INTEGER,
                billingblock INTEGER,
                tag TEXT
            )'
        );

        return $pdo;
    }
}

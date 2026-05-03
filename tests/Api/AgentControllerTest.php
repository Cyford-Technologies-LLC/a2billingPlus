<?php

declare(strict_types=1);

use A2BillingPlus\Api\AgentController;
use A2BillingPlus\Api\ApiAgentContextAuthenticator;
use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Api\AgentCustomerController;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;
use PHPUnit\Framework\TestCase;

final class AgentControllerTest extends TestCase
{
    public function testAdminListsAndLoadsAgents(): void
    {
        $controller = new AgentController(new ApiServiceKeyAuthenticator(new AppConfig(['A2BP_API_SERVICE_KEY' => 'secret-key'])), fn (): PDO => $this->pdo());

        $list = $controller->handle(new JsonRequest('GET', ['active' => 't'], [], $this->serviceHeaders()));
        $detail = $controller->handle(new JsonRequest('GET', ['id' => '1'], [], $this->serviceHeaders()));

        $this->assertSame(200, $list->getStatusCode());
        $this->assertSame('agent1', $detail->getPayload()['data']['agent']['login']);
        $this->assertArrayNotHasKey('passwd', $detail->getPayload()['data']['agent']);
    }

    public function testSignedAgentSeesOnlyOwnedCustomers(): void
    {
        $controller = new AgentCustomerController(new ApiAgentContextAuthenticator(new AppConfig(['A2BP_AGENT_API_SECRET' => 'agent-secret'])), fn (): PDO => $this->pdo());

        $response = $controller->handle(new JsonRequest('GET', [], [], $this->agentHeaders(1)));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $response->getPayload()['data']['customers']);
        $this->assertSame('alice', $response->getPayload()['data']['customers'][0]['username']);
        $this->assertSame(1, $response->getPayload()['meta']['agent_id']);
    }

    public function testAgentEndpointRejectsBadSignature(): void
    {
        $controller = new AgentCustomerController(new ApiAgentContextAuthenticator(new AppConfig(['A2BP_AGENT_API_SECRET' => 'agent-secret'])), fn (): PDO => $this->pdo());

        $response = $controller->handle(new JsonRequest('GET', [], [], [
            'X-A2BP-Agent-Id' => '1',
            'X-A2BP-Agent-Signature' => 'bad',
        ]));

        $this->assertSame(403, $response->getStatusCode());
    }

    /**
     * @return array<string,string>
     */
    private function serviceHeaders(): array
    {
        return ['Authorization' => 'Bearer secret-key'];
    }

    /**
     * @return array<string,string>
     */
    private function agentHeaders(int $agentId): array
    {
        return [
            'X-A2BP-Agent-Id' => (string)$agentId,
            'X-A2BP-Agent-Signature' => hash_hmac('sha256', (string)$agentId, 'agent-secret'),
        ];
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE cc_agent (id INTEGER PRIMARY KEY, datecreation TEXT, active TEXT, login TEXT, passwd TEXT, language TEXT, credit TEXT, currency TEXT, commission TEXT, vat TEXT, perms INTEGER, lastname TEXT, firstname TEXT, phone TEXT, email TEXT, company TEXT, com_balance TEXT, threshold_remittance TEXT)');
        $pdo->exec('CREATE TABLE cc_agent_commission (id INTEGER PRIMARY KEY, id_payment INTEGER, id_card INTEGER, date TEXT, amount TEXT, description TEXT, id_agent INTEGER, commission_type INTEGER, commission_percent TEXT)');
        $pdo->exec('CREATE TABLE cc_card_group (id INTEGER PRIMARY KEY, name TEXT, id_agent INTEGER)');
        $pdo->exec('CREATE TABLE cc_card (id INTEGER PRIMARY KEY, username TEXT, useralias TEXT, firstname TEXT, lastname TEXT, credit TEXT, currency TEXT, status INTEGER, id_group INTEGER, creationdate TEXT, email TEXT)');
        $pdo->exec("INSERT INTO cc_agent (id, active, login, passwd, credit, currency, commission, com_balance, threshold_remittance) VALUES (1, 't', 'agent1', 'secret', '0.00', 'USD', '10.0000', '2.50', '10.00')");
        $pdo->exec("INSERT INTO cc_agent_commission (id, id_payment, id_card, date, amount, description, id_agent, commission_type, commission_percent) VALUES (1, 1, 1, '2026-05-03', '2.50', 'commission', 1, 1, '10.0000')");
        $pdo->exec("INSERT INTO cc_card_group (id, name, id_agent) VALUES (1, 'Agent group', 1), (2, 'Other group', 2)");
        $pdo->exec("INSERT INTO cc_card (id, username, useralias, firstname, lastname, credit, currency, status, id_group, creationdate, email) VALUES (1, 'alice', 'alice-a', 'Alice', 'Able', '10.00', 'USD', 1, 1, '2026-05-03', 'alice@example.test')");
        $pdo->exec("INSERT INTO cc_card (id, username, useralias, firstname, lastname, credit, currency, status, id_group, creationdate, email) VALUES (2, 'bob', 'bob-b', 'Bob', 'Baker', '20.00', 'USD', 1, 2, '2026-05-03', 'bob@example.test')");

        return $pdo;
    }
}

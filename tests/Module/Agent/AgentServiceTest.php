<?php

declare(strict_types=1);

use A2BillingPlus\Module\Agent\AgentRepository;
use A2BillingPlus\Module\Agent\AgentService;
use PHPUnit\Framework\TestCase;

final class AgentServiceTest extends TestCase
{
    public function testListsLoadsAgentAndCommissions(): void
    {
        $service = new AgentService(new AgentRepository($this->pdo()));

        $list = $service->list(10, 0, 't');
        $detail = $service->detail(1);

        $this->assertCount(1, $list['items']);
        $this->assertSame('agent1', $detail['login']);
        $this->assertSame('2.50', $detail['commissions'][0]['amount']);
        $this->assertArrayNotHasKey('passwd', $detail);
    }

    public function testReturnsOnlyOwnedCustomers(): void
    {
        $service = new AgentService(new AgentRepository($this->pdo()));

        $customers = $service->customers(1, 10, 0);

        $this->assertCount(1, $customers['items']);
        $this->assertSame('alice', $customers['items'][0]['username']);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($pdo);

        return $pdo;
    }

    private function createSchema(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE cc_agent (id INTEGER PRIMARY KEY, datecreation TEXT, active TEXT, login TEXT, passwd TEXT, language TEXT, credit TEXT, currency TEXT, commission TEXT, vat TEXT, perms INTEGER, lastname TEXT, firstname TEXT, phone TEXT, email TEXT, company TEXT, com_balance TEXT, threshold_remittance TEXT)');
        $pdo->exec('CREATE TABLE cc_agent_commission (id INTEGER PRIMARY KEY, id_payment INTEGER, id_card INTEGER, date TEXT, amount TEXT, description TEXT, id_agent INTEGER, commission_type INTEGER, commission_percent TEXT)');
        $pdo->exec('CREATE TABLE cc_card_group (id INTEGER PRIMARY KEY, name TEXT, id_agent INTEGER)');
        $pdo->exec('CREATE TABLE cc_card (id INTEGER PRIMARY KEY, username TEXT, useralias TEXT, firstname TEXT, lastname TEXT, credit TEXT, currency TEXT, status INTEGER, id_group INTEGER, creationdate TEXT, email TEXT)');
        $pdo->exec("INSERT INTO cc_agent (id, active, login, passwd, credit, currency, commission, com_balance, threshold_remittance) VALUES (1, 't', 'agent1', 'secret', '0.00', 'USD', '10.0000', '2.50', '10.00')");
        $pdo->exec("INSERT INTO cc_agent_commission (id, id_payment, id_card, date, amount, description, id_agent, commission_type, commission_percent) VALUES (1, 1, 1, '2026-05-03', '2.50', 'commission', 1, 1, '10.0000')");
        $pdo->exec("INSERT INTO cc_card_group (id, name, id_agent) VALUES (1, 'Agent group', 1), (2, 'Other group', 2)");
        $pdo->exec("INSERT INTO cc_card (id, username, useralias, firstname, lastname, credit, currency, status, id_group, creationdate, email) VALUES (1, 'alice', 'alice-a', 'Alice', 'Able', '10.00', 'USD', 1, 1, '2026-05-03', 'alice@example.test')");
        $pdo->exec("INSERT INTO cc_card (id, username, useralias, firstname, lastname, credit, currency, status, id_group, creationdate, email) VALUES (2, 'bob', 'bob-b', 'Bob', 'Baker', '20.00', 'USD', 1, 2, '2026-05-03', 'bob@example.test')");
    }
}

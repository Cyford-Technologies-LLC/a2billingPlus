<?php

declare(strict_types=1);

use A2BillingPlus\Module\Customer\AdminCustomerWorkspaceService;
use A2BillingPlus\Module\Customer\CustomerAccountRepository;
use A2BillingPlus\Module\Customer\CustomerAccountService;
use PHPUnit\Framework\TestCase;

final class AdminCustomerWorkspaceServiceTest extends TestCase
{
    public function testBuildsWorkspaceWithSummaryAndGroups(): void
    {
        $workspace = new AdminCustomerWorkspaceService(
            new CustomerAccountService(new CustomerAccountRepository($this->pdo()))
        );

        $result = $workspace->workspace('alice', '1');

        $this->assertSame('alice', $result['filters']['search']);
        $this->assertSame('1', $result['filters']['status']);
        $this->assertSame(1, $result['summary']['total']);
        $this->assertSame('alice', $result['customers']['items'][0]['username']);
        $this->assertCount(2, $result['groups']);
    }

    public function testRejectsInvalidStatusFilter(): void
    {
        $workspace = new AdminCustomerWorkspaceService(
            new CustomerAccountService(new CustomerAccountRepository($this->pdo()))
        );

        $this->expectException(InvalidArgumentException::class);
        $workspace->workspace('', 'blocked');
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
                credit TEXT,
                currency TEXT,
                status INTEGER,
                activated TEXT,
                id_group INTEGER,
                creationdate TEXT,
                company_name TEXT,
                company_website TEXT,
                uipass TEXT
            )'
        );
        $pdo->exec('CREATE TABLE cc_card_group (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO cc_card_group (id, name) VALUES (1, 'Default'), (2, 'Business')");
        $pdo->exec("INSERT INTO cc_card (id, username, useralias, firstname, lastname, email, credit, currency, status, activated, id_group, creationdate, company_name, company_website, uipass) VALUES (3, 'alice', 'alice-a', 'Alice', 'Able', 'alice@example.test', '10.00', 'USD', 1, '1', 1, '2026-05-03', '', '', 'secret')");
        $pdo->exec("INSERT INTO cc_card (id, username, useralias, firstname, lastname, email, credit, currency, status, activated, id_group, creationdate, company_name, company_website, uipass) VALUES (2, 'bob', 'bob-b', 'Bob', 'Blocked', 'bob@example.test', '0.00', 'USD', 0, '1', 2, '2026-05-03', '', '', 'secret')");

        return $pdo;
    }
}

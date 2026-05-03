<?php

declare(strict_types=1);

use A2BillingPlus\Module\Customer\CustomerAccountRepository;
use A2BillingPlus\Module\Customer\CustomerAccountService;
use A2BillingPlus\Module\Customer\CustomerSearchCriteria;
use A2BillingPlus\Module\Security\AuditLogRepository;
use PHPUnit\Framework\TestCase;

final class CustomerAccountServiceTest extends TestCase
{
    public function testSearchFiltersByStatusAndTextWithoutSensitiveFields(): void
    {
        $service = new CustomerAccountService(new CustomerAccountRepository($this->pdo()));

        $result = $service->search(new CustomerSearchCriteria(10, 0, 'alice', 1));

        $this->assertCount(1, $result['items']);
        $this->assertSame('alice', $result['items'][0]['username']);
        $this->assertSame('alice@example.test', $result['items'][0]['email']);
        $this->assertNotContains('uipass', $result['columns']);
    }

    public function testSearchHonorsLimitAndOffset(): void
    {
        $service = new CustomerAccountService(new CustomerAccountRepository($this->pdo()));

        $result = $service->search(new CustomerSearchCriteria(1, 1));

        $this->assertCount(1, $result['items']);
        $this->assertSame('bob', $result['items'][0]['username']);
    }

    public function testLoadsCustomerDetailById(): void
    {
        $service = new CustomerAccountService(new CustomerAccountRepository($this->pdo()));

        $customer = $service->detail(3);

        $this->assertIsArray($customer);
        $this->assertSame('alice', $customer['username']);
        $this->assertArrayNotHasKey('uipass', $customer);
    }

    public function testChangesStatusAndRecordsAuditLog(): void
    {
        $pdo = $this->pdo();
        $service = new CustomerAccountService(
            new CustomerAccountRepository($pdo),
            new AuditLogRepository($pdo)
        );

        $customer = $service->changeStatus(3, 0, 'admin:root');

        $this->assertIsArray($customer);
        $this->assertSame(0, (int)$customer['status']);
        $this->assertSame('customer.status.update', $pdo->query('SELECT action FROM cc_a2bp_audit_log')->fetchColumn());
        $this->assertSame('admin:root', $pdo->query('SELECT actor FROM cc_a2bp_audit_log')->fetchColumn());
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
                uipass TEXT
            )'
        );
        $pdo->exec("INSERT INTO cc_card (id, username, useralias, firstname, lastname, email, credit, currency, status, activated, id_group, creationdate, uipass) VALUES (3, 'alice', 'alice-a', 'Alice', 'Able', 'alice@example.test', '10.00', 'USD', 1, '1', 1, '2026-05-03', 'secret')");
        $pdo->exec("INSERT INTO cc_card (id, username, useralias, firstname, lastname, email, credit, currency, status, activated, id_group, creationdate, uipass) VALUES (2, 'bob', 'bob-b', 'Bob', 'Blocked', 'bob@example.test', '0.00', 'USD', 0, '1', 1, '2026-05-03', 'secret')");
        $pdo->exec("INSERT INTO cc_card (id, username, useralias, firstname, lastname, email, credit, currency, status, activated, id_group, creationdate, uipass) VALUES (1, 'carol', 'carol-c', 'Carol', 'Customer', 'carol@example.test', '5.00', 'USD', 1, '1', 1, '2026-05-03', 'secret')");

        return $pdo;
    }
}

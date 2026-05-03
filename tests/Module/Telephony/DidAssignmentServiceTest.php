<?php

declare(strict_types=1);

use A2BillingPlus\Module\Telephony\DidAssignmentService;
use A2BillingPlus\Module\Telephony\DidRepository;
use A2BillingPlus\Module\Security\AuditLogRepository;
use PHPUnit\Framework\TestCase;

final class DidAssignmentServiceTest extends TestCase
{
    public function testListsAvailableInventoryAndAssignsDidToCustomer(): void
    {
        $pdo = $this->pdo();
        $repository = new DidRepository($pdo);
        $service = new DidAssignmentService($pdo, new AuditLogRepository($pdo));

        $available = $repository->listAvailable(10, 0, 'US', 'NY');
        $result = $service->assign(42, '+15551230000', true, true, 'admin:root');

        $this->assertSame(1, $available['total']);
        $this->assertSame('+15551230000', $available['items'][0]['did']);
        $this->assertTrue($result['success']);
        $this->assertSame(42, (int)$result['assignment']['customer_id']);
        $this->assertSame('assigned', $repository->findByNumber('+15551230000')['status']);
        $this->assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM cc_did_assignment WHERE did = '+15551230000' AND status = 'active'")->fetchColumn());
        $this->assertSame('did_assignment.assign', $pdo->query('SELECT action FROM cc_a2bp_audit_log')->fetchColumn());
    }

    public function testRejectsUnavailableDid(): void
    {
        $result = (new DidAssignmentService($this->pdo()))->assign(42, '+15551230001');

        $this->assertFalse($result['success']);
        $this->assertSame('DID is not available for assignment.', $result['message']);
    }

    public function testListsAssignedDidsAndReleasesAssignment(): void
    {
        $pdo = $this->pdo();
        $service = new DidAssignmentService($pdo, new AuditLogRepository($pdo));
        $repository = new DidRepository($pdo);

        $service->assign(42, '+15551230000');
        $assigned = $repository->listAssignedToCustomer(42, 10, 0);
        $released = $service->release(42, '+15551230000', 'admin:root');

        $this->assertSame(1, $assigned['total']);
        $this->assertSame('+15551230000', $assigned['items'][0]['did']);
        $this->assertTrue($released['success']);
        $this->assertSame('available', $repository->findByNumber('+15551230000')['status']);
        $this->assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM cc_did_assignment WHERE did = '+15551230000' AND status = 'released'")->fetchColumn());
        $this->assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM cc_a2bp_audit_log')->fetchColumn());
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cc_vectavoip_did_inventory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                did TEXT NOT NULL,
                country TEXT NOT NULL,
                region TEXT NOT NULL,
                monthly_rate REAL NOT NULL,
                setup_rate REAL NOT NULL,
                currency TEXT NOT NULL,
                status TEXT NOT NULL,
                provider_reference TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE cc_did_assignment (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER NOT NULL,
                did TEXT NOT NULL,
                status TEXT NOT NULL,
                sms_enabled INTEGER NOT NULL,
                voice_enabled INTEGER NOT NULL,
                provider_reference TEXT NOT NULL,
                assigned_at TEXT NOT NULL,
                released_at TEXT
            )'
        );
        $pdo->exec("INSERT INTO cc_vectavoip_did_inventory (did, country, region, monthly_rate, setup_rate, currency, status, provider_reference, created_at, updated_at) VALUES ('+15551230000', 'US', 'NY', 1.25, 0.00, 'USD', 'available', 'did-1', '2026-05-03', '2026-05-03')");
        $pdo->exec("INSERT INTO cc_vectavoip_did_inventory (did, country, region, monthly_rate, setup_rate, currency, status, provider_reference, created_at, updated_at) VALUES ('+15551230001', 'US', 'CA', 1.25, 0.00, 'USD', 'assigned', 'did-2', '2026-05-03', '2026-05-03')");

        return $pdo;
    }
}

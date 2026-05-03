<?php

declare(strict_types=1);

use A2BillingPlus\Module\Security\AuditLogRepository;
use A2BillingPlus\Module\Security\CredentialPolicy;
use A2BillingPlus\Module\Security\CsrfTokenService;
use A2BillingPlus\Module\Security\RateLimitPolicy;
use PHPUnit\Framework\TestCase;

final class SecurityPolicyTest extends TestCase
{
    public function testBlocksDefaultCredentialsAndRequiresStrongPasswords(): void
    {
        $policy = new CredentialPolicy();

        $this->assertTrue($policy->isDefaultCredential('root', 'changepassword'));
        $this->assertFalse($policy->isDefaultCredential('admin', 'unique-secret'));
        $this->assertFalse($policy->isStrongPassword('short'));
        $this->assertTrue($policy->isStrongPassword('VectaVoIPSecret2026'));
    }

    public function testRateLimitPolicyRejectsExcessAttempts(): void
    {
        $policy = new RateLimitPolicy();

        $this->assertTrue($policy->allow('login:admin', 2, 60, 100));
        $this->assertTrue($policy->allow('login:admin', 2, 60, 101));
        $this->assertFalse($policy->allow('login:admin', 2, 60, 102));
        $this->assertTrue($policy->allow('login:admin', 2, 60, 200));
    }

    public function testRecordsAuditLogRows(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $repository = new AuditLogRepository($pdo);
        $repository->record('admin:1', 'balance.adjust', 'cc_card', '1001', ['amount' => '5.00']);

        $this->assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM cc_a2bp_audit_log')->fetchColumn());
        $this->assertSame('balance.adjust', $pdo->query('SELECT action FROM cc_a2bp_audit_log')->fetchColumn());
    }

    public function testIssuesAndVerifiesCsrfTokens(): void
    {
        $service = new CsrfTokenService();
        $token = $service->issue('session-123', 'balance-adjust', 'nonce');

        $this->assertTrue($service->verify('session-123', 'balance-adjust', $token));
        $this->assertFalse($service->verify('session-123', 'other-form', $token));
    }
}

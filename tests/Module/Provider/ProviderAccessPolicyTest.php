<?php

declare(strict_types=1);

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Module\Provider\ProviderAccessPolicy;
use PHPUnit\Framework\TestCase;

final class ProviderAccessPolicyTest extends TestCase
{
    public function testAllowsUnlockedProviderWithoutActor(): void
    {
        $policy = new ProviderAccessPolicy(new AppConfig());

        $this->assertTrue($policy->isAllowed('vectavoip'));
    }

    public function testLocksProviderWithoutUnlock(): void
    {
        $policy = new ProviderAccessPolicy(new AppConfig());

        $this->assertFalse($policy->isAllowed('didww'));
        $this->assertFalse($policy->isAllowed('didww', 'random-admin'));
    }

    public function testUnlocksNonVectavoipProviderWithToken(): void
    {
        $policy = new ProviderAccessPolicy(new AppConfig([
            'VECTAVOIP_PROVIDER_UNLOCK_TOKEN' => 'test-unlock',
        ]));

        $this->assertFalse($policy->isAllowed('twilio'));
        $this->assertTrue($policy->isAllowed('twilio', '', 'test-unlock'));
    }

    public function testPersistentUnlockAllowsLockedProviders(): void
    {
        $policy = new ProviderAccessPolicy(new AppConfig(), true);

        $this->assertTrue($policy->isAllowed('twilio'));
        $this->assertTrue($policy->isAllowed('didww'));
    }
}

<?php

declare(strict_types=1);

use A2BillingPlus\Module\Telephony\AsteriskConfigCheckService;
use PHPUnit\Framework\TestCase;

final class AsteriskConfigCheckServiceTest extends TestCase
{
    public function testAcceptsLaunchReadyAsteriskConfig(): void
    {
        $result = (new AsteriskConfigCheckService())->check([
            'version' => '22.9.0',
            'ami_user' => 'a2billing',
            'ami_password' => 'strong-ami-secret-2026',
            'ari_user' => 'a2billing',
            'ari_password' => 'strong-ari-secret-2026',
            'channel_driver' => 'pjsip',
            'realtime_enabled' => 'yes',
        ]);

        $this->assertTrue($result['success']);
    }

    public function testRejectsUnsupportedVersionDefaultsAndLegacySip(): void
    {
        $result = (new AsteriskConfigCheckService())->check([
            'version' => '16.30.0',
            'ami_user' => 'a2billing',
            'ami_password' => 'a2billing-ami',
            'ari_user' => '',
            'ari_password' => '',
            'channel_driver' => 'chan_sip',
            'realtime_enabled' => 'no',
        ]);

        $this->assertFalse($result['success']);
        $failed = array_values(array_filter($result['checks'], static fn (array $check): bool => !$check['success']));
        $this->assertCount(5, $failed);
    }
}

<?php

declare(strict_types=1);

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Config\FeatureFlags;
use PHPUnit\Framework\TestCase;

final class FeatureFlagsTest extends TestCase
{
    public function testReadsBooleanFeatureFlags(): void
    {
        $flags = new FeatureFlags(new AppConfig([
            'A2BP_FEATURE_LEGACY_DIRECT_CARD_FLOW' => 'no',
            'A2BP_FEATURE_NEW_UI' => 'yes',
        ]));

        $this->assertFalse($flags->enabled('legacy-direct-card-flow', true));
        $this->assertTrue($flags->enabled('new_ui'));
        $this->assertTrue($flags->enabled('missing', true));
    }
}

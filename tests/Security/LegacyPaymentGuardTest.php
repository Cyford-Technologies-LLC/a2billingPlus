<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../common/lib/a2bp_legacy_payment_guard.php';

final class LegacyPaymentGuardTest extends TestCase
{
    private array $originalPost = [];
    private string|false $originalFeatureFlag = false;

    protected function setUp(): void
    {
        $this->originalPost = $_POST;
        $this->originalFeatureFlag = getenv('A2BP_FEATURE_LEGACY_DIRECT_CARD_FLOW');
    }

    protected function tearDown(): void
    {
        $_POST = $this->originalPost;

        if ($this->originalFeatureFlag === false) {
            putenv('A2BP_FEATURE_LEGACY_DIRECT_CARD_FLOW');
            return;
        }

        putenv('A2BP_FEATURE_LEGACY_DIRECT_CARD_FLOW=' . $this->originalFeatureFlag);
    }

    public function testLegacyDirectCardFlowIsDisabledByDefault(): void
    {
        putenv('A2BP_FEATURE_LEGACY_DIRECT_CARD_FLOW');

        $this->assertFalse(a2bp_legacy_direct_card_flow_enabled());
    }

    public function testLegacyDirectCardFlowRequiresExplicitFeatureFlag(): void
    {
        putenv('A2BP_FEATURE_LEGACY_DIRECT_CARD_FLOW=true');

        $this->assertTrue(a2bp_legacy_direct_card_flow_enabled());
    }

    public function testRedactsSensitivePaymentFieldsFromPostPayloads(): void
    {
        $_POST = [
            'card_number' => '4111111111111111',
            'cvv' => '123',
            'customer_id' => '42',
        ];

        $this->assertSame(
            [
                'card_number' => '[redacted]',
                'cvv' => '[redacted]',
                'customer_id' => '42',
            ],
            a2bp_redacted_payment_post()
        );
    }

    public function testCheckoutProcessUsesGuardAndRedactedPostLogging(): void
    {
        $source = file_get_contents(__DIR__ . '/../../customer/checkout_process.php');
        $rawPostDump = 'print_r($' . '_POST, true)';

        $this->assertIsString($source);
        $this->assertStringContainsString('a2bp_legacy_direct_card_flow_enabled()', $source);
        $this->assertStringContainsString('a2bp_redacted_payment_post()', $source);
        $this->assertStringNotContainsString($rawPostDump, $source);
    }
}

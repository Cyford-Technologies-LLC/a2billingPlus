<?php

declare(strict_types=1);

use A2BillingPlus\Module\Payment\PaymentConfigValidator;
use A2BillingPlus\Module\Payment\PaymentGatewayInventory;
use A2BillingPlus\Module\Payment\PaymentReconciliationService;
use A2BillingPlus\Module\Payment\PaymentSensitiveDataGuard;
use A2BillingPlus\Module\Payment\StripePaymentModule;
use A2BillingPlus\Module\Payment\StripeWebhookVerifier;
use PHPUnit\Framework\TestCase;

final class PaymentModuleTest extends TestCase
{
    public function testInventoriesAndDisablesUnsafeLegacyGateways(): void
    {
        $inventory = new PaymentGatewayInventory();

        $this->assertContains('iridium', $inventory->disabledLegacyGatewayCodes());
        $this->assertContains('plugnpay', $inventory->disabledLegacyGatewayCodes());
    }

    public function testValidatesStripeConfig(): void
    {
        $result = (new PaymentConfigValidator())->validate([
            'provider' => 'stripe',
            'secret_key' => 'sk_test_123',
            'webhook_secret' => 'whsec_123',
            'currency' => 'USD',
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue((new StripePaymentModule('sk_test_123', 'whsec_123'))->status()['configured']);
    }

    public function testValidatesBraintreeConfigWhenNeeded(): void
    {
        $result = (new PaymentConfigValidator())->validate([
            'provider' => 'braintree',
            'merchant_id' => 'merchant',
            'public_key' => 'public',
            'private_key' => 'private',
            'currency' => 'USD',
        ]);

        $this->assertTrue($result['success']);
    }

    public function testRejectsRawCardData(): void
    {
        $guard = new PaymentSensitiveDataGuard();

        $this->assertSame(['payment.card_number', 'payment.cvv'], $guard->blockedKeys([
            'payment' => [
                'card_number' => '4111111111111111',
                'cvv' => '123',
            ],
        ]));

        $this->expectException(InvalidArgumentException::class);
        $guard->assertSafe(['cc_number' => '4111111111111111']);
    }

    public function testVerifiesStripeWebhookSignature(): void
    {
        $payload = '{"id":"evt_test","type":"payment_intent.succeeded"}';
        $timestamp = 1777800000;
        $secret = 'whsec_test';
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        $verifier = new StripeWebhookVerifier($secret);

        $this->assertTrue($verifier->verify($payload, 't=' . $timestamp . ',v1=' . $signature, $timestamp));
        $this->assertFalse($verifier->verify($payload, 't=' . $timestamp . ',v1=bad', $timestamp));
        $this->assertFalse($verifier->verify($payload, 't=' . $timestamp . ',v1=' . $signature, $timestamp + 301));
    }

    public function testReconcilesPaymentsAgainstRefills(): void
    {
        $service = new PaymentReconciliationService($this->pdo());
        $summary = $service->summarize('2026-05-01 00:00:00', '2026-05-03 00:00:00');

        $this->assertSame(2, $summary['payments']);
        $this->assertSame('30.00000', $summary['total_paid']);
        $this->assertSame('29.50000', $summary['total_refilled']);
        $this->assertSame('0.50000', $summary['difference']);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE cc_logpayment (id INTEGER PRIMARY KEY, date TEXT, payment TEXT, added_refill TEXT)');
        $pdo->exec("INSERT INTO cc_logpayment (id, date, payment, added_refill) VALUES (1, '2026-05-01 10:00:00', '10.00', '10.00')");
        $pdo->exec("INSERT INTO cc_logpayment (id, date, payment, added_refill) VALUES (2, '2026-05-02 10:00:00', '20.00', '19.50')");

        return $pdo;
    }
}

<?php

declare(strict_types=1);

use A2BillingPlus\Module\Payment\PaymentIntentService;
use A2BillingPlus\Module\Payment\StripePaymentIntentClient;
use PHPUnit\Framework\TestCase;

final class PaymentIntentServiceTest extends TestCase
{
    public function testCreatesStripePaymentIntentWithHostedTokenizedFlow(): void
    {
        $calls = [];
        $service = new PaymentIntentService(new StripePaymentIntentClient(
            'sk_test_local',
            function (string $url, array $headers, array $fields) use (&$calls): array {
                $calls[] = compact('url', 'headers', 'fields');

                return [
                    'status' => 200,
                    'body' => json_encode([
                        'id' => 'pi_test_123',
                        'client_secret' => 'pi_test_123_secret_client',
                        'status' => 'requires_payment_method',
                    ], JSON_THROW_ON_ERROR),
                ];
            }
        ));

        $result = $service->createStripeIntent([
            'amount_minor_units' => '2500',
            'currency' => 'usd',
            'customer_id' => 42,
            'description' => 'A2BillingPlus top-up',
            'idempotency_key' => 'topup-42-2500',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->statusCode);
        $this->assertSame('pi_test_123', $result->paymentIntentId);
        $this->assertSame('pi_test_123_secret_client', $result->clientSecret);
        $this->assertSame('requires_payment_method', $result->status);
        $this->assertCount(1, $calls);
        $this->assertSame('https://api.stripe.com/v1/payment_intents', $calls[0]['url']);
        $this->assertSame('Bearer sk_test_local', $calls[0]['headers']['Authorization']);
        $this->assertSame('topup-42-2500', $calls[0]['headers']['Idempotency-Key']);
        $this->assertSame('2500', $calls[0]['fields']['amount']);
        $this->assertSame('usd', $calls[0]['fields']['currency']);
        $this->assertSame('true', $calls[0]['fields']['automatic_payment_methods[enabled]']);
        $this->assertSame('42', $calls[0]['fields']['metadata[a2bp_customer_id]']);
    }

    public function testRejectsRawCardDataBeforeCallingStripe(): void
    {
        $called = false;
        $service = new PaymentIntentService(new StripePaymentIntentClient(
            'sk_test_local',
            function () use (&$called): array {
                $called = true;

                return ['status' => 200, 'body' => '{}'];
            }
        ));

        $result = $service->createStripeIntent([
            'amount_minor_units' => 1000,
            'currency' => 'USD',
            'customer_id' => 7,
            'card_number' => '4242424242424242',
        ]);

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->statusCode);
        $this->assertStringContainsString('Raw card data is not allowed', $result->message);
        $this->assertFalse($called);
    }

    public function testValidatesRequiredPaymentIntentFields(): void
    {
        $service = new PaymentIntentService(new StripePaymentIntentClient('sk_test_local'));

        $this->assertSame(422, $service->createStripeIntent([
            'amount_minor_units' => 0,
            'currency' => 'USD',
            'customer_id' => 1,
        ])->statusCode);
        $this->assertSame(422, $service->createStripeIntent([
            'amount_minor_units' => 1000,
            'currency' => 'US',
            'customer_id' => 1,
        ])->statusCode);
        $this->assertSame(422, $service->createStripeIntent([
            'amount_minor_units' => 1000,
            'currency' => 'USD',
            'customer_id' => 0,
        ])->statusCode);
    }

    public function testRequiresConfiguredStripeSecretKey(): void
    {
        $service = new PaymentIntentService(new StripePaymentIntentClient(''));

        $result = $service->createStripeIntent([
            'amount_minor_units' => 1000,
            'currency' => 'USD',
            'customer_id' => 5,
        ]);

        $this->assertFalse($result->success);
        $this->assertSame(503, $result->statusCode);
        $this->assertSame('Stripe secret key is not configured.', $result->message);
    }
}

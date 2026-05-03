<?php

declare(strict_types=1);

use A2BillingPlus\Module\Payment\PaymentWebhookRepository;
use A2BillingPlus\Module\Payment\PaymentWebhookService;
use A2BillingPlus\Module\Payment\StripeWebhookVerifier;
use PHPUnit\Framework\TestCase;

final class PaymentWebhookServiceTest extends TestCase
{
    public function testAcceptsSignedStripePaymentIntentSucceededEvent(): void
    {
        $service = $this->service();
        $payload = json_encode([
            'id' => 'evt_stripe_1',
            'type' => 'payment_intent.succeeded',
        ], JSON_THROW_ON_ERROR);
        $timestamp = 1777800000;
        $signature = $this->stripeSignature($payload, $timestamp);

        $result = $service->handleStripe($payload, $signature, $timestamp);

        $this->assertSame(202, $result['status']);
        $this->assertTrue($result['body']['success']);
        $this->assertSame('stripe', $result['body']['provider']);
        $this->assertSame('payment_intent.succeeded', $result['body']['event_type']);
    }

    public function testReturnsDuplicateForRepeatedStripeEvent(): void
    {
        $service = $this->service();
        $payload = json_encode([
            'id' => 'evt_duplicate',
            'type' => 'payment_intent.payment_failed',
        ], JSON_THROW_ON_ERROR);
        $timestamp = 1777800000;
        $signature = $this->stripeSignature($payload, $timestamp);

        $first = $service->handleStripe($payload, $signature, $timestamp);
        $second = $service->handleStripe($payload, $signature, $timestamp);

        $this->assertSame(202, $first['status']);
        $this->assertSame(200, $second['status']);
        $this->assertTrue($second['body']['duplicate']);
    }

    public function testRejectsInvalidStripeSignature(): void
    {
        $result = $this->service()->handleStripe('{"id":"evt_bad","type":"payment_intent.succeeded"}', 't=1777800000,v1=bad', 1777800000);

        $this->assertSame(401, $result['status']);
    }

    public function testRequiresStripeEventIdAndType(): void
    {
        $payload = '{}';
        $timestamp = 1777800000;

        $result = $this->service()->handleStripe($payload, $this->stripeSignature($payload, $timestamp), $timestamp);

        $this->assertSame(422, $result['status']);
    }

    private function service(): PaymentWebhookService
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return new PaymentWebhookService(
            new StripeWebhookVerifier('whsec_test'),
            new PaymentWebhookRepository($pdo)
        );
    }

    private function stripeSignature(string $payload, int $timestamp): string
    {
        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, 'whsec_test');
    }
}

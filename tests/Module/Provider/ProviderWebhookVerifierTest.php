<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\ProviderWebhookVerifier;
use PHPUnit\Framework\TestCase;

final class ProviderWebhookVerifierTest extends TestCase
{
    public function testVerifiesSignedPayload(): void
    {
        $verifier = new ProviderWebhookVerifier('secret');
        $payload = '{"event_id":"evt_1"}';
        $timestamp = 1000;
        $signature = $verifier->sign($payload, $timestamp);

        $this->assertTrue($verifier->verify($payload, $signature, $timestamp, 1001));
    }

    public function testRejectsExpiredPayload(): void
    {
        $verifier = new ProviderWebhookVerifier('secret', 300);
        $payload = '{"event_id":"evt_1"}';
        $timestamp = 1000;

        $this->assertFalse($verifier->verify($payload, $verifier->sign($payload, $timestamp), $timestamp, 1401));
    }

    public function testRejectsBadSignature(): void
    {
        $verifier = new ProviderWebhookVerifier('secret');

        $this->assertFalse($verifier->verify('payload', 'bad', 1000, 1000));
    }
}

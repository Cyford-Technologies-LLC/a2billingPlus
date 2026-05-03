<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\ProviderWebhookRepository;
use A2BillingPlus\Module\Provider\ProviderWebhookService;
use A2BillingPlus\Module\Provider\ProviderWebhookVerifier;
use PHPUnit\Framework\TestCase;

final class ProviderWebhookServiceTest extends TestCase
{
    public function testAcceptsSignedRateDeckUpdatedEvent(): void
    {
        $service = $this->service();
        $payload = json_encode([
            'event_id' => 'evt_1',
            'event_type' => 'rate_deck.updated',
            'rate_deck' => 'retail',
        ], JSON_THROW_ON_ERROR);
        $timestamp = 1000;
        $signature = (new ProviderWebhookVerifier('secret'))->sign($payload, $timestamp);

        $result = $service->handle('vectavoip', $payload, $signature, $timestamp, 1000);

        $this->assertSame(202, $result['status']);
        $this->assertTrue($result['body']['success']);
        $this->assertSame('rate_deck.updated', $result['body']['event_type']);
    }

    public function testReturnsDuplicateForRepeatedEvent(): void
    {
        $service = $this->service();
        $payload = json_encode([
            'event_id' => 'evt_duplicate',
            'event_type' => 'account.updated',
        ], JSON_THROW_ON_ERROR);
        $timestamp = 1000;
        $signature = (new ProviderWebhookVerifier('secret'))->sign($payload, $timestamp);

        $first = $service->handle('vectavoip', $payload, $signature, $timestamp, 1000);
        $second = $service->handle('vectavoip', $payload, $signature, $timestamp, 1000);

        $this->assertSame(202, $first['status']);
        $this->assertSame(200, $second['status']);
        $this->assertTrue($second['body']['duplicate']);
    }

    public function testRejectsInvalidSignature(): void
    {
        $result = $this->service()->handle('vectavoip', '{}', 'bad', 1000, 1000);

        $this->assertSame(401, $result['status']);
    }

    public function testRequiresEventTypeAndId(): void
    {
        $service = $this->service();
        $payload = '{}';
        $timestamp = 1000;
        $signature = (new ProviderWebhookVerifier('secret'))->sign($payload, $timestamp);

        $result = $service->handle('vectavoip', $payload, $signature, $timestamp, 1000);

        $this->assertSame(422, $result['status']);
    }

    private function service(): ProviderWebhookService
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return new ProviderWebhookService(
            new ProviderWebhookVerifier('secret'),
            new ProviderWebhookRepository($pdo)
        );
    }
}

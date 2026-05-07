<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\Didww\DidwwApiClient;
use A2BillingPlus\Module\Provider\ProviderCredentials;
use PHPUnit\Framework\TestCase;

final class DidwwApiClientTest extends TestCase
{
    public function testSearchAvailableDidsBuildsOfficialEndpointWithIncludes(): void
    {
        $client = new DidwwApiClient(function (string $method, string $url, ProviderCredentials $credentials, ?array $payload): array {
            $this->assertSame('GET', $method);
            $this->assertStringContainsString('/v3/available_dids', $url);
            $this->assertStringContainsString('include=did_group%2Cdid_group.stock_keeping_units%2Cnanpa_prefix', $url);
            $this->assertStringContainsString('filter%5Bcountry.id%5D=US', $url);
            $this->assertSame('didww-key', $credentials->getApiKey());
            $this->assertNull($payload);

            return [
                'status' => 200,
                'body' => json_encode([
                    'data' => [
                        ['id' => 'did-1', 'type' => 'available_dids', 'attributes' => ['number' => '12125550100']],
                    ],
                    'meta' => ['total_count' => 1],
                ], JSON_THROW_ON_ERROR),
            ];
        });

        $response = $client->searchAvailableDids(
            new ProviderCredentials('https://api.didww.com', 'didww-key', '', ['api_version' => '2026-04-16']),
            ['filter[country.id]' => 'US']
        );

        $this->assertSame('did-1', $response['data'][0]['id']);
    }

    public function testCreateOrderPostsOfficialDidOrderPayload(): void
    {
        $client = new DidwwApiClient(function (string $method, string $url, ProviderCredentials $credentials, ?array $payload): array {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.didww.com/v3/orders', $url);
            $this->assertSame('didww-key', $credentials->getApiKey());
            $this->assertSame('orders', $payload['data']['type'] ?? null);
            $this->assertFalse($payload['data']['attributes']['allow_back_ordering'] ?? true);
            $this->assertSame('available-1', $payload['data']['attributes']['items'][0]['attributes']['available_did_id'] ?? null);
            $this->assertSame('sku-1', $payload['data']['attributes']['items'][0]['attributes']['sku_id'] ?? null);
            $this->assertSame('https://example.test/callback', $payload['data']['attributes']['callback_url'] ?? null);

            return [
                'status' => 201,
                'body' => json_encode([
                    'data' => [
                        'id' => 'order-1',
                        'type' => 'orders',
                        'attributes' => ['status' => 'pending'],
                    ],
                ], JSON_THROW_ON_ERROR),
            ];
        });

        $response = $client->createOrder(
            new ProviderCredentials('https://api.didww.com', 'didww-key'),
            'available-1',
            'sku-1',
            'https://example.test/callback'
        );

        $this->assertSame('order-1', $response['data']['id']);
    }

    public function testThrowsReadableErrorMessageForApiErrors(): void
    {
        $client = new DidwwApiClient(function (): array {
            return [
                'status' => 422,
                'body' => json_encode([
                    'errors' => [[
                        'detail' => 'Endpoint not enabled on this account.',
                    ]],
                ], JSON_THROW_ON_ERROR),
            ];
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Endpoint not enabled on this account.');

        $client->searchAvailableDids(new ProviderCredentials('https://api.didww.com', 'didww-key'));
    }
}

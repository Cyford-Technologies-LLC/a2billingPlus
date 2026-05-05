<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\Didww\DidwwConnector;
use A2BillingPlus\Module\Provider\Didww\DidwwStatusClient;
use A2BillingPlus\Module\Provider\ProviderCredentials;
use PHPUnit\Framework\TestCase;

final class DidwwConnectorTest extends TestCase
{
    public function testConnectionRequiresApiKey(): void
    {
        $connector = new DidwwConnector();
        $result = $connector->testConnection(new ProviderCredentials('https://api.didww.com', ''));

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('DIDWW API key is required.', $result->getMessage());
    }

    public function testConnectionChecksDidwwApiStatus(): void
    {
        $connector = new DidwwConnector(fn (): DidwwStatusClient => new DidwwStatusClient(
            function (string $url, ProviderCredentials $credentials): array {
                $this->assertSame('https://api.didww.com/v3/balance', $url);
                $this->assertSame('didww-key', $credentials->getApiKey());
                $this->assertSame('2026-04-16', $credentials->getMetadataValue('api_version'));

                return [
                    'status' => 200,
                    'body' => json_encode([
                        'meta' => ['api_version' => '2026-04-16'],
                        'data' => [
                            'id' => 'balance',
                            'attributes' => [
                                'balance' => '42.50',
                            ],
                        ],
                    ], JSON_THROW_ON_ERROR),
                ];
            }
        ));

        $result = $connector->testConnection(new ProviderCredentials(
            'https://api.didww.com',
            'didww-key',
            '',
            ['api_version' => '2026-04-16']
        ));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(1, $result->getDetails()['resource_count']);
        $this->assertSame('42.50', $result->getDetails()['balance']);
        $this->assertSame('2026-04-16', $result->getDetails()['api_version']);
    }
}

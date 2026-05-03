<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPRegistrationClient;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPRegistrationRequest;
use PHPUnit\Framework\TestCase;

final class VectaVoIPRegistrationClientTest extends TestCase
{
    public function testRegistersInstallAndReturnsCredentials(): void
    {
        $client = new VectaVoIPRegistrationClient('https://api.vectavoip.com', function (string $url, array $payload): array {
            $this->assertSame('https://api.vectavoip.com/v1/installations/register', $url);
            $this->assertSame('install-key', $payload['install_key']);
            $this->assertSame('Jane Admin', $payload['contact_name']);
            $this->assertSame('jane@example.test', $payload['contact_email']);

            return [
                'status' => 201,
                'body' => json_encode([
                    'message' => 'registered',
                    'installation_id' => 'inst_123',
                    'api_key' => 'key_123',
                    'api_secret' => 'secret_123',
                    'metadata' => ['region' => 'us'],
                ], JSON_THROW_ON_ERROR),
            ];
        });

        $result = $client->register(new VectaVoIPRegistrationRequest(
            'install-key',
            'VectaVoIP',
            'VectaVoIP.com',
            'Jane Admin',
            'jane@example.test',
            '+15551234567',
            'Sandbox install',
            'A2BillingPlus',
            '0.1.0-alpha'
        ));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('inst_123', $result->getInstallationId());
        $this->assertSame('key_123', $result->getApiKey());
        $this->assertSame('secret_123', $result->getApiSecret());
        $this->assertSame('us', $result->getMetadata()['region']);
    }

    public function testRequiresApiKeyInRegistrationResponse(): void
    {
        $client = new VectaVoIPRegistrationClient('https://api.vectavoip.com', fn (): array => [
            'status' => 200,
            'body' => '{"message":"ok"}',
        ]);

        $result = $client->register(new VectaVoIPRegistrationRequest(
            'install-key',
            'VectaVoIP',
            'VectaVoIP.com',
            '',
            '',
            '',
            '',
            'A2BillingPlus',
            '0.1.0-alpha'
        ));

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('VectaVoIP registration did not return an API key.', $result->getMessage());
    }
}

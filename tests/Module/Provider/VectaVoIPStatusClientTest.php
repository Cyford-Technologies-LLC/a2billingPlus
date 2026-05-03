<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPStatusClient;
use PHPUnit\Framework\TestCase;

final class VectaVoIPStatusClientTest extends TestCase
{
    public function testChecksInstallationStatusWithProviderApi(): void
    {
        $client = new VectaVoIPStatusClient(function (string $url, ProviderCredentials $credentials): array {
            $this->assertSame('https://api.vectavoip.com/v1/installations/status', $url);
            $this->assertSame('vvp_key', $credentials->getApiKey());
            $this->assertSame('vvs_secret', $credentials->getApiSecret());

            return [
                'status' => 200,
                'body' => json_encode([
                    'message' => 'active',
                    'installation_id' => 'inst_123',
                    'status' => 'active',
                ], JSON_THROW_ON_ERROR),
            ];
        });

        $result = $client->check(new ProviderCredentials('https://api.vectavoip.com', 'vvp_key', 'vvs_secret'));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('inst_123', $result->getDetails()['installation_id']);
    }

    public function testReportsRejectedCredentials(): void
    {
        $client = new VectaVoIPStatusClient(fn (): array => [
            'status' => 401,
            'body' => json_encode(['message' => 'Invalid credentials.'], JSON_THROW_ON_ERROR),
        ]);

        $result = $client->check(new ProviderCredentials('https://api.vectavoip.com', 'vvp_key', 'wrong'));

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('Invalid credentials.', $result->getMessage());
    }

    public function testRetriesTransientStatusFailure(): void
    {
        $attempts = 0;
        $client = new VectaVoIPStatusClient(function () use (&$attempts): array {
            $attempts++;
            if ($attempts === 1) {
                return ['status' => 502, 'body' => '{"message":"bad gateway"}'];
            }

            return [
                'status' => 200,
                'body' => json_encode([
                    'message' => 'active',
                    'installation_id' => 'inst_123',
                    'status' => 'active',
                ], JSON_THROW_ON_ERROR),
            ];
        }, 2);

        $result = $client->check(new ProviderCredentials('https://api.vectavoip.com', 'vvp_key', 'vvs_secret'));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(2, $attempts);
    }
}

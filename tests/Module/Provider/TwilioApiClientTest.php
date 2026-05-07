<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\Twilio\TwilioApiClient;
use PHPUnit\Framework\TestCase;

final class TwilioApiClientTest extends TestCase
{
    public function testSearchAvailableLocalNumbersBuildsOfficialEndpoint(): void
    {
        $client = new TwilioApiClient(function (string $method, string $url, ProviderCredentials $credentials, ?array $payload, array $headers): array {
            $this->assertSame('GET', $method);
            $this->assertStringContainsString('/2010-04-01/Accounts/AC123/AvailablePhoneNumbers/US/Local.json', $url);
            $this->assertStringContainsString('AreaCode=212', $url);
            $this->assertStringContainsString('SmsEnabled=true', $url);
            $this->assertSame('SK123', $credentials->getApiKey());
            $this->assertArrayHasKey('Authorization', $headers);
            $this->assertNull($payload);

            return [
                'status' => 200,
                'body' => json_encode([
                    'available_phone_numbers' => [
                        ['phone_number' => '+12125550100', 'friendly_name' => '(212) 555-0100'],
                    ],
                ], JSON_THROW_ON_ERROR),
            ];
        });

        $response = $client->searchAvailableLocalNumbers(
            new ProviderCredentials('https://api.twilio.com', 'SK123', 'secret', ['account_sid' => 'AC123']),
            'US',
            ['AreaCode' => '212', 'SmsEnabled' => 'true']
        );

        $this->assertSame('+12125550100', $response['available_phone_numbers'][0]['phone_number']);
    }

    public function testPurchaseIncomingPhoneNumberPostsPhoneNumberPayload(): void
    {
        $client = new TwilioApiClient(function (string $method, string $url, ProviderCredentials $credentials, ?array $payload): array {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.twilio.com/2010-04-01/Accounts/AC123/IncomingPhoneNumbers.json', $url);
            $this->assertSame('SK123', $credentials->getApiKey());
            $this->assertSame('+12125550100', $payload['PhoneNumber'] ?? null);
            $this->assertSame('https://voice.example.test', $payload['VoiceUrl'] ?? null);

            return [
                'status' => 201,
                'body' => json_encode([
                    'sid' => 'PN123',
                    'phone_number' => '+12125550100',
                    'friendly_name' => '(212) 555-0100',
                ], JSON_THROW_ON_ERROR),
            ];
        });

        $response = $client->purchaseIncomingPhoneNumber(
            new ProviderCredentials('https://api.twilio.com', 'SK123', 'secret', ['account_sid' => 'AC123']),
            ['PhoneNumber' => '+12125550100', 'VoiceUrl' => 'https://voice.example.test']
        );

        $this->assertSame('PN123', $response['sid']);
    }

    public function testGetTrunkBuildsOfficialEndpoint(): void
    {
        $client = new TwilioApiClient(function (string $method, string $url, ProviderCredentials $credentials): array {
            $this->assertSame('GET', $method);
            $this->assertSame('https://trunking.twilio.com/v1/Trunks/TK123', $url);
            $this->assertSame('SK123', $credentials->getApiKey());

            return [
                'status' => 200,
                'body' => json_encode([
                    'sid' => 'TK123',
                    'friendly_name' => 'BYOC Main',
                    'domain_name' => 'example.pstn.twilio.com',
                ], JSON_THROW_ON_ERROR),
            ];
        });

        $response = $client->getTrunk(
            new ProviderCredentials('https://api.twilio.com', 'SK123', 'secret', ['account_sid' => 'AC123']),
            'TK123'
        );

        $this->assertSame('BYOC Main', $response['friendly_name']);
    }

    public function testThrowsReadableTwilioError(): void
    {
        $client = new TwilioApiClient(function (): array {
            return [
                'status' => 400,
                'body' => json_encode([
                    'message' => 'The requested resource was not found',
                ], JSON_THROW_ON_ERROR),
            ];
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The requested resource was not found');

        $client->account(new ProviderCredentials('https://api.twilio.com', 'SK123', 'secret', ['account_sid' => 'AC123']));
    }
}

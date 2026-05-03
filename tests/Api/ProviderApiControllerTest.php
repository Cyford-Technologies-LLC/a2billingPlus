<?php

declare(strict_types=1);

use A2BillingPlus\Api\ProviderApiController;
use A2BillingPlus\Bootstrap\ProviderRegistryFactory;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPRegistrationClient;
use PHPUnit\Framework\TestCase;

final class ProviderApiControllerTest extends TestCase
{
    public function testListsProviders(): void
    {
        $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
        $response = $controller->handle(new JsonRequest('GET'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('vectavoip', $response->getPayload()['providers'][0]['code']);
        $this->assertSame('info@VectaVoIP.com', $response->getPayload()['providers'][0]['support_email']);
        $this->assertSame('https://api.VectaVoIP.com', $response->getPayload()['providers'][0]['api_base_url']);
    }

    public function testTestsProviderConnection(): void
    {
        $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
        $response = $controller->handle(new JsonRequest('POST', [], [
            'action' => 'test_connection',
            'provider' => 'vectavoip',
            'base_url' => 'https://api.VectaVoIP.com',
            'api_key' => 'test-key',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getPayload()['success']);
    }

    public function testUsesRegisteredProviderCredentialsFromEnvironment(): void
    {
        putenv('VECTAVOIP_API_KEY=registered-key');

        try {
            $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
            $response = $controller->handle(new JsonRequest('POST', [], [
                'action' => 'test_connection',
                'provider' => 'vectavoip',
            ]));

            $this->assertSame(200, $response->getStatusCode());
            $this->assertTrue($response->getPayload()['success']);
            $this->assertSame('https://api.VectaVoIP.com', $response->getPayload()['details']['base_url']);
        } finally {
            putenv('VECTAVOIP_API_KEY');
        }
    }

    public function testReportsProviderRegistrationStatus(): void
    {
        putenv('VECTAVOIP_API_KEY=registered-key');
        putenv('VECTAVOIP_INSTALLATION_ID=inst_123');
        putenv('VECTAVOIP_API_BASE_URL=https://api.VectaVoIP.com');

        try {
            $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
            $response = $controller->handle(new JsonRequest('POST', [], [
                'action' => 'provider_status',
                'provider' => 'vectavoip',
            ]));

            $this->assertSame(200, $response->getStatusCode());
            $this->assertTrue($response->getPayload()['registered']);
            $this->assertSame('inst_123', $response->getPayload()['installation_id']);
            $this->assertSame('https://api.VectaVoIP.com', $response->getPayload()['api_base_url']);
        } finally {
            putenv('VECTAVOIP_API_KEY');
            putenv('VECTAVOIP_INSTALLATION_ID');
            putenv('VECTAVOIP_API_BASE_URL');
        }
    }

    public function testRegistersProviderInstall(): void
    {
        $controller = new ProviderApiController(
            ProviderRegistryFactory::createDefault(),
            fn (string $baseUrl): VectaVoIPRegistrationClient => new VectaVoIPRegistrationClient($baseUrl, function (string $url, array $payload): array {
                $this->assertSame('http://localhost:8080/api/sandbox/v1/installations/register', $url);
                $this->assertSame('a2bp_test', $payload['install_key']);
                $this->assertSame('Jane Admin', $payload['contact_name']);

                return [
                    'status' => 201,
                    'body' => json_encode([
                        'message' => 'registered',
                        'installation_id' => 'inst_123',
                        'api_key' => 'key_123',
                        'api_secret' => 'secret_123',
                    ], JSON_THROW_ON_ERROR),
                ];
            })
        );

        $response = $controller->handle(new JsonRequest('POST', [], [
            'action' => 'register_install',
            'provider' => 'vectavoip',
            'base_url' => 'http://localhost:8080/api/sandbox',
            'install_key' => 'a2bp_test',
            'company_name' => 'ExampleCo',
            'company_domain' => 'example.test',
            'contact_name' => 'Jane Admin',
            'contact_email' => 'jane@example.test',
            'app_name' => 'A2BillingPlus',
            'app_version' => '0.1.0-alpha',
        ]));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($response->getPayload()['success']);
        $this->assertSame('a2bp_test', $response->getPayload()['install_key']);
        $this->assertSame('inst_123', $response->getPayload()['installation_id']);
        $this->assertSame('key_123', $response->getPayload()['api_key']);
    }

    public function testRejectsUnknownProvider(): void
    {
        $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
        $response = $controller->handle(new JsonRequest('POST', [], [
            'action' => 'test_connection',
            'provider' => 'missing',
        ]));

        $this->assertSame(404, $response->getStatusCode());
    }
}

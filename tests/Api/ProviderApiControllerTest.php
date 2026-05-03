<?php

declare(strict_types=1);

use A2BillingPlus\Api\ProviderApiController;
use A2BillingPlus\Bootstrap\ProviderRegistryFactory;
use A2BillingPlus\Http\JsonRequest;
use PHPUnit\Framework\TestCase;

final class ProviderApiControllerTest extends TestCase
{
    public function testListsProviders(): void
    {
        $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
        $response = $controller->handle(new JsonRequest('GET'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('vectavoip', $response->getPayload()['providers'][0]['code']);
    }

    public function testTestsProviderConnection(): void
    {
        $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
        $response = $controller->handle(new JsonRequest('POST', [], [
            'action' => 'test_connection',
            'provider' => 'vectavoip',
            'base_url' => 'https://VectaVoIP.com/api',
            'api_key' => 'test-key',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getPayload()['success']);
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

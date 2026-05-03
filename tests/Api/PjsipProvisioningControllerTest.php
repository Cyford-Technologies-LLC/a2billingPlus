<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Api\PjsipProvisioningController;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;
use PHPUnit\Framework\TestCase;

final class PjsipProvisioningControllerTest extends TestCase
{
    public function testProvisionsCustomerDeviceThroughApi(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $controller = $this->controller($pdo);

        $response = $controller->handle(new JsonRequest('POST', [], [
            'action' => 'customer_device',
            'provisioning' => [
                'customer_id' => 10,
                'username' => '1001',
                'secret' => 'strong-device-secret',
            ],
        ], $this->headers()));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('cust-10-1001', $response->getPayload()['data']['endpoint']['endpoint_id']);
        $this->assertArrayNotHasKey('secret', $response->getPayload()['data']['endpoint']);
    }

    public function testRejectsInvalidAction(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $response = $this->controller($pdo)->handle(new JsonRequest('POST', [], ['action' => 'bad'], $this->headers()));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('invalid_action', $response->getPayload()['error']['code']);
    }

    public function testListsLoadsAndUpdatesEndpointThroughApi(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $controller = $this->controller($pdo);
        $controller->handle(new JsonRequest('POST', [], [
            'action' => 'customer_device',
            'provisioning' => [
                'customer_id' => 10,
                'username' => '1001',
                'secret' => 'strong-device-secret',
            ],
        ], $this->headers()));

        $list = $controller->handle(new JsonRequest('GET', [
            'endpoint_type' => 'customer_device',
            'owner_id' => '10',
        ], [], $this->headers()));
        $detail = $controller->handle(new JsonRequest('GET', [
            'endpoint_id' => 'cust-10-1001',
        ], [], $this->headers()));
        $update = $controller->handle(new JsonRequest('PUT', [
            'endpoint_id' => 'cust-10-1001',
        ], [
            'provisioning' => [
                'context' => 'from-internal',
                'allow' => 'ulaw',
            ],
        ], $this->headers()));

        $this->assertSame(200, $list->getStatusCode());
        $this->assertCount(1, $list->getPayload()['data']['endpoints']);
        $this->assertSame(200, $detail->getStatusCode());
        $this->assertArrayNotHasKey('password', $detail->getPayload()['data']['endpoint']);
        $this->assertSame(200, $update->getStatusCode());
        $this->assertSame('from-internal', $update->getPayload()['data']['endpoint']['context']);
    }

    public function testRequiresServiceKey(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $response = $this->controller($pdo)->handle(new JsonRequest('POST'));

        $this->assertSame(401, $response->getStatusCode());
    }

    private function controller(PDO $pdo): PjsipProvisioningController
    {
        return new PjsipProvisioningController(
            new ApiServiceKeyAuthenticator(new AppConfig(['A2BP_API_SERVICE_KEY' => 'secret-key'])),
            fn (): PDO => $pdo
        );
    }

    /**
     * @return array<string,string>
     */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer secret-key',
            'X-A2BP-Actor' => 'admin:root',
        ];
    }
}

<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Api\AsteriskHealthController;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;
use PHPUnit\Framework\TestCase;

final class AsteriskHealthControllerTest extends TestCase
{
    public function testChecksAsteriskSettingsFromPostPayload(): void
    {
        $response = $this->controller()->handle(new JsonRequest('POST', [], [
            'settings' => [
                'version' => '22.9.0',
                'ami_user' => 'a2billing',
                'ami_password' => 'strong-ami-secret-2026',
                'ari_user' => 'a2billing',
                'ari_password' => 'strong-ari-secret-2026',
                'channel_driver' => 'pjsip',
                'realtime_enabled' => 'yes',
            ],
        ], $this->headers()));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getPayload()['data']['asterisk']['success']);
    }

    public function testReadsDefaultsFromConfigAndReportsFailures(): void
    {
        $controller = new AsteriskHealthController(
            new ApiServiceKeyAuthenticator(new AppConfig(['A2BP_API_SERVICE_KEY' => 'secret-key'])),
            new AppConfig([
                'A2BP_API_SERVICE_KEY' => 'secret-key',
                'A2BP_ASTERISK_VERSION' => '16.30.0',
                'A2BP_AMI_USER' => 'a2billing',
                'A2BP_AMI_PASSWORD' => 'password',
                'A2BP_ARI_USER' => '',
                'A2BP_ARI_PASSWORD' => '',
                'A2BP_ASTERISK_CHANNEL_DRIVER' => 'chan_sip',
                'A2BP_ASTERISK_REALTIME_ENABLED' => 'no',
            ])
        );

        $response = $controller->handle(new JsonRequest('GET', [], [], $this->headers()));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->getPayload()['data']['asterisk']['success']);
    }

    public function testRequiresServiceKey(): void
    {
        $response = $this->controller()->handle(new JsonRequest('GET'));

        $this->assertSame(401, $response->getStatusCode());
    }

    private function controller(): AsteriskHealthController
    {
        $config = new AppConfig(['A2BP_API_SERVICE_KEY' => 'secret-key']);
        return new AsteriskHealthController(new ApiServiceKeyAuthenticator($config), $config);
    }

    /**
     * @return array<string,string>
     */
    private function headers(): array
    {
        return ['Authorization' => 'Bearer secret-key'];
    }
}

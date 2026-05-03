<?php

declare(strict_types=1);

use A2BillingPlus\Module\Messaging\VectaVoIPSmsGateway;
use PHPUnit\Framework\TestCase;

final class VectaVoIPSmsGatewayTest extends TestCase
{
    public function testSendsMessageWithVectaVoipHeaders(): void
    {
        $captured = [];
        $gateway = new VectaVoIPSmsGateway(
            'https://api.example.test',
            'key-123',
            'secret-456',
            static function (string $url, string $json, array $headers) use (&$captured): array {
                $captured = ['url' => $url, 'json' => $json, 'headers' => $headers];

                return ['status' => 202, 'body' => '{"message_id":"msg_123"}'];
            }
        );

        $result = $gateway->send('+15551230000', '+15557654321', 'hello');

        $this->assertTrue($result->success);
        $this->assertSame('msg_123', $result->gatewayMessageId);
        $this->assertSame('https://api.example.test/v1/sms/send', $captured['url']);
        $this->assertSame('key-123', $captured['headers']['X-VectaVoIP-Api-Key']);
        $this->assertSame('secret-456', $captured['headers']['X-VectaVoIP-Api-Secret']);
        $this->assertSame(
            ['from' => '+15551230000', 'to' => '+15557654321', 'body' => 'hello'],
            json_decode($captured['json'], true)
        );
    }

    public function testReturnsProviderErrorMessage(): void
    {
        $gateway = new VectaVoIPSmsGateway(
            'https://api.example.test',
            'key-123',
            'secret-456',
            static fn (): array => ['status' => 400, 'body' => '{"message":"invalid destination"}']
        );

        $result = $gateway->send('+15551230000', '+15557654321', 'hello');

        $this->assertFalse($result->success);
        $this->assertSame('invalid destination', $result->message);
    }
}

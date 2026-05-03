<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiCustomerContextAuthenticator;
use A2BillingPlus\Api\CustomerPaymentController;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Module\Payment\PaymentIntentService;
use A2BillingPlus\Module\Payment\StripePaymentIntentClient;
use PHPUnit\Framework\TestCase;

final class CustomerPaymentControllerTest extends TestCase
{
    public function testReturnsOnlySignedCustomerPaymentsAndDocuments(): void
    {
        $response = $this->controller($this->pdo())->handle(new JsonRequest('GET', [], [], $this->headers(1)));
        $payload = $response->getPayload();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $payload['data']['payments']);
        $this->assertSame('10.00', $payload['data']['payments'][0]['payment']);
        $this->assertSame('INV-1', $payload['data']['documents']['invoices'][0]['reference']);
        $this->assertSame(1, $payload['meta']['customer_id']);
    }

    public function testCreatesHostedIntentForSignedCustomer(): void
    {
        $response = $this->controller($this->pdo())->handle(new JsonRequest('POST', [], [
            'payment' => [
                'amount_minor_units' => '2500',
                'currency' => 'USD',
                'customer_id' => 999,
            ],
        ], $this->headers(1)));
        $payload = $response->getPayload();

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('pi_customer_123', $payload['data']['payment_intent']['id']);
        $this->assertSame('hosted_intent', $payload['meta']['mode']);
        $this->assertSame(1, $payload['meta']['customer_id']);
    }

    public function testRejectsRawCardPayload(): void
    {
        $response = $this->controller($this->pdo())->handle(new JsonRequest('POST', [], [
            'payment' => [
                'amount_minor_units' => '2500',
                'currency' => 'USD',
                'card_number' => '4242424242424242',
            ],
        ], $this->headers(1)));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('payment_intent_failed', $response->getPayload()['error']['code']);
    }

    public function testRejectsUnsignedAccess(): void
    {
        $response = $this->controller($this->pdo())->handle(new JsonRequest('GET'));

        $this->assertSame(401, $response->getStatusCode());
    }

    private function controller(PDO $pdo): CustomerPaymentController
    {
        $config = new AppConfig([
            'A2BP_CUSTOMER_API_SECRET' => 'customer-secret',
            'STRIPE_SECRET_KEY' => 'sk_test_local',
        ]);

        return new CustomerPaymentController(
            new ApiCustomerContextAuthenticator($config),
            $config,
            fn (): PDO => $pdo,
            fn (): PaymentIntentService => new PaymentIntentService(new StripePaymentIntentClient(
                'sk_test_local',
                static fn (): array => [
                    'status' => 200,
                    'body' => json_encode([
                        'id' => 'pi_customer_123',
                        'client_secret' => 'pi_customer_123_secret',
                        'status' => 'requires_payment_method',
                    ], JSON_THROW_ON_ERROR),
                ]
            ))
        );
    }

    /**
     * @return array<string,string>
     */
    private function headers(int $customerId): array
    {
        return [
            'X-A2BP-Customer-Id' => (string)$customerId,
            'X-A2BP-Customer-Signature' => hash_hmac('sha256', (string)$customerId, 'customer-secret'),
        ];
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE cc_logpayment (id INTEGER PRIMARY KEY, date TEXT, payment TEXT, card_id INTEGER, description TEXT)');
        $pdo->exec('CREATE TABLE cc_invoice (id INTEGER PRIMARY KEY, id_card INTEGER, title TEXT, reference TEXT, date TEXT, paid_status INTEGER, status INTEGER)');
        $pdo->exec('CREATE TABLE cc_receipt (id INTEGER PRIMARY KEY, id_card INTEGER, title TEXT, date TEXT, status INTEGER)');
        $pdo->exec("INSERT INTO cc_logpayment (id, date, payment, card_id, description) VALUES (1, '2026-05-03', '10.00', 1, 'top up')");
        $pdo->exec("INSERT INTO cc_logpayment (id, date, payment, card_id, description) VALUES (2, '2026-05-03', '20.00', 2, 'other top up')");
        $pdo->exec("INSERT INTO cc_invoice (id, id_card, title, reference, date, paid_status, status) VALUES (1, 1, 'Invoice', 'INV-1', '2026-05-03', 0, 0)");
        $pdo->exec("INSERT INTO cc_receipt (id, id_card, title, date, status) VALUES (1, 1, 'Receipt', '2026-05-03', 0)");

        return $pdo;
    }
}

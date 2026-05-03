<?php

declare(strict_types=1);

use A2BillingPlus\Module\Payment\CustomerPaymentPortalService;
use A2BillingPlus\Module\Payment\PaymentIntentService;
use A2BillingPlus\Module\Payment\StripePaymentIntentClient;
use PHPUnit\Framework\TestCase;

final class CustomerPaymentPortalServiceTest extends TestCase
{
    public function testReturnsCustomerPaymentHistoryAndDocumentReferences(): void
    {
        $service = new CustomerPaymentPortalService($this->pdo(), $this->intentService());

        $history = $service->history(1, 10, 0);

        $this->assertCount(1, $history['payments']['items']);
        $this->assertSame('10.00', $history['payments']['items'][0]['payment']);
        $this->assertSame('INV-1', $history['documents']['invoices'][0]['reference']);
        $this->assertSame('Receipt', $history['documents']['receipts'][0]['title']);
    }

    public function testHostedIntentForcesSignedCustomerIdAndRejectsRawCardData(): void
    {
        $calls = [];
        $service = new CustomerPaymentPortalService($this->pdo(), $this->intentService($calls));

        $result = $service->createHostedIntent(1, [
            'amount_minor_units' => '2500',
            'currency' => 'USD',
            'customer_id' => 999,
        ]);
        $blocked = $service->createHostedIntent(1, [
            'amount_minor_units' => '2500',
            'currency' => 'USD',
            'card_number' => '4242424242424242',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('1', $calls[0]['fields']['metadata[a2bp_customer_id]']);
        $this->assertFalse($blocked->success);
        $this->assertSame(422, $blocked->statusCode);
    }

    /**
     * @param list<array<string,mixed>> $calls
     */
    private function intentService(array &$calls = []): PaymentIntentService
    {
        return new PaymentIntentService(new StripePaymentIntentClient(
            'sk_test_local',
            function (string $url, array $headers, array $fields) use (&$calls): array {
                $calls[] = compact('url', 'headers', 'fields');

                return [
                    'status' => 200,
                    'body' => json_encode([
                        'id' => 'pi_customer_123',
                        'client_secret' => 'pi_customer_123_secret',
                        'status' => 'requires_payment_method',
                    ], JSON_THROW_ON_ERROR),
                ];
            }
        ));
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

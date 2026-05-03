<?php

declare(strict_types=1);

use A2BillingPlus\Module\Messaging\SmsMessageRepository;
use PHPUnit\Framework\TestCase;

final class SmsMessageRepositoryTest extends TestCase
{
    public function testCreatesSearchesAndUpdatesMessageDelivery(): void
    {
        $repository = new SmsMessageRepository($this->pdo());

        $created = $repository->create(42, '+15551230000', '+15557654321', 'hello', 'outbound', 'pending');
        $updated = $repository->updateDelivery((int)$created['id'], 'sent', 'msg_123', '');
        $search = $repository->search(10, 0, 42, 'outbound', '+15551230000');

        $this->assertSame(42, (int)$created['customer_id']);
        $this->assertSame('sent', $updated['status']);
        $this->assertSame('msg_123', $updated['gateway_message_id']);
        $this->assertSame(1, $search['total']);
        $this->assertSame('hello', $search['items'][0]['body']);
    }

    public function testUpdatesFailureMessage(): void
    {
        $repository = new SmsMessageRepository($this->pdo());

        $created = $repository->create(42, '+15551230000', '+15557654321', 'hello', 'outbound', 'pending');
        $updated = $repository->updateStatus((int)$created['id'], 'failed', 'provider rejected');

        $this->assertSame('failed', $updated['status']);
        $this->assertSame('provider rejected', $updated['error_message']);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cc_sms_message (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER NOT NULL,
                from_number TEXT NOT NULL,
                to_number TEXT NOT NULL,
                body TEXT NOT NULL,
                direction TEXT NOT NULL,
                status TEXT NOT NULL,
                gateway_message_id TEXT NOT NULL DEFAULT "",
                error_message TEXT NOT NULL DEFAULT "",
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );

        return $pdo;
    }
}

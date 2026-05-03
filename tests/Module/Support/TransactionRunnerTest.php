<?php

declare(strict_types=1);

use A2BillingPlus\Module\Support\TransactionRunner;
use PHPUnit\Framework\TestCase;

final class TransactionRunnerTest extends TestCase
{
    public function testCommitsSuccessfulWork(): void
    {
        $pdo = $this->pdo();
        $result = (new TransactionRunner($pdo))->run(function () use ($pdo): string {
            $pdo->exec("INSERT INTO test_items (name) VALUES ('committed')");
            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM test_items')->fetchColumn());
    }

    public function testRollsBackFailedWork(): void
    {
        $pdo = $this->pdo();

        try {
            (new TransactionRunner($pdo))->run(function () use ($pdo): void {
                $pdo->exec("INSERT INTO test_items (name) VALUES ('rolled-back')");
                throw new RuntimeException('fail');
            });
            $this->fail('Expected exception.');
        } catch (RuntimeException) {
            $this->assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM test_items')->fetchColumn());
        }
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE test_items (id INTEGER PRIMARY KEY, name TEXT)');

        return $pdo;
    }
}

<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Support;

final class TransactionRunner
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function run(callable $callback): mixed
    {
        $started = !$this->pdo->inTransaction();
        if ($started) {
            $this->pdo->beginTransaction();
        }

        try {
            $result = $callback();
            if ($started) {
                $this->pdo->commit();
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}

<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Payment;

final class PaymentReconciliationService
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return array{payments:int,total_paid:string,total_refilled:string,difference:string}
     */
    public function summarize(string $from, string $to): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                COUNT(*) AS payments,
                COALESCE(SUM(payment), 0) AS total_paid,
                COALESCE(SUM(added_refill), 0) AS total_refilled
             FROM cc_logpayment
             WHERE date >= :from_date AND date < :to_date'
        );
        $statement->execute([
            'from_date' => $from,
            'to_date' => $to,
        ]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC) ?: [];

        $paid = is_numeric($row['total_paid'] ?? null) ? (float)$row['total_paid'] : 0;
        $refilled = is_numeric($row['total_refilled'] ?? null) ? (float)$row['total_refilled'] : 0;

        return [
            'payments' => (int)($row['payments'] ?? 0),
            'total_paid' => number_format($paid, 5, '.', ''),
            'total_refilled' => number_format($refilled, 5, '.', ''),
            'difference' => number_format($paid - $refilled, 5, '.', ''),
        ];
    }
}

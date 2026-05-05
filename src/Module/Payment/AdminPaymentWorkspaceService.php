<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Payment;

final class AdminPaymentWorkspaceService
{
    public function __construct(
        private readonly PaymentLedgerService $ledgerService,
        private readonly PaymentReconciliationService $reconciliationService
    ) {
    }

    /**
     * @return array{
     *     filters:array{from:string,to:string,customer_id:string},
     *     summary:array{payments:int,total_paid:string,total_refilled:string,difference:string},
     *     ledger:array{items:list<array<string, mixed>>,columns:list<string>}
     * }
     */
    public function workspace(string $from, string $to, string $customerId = '', int $limit = 25): array
    {
        $from = $this->normalizeDate($from, 'from');
        $to = $this->normalizeDate($to, 'to');
        $customerId = trim($customerId);
        $customerFilter = $customerId !== '' ? (int)$customerId : null;

        if ($customerId !== '' && (string)$customerFilter !== $customerId) {
            throw new \InvalidArgumentException('Customer ID must be a number.');
        }

        return [
            'filters' => [
                'from' => $from,
                'to' => $to,
                'customer_id' => $customerId,
            ],
            'summary' => $this->reconciliationService->summarize($from, $to),
            'ledger' => $this->ledgerService->search(new PaymentSearchCriteria($limit, 0, $from, $to, $customerFilter)),
        ];
    }

    private function normalizeDate(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '') {
            return $field === 'from'
                ? (new \DateTimeImmutable('-30 days'))->format('Y-m-d 00:00:00')
                : (new \DateTimeImmutable('+1 day'))->format('Y-m-d 00:00:00');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value . ($field === 'from' ? ' 00:00:00' : ' 23:59:59');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        throw new \InvalidArgumentException(ucfirst($field) . ' date must be YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.');
    }
}

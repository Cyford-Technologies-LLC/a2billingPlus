<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Invoice;

final class InvoiceSearchCriteria
{
    public function __construct(
        public readonly int $limit = 50,
        public readonly int $offset = 0,
        public readonly string $from = '',
        public readonly string $to = '',
        public readonly ?int $customerId = null,
        public readonly ?int $status = null,
        public readonly ?int $paidStatus = null
    ) {
    }
}

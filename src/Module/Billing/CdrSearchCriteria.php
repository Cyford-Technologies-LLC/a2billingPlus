<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Billing;

final class CdrSearchCriteria
{
    public function __construct(
        public readonly int $limit = 50,
        public readonly int $offset = 0,
        public readonly string $from = '',
        public readonly string $to = '',
        public readonly ?int $customerId = null,
        public readonly string $calledStation = ''
    ) {
    }
}

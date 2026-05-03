<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Customer;

final class CustomerSearchCriteria
{
    public function __construct(
        public readonly int $limit = 50,
        public readonly int $offset = 0,
        public readonly string $search = '',
        public readonly ?int $status = null
    ) {
    }
}

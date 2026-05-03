<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Rate;

final class RatecardSearchCriteria
{
    public function __construct(
        public readonly int $limit = 50,
        public readonly int $offset = 0,
        public readonly string $prefix = '',
        public readonly ?int $tariffPlanId = null,
        public readonly string $tag = ''
    ) {
    }
}

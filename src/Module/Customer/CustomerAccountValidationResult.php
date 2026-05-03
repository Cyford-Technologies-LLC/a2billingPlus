<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Customer;

final class CustomerAccountValidationResult
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly bool $valid,
        public readonly array $data = [],
        public readonly string $message = '',
        public readonly string $field = ''
    ) {
    }
}

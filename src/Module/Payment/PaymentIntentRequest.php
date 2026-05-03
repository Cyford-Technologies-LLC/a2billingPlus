<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Payment;

final class PaymentIntentRequest
{
    public function __construct(
        public readonly int $amountMinorUnits,
        public readonly string $currency,
        public readonly int $customerId,
        public readonly string $description = '',
        public readonly string $idempotencyKey = ''
    ) {
    }
}

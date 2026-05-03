<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Payment;

final class PaymentIntentResult
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly bool $success,
        public readonly int $statusCode,
        public readonly string $provider,
        public readonly string $paymentIntentId = '',
        public readonly string $clientSecret = '',
        public readonly string $status = '',
        public readonly string $message = '',
        public readonly array $raw = []
    ) {
    }
}

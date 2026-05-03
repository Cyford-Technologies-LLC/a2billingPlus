<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Messaging;

final class SmsResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly string $gatewayMessageId = '',
    ) {
    }
}

<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Payment;

final class PaymentIntentService
{
    public function __construct(
        private readonly StripePaymentIntentClient $stripeClient,
        private readonly PaymentSensitiveDataGuard $sensitiveDataGuard = new PaymentSensitiveDataGuard()
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createStripeIntent(array $payload): PaymentIntentResult
    {
        try {
            $this->sensitiveDataGuard->assertSafe($payload);
        } catch (\InvalidArgumentException $exception) {
            return new PaymentIntentResult(false, 422, 'stripe', message: $exception->getMessage());
        }

        $amount = $this->intValue($payload, 'amount_minor_units');
        if ($amount <= 0) {
            return new PaymentIntentResult(false, 422, 'stripe', message: 'amount_minor_units must be a positive integer.');
        }

        $currency = strtoupper($this->stringValue($payload, 'currency', 'USD'));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            return new PaymentIntentResult(false, 422, 'stripe', message: 'currency must be a three-letter ISO code.');
        }

        $customerId = $this->intValue($payload, 'customer_id');
        if ($customerId <= 0) {
            return new PaymentIntentResult(false, 422, 'stripe', message: 'customer_id must be a positive integer.');
        }

        $description = $this->stringValue($payload, 'description');
        if (strlen($description) > 255) {
            return new PaymentIntentResult(false, 422, 'stripe', message: 'description must be 255 characters or fewer.');
        }

        return $this->stripeClient->create(new PaymentIntentRequest(
            $amount,
            $currency,
            $customerId,
            $description,
            $this->stringValue($payload, 'idempotency_key')
        ));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function stringValue(array $payload, string $key, string $default = ''): string
    {
        $value = $payload[$key] ?? $default;
        return is_scalar($value) ? trim((string)$value) : $default;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function intValue(array $payload, string $key): int
    {
        $value = $payload[$key] ?? 0;
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[0-9]+$/', $value) === 1) {
            return (int)$value;
        }

        return 0;
    }
}

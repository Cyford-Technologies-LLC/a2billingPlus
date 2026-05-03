<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Payment;

final class StripePaymentModule
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $webhookSecret,
        private readonly string $currency = 'USD'
    ) {
    }

    /**
     * @return array{provider:string,configured:bool,currency:string}
     */
    public function status(): array
    {
        $validation = (new PaymentConfigValidator())->validate([
            'provider' => 'stripe',
            'secret_key' => $this->secretKey,
            'webhook_secret' => $this->webhookSecret,
            'currency' => $this->currency,
        ]);

        return [
            'provider' => 'stripe',
            'configured' => $validation['success'],
            'currency' => $this->currency,
        ];
    }
}

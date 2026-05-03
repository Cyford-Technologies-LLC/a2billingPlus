<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Payment;

final class PaymentConfigValidator
{
    /**
     * @param array<string, string> $config
     * @return array{success:bool,checks:list<array{name:string,success:bool,message:string}>}
     */
    public function validate(array $config): array
    {
        $provider = strtolower($config['provider'] ?? 'stripe');
        $checks = match ($provider) {
            'stripe' => $this->stripeChecks($config),
            'braintree' => $this->braintreeChecks($config),
            default => [[
                'name' => 'provider',
                'success' => false,
                'message' => 'Unsupported payment provider.',
            ]],
        };

        return [
            'success' => count(array_filter($checks, static fn (array $check): bool => !$check['success'])) === 0,
            'checks' => $checks,
        ];
    }

    /**
     * @param array<string, string> $config
     * @return list<array{name:string,success:bool,message:string}>
     */
    private function stripeChecks(array $config): array
    {
        return [
            $this->requiredSecret('stripe_secret_key', $config['secret_key'] ?? '', 'sk_'),
            $this->requiredSecret('stripe_webhook_secret', $config['webhook_secret'] ?? '', 'whsec_'),
            $this->currency($config['currency'] ?? 'USD'),
        ];
    }

    /**
     * @param array<string, string> $config
     * @return list<array{name:string,success:bool,message:string}>
     */
    private function braintreeChecks(array $config): array
    {
        return [
            $this->requiredSecret('braintree_merchant_id', $config['merchant_id'] ?? '', ''),
            $this->requiredSecret('braintree_public_key', $config['public_key'] ?? '', ''),
            $this->requiredSecret('braintree_private_key', $config['private_key'] ?? '', ''),
            $this->currency($config['currency'] ?? 'USD'),
        ];
    }

    /**
     * @return array{name:string,success:bool,message:string}
     */
    private function requiredSecret(string $name, string $value, string $prefix): array
    {
        $success = trim($value) !== '' && ($prefix === '' || str_starts_with($value, $prefix));

        return [
            'name' => $name,
            'success' => $success,
            'message' => $success ? $name . ' is set.' : $name . ' is missing or malformed.',
        ];
    }

    /**
     * @return array{name:string,success:bool,message:string}
     */
    private function currency(string $currency): array
    {
        $success = preg_match('/^[A-Z]{3}$/', $currency) === 1;

        return [
            'name' => 'currency',
            'success' => $success,
            'message' => $success ? 'Currency is valid.' : 'Currency must be a three-letter ISO code.',
        ];
    }
}

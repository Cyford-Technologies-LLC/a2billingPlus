<?php

declare(strict_types=1);

namespace A2BillingPlus\Config;

final class AppConfig
{
    /**
     * @param array<string, string> $values
     */
    private readonly array $values;

    public function __construct(array $values = [])
    {
        $this->values = $this->withAliases($values);
    }

    public static function fromEnvironment(): self
    {
        $values = [];
        foreach ($_ENV as $key => $value) {
            if (is_scalar($value)) {
                $values[(string)$key] = (string)$value;
            }
        }

        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'A2BP_') && is_scalar($value)) {
                $values[$key] = (string)$value;
            }
        }

        foreach ([
            'A2BP_DB_DSN',
            'A2BP_DB_HOST',
            'A2BP_DB_NAME',
            'A2BP_DB_USER',
            'A2BP_DB_PASSWORD',
            'A2BP_API_SERVICE_KEY',
            'A2BP_UI_THEME',
            'MODE',
            'VECTAVOIP_API_BASE_URL',
            'VECTAVOIP_API_KEY',
            'VECTAVOIP_API_SECRET',
            'VECTAVOIP_INSTALLATION_ID',
            'STRIPE_SECRET_KEY',
            'STRIPE_WEBHOOK_SECRET',
            'STRIPE_TEST_PUBLISHABLE_KEY',
            'STRIPE_TEST_SECRET_KEY',
            'STRIPE_TEST_RESTRICTED_KEY',
            'STRIPE_LIVE_SECRET_KEY',
            'STRIPE_LIVE_PUBLISHABLE_KEY',
            'STRIPE_LIVE_RESTRICTED_KEY',
            'STRIPE_TEST_WEBHOOK_SECRET',
            'STRIPE_LIVE_WEBHOOK_SECRET',
            'BRAINTREE_MERCHANT_ID',
            'BRAINTREE_PUBLIC_KEY',
            'BRAINTREE_PRIVATE_KEY',
            'PAYMENT_CURRENCY',
        ] as $key) {
            $value = getenv($key);
            if (is_string($value) && $value !== '') {
                $values[$key] = $value;
            }

            $file = getenv($key . '_FILE');
            if (is_string($file) && $file !== '' && is_readable($file)) {
                $contents = file_get_contents($file);
                if (is_string($contents)) {
                    $values[$key] = trim($contents);
                }
            }
        }

        return new self($values);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->values[$key] ?? $default;
        return is_scalar($value) ? (string)$value : $default;
    }

    public function databaseDsn(): string
    {
        $dsn = $this->string('A2BP_DB_DSN');
        if ($dsn !== '') {
            return $dsn;
        }

        return sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $this->string('A2BP_DB_HOST', 'db'),
            $this->string('A2BP_DB_NAME', 'mya2billing')
        );
    }

    /**
     * @param array<string, string> $values
     * @return array<string, string>
     */
    private function withAliases(array $values): array
    {
        $mode = strtolower(trim((string)($values['MODE'] ?? 'test')));
        $stripePrefix = $mode === 'live' ? 'STRIPE_LIVE' : 'STRIPE_TEST';

        if (($values['STRIPE_SECRET_KEY'] ?? '') === '' && ($values[$stripePrefix . '_SECRET_KEY'] ?? '') !== '') {
            $values['STRIPE_SECRET_KEY'] = $values[$stripePrefix . '_SECRET_KEY'];
        }

        if (($values['STRIPE_SECRET_KEY'] ?? '') === '' && ($values[$stripePrefix . '_RESTRICTED_KEY'] ?? '') !== '') {
            $values['STRIPE_SECRET_KEY'] = $values[$stripePrefix . '_RESTRICTED_KEY'];
        }

        if (($values['STRIPE_WEBHOOK_SECRET'] ?? '') === '' && ($values[$stripePrefix . '_WEBHOOK_SECRET'] ?? '') !== '') {
            $values['STRIPE_WEBHOOK_SECRET'] = $values[$stripePrefix . '_WEBHOOK_SECRET'];
        }

        return $values;
    }
}

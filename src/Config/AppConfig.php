<?php

declare(strict_types=1);

namespace A2BillingPlus\Config;

final class AppConfig
{
    /**
     * @param array<string, string> $values
     */
    public function __construct(private readonly array $values = [])
    {
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
            'VECTAVOIP_API_BASE_URL',
            'VECTAVOIP_API_KEY',
            'VECTAVOIP_API_SECRET',
            'VECTAVOIP_INSTALLATION_ID',
        ] as $key) {
            $value = getenv($key);
            if (is_string($value) && $value !== '') {
                $values[$key] = $value;
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
}

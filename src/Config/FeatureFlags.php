<?php

declare(strict_types=1);

namespace A2BillingPlus\Config;

final class FeatureFlags
{
    public function __construct(private readonly AppConfig $config)
    {
    }

    public function enabled(string $name, bool $default = false): bool
    {
        $key = 'A2BP_FEATURE_' . strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $name) ?? '');
        $value = $this->config->string($key);
        if ($value === '') {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}

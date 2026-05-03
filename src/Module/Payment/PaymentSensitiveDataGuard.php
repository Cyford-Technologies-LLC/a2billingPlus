<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Payment;

final class PaymentSensitiveDataGuard
{
    private const BLOCKED_KEYS = [
        'card_number',
        'cc_number',
        'cc_card_number',
        'cvv',
        'cvc',
        'cvv2',
        'security_code',
    ];

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    public function blockedKeys(array $payload): array
    {
        $found = [];
        $this->scan($payload, '', $found);

        return array_values(array_unique($found));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function assertSafe(array $payload): void
    {
        $blocked = $this->blockedKeys($payload);
        if ($blocked !== []) {
            throw new \InvalidArgumentException('Raw card data is not allowed: ' . implode(', ', $blocked));
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $found
     */
    private function scan(array $payload, string $path, array &$found): void
    {
        foreach ($payload as $key => $value) {
            $current = $path === '' ? (string)$key : $path . '.' . (string)$key;
            if (in_array(strtolower((string)$key), self::BLOCKED_KEYS, true)) {
                $found[] = $current;
            }

            if (is_array($value)) {
                $this->scan($value, $current, $found);
            }
        }
    }
}

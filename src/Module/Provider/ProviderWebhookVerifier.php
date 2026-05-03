<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider;

final class ProviderWebhookVerifier
{
    public function __construct(
        private readonly string $secret,
        private readonly int $toleranceSeconds = 300
    ) {
    }

    public function sign(string $payload, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $payload, $this->secret);
    }

    public function verify(string $payload, string $signature, int $timestamp, ?int $now = null): bool
    {
        if ($this->secret === '' || $signature === '' || $timestamp <= 0) {
            return false;
        }

        $now ??= time();
        if (abs($now - $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        return hash_equals($this->sign($payload, $timestamp), $signature);
    }
}

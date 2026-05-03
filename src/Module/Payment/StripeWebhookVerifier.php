<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Payment;

final class StripeWebhookVerifier
{
    public function __construct(
        private readonly string $webhookSecret,
        private readonly int $toleranceSeconds = 300
    ) {
    }

    public function verify(string $rawBody, string $signatureHeader, ?int $now = null): bool
    {
        if ($this->webhookSecret === '' || $signatureHeader === '') {
            return false;
        }

        $parts = $this->parseHeader($signatureHeader);
        $timestamp = isset($parts['t']) ? (int)$parts['t'][0] : 0;
        $signatures = $parts['v1'] ?? [];
        if ($timestamp <= 0 || $signatures === []) {
            return false;
        }

        $now ??= time();
        if (abs($now - $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $this->webhookSecret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, list<string>>
     */
    private function parseHeader(string $header): array
    {
        $parsed = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key !== '' && $value !== '') {
                $parsed[$key][] = $value;
            }
        }

        return $parsed;
    }
}

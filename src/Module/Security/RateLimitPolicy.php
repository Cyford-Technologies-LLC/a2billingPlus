<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Security;

final class RateLimitPolicy
{
    /** @var array<string, list<int>> */
    private array $attempts = [];

    public function allow(string $key, int $limit, int $windowSeconds, ?int $now = null): bool
    {
        $now ??= time();
        $windowStart = $now - max(1, $windowSeconds);
        $this->attempts[$key] = array_values(array_filter(
            $this->attempts[$key] ?? [],
            static fn (int $timestamp): bool => $timestamp > $windowStart
        ));

        if (count($this->attempts[$key]) >= $limit) {
            return false;
        }

        $this->attempts[$key][] = $now;
        return true;
    }
}

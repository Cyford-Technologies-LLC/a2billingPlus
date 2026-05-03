<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Billing;

final class CallRatingRequest
{
    public function __construct(
        private readonly string $destination,
        private readonly int $durationSeconds,
        private readonly int $tariffPlanId = 0
    ) {
    }

    public function getDestination(): string
    {
        return preg_replace('/\D+/', '', $this->destination) ?? '';
    }

    public function getDurationSeconds(): int
    {
        return max(0, $this->durationSeconds);
    }

    public function getTariffPlanId(): int
    {
        return $this->tariffPlanId;
    }
}

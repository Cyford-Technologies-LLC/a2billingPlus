<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Billing;

final class CallRatingService
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function rate(CallRatingRequest $request): CallRatingResult
    {
        $destination = $request->getDestination();
        if ($destination === '') {
            return new CallRatingResult(false, 'Destination is required.');
        }

        $rate = $this->findRate($destination, $request->getTariffPlanId());
        if ($rate === null) {
            return new CallRatingResult(false, 'No matching rate was found.');
        }

        $billableSeconds = $this->billableSeconds(
            $request->getDurationSeconds(),
            $this->intValue($rate, 'initblock', 60),
            $this->intValue($rate, 'billingblock', 60)
        );

        $customerCost = $this->ratedCost(
            $billableSeconds,
            $this->decimalValue($rate, 'rateinitial'),
            $this->decimalValue($rate, 'connectcharge'),
            $this->decimalValue($rate, 'mincharge')
        );
        $providerCost = $this->ratedCost(
            $billableSeconds,
            $this->decimalValue($rate, 'buyrate'),
            $this->decimalValue($rate, 'buyrateconnectcharge'),
            $this->decimalValue($rate, 'buyratemincharge')
        );

        return new CallRatingResult(
            true,
            'Call rated.',
            (string)$rate['dialprefix'],
            $billableSeconds,
            $customerCost,
            $providerCost
        );
    }

    private function billableSeconds(int $durationSeconds, int $initialBlock, int $billingBlock): int
    {
        if ($durationSeconds <= 0) {
            return 0;
        }

        $initialBlock = max(1, $initialBlock);
        $billingBlock = max(1, $billingBlock);
        if ($durationSeconds <= $initialBlock) {
            return $initialBlock;
        }

        return $initialBlock + (int)(ceil(($durationSeconds - $initialBlock) / $billingBlock) * $billingBlock);
    }

    private function ratedCost(int $billableSeconds, float $perMinuteRate, float $connectionCharge, float $minimumCharge): string
    {
        if ($billableSeconds <= 0) {
            return '0.00000';
        }

        $cost = $connectionCharge + (($billableSeconds / 60) * $perMinuteRate);
        if ($minimumCharge > 0) {
            $cost = max($minimumCharge, $cost);
        }

        return number_format(round($cost, 5, PHP_ROUND_HALF_UP), 5, '.', '');
    }

    /**
     * @return null|array<string, mixed>
     */
    private function findRate(string $destination, int $tariffPlanId): ?array
    {
        if ($tariffPlanId > 0) {
            $statement = $this->pdo->prepare(
                'SELECT * FROM cc_ratecard WHERE idtariffplan = :tariff_plan_id ORDER BY LENGTH(dialprefix) DESC, id DESC'
            );
            $statement->execute(['tariff_plan_id' => $tariffPlanId]);
        } else {
            $statement = $this->pdo->query('SELECT * FROM cc_ratecard ORDER BY LENGTH(dialprefix) DESC, id DESC');
        }

        if (!$statement) {
            return null;
        }

        while (($row = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            if (str_starts_with($destination, (string)$row['dialprefix'])) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function decimalValue(array $row, string $key): float
    {
        $value = $row[$key] ?? 0;
        return is_numeric($value) ? (float)$value : 0.0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function intValue(array $row, string $key, int $default): int
    {
        $value = $row[$key] ?? $default;
        return is_numeric($value) ? max(1, (int)$value) : $default;
    }
}

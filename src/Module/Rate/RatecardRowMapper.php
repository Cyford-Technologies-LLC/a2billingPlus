<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Rate;

final class RatecardRowMapper
{
    /**
     * @param array<string, mixed> $providerRow
     * @return array<string, int|string>
     */
    public function map(array $providerRow, int $tariffPlanId, string $tag): array
    {
        $prefix = $this->stringValue($providerRow, 'prefix');
        $rate = $this->decimalValue($providerRow, 'rate');
        $buyRate = $this->decimalValue($providerRow, 'buyrate', $rate);
        $increment = $this->intValue($providerRow, 'increment', 60);
        $destination = $this->stringValue($providerRow, 'destination');

        return [
            'idtariffplan' => $tariffPlanId,
            'dialprefix' => $prefix,
            'destination' => $destination !== '' ? crc32($destination) : 0,
            'buyrate' => $buyRate,
            'buyrateinitblock' => $increment,
            'buyrateincrement' => $increment,
            'rateinitial' => $rate,
            'initblock' => $increment,
            'billingblock' => $increment,
            'tag' => $tag,
        ];
    }

    /**
     * @param array<string, mixed> $providerRow
     */
    public function isImportable(array $providerRow): bool
    {
        return $this->stringValue($providerRow, 'prefix') !== ''
            && $this->decimalValue($providerRow, 'rate') !== '';
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? $default;
        return is_scalar($value) ? trim((string)$value) : $default;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function decimalValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? $default;
        if (!is_scalar($value) || !is_numeric((string)$value)) {
            return $default;
        }

        return number_format((float)$value, 5, '.', '');
    }

    /**
     * @param array<string, mixed> $values
     */
    private function intValue(array $values, string $key, int $default): int
    {
        $value = $values[$key] ?? $default;
        return is_numeric($value) ? max(1, (int)$value) : $default;
    }
}

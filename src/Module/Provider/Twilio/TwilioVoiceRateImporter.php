<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\Twilio;

use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\RateImporterInterface;
use A2BillingPlus\Module\Provider\RateImportPreview;
use A2BillingPlus\Module\Provider\RateImportRequest;
use A2BillingPlus\Module\Provider\RateImportResult;

final class TwilioVoiceRateImporter implements RateImporterInterface
{
    public function __construct(
        private readonly ProviderCredentials $credentials,
        private readonly TwilioApiClient $client = new TwilioApiClient()
    ) {
    }

    public function preview(RateImportRequest $request): RateImportPreview
    {
        try {
            $rows = $this->rows($request);
        } catch (\Throwable $exception) {
            return new RateImportPreview(0, [], 'Twilio voice rate preview failed: ' . $exception->getMessage());
        }

        return new RateImportPreview(
            count($rows),
            $rows,
            count($rows) === 0
                ? 'No Twilio outbound voice rates matched the selected filters.'
                : 'Twilio outbound voice rates previewed with retail markup applied.'
        );
    }

    public function import(RateImportRequest $request): RateImportResult
    {
        return new RateImportResult(false, 0, 0, 'Use the A2BillingPlus ratecard importer to write Twilio preview rows.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(RateImportRequest $request): array
    {
        $filters = $request->getFilters();
        $countries = $this->requestedCountries($filters);
        if ($countries === []) {
            $countries = $this->availableCountryCodes();
        }

        $markupPercent = $this->boundedDecimal($filters['markup_percent'] ?? '0', 0.0, 1000.0);
        $destinationFilter = strtolower(trim($filters['destination'] ?? ''));
        $prefixFilter = preg_replace('/\D+/', '', (string)($filters['prefix'] ?? '')) ?: '';
        $rows = [];

        foreach ($countries as $countryCode) {
            $country = $this->client->fetchVoicePricingCountry($this->credentials, $countryCode);
            $countryName = $this->stringValue($country, 'country', $countryCode);
            $currency = strtoupper($this->stringValue($country, 'price_unit', $request->getTargetCurrency()));

            foreach ($country['outbound_prefix_prices'] ?? $country['outboundPrefixPrices'] ?? [] as $priceRow) {
                if (!is_array($priceRow)) {
                    continue;
                }

                $friendlyName = $this->stringValue($priceRow, 'friendly_name', $this->stringValue($priceRow, 'friendlyName', $countryName));
                $destinationName = $this->destinationName($countryName, $friendlyName);
                if ($destinationFilter !== '' && !str_contains(strtolower($destinationName), $destinationFilter)) {
                    continue;
                }

                $buyRate = $this->numericString($priceRow['current_price'] ?? $priceRow['currentPrice'] ?? $priceRow['base_price'] ?? $priceRow['basePrice'] ?? null);
                if ($buyRate === '') {
                    continue;
                }

                foreach ($priceRow['destination_prefixes'] ?? $priceRow['destinationPrefixes'] ?? [] as $prefix) {
                    $dialPrefix = preg_replace('/\D+/', '', (string)$prefix) ?: '';
                    if ($dialPrefix === '' || ($prefixFilter !== '' && !str_starts_with($dialPrefix, $prefixFilter))) {
                        continue;
                    }

                    $retailRate = $this->markedUpRate($buyRate, $markupPercent);
                    $rows[] = [
                        'destination' => $destinationName,
                        'prefix' => $dialPrefix,
                        'buyrate' => $buyRate,
                        'rate' => $retailRate,
                        'currency' => $currency,
                        'increment' => 60,
                        'rate_deck' => $request->getRateDeck() !== '' ? $request->getRateDeck() : 'voice-outbound',
                        'markup_percent' => number_format($markupPercent, 2, '.', ''),
                    ];
                }
            }
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string)$a['prefix'], (string)$b['prefix']));

        return $rows;
    }

    /**
     * @param array<string, string> $filters
     * @return list<string>
     */
    private function requestedCountries(array $filters): array
    {
        $value = strtoupper(trim($filters['countries'] ?? $filters['country'] ?? $filters['iso_country'] ?? ''));
        if ($value === '') {
            return [];
        }

        $countries = [];
        foreach (preg_split('/[\s,]+/', $value) ?: [] as $country) {
            $country = strtoupper(trim($country));
            if (preg_match('/^[A-Z]{2}$/', $country) === 1) {
                $countries[] = $country;
            }
        }

        return array_values(array_unique($countries));
    }

    /**
     * @return list<string>
     */
    private function availableCountryCodes(): array
    {
        $payload = $this->client->listVoicePricingCountries($this->credentials, ['PageSize' => '1000']);
        $countries = [];
        foreach ($payload['countries'] ?? [] as $country) {
            if (!is_array($country)) {
                continue;
            }
            $code = strtoupper($this->stringValue($country, 'iso_country', $this->stringValue($country, 'isoCountry')));
            if (preg_match('/^[A-Z]{2}$/', $code) === 1) {
                $countries[] = $code;
            }
        }

        return array_values(array_unique($countries));
    }

    private function markedUpRate(string $buyRate, float $markupPercent): string
    {
        return number_format(((float)$buyRate) * (1 + ($markupPercent / 100)), 5, '.', '');
    }

    private function destinationName(string $countryName, string $friendlyName): string
    {
        $countryName = trim($countryName);
        $friendlyName = trim($friendlyName);
        if ($friendlyName === '' || strcasecmp($friendlyName, $countryName) === 0) {
            return $countryName;
        }
        if ($countryName !== '' && str_starts_with(strtolower($friendlyName), strtolower($countryName))) {
            return $friendlyName;
        }

        return trim($countryName . ' ' . $friendlyName);
    }

    private function numericString(mixed $value): string
    {
        if (!is_scalar($value) || !is_numeric((string)$value)) {
            return '';
        }

        return number_format((float)$value, 5, '.', '');
    }

    private function boundedDecimal(string $value, float $min, float $max): float
    {
        if (!is_numeric($value)) {
            return $min;
        }

        return max($min, min($max, (float)$value));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? $default;
        return is_scalar($value) ? trim((string)$value) : $default;
    }
}

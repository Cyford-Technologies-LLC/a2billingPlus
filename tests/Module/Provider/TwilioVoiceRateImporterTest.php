<?php

declare(strict_types=1);

namespace A2BillingPlus\Tests\Module\Provider;

use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\RateImportRequest;
use A2BillingPlus\Module\Provider\Twilio\TwilioApiClient;
use A2BillingPlus\Module\Provider\Twilio\TwilioVoiceRateImporter;
use PHPUnit\Framework\TestCase;

final class TwilioVoiceRateImporterTest extends TestCase
{
    public function testPreviewMapsTwilioOutboundPrefixesWithMarkup(): void
    {
        $client = new TwilioApiClient(function (string $method, string $url): array {
            $this->assertSame('GET', $method);
            $this->assertStringContainsString('/v2/Voice/Countries/US', $url);

            return [
                'status' => 200,
                'body' => json_encode([
                    'country' => 'United States',
                    'iso_country' => 'US',
                    'price_unit' => 'usd',
                    'outbound_prefix_prices' => [[
                        'friendly_name' => 'United States - Mobile',
                        'destination_prefixes' => ['+1', '+1201'],
                        'current_price' => '0.0100',
                    ]],
                ], JSON_THROW_ON_ERROR),
            ];
        });

        $importer = new TwilioVoiceRateImporter(
            new ProviderCredentials('https://api.twilio.com', 'SK123', 'secret', ['account_sid' => 'AC123']),
            $client
        );

        $preview = $importer->preview(new RateImportRequest('USD', 'voice-outbound', [
            'countries' => 'US',
            'markup_percent' => '35',
        ]));

        $this->assertSame(2, $preview->getTotalRows());
        $this->assertSame('1', $preview->getSampleRows()[0]['prefix']);
        $this->assertSame('0.01000', $preview->getSampleRows()[0]['buyrate']);
        $this->assertSame('0.01350', $preview->getSampleRows()[0]['rate']);
        $this->assertSame('USD', $preview->getSampleRows()[0]['currency']);
    }
}

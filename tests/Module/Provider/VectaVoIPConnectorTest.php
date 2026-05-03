<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\RateImportRequest;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPConnector;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPRateImporter;
use PHPUnit\Framework\TestCase;

final class VectaVoIPConnectorTest extends TestCase
{
    public function testConnectionRequiresApiKey(): void
    {
        $connector = new VectaVoIPConnector();
        $result = $connector->testConnection(new ProviderCredentials('https://api.vectavoip.com', ''));

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('VectaVoIP API key is required. Contact info@VectaVoIP.com for access.', $result->getMessage());
    }

    public function testConnectionAcceptsStructurallyValidCredentials(): void
    {
        $connector = new VectaVoIPConnector();
        $result = $connector->testConnection(new ProviderCredentials('https://api.vectavoip.com/', 'test-key'));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('https://api.vectavoip.com', $result->getDetails()['base_url']);
        $this->assertSame('info@VectaVoIP.com', $result->getDetails()['support_email']);
    }

    public function testProvidesRateImporter(): void
    {
        $connector = new VectaVoIPConnector();
        $importer = $connector->getRateImporter(new ProviderCredentials('https://api.vectavoip.com', 'test-key'));

        $this->assertInstanceOf(VectaVoIPRateImporter::class, $importer);
    }

    public function testRatePreviewFetchesProviderRows(): void
    {
        $importer = new VectaVoIPRateImporter(
            new ProviderCredentials('http://localhost/api/sandbox', 'test-key'),
            function (string $url, ProviderCredentials $credentials): array {
                $this->assertSame('http://localhost/api/sandbox/v1/rates/preview?rate_deck=retail&currency=USD&destination=US', $url);
                $this->assertSame('test-key', $credentials->getApiKey());

                return [
                    'status' => 200,
                    'body' => json_encode([
                        'message' => 'preview ok',
                        'total_rows' => 2,
                        'sample_rows' => [
                            ['destination' => 'United States', 'prefix' => '1', 'rate' => '0.0100'],
                            ['destination' => 'Canada', 'prefix' => '1', 'rate' => '0.0125'],
                        ],
                    ], JSON_THROW_ON_ERROR),
                ];
            }
        );

        $preview = $importer->preview(new RateImportRequest('usd', 'retail', ['destination' => 'US']));

        $this->assertSame(2, $preview->getTotalRows());
        $this->assertSame('preview ok', $preview->getMessage());
        $this->assertSame('United States', $preview->getSampleRows()[0]['destination']);
    }

    public function testRatePreviewReportsProviderFailure(): void
    {
        $importer = new VectaVoIPRateImporter(
            new ProviderCredentials('http://localhost/api/sandbox', 'test-key'),
            fn (): array => [
                'status' => 422,
                'body' => '{"message":"invalid deck"}',
            ]
        );

        $preview = $importer->preview(new RateImportRequest('usd'));

        $this->assertSame(0, $preview->getTotalRows());
        $this->assertSame('invalid deck', $preview->getMessage());
    }
}

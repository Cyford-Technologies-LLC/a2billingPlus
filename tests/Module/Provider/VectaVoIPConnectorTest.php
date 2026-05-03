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
        $result = $connector->testConnection(new ProviderCredentials('https://VectaVoIP.com/api', ''));

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('VectaVoIP API key is required.', $result->getMessage());
    }

    public function testConnectionAcceptsStructurallyValidCredentials(): void
    {
        $connector = new VectaVoIPConnector();
        $result = $connector->testConnection(new ProviderCredentials('https://VectaVoIP.com/api/', 'test-key'));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('https://VectaVoIP.com/api', $result->getDetails()['base_url']);
    }

    public function testProvidesRateImporter(): void
    {
        $connector = new VectaVoIPConnector();
        $importer = $connector->getRateImporter(new ProviderCredentials('https://VectaVoIP.com/api', 'test-key'));
        $preview = $importer->preview(new RateImportRequest('usd'));

        $this->assertInstanceOf(VectaVoIPRateImporter::class, $importer);
        $this->assertSame(0, $preview->getTotalRows());
        $this->assertStringContainsString('VectaVoIP rate preview', $preview->getMessage());
    }
}

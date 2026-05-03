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
        $result = $connector->testConnection(new ProviderCredentials('https://api.VectaVoIP.com', ''));

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('VectaVoIP API key is required. Contact info@VectaVoIP.com for access.', $result->getMessage());
    }

    public function testConnectionAcceptsStructurallyValidCredentials(): void
    {
        $connector = new VectaVoIPConnector();
        $result = $connector->testConnection(new ProviderCredentials('https://api.VectaVoIP.com/', 'test-key'));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('https://api.VectaVoIP.com', $result->getDetails()['base_url']);
        $this->assertSame('info@VectaVoIP.com', $result->getDetails()['support_email']);
    }

    public function testProvidesRateImporter(): void
    {
        $connector = new VectaVoIPConnector();
        $importer = $connector->getRateImporter(new ProviderCredentials('https://api.VectaVoIP.com', 'test-key'));
        $preview = $importer->preview(new RateImportRequest('usd'));

        $this->assertInstanceOf(VectaVoIPRateImporter::class, $importer);
        $this->assertSame(0, $preview->getTotalRows());
        $this->assertStringContainsString('VectaVoIP rate preview', $preview->getMessage());
    }
}

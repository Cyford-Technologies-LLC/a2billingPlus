<?php

declare(strict_types=1);

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPConnector;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPProviderModule;
use PHPUnit\Framework\TestCase;

final class VectaVoIPProviderModuleTest extends TestCase
{
    public function testProvidesVectaVoIPConnector(): void
    {
        $module = new VectaVoIPProviderModule(new AppConfig());
        $connectors = iterator_to_array($module->connectors());

        $this->assertCount(1, $connectors);
        $this->assertInstanceOf(VectaVoIPConnector::class, $connectors[0]);
    }

    public function testReadsConfiguredApiBaseUrl(): void
    {
        $module = new VectaVoIPProviderModule(new AppConfig([
            'VECTAVOIP_API_BASE_URL' => 'https://provider.example',
        ]));

        $this->assertSame('https://provider.example', $module->apiBaseUrl());
    }
}

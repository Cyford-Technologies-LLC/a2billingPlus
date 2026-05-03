<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\ProviderRegistry;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPConnector;
use PHPUnit\Framework\TestCase;

final class ProviderRegistryTest extends TestCase
{
    public function testRegistersAndReturnsProviderConnector(): void
    {
        $connector = new VectaVoIPConnector();
        $registry = new ProviderRegistry([$connector]);

        $this->assertSame($connector, $registry->get('vectavoip'));
        $this->assertArrayHasKey('vectavoip', $registry->all());
    }
}

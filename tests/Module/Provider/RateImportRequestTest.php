<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\RateImportRequest;
use PHPUnit\Framework\TestCase;

final class RateImportRequestTest extends TestCase
{
    public function testNormalizesTargetCurrencyForProviderRateImports(): void
    {
        $request = new RateImportRequest('usd', 'retail', ['country' => 'US'], false);

        $this->assertSame('USD', $request->getTargetCurrency());
        $this->assertSame('retail', $request->getRateDeck());
        $this->assertSame(['country' => 'US'], $request->getFilters());
        $this->assertFalse($request->isDryRun());
    }
}

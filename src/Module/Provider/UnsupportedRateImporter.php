<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider;

final class UnsupportedRateImporter implements RateImporterInterface
{
    public function __construct(private readonly string $providerName)
    {
    }

    public function preview(RateImportRequest $request): RateImportPreview
    {
        return new RateImportPreview(0, [], $this->providerName . ' rate import is not supported by this integration.');
    }

    public function import(RateImportRequest $request): RateImportResult
    {
        return new RateImportResult(false, 0, 0, $this->providerName . ' rate import is not supported by this integration.');
    }
}

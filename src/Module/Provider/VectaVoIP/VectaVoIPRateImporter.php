<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\VectaVoIP;

use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\RateImporterInterface;
use A2BillingPlus\Module\Provider\RateImportPreview;
use A2BillingPlus\Module\Provider\RateImportRequest;
use A2BillingPlus\Module\Provider\RateImportResult;

final class VectaVoIPRateImporter implements RateImporterInterface
{
    public function __construct(private readonly ProviderCredentials $credentials)
    {
    }

    public function preview(RateImportRequest $request): RateImportPreview
    {
        return new RateImportPreview(0, [], sprintf(
            'VectaVoIP rate preview is ready for %s/%s. API implementation is pending.',
            $request->getRateDeck(),
            $request->getTargetCurrency()
        ));
    }

    public function import(RateImportRequest $request): RateImportResult
    {
        if ($request->isDryRun()) {
            return new RateImportResult(true, 0, 0, 'Dry run completed. No rates were imported.');
        }

        return new RateImportResult(false, 0, 0, 'VectaVoIP API rate import is not implemented yet.');
    }

    public function getCredentials(): ProviderCredentials
    {
        return $this->credentials;
    }
}

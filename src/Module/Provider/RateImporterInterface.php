<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider;

interface RateImporterInterface
{
    public function preview(RateImportRequest $request): RateImportPreview;

    public function import(RateImportRequest $request): RateImportResult;
}

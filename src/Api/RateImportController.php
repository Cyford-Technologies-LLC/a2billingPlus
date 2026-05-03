<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Rate\RatecardImportService;
use A2BillingPlus\Module\Security\AuditLogRepository;

final class RateImportController
{
    /**
     * @param callable(): \PDO $pdoFactory
     */
    public function __construct(
        private readonly ApiServiceKeyAuthenticator $authenticator,
        private $pdoFactory
    ) {
    }

    public function handle(JsonRequest $request): JsonResponse
    {
        $authError = $this->authenticator->authenticate($request);
        if ($authError !== null) {
            return $authError;
        }

        if ($request->getMethod() !== 'POST') {
            return ApiResponder::error('method_not_allowed', 'Rate import requires POST.', 405);
        }

        $tariffPlanId = $request->getInt('tariff_plan_id');
        if ($tariffPlanId <= 0) {
            return ApiResponder::error('invalid_tariff_plan_id', 'tariff_plan_id must be a positive integer.', 422, ['field' => 'tariff_plan_id']);
        }

        $tag = trim($request->getString('tag'));
        if ($tag === '' || strlen($tag) > 100) {
            return ApiResponder::error('invalid_tag', 'tag is required and must be 100 characters or fewer.', 422, ['field' => 'tag']);
        }

        $rows = $request->getArray('rows');
        if ($rows === []) {
            return ApiResponder::error('invalid_rows', 'At least one rate row is required.', 422, ['field' => 'rows']);
        }

        $dryRun = $request->getString('dry_run', '1') !== '0';
        $updateExisting = $request->getString('update_existing', '0') === '1';

        try {
            $pdo = ($this->pdoFactory)();
            $summary = (new RatecardImportService($pdo))->importRows($rows, $tariffPlanId, $tag, $dryRun, $updateExisting);
            (new AuditLogRepository($pdo))->record(
                $request->getHeader('X-A2BP-Actor') ?: 'service-key',
                'rate.import.apply',
                'cc_ratecard',
                (string)$tariffPlanId,
                [
                    'tag' => $tag,
                    'dry_run' => $dryRun,
                    'update_existing' => $updateExisting,
                    'imported_rows' => $summary->getImportedRows(),
                    'skipped_rows' => $summary->getSkippedRows(),
                ]
            );
        } catch (\Throwable $exception) {
            return ApiResponder::error('rate_import_failed', $exception->getMessage(), 500);
        }

        return ApiResponder::ok([
            'import' => [
                'success' => $summary->isSuccessful(),
                'message' => $summary->getMessage(),
                'imported_rows' => $summary->getImportedRows(),
                'skipped_rows' => $summary->getSkippedRows(),
                'dry_run' => $dryRun,
                'update_existing' => $updateExisting,
            ],
        ], [
            'resource' => 'rate-imports',
            'tariff_plan_id' => $tariffPlanId,
            'tag' => $tag,
        ], $summary->isSuccessful() ? 200 : 422);
    }
}

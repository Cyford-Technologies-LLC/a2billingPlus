<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Billing\BillingReportService;

final class BillingReportController
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

        if ($request->getMethod() !== 'GET') {
            return ApiResponder::error('method_not_allowed', 'Reports require GET.', 405);
        }

        $report = trim($request->getString('report', 'call_quality'));
        if (!in_array($report, ['call_quality', 'daily_call_quality', 'reconciliation'], true)) {
            return ApiResponder::error('invalid_report', 'Unknown report.', 422, ['field' => 'report']);
        }

        $from = trim($request->getString('from'));
        $to = trim($request->getString('to'));
        foreach (['from' => $from, 'to' => $to] as $field => $value) {
            if ($value === '') {
                return ApiResponder::error('missing_' . $field, ucfirst($field) . ' is required.', 422, ['field' => $field]);
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value) !== 1) {
                return ApiResponder::error('invalid_' . $field, ucfirst($field) . ' must be YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.', 422, ['field' => $field]);
            }
        }

        $timezone = trim($request->getString('timezone', 'UTC'));
        try {
            $service = new BillingReportService(($this->pdoFactory)());
            $data = match ($report) {
                'daily_call_quality' => ['daily_call_quality' => $service->dailyCallQuality($from, $to, $timezone)],
                'reconciliation' => ['reconciliation' => $service->reconciliationSummary($from, $to)],
                default => ['call_quality' => $service->callQualitySummary($from, $to)],
            };
        } catch (\Throwable $exception) {
            return ApiResponder::error('report_failed', $exception->getMessage(), 500);
        }

        return ApiResponder::ok($data, [
            'resource' => 'reports',
            'report' => $report,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'timezone' => $timezone,
            ],
        ]);
    }
}

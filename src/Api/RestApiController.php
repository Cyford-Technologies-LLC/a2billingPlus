<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Billing\CdrRepository;
use A2BillingPlus\Module\Billing\CdrSearchCriteria;
use A2BillingPlus\Module\Billing\CdrSearchService;
use A2BillingPlus\Module\Customer\CustomerAccountRepository;
use A2BillingPlus\Module\Customer\CustomerAccountService;
use A2BillingPlus\Module\Customer\CustomerSearchCriteria;
use A2BillingPlus\Module\Invoice\InvoiceRepository;
use A2BillingPlus\Module\Invoice\InvoiceSearchCriteria;
use A2BillingPlus\Module\Invoice\InvoiceService;
use A2BillingPlus\Module\Invoice\ReceiptRepository;
use A2BillingPlus\Module\Invoice\ReceiptSearchCriteria;
use A2BillingPlus\Module\Invoice\ReceiptService;
use A2BillingPlus\Module\Payment\PaymentLedgerRepository;
use A2BillingPlus\Module\Payment\PaymentLedgerService;
use A2BillingPlus\Module\Payment\PaymentSearchCriteria;
use A2BillingPlus\Module\Rate\RatecardRepository;
use A2BillingPlus\Module\Rate\RatecardSearchCriteria;
use A2BillingPlus\Module\Rate\RatecardSearchService;
use A2BillingPlus\Module\Security\AuditLogRepository;

final class RestApiController
{
    public const RESOURCES = ['customers', 'balances', 'rates', 'payments', 'cdrs', 'providers', 'invoices', 'receipts'];

    /**
     * @param callable(): \PDO $pdoFactory
     */
    public function __construct(
        private readonly ApiServiceKeyAuthenticator $authenticator,
        private $pdoFactory
    ) {
    }

    public function handle(string $resource, JsonRequest $request): JsonResponse
    {
        $authError = $this->authenticator->authenticate($request);
        if ($authError !== null) {
            return $authError;
        }

        if (!in_array($resource, self::RESOURCES, true)) {
            return ApiResponder::error('resource_not_found', 'Unknown API resource.', 404);
        }

        if ($request->getMethod() !== 'GET' && !($resource === 'customers' && $request->getMethod() === 'PATCH')) {
            return ApiResponder::error('method_not_allowed', 'This API resource currently supports GET only.', 405);
        }

        $limit = $request->getInt('limit', 50);
        $offset = $request->getInt('offset', 0);
        if ($limit < 1 || $limit > 100) {
            return ApiResponder::error('invalid_limit', 'Limit must be between 1 and 100.', 422, ['field' => 'limit']);
        }

        if ($offset < 0) {
            return ApiResponder::error('invalid_offset', 'Offset must be zero or greater.', 422, ['field' => 'offset']);
        }

        if ($resource === 'customers') {
            return $this->handleCustomers($request, $limit, $offset);
        }

        if ($resource === 'rates') {
            return $this->handleRates($request, $limit, $offset);
        }

        if ($resource === 'cdrs') {
            return $this->handleCdrs($request, $limit, $offset);
        }

        if ($resource === 'payments') {
            return $this->handlePayments($request, $limit, $offset);
        }

        if ($resource === 'invoices') {
            return $this->handleInvoices($request, $limit, $offset);
        }

        if ($resource === 'receipts') {
            return $this->handleReceipts($request, $limit, $offset);
        }

        try {
            $repository = new ResourceListRepository(($this->pdoFactory)());
            $result = $repository->list($resource, $limit, $offset);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponder::error('resource_not_found', $exception->getMessage(), 404);
        } catch (\Throwable $exception) {
            return ApiResponder::error('resource_query_failed', $exception->getMessage(), 500);
        }

        return ApiResponder::ok([
            $resource => $result['items'],
        ], [
            'resource' => $resource,
            'limit' => $limit,
            'offset' => $offset,
            'columns' => $result['columns'],
        ]);
    }

    private function handleCustomers(JsonRequest $request, int $limit, int $offset): JsonResponse
    {
        $id = $request->getInt('id');
        if ($request->getMethod() === 'PATCH') {
            return $this->handleCustomerStatusUpdate($request, $id);
        }

        $statusValue = $request->getString('status');
        $status = null;
        if ($statusValue !== '') {
            if (!in_array($statusValue, ['0', '1'], true)) {
                return ApiResponder::error('invalid_status', 'Status must be 0 or 1.', 422, ['field' => 'status']);
            }
            $status = (int)$statusValue;
        }

        $search = trim($request->getString('search'));
        if (strlen($search) > 100) {
            return ApiResponder::error('invalid_search', 'Search must be 100 characters or fewer.', 422, ['field' => 'search']);
        }

        try {
            $service = $this->customerService();
            if ($id > 0) {
                $customer = $service->detail($id);
                if ($customer === null) {
                    return ApiResponder::error('customer_not_found', 'Customer was not found.', 404, ['id' => $id]);
                }

                return ApiResponder::ok([
                    'customer' => $customer,
                ], [
                    'resource' => 'customers',
                    'id' => $id,
                ]);
            }

            $result = $service->search(new CustomerSearchCriteria($limit, $offset, $search, $status));
        } catch (\Throwable $exception) {
            return ApiResponder::error('customer_query_failed', $exception->getMessage(), 500);
        }

        return ApiResponder::ok([
            'customers' => $result['items'],
        ], [
            'resource' => 'customers',
            'limit' => $limit,
            'offset' => $offset,
            'columns' => $result['columns'],
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    private function handleCustomerStatusUpdate(JsonRequest $request, int $id): JsonResponse
    {
        if ($id <= 0) {
            return ApiResponder::error('invalid_customer_id', 'Customer id is required.', 422, ['field' => 'id']);
        }

        $statusValue = $request->getString('status');
        if (!in_array($statusValue, ['0', '1'], true)) {
            return ApiResponder::error('invalid_status', 'Status must be 0 or 1.', 422, ['field' => 'status']);
        }

        try {
            $customer = $this->customerService()->changeStatus(
                $id,
                (int)$statusValue,
                $request->getHeader('X-A2BP-Actor') ?: 'service-key'
            );
        } catch (\Throwable $exception) {
            return ApiResponder::error('customer_update_failed', $exception->getMessage(), 500);
        }

        if ($customer === null) {
            return ApiResponder::error('customer_not_found', 'Customer was not found.', 404, ['id' => $id]);
        }

        return ApiResponder::ok([
            'customer' => $customer,
        ], [
            'resource' => 'customers',
            'id' => $id,
            'action' => 'status_update',
        ]);
    }

    private function customerService(): CustomerAccountService
    {
        $pdo = ($this->pdoFactory)();

        return new CustomerAccountService(
            new CustomerAccountRepository($pdo),
            new AuditLogRepository($pdo)
        );
    }

    private function handleRates(JsonRequest $request, int $limit, int $offset): JsonResponse
    {
        $prefix = trim($request->getString('prefix'));
        if ($prefix !== '' && preg_match('/^[0-9*#+]+$/', $prefix) !== 1) {
            return ApiResponder::error('invalid_prefix', 'Prefix may only contain digits, *, #, or +.', 422, ['field' => 'prefix']);
        }

        $tariffPlanId = null;
        $tariffPlanValue = $request->getString('tariff_plan_id');
        if ($tariffPlanValue !== '') {
            if (preg_match('/^[1-9][0-9]*$/', $tariffPlanValue) !== 1) {
                return ApiResponder::error('invalid_tariff_plan_id', 'Tariff plan id must be a positive integer.', 422, ['field' => 'tariff_plan_id']);
            }
            $tariffPlanId = (int)$tariffPlanValue;
        }

        $tag = trim($request->getString('tag'));
        if (strlen($tag) > 100) {
            return ApiResponder::error('invalid_tag', 'Tag must be 100 characters or fewer.', 422, ['field' => 'tag']);
        }

        try {
            $service = new RatecardSearchService(new RatecardRepository(($this->pdoFactory)()));
            $result = $service->search(new RatecardSearchCriteria($limit, $offset, $prefix, $tariffPlanId, $tag));
        } catch (\Throwable $exception) {
            return ApiResponder::error('rate_query_failed', $exception->getMessage(), 500);
        }

        return ApiResponder::ok([
            'rates' => $result['items'],
        ], [
            'resource' => 'rates',
            'limit' => $limit,
            'offset' => $offset,
            'columns' => $result['columns'],
            'filters' => [
                'prefix' => $prefix,
                'tariff_plan_id' => $tariffPlanId,
                'tag' => $tag,
            ],
        ]);
    }

    private function handleCdrs(JsonRequest $request, int $limit, int $offset): JsonResponse
    {
        $from = trim($request->getString('from'));
        $to = trim($request->getString('to'));
        foreach (['from' => $from, 'to' => $to] as $field => $value) {
            if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value) !== 1) {
                return ApiResponder::error('invalid_' . $field, ucfirst($field) . ' must be YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.', 422, ['field' => $field]);
            }
        }

        $customerId = null;
        $customerIdValue = $request->getString('customer_id');
        if ($customerIdValue !== '') {
            if (preg_match('/^[1-9][0-9]*$/', $customerIdValue) !== 1) {
                return ApiResponder::error('invalid_customer_id', 'Customer id must be a positive integer.', 422, ['field' => 'customer_id']);
            }
            $customerId = (int)$customerIdValue;
        }

        $calledStation = trim($request->getString('calledstation'));
        if (strlen($calledStation) > 64) {
            return ApiResponder::error('invalid_calledstation', 'Called station filter must be 64 characters or fewer.', 422, ['field' => 'calledstation']);
        }

        try {
            $service = new CdrSearchService(new CdrRepository(($this->pdoFactory)()));
            $result = $service->search(new CdrSearchCriteria($limit, $offset, $from, $to, $customerId, $calledStation));
        } catch (\Throwable $exception) {
            return ApiResponder::error('cdr_query_failed', $exception->getMessage(), 500);
        }

        return ApiResponder::ok([
            'cdrs' => $result['items'],
        ], [
            'resource' => 'cdrs',
            'limit' => $limit,
            'offset' => $offset,
            'columns' => $result['columns'],
            'filters' => [
                'from' => $from,
                'to' => $to,
                'customer_id' => $customerId,
                'calledstation' => $calledStation,
            ],
        ]);
    }

    private function handlePayments(JsonRequest $request, int $limit, int $offset): JsonResponse
    {
        $from = trim($request->getString('from'));
        $to = trim($request->getString('to'));
        foreach (['from' => $from, 'to' => $to] as $field => $value) {
            if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value) !== 1) {
                return ApiResponder::error('invalid_' . $field, ucfirst($field) . ' must be YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.', 422, ['field' => $field]);
            }
        }

        $customerId = null;
        $customerIdValue = $request->getString('customer_id');
        if ($customerIdValue !== '') {
            if (preg_match('/^[1-9][0-9]*$/', $customerIdValue) !== 1) {
                return ApiResponder::error('invalid_customer_id', 'Customer id must be a positive integer.', 422, ['field' => 'customer_id']);
            }
            $customerId = (int)$customerIdValue;
        }

        try {
            $service = new PaymentLedgerService(new PaymentLedgerRepository(($this->pdoFactory)()));
            $result = $service->search(new PaymentSearchCriteria($limit, $offset, $from, $to, $customerId));
        } catch (\Throwable $exception) {
            return ApiResponder::error('payment_query_failed', $exception->getMessage(), 500);
        }

        return ApiResponder::ok([
            'payments' => $result['items'],
        ], [
            'resource' => 'payments',
            'limit' => $limit,
            'offset' => $offset,
            'columns' => $result['columns'],
            'filters' => [
                'from' => $from,
                'to' => $to,
                'customer_id' => $customerId,
            ],
        ]);
    }

    private function handleInvoices(JsonRequest $request, int $limit, int $offset): JsonResponse
    {
        $from = trim($request->getString('from'));
        $to = trim($request->getString('to'));
        foreach (['from' => $from, 'to' => $to] as $field => $value) {
            if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value) !== 1) {
                return ApiResponder::error('invalid_' . $field, ucfirst($field) . ' must be YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.', 422, ['field' => $field]);
            }
        }

        $customerId = $this->positiveIntFilter($request, 'customer_id');
        if ($customerId === false) {
            return ApiResponder::error('invalid_customer_id', 'Customer id must be a positive integer.', 422, ['field' => 'customer_id']);
        }

        $status = $this->binaryIntFilter($request, 'status');
        if ($status === false) {
            return ApiResponder::error('invalid_status', 'Status must be 0 or 1.', 422, ['field' => 'status']);
        }

        $paidStatus = $this->binaryIntFilter($request, 'paid_status');
        if ($paidStatus === false) {
            return ApiResponder::error('invalid_paid_status', 'Paid status must be 0 or 1.', 422, ['field' => 'paid_status']);
        }

        try {
            $service = new InvoiceService(new InvoiceRepository(($this->pdoFactory)()));
            $result = $service->search(new InvoiceSearchCriteria($limit, $offset, $from, $to, $customerId, $status, $paidStatus));
        } catch (\Throwable $exception) {
            return ApiResponder::error('invoice_query_failed', $exception->getMessage(), 500);
        }

        return ApiResponder::ok([
            'invoices' => $result['items'],
        ], [
            'resource' => 'invoices',
            'limit' => $limit,
            'offset' => $offset,
            'columns' => $result['columns'],
            'filters' => [
                'from' => $from,
                'to' => $to,
                'customer_id' => $customerId,
                'status' => $status,
                'paid_status' => $paidStatus,
            ],
        ]);
    }

    private function handleReceipts(JsonRequest $request, int $limit, int $offset): JsonResponse
    {
        $from = trim($request->getString('from'));
        $to = trim($request->getString('to'));
        foreach (['from' => $from, 'to' => $to] as $field => $value) {
            if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value) !== 1) {
                return ApiResponder::error('invalid_' . $field, ucfirst($field) . ' must be YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.', 422, ['field' => $field]);
            }
        }

        $customerId = $this->positiveIntFilter($request, 'customer_id');
        if ($customerId === false) {
            return ApiResponder::error('invalid_customer_id', 'Customer id must be a positive integer.', 422, ['field' => 'customer_id']);
        }

        $status = $this->binaryIntFilter($request, 'status');
        if ($status === false) {
            return ApiResponder::error('invalid_status', 'Status must be 0 or 1.', 422, ['field' => 'status']);
        }

        try {
            $service = new ReceiptService(new ReceiptRepository(($this->pdoFactory)()));
            $result = $service->search(new ReceiptSearchCriteria($limit, $offset, $from, $to, $customerId, $status));
        } catch (\Throwable $exception) {
            return ApiResponder::error('receipt_query_failed', $exception->getMessage(), 500);
        }

        return ApiResponder::ok([
            'receipts' => $result['items'],
        ], [
            'resource' => 'receipts',
            'limit' => $limit,
            'offset' => $offset,
            'columns' => $result['columns'],
            'filters' => [
                'from' => $from,
                'to' => $to,
                'customer_id' => $customerId,
                'status' => $status,
            ],
        ]);
    }

    private function positiveIntFilter(JsonRequest $request, string $key): int|null|false
    {
        $value = $request->getString($key);
        if ($value === '') {
            return null;
        }

        return preg_match('/^[1-9][0-9]*$/', $value) === 1 ? (int)$value : false;
    }

    private function binaryIntFilter(JsonRequest $request, string $key): int|null|false
    {
        $value = $request->getString($key);
        if ($value === '') {
            return null;
        }

        return in_array($value, ['0', '1'], true) ? (int)$value : false;
    }
}

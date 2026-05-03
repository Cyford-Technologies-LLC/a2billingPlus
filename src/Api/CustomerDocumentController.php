<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Invoice\InvoiceRepository;
use A2BillingPlus\Module\Invoice\InvoiceSearchCriteria;
use A2BillingPlus\Module\Invoice\InvoiceService;
use A2BillingPlus\Module\Invoice\ReceiptRepository;
use A2BillingPlus\Module\Invoice\ReceiptSearchCriteria;
use A2BillingPlus\Module\Invoice\ReceiptService;

final class CustomerDocumentController
{
    /**
     * @param callable(): \PDO $pdoFactory
     */
    public function __construct(
        private readonly ApiCustomerContextAuthenticator $authenticator,
        private $pdoFactory
    ) {
    }

    public function handle(string $resource, JsonRequest $request): JsonResponse
    {
        $context = $this->authenticator->authenticate($request);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        if (!in_array($resource, ['customer-invoices', 'customer-receipts'], true)) {
            return ApiResponder::error('resource_not_found', 'Unknown customer document resource.', 404);
        }

        if ($request->getMethod() !== 'GET') {
            return ApiResponder::error('method_not_allowed', 'Customer documents require GET.', 405);
        }

        $limit = $request->getInt('limit', 50);
        $offset = $request->getInt('offset', 0);
        if ($limit < 1 || $limit > 100) {
            return ApiResponder::error('invalid_limit', 'Limit must be between 1 and 100.', 422, ['field' => 'limit']);
        }
        if ($offset < 0) {
            return ApiResponder::error('invalid_offset', 'Offset must be zero or greater.', 422, ['field' => 'offset']);
        }

        $id = $request->getInt('id');
        $from = trim($request->getString('from'));
        $to = trim($request->getString('to'));
        foreach (['from' => $from, 'to' => $to] as $field => $value) {
            if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value) !== 1) {
                return ApiResponder::error('invalid_' . $field, ucfirst($field) . ' must be YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.', 422, ['field' => $field]);
            }
        }

        $pdo = ($this->pdoFactory)();
        if ($resource === 'customer-invoices') {
            return $this->handleInvoices($request, $pdo, $context->customerId, $id, $limit, $offset, $from, $to);
        }

        return $this->handleReceipts($request, $pdo, $context->customerId, $id, $limit, $offset, $from, $to);
    }

    private function handleInvoices(JsonRequest $request, \PDO $pdo, int $customerId, int $id, int $limit, int $offset, string $from, string $to): JsonResponse
    {
        $service = new InvoiceService(new InvoiceRepository($pdo));
        if ($id > 0) {
            $invoice = $service->customerDetail($id, $customerId);
            if ($invoice === null) {
                return ApiResponder::error('invoice_not_found', 'Invoice was not found.', 404, ['id' => $id]);
            }

            return ApiResponder::ok(['invoice' => $invoice], [
                'resource' => 'customer-invoices',
                'customer_id' => $customerId,
                'id' => $id,
            ]);
        }

        $paidStatus = $this->binaryFilter($request, 'paid_status');
        if ($paidStatus === false) {
            return ApiResponder::error('invalid_paid_status', 'Paid status must be 0 or 1.', 422, ['field' => 'paid_status']);
        }
        $result = $service->search(new InvoiceSearchCriteria($limit, $offset, $from, $to, $customerId, null, $paidStatus));

        return ApiResponder::ok(['invoices' => $result['items']], [
            'resource' => 'customer-invoices',
            'customer_id' => $customerId,
            'limit' => $limit,
            'offset' => $offset,
            'columns' => $result['columns'],
        ]);
    }

    private function handleReceipts(JsonRequest $request, \PDO $pdo, int $customerId, int $id, int $limit, int $offset, string $from, string $to): JsonResponse
    {
        $service = new ReceiptService(new ReceiptRepository($pdo));
        if ($id > 0) {
            $receipt = $service->customerDetail($id, $customerId);
            if ($receipt === null) {
                return ApiResponder::error('receipt_not_found', 'Receipt was not found.', 404, ['id' => $id]);
            }

            return ApiResponder::ok(['receipt' => $receipt], [
                'resource' => 'customer-receipts',
                'customer_id' => $customerId,
                'id' => $id,
            ]);
        }

        $status = $this->binaryFilter($request, 'status');
        if ($status === false) {
            return ApiResponder::error('invalid_status', 'Status must be 0 or 1.', 422, ['field' => 'status']);
        }
        $result = $service->search(new ReceiptSearchCriteria($limit, $offset, $from, $to, $customerId, $status));

        return ApiResponder::ok(['receipts' => $result['items']], [
            'resource' => 'customer-receipts',
            'customer_id' => $customerId,
            'limit' => $limit,
            'offset' => $offset,
            'columns' => $result['columns'],
        ]);
    }

    private function binaryFilter(JsonRequest $request, string $key): int|null|false
    {
        $value = $request->getString($key);
        if ($value === '') {
            return null;
        }

        return in_array($value, ['0', '1'], true) ? (int)$value : false;
    }
}

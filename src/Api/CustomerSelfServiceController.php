<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Customer\CustomerAccountRepository;
use A2BillingPlus\Module\Customer\CustomerAccountService;
use A2BillingPlus\Module\Security\AuditLogRepository;

final class CustomerSelfServiceController
{
    /**
     * @param callable(): \PDO $pdoFactory
     */
    public function __construct(
        private readonly ApiCustomerContextAuthenticator $authenticator,
        private $pdoFactory
    ) {
    }

    public function handle(JsonRequest $request): JsonResponse
    {
        $context = $this->authenticator->authenticate($request);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        if (!in_array($request->getMethod(), ['GET', 'PUT'], true)) {
            return ApiResponder::error('method_not_allowed', 'Customer profile supports GET and PUT.', 405);
        }

        $pdo = ($this->pdoFactory)();
        $service = new CustomerAccountService(new CustomerAccountRepository($pdo), new AuditLogRepository($pdo));

        if ($request->getMethod() === 'GET') {
            $customer = $service->detail($context->customerId);
            if ($customer === null) {
                return ApiResponder::error('customer_not_found', 'Customer was not found.', 404);
            }

            return ApiResponder::ok([
                'profile' => $customer,
                'balance' => [
                    'credit' => $customer['credit'] ?? null,
                    'currency' => $customer['currency'] ?? null,
                ],
                'status' => [
                    'status' => $customer['status'] ?? null,
                    'activated' => $customer['activated'] ?? null,
                ],
            ], [
                'resource' => 'customer-profile',
                'customer_id' => $context->customerId,
            ]);
        }

        $payload = array_intersect_key($request->getArray('customer'), array_flip([
            'firstname',
            'lastname',
            'email',
            'address',
            'city',
            'state',
            'country',
            'zipcode',
            'phone',
            'company_name',
            'company_website',
        ]));
        $result = $service->update($context->customerId, $payload, 'customer:' . $context->customerId);

        if (($result['body']['success'] ?? false) !== true) {
            return ApiResponder::error(
                (string)$result['body']['code'],
                (string)$result['body']['message'],
                $result['status'],
                ['field' => $result['body']['field']]
            );
        }

        return ApiResponder::ok([
            'profile' => $result['body']['customer'],
        ], [
            'resource' => 'customer-profile',
            'customer_id' => $context->customerId,
            'action' => 'update',
        ]);
    }
}

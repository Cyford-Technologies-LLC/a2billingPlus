<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;

final class ApiCustomerContextAuthenticator
{
    public function __construct(private readonly AppConfig $config)
    {
    }

    public function authenticate(JsonRequest $request): ApiCustomerContext|JsonResponse
    {
        $secret = $this->config->string('A2BP_CUSTOMER_API_SECRET');
        if ($secret === '') {
            return ApiResponder::error('customer_api_auth_not_configured', 'Customer API authentication is not configured.', 503);
        }

        $customerId = $request->getHeader('X-A2BP-Customer-Id');
        if (preg_match('/^[1-9][0-9]*$/', $customerId) !== 1) {
            return ApiResponder::error('missing_customer_context', 'X-A2BP-Customer-Id is required.', 401);
        }

        $signature = $request->getHeader('X-A2BP-Customer-Signature');
        $expected = hash_hmac('sha256', $customerId, $secret);
        if ($signature === '' || !hash_equals($expected, $signature)) {
            return ApiResponder::error('invalid_customer_signature', 'The supplied customer API signature is not valid.', 403);
        }

        return new ApiCustomerContext((int)$customerId);
    }
}

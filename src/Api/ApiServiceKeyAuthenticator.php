<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;

final class ApiServiceKeyAuthenticator
{
    public function __construct(private readonly AppConfig $config)
    {
    }

    public function authenticate(JsonRequest $request): ?JsonResponse
    {
        $expected = $this->config->string('A2BP_API_SERVICE_KEY');
        if ($expected === '') {
            return ApiResponder::error(
                'api_auth_not_configured',
                'API service key authentication is not configured.',
                503
            );
        }

        $authorization = $request->getHeader('Authorization');
        if (!str_starts_with($authorization, 'Bearer ')) {
            return ApiResponder::error(
                'missing_authorization',
                'Authorization header must use Bearer service key authentication.',
                401
            );
        }

        $actual = trim(substr($authorization, 7));
        if ($actual === '' || !hash_equals($expected, $actual)) {
            return ApiResponder::error(
                'invalid_service_key',
                'The supplied API service key is not valid.',
                403
            );
        }

        return null;
    }
}

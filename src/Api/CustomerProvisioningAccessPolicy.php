<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;

final class CustomerProvisioningAccessPolicy
{
    public function __construct(private readonly AppConfig $config)
    {
    }

    public function authorizeExternalId(JsonRequest $request, string $externalId): ?JsonResponse
    {
        $externalId = trim($externalId);
        if ($externalId === '') {
            return null;
        }

        $appId = trim($request->getHeader('X-A2BP-App-Id'));
        $token = trim($request->getHeader('X-A2BP-Provisioning-Token'));
        if ($appId === '' || $token === '') {
            return ApiResponder::error(
                'customer_provisioning_forbidden',
                'Customer external_id provisioning requires X-A2BP-App-Id and X-A2BP-Provisioning-Token.',
                403
            );
        }

        if (!hash_equals($externalId, $appId)) {
            return ApiResponder::error(
                'customer_provisioning_scope_mismatch',
                'The provisioning app id must match the requested customer external_id.',
                403,
                ['external_id' => $externalId, 'app_id' => $appId]
            );
        }

        $expected = $this->tokenForApp($appId);
        if ($expected === '' || !hash_equals($expected, $token)) {
            return ApiResponder::error(
                'invalid_customer_provisioning_token',
                'The supplied customer provisioning token is not valid for this app id.',
                403,
                ['app_id' => $appId]
            );
        }

        return null;
    }

    private function tokenForApp(string $appId): string
    {
        foreach ($this->appTokens() as $configuredAppId => $token) {
            if (hash_equals($configuredAppId, $appId)) {
                return $token;
            }
        }

        return '';
    }

    /**
     * @return array<string,string>
     */
    private function appTokens(): array
    {
        $tokens = [];
        foreach (explode(',', $this->config->string('A2BP_CUSTOMER_PROVISIONING_APPS')) as $entry) {
            $entry = trim($entry);
            if ($entry === '' || !str_contains($entry, ':')) {
                continue;
            }

            [$appId, $token] = explode(':', $entry, 2);
            $appId = trim($appId);
            $token = trim($token);
            if ($appId !== '' && $token !== '') {
                $tokens[$appId] = $token;
            }
        }

        return $tokens;
    }
}

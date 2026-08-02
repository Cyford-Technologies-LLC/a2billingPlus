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

        // ZeroAI-CRM is a single trusted platform provisioning arbitrary many
        // tenants (app_id = "crm_{orgId}") behind one hardcoded platform
        // connection — not a fixed set of pre-registered client apps. Rather
        // than requiring every new CRM tenant's app_id to be hand-registered
        // here before it can provision an account, the whole crm_ family
        // shares one platform-level provisioning token.
        $platformToken = $this->config->string('A2BP_CRM_PLATFORM_PROVISIONING_TOKEN');
        if ($platformToken !== '' && str_starts_with($appId, 'crm_')) {
            return $platformToken;
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

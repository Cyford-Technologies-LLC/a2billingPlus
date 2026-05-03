<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;

final class ApiAgentContextAuthenticator
{
    public function __construct(private readonly AppConfig $config)
    {
    }

    public function authenticate(JsonRequest $request): ApiAgentContext|JsonResponse
    {
        $secret = $this->config->string('A2BP_AGENT_API_SECRET');
        if ($secret === '') {
            return ApiResponder::error('agent_api_auth_not_configured', 'Agent API authentication is not configured.', 503);
        }

        $agentId = $request->getHeader('X-A2BP-Agent-Id');
        if (preg_match('/^[1-9][0-9]*$/', $agentId) !== 1) {
            return ApiResponder::error('missing_agent_context', 'X-A2BP-Agent-Id is required.', 401);
        }

        $signature = $request->getHeader('X-A2BP-Agent-Signature');
        $expected = hash_hmac('sha256', $agentId, $secret);
        if ($signature === '' || !hash_equals($expected, $signature)) {
            return ApiResponder::error('invalid_agent_signature', 'The supplied agent API signature is not valid.', 403);
        }

        return new ApiAgentContext((int)$agentId);
    }
}

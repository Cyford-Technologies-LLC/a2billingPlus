<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Agent\AgentRepository;
use A2BillingPlus\Module\Agent\AgentService;

final class AgentCustomerController
{
    /**
     * @param callable(): \PDO $pdoFactory
     */
    public function __construct(
        private readonly ApiAgentContextAuthenticator $authenticator,
        private $pdoFactory
    ) {
    }

    public function handle(JsonRequest $request): JsonResponse
    {
        $context = $this->authenticator->authenticate($request);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        if ($request->getMethod() !== 'GET') {
            return ApiResponder::error('method_not_allowed', 'Agent customers support GET.', 405);
        }

        $limit = $request->getInt('limit', 50);
        $offset = $request->getInt('offset', 0);
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            return ApiResponder::error('invalid_pagination', 'Limit must be 1-100 and offset must be zero or greater.', 422);
        }

        $result = (new AgentService(new AgentRepository(($this->pdoFactory)())))->customers($context->agentId, $limit, $offset);

        return ApiResponder::ok(['customers' => $result['items']], [
            'resource' => 'agent-customers',
            'agent_id' => $context->agentId,
            'limit' => $limit,
            'offset' => $offset,
            'columns' => $result['columns'],
        ]);
    }
}

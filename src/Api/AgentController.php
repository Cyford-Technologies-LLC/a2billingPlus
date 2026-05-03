<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Agent\AgentRepository;
use A2BillingPlus\Module\Agent\AgentService;

final class AgentController
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
            return ApiResponder::error('method_not_allowed', 'Agents support GET.', 405);
        }

        $limit = $request->getInt('limit', 50);
        $offset = $request->getInt('offset', 0);
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            return ApiResponder::error('invalid_pagination', 'Limit must be 1-100 and offset must be zero or greater.', 422);
        }

        $service = new AgentService(new AgentRepository(($this->pdoFactory)()));
        $id = $request->getInt('id');
        if ($id > 0) {
            $agent = $service->detail($id);
            if ($agent === null) {
                return ApiResponder::error('agent_not_found', 'Agent was not found.', 404, ['id' => $id]);
            }

            return ApiResponder::ok(['agent' => $agent], ['resource' => 'agents', 'id' => $id]);
        }

        $active = $request->getString('active');
        if ($active !== '' && !in_array($active, ['t', 'f'], true)) {
            return ApiResponder::error('invalid_active', 'active must be t or f.', 422, ['field' => 'active']);
        }
        $result = $service->list($limit, $offset, $active === '' ? null : $active);

        return ApiResponder::ok(['agents' => $result['items']], [
            'resource' => 'agents',
            'limit' => $limit,
            'offset' => $offset,
            'columns' => $result['columns'],
            'filters' => ['active' => $active === '' ? null : $active],
        ]);
    }
}

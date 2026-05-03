<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Security\AuditLogRepository;
use A2BillingPlus\Module\Telephony\TrunkRepository;
use A2BillingPlus\Module\Telephony\TrunkService;

final class TrunkController
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

        if (!in_array($request->getMethod(), ['GET', 'POST', 'PUT'], true)) {
            return ApiResponder::error('method_not_allowed', 'Trunks support GET, POST, and PUT.', 405);
        }

        $pdo = ($this->pdoFactory)();
        $service = new TrunkService(new TrunkRepository($pdo), new AuditLogRepository($pdo));

        if ($request->getMethod() === 'POST') {
            return $this->mutationResponse($service->create($request->getArray('trunk'), $request->getHeader('X-A2BP-Actor') ?: 'service-key'), 'create');
        }

        $id = $request->getInt('id');
        if ($request->getMethod() === 'PUT') {
            if ($id <= 0) {
                return ApiResponder::error('invalid_trunk_id', 'Trunk id is required.', 422, ['field' => 'id']);
            }

            return $this->mutationResponse($service->update($id, $request->getArray('trunk'), $request->getHeader('X-A2BP-Actor') ?: 'service-key'), 'update', $id);
        }

        if ($id > 0) {
            $trunk = $service->detail($id);
            if ($trunk === null) {
                return ApiResponder::error('trunk_not_found', 'Trunk was not found.', 404, ['id' => $id]);
            }

            return ApiResponder::ok(['trunk' => $trunk], ['resource' => 'trunks', 'id' => $id]);
        }

        $limit = $request->getInt('limit', 50);
        $offset = $request->getInt('offset', 0);
        if ($limit < 1 || $limit > 100) {
            return ApiResponder::error('invalid_limit', 'Limit must be between 1 and 100.', 422, ['field' => 'limit']);
        }
        if ($offset < 0) {
            return ApiResponder::error('invalid_offset', 'Offset must be zero or greater.', 422, ['field' => 'offset']);
        }
        $status = $this->binaryFilter($request, 'status');
        if ($status === false) {
            return ApiResponder::error('invalid_status', 'Status must be 0 or 1.', 422, ['field' => 'status']);
        }

        try {
            $result = $service->list($limit, $offset, $status);
        } catch (\Throwable $exception) {
            return ApiResponder::error('trunk_query_failed', $exception->getMessage(), 500);
        }

        return ApiResponder::ok(['trunks' => $result['items']], [
            'resource' => 'trunks',
            'limit' => $limit,
            'offset' => $offset,
            'columns' => $result['columns'],
            'filters' => ['status' => $status],
        ]);
    }

    /**
     * @param array{status:int,body:array<string,mixed>} $result
     */
    private function mutationResponse(array $result, string $action, ?int $id = null): JsonResponse
    {
        if (($result['body']['success'] ?? false) !== true) {
            return ApiResponder::error(
                (string)$result['body']['code'],
                (string)$result['body']['message'],
                $result['status'],
                ['field' => $result['body']['field']]
            );
        }

        $meta = ['resource' => 'trunks', 'action' => $action];
        if ($id !== null) {
            $meta['id'] = $id;
        }

        return ApiResponder::ok(['trunk' => $result['body']['trunk']], $meta, $result['status']);
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

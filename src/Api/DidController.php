<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Security\AuditLogRepository;
use A2BillingPlus\Module\Telephony\DidRepository;
use A2BillingPlus\Module\Telephony\DidService;

final class DidController
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

        if (!in_array($request->getMethod(), ['GET', 'POST', 'PUT', 'DELETE'], true)) {
            return ApiResponder::error('method_not_allowed', 'DIDs support GET, POST, PUT, and DELETE.', 405);
        }

        $pdo = ($this->pdoFactory)();
        $service = new DidService(new DidRepository($pdo), $pdo, new AuditLogRepository($pdo));
        $actor = $request->getHeader('X-A2BP-Actor') ?: 'service-key';

        if ($request->getMethod() === 'POST') {
            $payload = $request->getArray('assignment');
            return $this->mutationResponse($service->assign($this->idFromRequest($request, $payload), $this->customerId($payload), $actor), 'assign');
        }

        if ($request->getMethod() === 'PUT') {
            $payload = $request->getArray('routing');
            return $this->mutationResponse($service->updateRouting($this->idFromRequest($request, $payload), $payload, $actor), 'routing.update');
        }

        if ($request->getMethod() === 'DELETE') {
            return $this->mutationResponse($service->release($request->getInt('id'), $actor), 'release');
        }

        $id = $request->getInt('id');
        if ($id > 0) {
            $did = $service->detail($id);
            if ($did === null) {
                return ApiResponder::error('did_not_found', 'DID was not found.', 404, ['id' => $id]);
            }

            return ApiResponder::ok(['did' => $did], ['resource' => 'dids', 'id' => $id]);
        }

        $limit = $request->getInt('limit', 50);
        $offset = $request->getInt('offset', 0);
        if ($limit < 1 || $limit > 100) {
            return ApiResponder::error('invalid_limit', 'Limit must be between 1 and 100.', 422, ['field' => 'limit']);
        }
        if ($offset < 0) {
            return ApiResponder::error('invalid_offset', 'Offset must be zero or greater.', 422, ['field' => 'offset']);
        }

        $customerId = $this->optionalPositiveInt($request, 'customer_id');
        $reserved = $this->optionalBinary($request, 'reserved');
        $activated = $this->optionalBinary($request, 'activated');
        if ($customerId === false || $reserved === false || $activated === false) {
            return ApiResponder::error('invalid_filter', 'customer_id must be positive; reserved and activated must be 0 or 1.', 422);
        }

        $result = $service->list($limit, $offset, $customerId, $reserved, $activated);

        return ApiResponder::ok(['dids' => $result['items']], [
            'resource' => 'dids',
            'limit' => $limit,
            'offset' => $offset,
            'columns' => $result['columns'],
            'filters' => ['customer_id' => $customerId, 'reserved' => $reserved, 'activated' => $activated],
        ]);
    }

    /**
     * @param array<string,mixed> $result
     */
    private function mutationResponse(array $result, string $action): JsonResponse
    {
        if (($result['body']['success'] ?? false) !== true) {
            return ApiResponder::error(
                (string)$result['body']['code'],
                (string)$result['body']['message'],
                $result['status'],
                ['field' => $result['body']['field']]
            );
        }

        return ApiResponder::ok(['did' => $result['body']['did']], ['resource' => 'dids', 'action' => $action], $result['status']);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function idFromRequest(JsonRequest $request, array $payload): int
    {
        $value = $payload['id'] ?? $payload['did_id'] ?? $request->getInt('id');
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            return (int)$value;
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function customerId(array $payload): int
    {
        $value = $payload['customer_id'] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            return (int)$value;
        }

        return 0;
    }

    private function optionalPositiveInt(JsonRequest $request, string $key): int|null|false
    {
        $value = $request->getString($key);
        if ($value === '') {
            return null;
        }

        return preg_match('/^[1-9][0-9]*$/', $value) === 1 ? (int)$value : false;
    }

    private function optionalBinary(JsonRequest $request, string $key): int|null|false
    {
        $value = $request->getString($key);
        if ($value === '') {
            return null;
        }

        return in_array($value, ['0', '1'], true) ? (int)$value : false;
    }
}

<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Security\AuditLogRepository;
use A2BillingPlus\Module\Telephony\PjsipProvisioningService;

final class PjsipProvisioningController
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
            return ApiResponder::error('method_not_allowed', 'PJSIP provisioning supports GET, POST, and PUT.', 405);
        }

        $pdo = ($this->pdoFactory)();
        $service = new PjsipProvisioningService($pdo, new AuditLogRepository($pdo));
        $actor = $request->getHeader('X-A2BP-Actor') ?: 'service-key';

        if ($request->getMethod() === 'GET') {
            $limit = $request->getInt('limit', 50);
            $offset = $request->getInt('offset', 0);
            if ($limit < 1 || $limit > 100) {
                return ApiResponder::error('invalid_limit', 'Limit must be between 1 and 100.', 422, ['field' => 'limit']);
            }
            if ($offset < 0) {
                return ApiResponder::error('invalid_offset', 'Offset must be zero or greater.', 422, ['field' => 'offset']);
            }

            $endpointId = trim($request->getString('endpoint_id'));
            if ($endpointId !== '') {
                $endpoint = $service->endpointDetail($endpointId);
                if ($endpoint === null) {
                    return ApiResponder::error('pjsip_endpoint_not_found', 'PJSIP endpoint was not found.', 404, ['endpoint_id' => $endpointId]);
                }

                return ApiResponder::ok(['endpoint' => $endpoint], ['resource' => 'pjsip-provisioning', 'endpoint_id' => $endpointId]);
            }

            $type = trim($request->getString('endpoint_type'));
            $ownerId = $this->optionalPositiveInt($request, 'owner_id');
            if ($ownerId === false) {
                return ApiResponder::error('invalid_owner_id', 'owner_id must be a positive integer.', 422, ['field' => 'owner_id']);
            }

            $result = $service->listEndpoints($limit, $offset, $type, $ownerId);

            return ApiResponder::ok(['endpoints' => $result['items']], [
                'resource' => 'pjsip-provisioning',
                'limit' => $limit,
                'offset' => $offset,
                'columns' => $result['columns'],
                'filters' => ['endpoint_type' => $type, 'owner_id' => $ownerId],
            ]);
        }

        if ($request->getMethod() === 'PUT') {
            $endpointId = trim($request->getString('endpoint_id'));
            if ($endpointId === '') {
                return ApiResponder::error('invalid_endpoint_id', 'endpoint_id is required.', 422, ['field' => 'endpoint_id']);
            }

            $result = $service->updateEndpoint($endpointId, $request->getArray('provisioning'), $actor);
            if (($result['body']['success'] ?? false) !== true) {
                return ApiResponder::error(
                    (string)$result['body']['code'],
                    (string)$result['body']['message'],
                    $result['status'],
                    ['field' => $result['body']['field']]
                );
            }

            return ApiResponder::ok(['endpoint' => $result['body']['endpoint']], ['resource' => 'pjsip-provisioning', 'action' => 'update'], $result['status']);
        }

        $action = $request->getString('action');
        $payload = $request->getArray('provisioning');

        $result = match ($action) {
            'customer_device' => $service->provisionCustomerDevice($payload, $actor),
            'trunk' => $service->provisionTrunk($payload, $actor),
            default => ['status' => 422, 'body' => ['success' => false, 'code' => 'invalid_action', 'message' => 'action must be customer_device or trunk.', 'field' => 'action']],
        };

        if (($result['body']['success'] ?? false) !== true) {
            return ApiResponder::error(
                (string)$result['body']['code'],
                (string)$result['body']['message'],
                $result['status'],
                ['field' => $result['body']['field']]
            );
        }

        return ApiResponder::ok(['endpoint' => $result['body']['endpoint']], ['resource' => 'pjsip-provisioning', 'action' => $action], $result['status']);
    }

    private function optionalPositiveInt(JsonRequest $request, string $key): int|null|false
    {
        $value = $request->getString($key);
        if ($value === '') {
            return null;
        }

        return preg_match('/^[1-9][0-9]*$/', $value) === 1 ? (int)$value : false;
    }
}

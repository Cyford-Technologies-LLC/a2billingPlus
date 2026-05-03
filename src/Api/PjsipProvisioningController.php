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

        if ($request->getMethod() !== 'POST') {
            return ApiResponder::error('method_not_allowed', 'PJSIP provisioning supports POST.', 405);
        }

        $pdo = ($this->pdoFactory)();
        $service = new PjsipProvisioningService($pdo, new AuditLogRepository($pdo));
        $actor = $request->getHeader('X-A2BP-Actor') ?: 'service-key';
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
}

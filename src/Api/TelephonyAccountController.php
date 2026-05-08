<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Module\Security\AuditLogRepository;
use A2BillingPlus\Module\Telephony\PjsipProvisioningService;
use A2BillingPlus\Module\Telephony\TelephonyAccountRepository;
use A2BillingPlus\Module\Telephony\TelephonyAccountService;

final class TelephonyAccountController
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
            return ApiResponder::error('method_not_allowed', 'Telephony accounts support GET, POST, and PUT.', 405);
        }

        $pdo = ($this->pdoFactory)();
        $config = AppConfig::fromEnvironment();
        $service = new TelephonyAccountService(
            new TelephonyAccountRepository($pdo),
            new AuditLogRepository($pdo),
            new PjsipProvisioningService($pdo, new AuditLogRepository($pdo)),
            $config->string('A2BP_ASTERISK_CHANNEL_DRIVER', 'pjsip'),
            in_array(strtolower($config->string('A2BP_ASTERISK_REALTIME', 'yes')), ['1', 'yes', 'true', 'on'], true)
        );
        $technology = strtolower($request->getString('technology', 'sip'));
        $actor = $request->getHeader('X-A2BP-Actor') ?: 'service-key';

        try {
            if ($request->getMethod() === 'POST') {
                return $this->mutationResponse($service->create($technology, $request->getArray('account'), $actor), 'create');
            }

            $id = $request->getInt('id');
            if ($request->getMethod() === 'PUT') {
                if ($id <= 0) {
                    return ApiResponder::error('invalid_account_id', 'Account id is required.', 422, ['field' => 'id']);
                }

                return $this->mutationResponse($service->update($technology, $id, $request->getArray('account'), $actor), 'update');
            }

            if ($id > 0) {
                $account = $service->detail($technology, $id);
                if ($account === null) {
                    return ApiResponder::error('telephony_account_not_found', 'Telephony account was not found.', 404, ['id' => $id]);
                }

                return ApiResponder::ok(['account' => $account], ['resource' => 'telephony-accounts', 'technology' => $technology, 'id' => $id]);
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
            if ($customerId === false) {
                return ApiResponder::error('invalid_customer_id', 'customer_id must be a positive integer.', 422, ['field' => 'customer_id']);
            }

            $result = $service->list($technology, $limit, $offset, $customerId);

            return ApiResponder::ok(['accounts' => $result['items']], [
                'resource' => 'telephony-accounts',
                'technology' => $technology,
                'limit' => $limit,
                'offset' => $offset,
                'columns' => $result['columns'],
                'filters' => ['customer_id' => $customerId],
            ]);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponder::error('invalid_technology', $exception->getMessage(), 422, ['field' => 'technology']);
        }
    }

    /**
     * @param array{status:int,body:array<string,mixed>} $result
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

        return ApiResponder::ok(['account' => $result['body']['account']], ['resource' => 'telephony-accounts', 'action' => $action], $result['status']);
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

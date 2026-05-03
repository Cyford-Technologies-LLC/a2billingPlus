<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Admin\AdminSettingsRepository;
use A2BillingPlus\Module\Admin\AdminSettingsService;
use A2BillingPlus\Module\Security\AuditLogRepository;

final class AdminSettingsController
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

        if (!in_array($request->getMethod(), ['GET', 'PUT'], true)) {
            return ApiResponder::error('method_not_allowed', 'Admin settings support GET and PUT.', 405);
        }

        $pdo = ($this->pdoFactory)();
        $service = new AdminSettingsService(new AdminSettingsRepository($pdo), new AuditLogRepository($pdo));
        $key = trim($request->getString('key'));

        if ($request->getMethod() === 'PUT') {
            if ($key === '') {
                return ApiResponder::error('missing_key', 'key is required.', 422, ['field' => 'key']);
            }
            $result = $service->update($key, $request->getString('value'), $request->getHeader('X-A2BP-Actor') ?: 'service-key');
            if (($result['body']['success'] ?? false) !== true) {
                return ApiResponder::error(
                    (string)$result['body']['code'],
                    (string)$result['body']['message'],
                    $result['status'],
                    ['field' => $result['body']['field']]
                );
            }

            return ApiResponder::ok(['setting' => $result['body']['setting']], ['resource' => 'admin-settings', 'action' => 'update']);
        }

        if ($key !== '') {
            $setting = $service->detail($key);
            if ($setting === null) {
                return ApiResponder::error('setting_not_found', 'Setting was not found or is not exposed by the safe settings API.', 404, ['key' => $key]);
            }

            return ApiResponder::ok(['setting' => $setting], ['resource' => 'admin-settings', 'key' => $key]);
        }

        return ApiResponder::ok(['settings' => $service->list()], ['resource' => 'admin-settings']);
    }
}

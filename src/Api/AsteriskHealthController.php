<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Telephony\AsteriskConfigCheckService;

final class AsteriskHealthController
{
    public function __construct(
        private readonly ApiServiceKeyAuthenticator $authenticator,
        private readonly AppConfig $config,
        private readonly AsteriskConfigCheckService $service = new AsteriskConfigCheckService()
    ) {
    }

    public function handle(JsonRequest $request): JsonResponse
    {
        $authError = $this->authenticator->authenticate($request);
        if ($authError !== null) {
            return $authError;
        }

        if (!in_array($request->getMethod(), ['GET', 'POST'], true)) {
            return ApiResponder::error('method_not_allowed', 'Asterisk health supports GET and POST.', 405);
        }

        $settings = $request->getMethod() === 'POST'
            ? $this->settingsFromPayload($request->getArray('settings'))
            : $this->settingsFromRequest($request);
        $result = $this->service->check($settings);

        return ApiResponder::ok(['asterisk' => $result], ['resource' => 'asterisk-health']);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,string>
     */
    private function settingsFromPayload(array $payload): array
    {
        $settings = [];
        foreach (['version', 'ami_user', 'ami_password', 'ari_user', 'ari_password', 'channel_driver', 'realtime_enabled'] as $key) {
            $settings[$key] = is_scalar($payload[$key] ?? null) ? (string)$payload[$key] : $this->defaultSetting($key);
        }

        return $settings;
    }

    /**
     * @return array<string,string>
     */
    private function settingsFromRequest(JsonRequest $request): array
    {
        $settings = [];
        foreach (['version', 'ami_user', 'ami_password', 'ari_user', 'ari_password', 'channel_driver', 'realtime_enabled'] as $key) {
            $settings[$key] = $request->getString($key, $this->defaultSetting($key));
        }

        return $settings;
    }

    private function defaultSetting(string $key): string
    {
        return match ($key) {
            'version' => $this->config->string('A2BP_ASTERISK_VERSION', ''),
            'ami_user' => $this->config->string('A2BP_AMI_USER', ''),
            'ami_password' => $this->config->string('A2BP_AMI_PASSWORD', ''),
            'ari_user' => $this->config->string('A2BP_ARI_USER', ''),
            'ari_password' => $this->config->string('A2BP_ARI_PASSWORD', ''),
            'channel_driver' => $this->config->string('A2BP_ASTERISK_CHANNEL_DRIVER', 'pjsip'),
            'realtime_enabled' => $this->config->string('A2BP_ASTERISK_REALTIME_ENABLED', 'yes'),
            default => '',
        };
    }
}

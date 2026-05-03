<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\ProviderRegistry;
use A2BillingPlus\Module\Provider\RateImportRequest;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPConnector;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPRegistrationClient;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPRegistrationRequest;

final class ProviderApiController
{
    /**
     * @param null|callable(string): VectaVoIPRegistrationClient $registrationClientFactory
     */
    public function __construct(
        private readonly ProviderRegistry $registry,
        private $registrationClientFactory = null
    ) {
    }

    public function handle(JsonRequest $request): JsonResponse
    {
        if ($request->getMethod() === 'GET') {
            return $this->listProviders();
        }

        if ($request->getMethod() !== 'POST') {
            return new JsonResponse(['error' => 'Method not allowed.'], 405);
        }

        return match ($request->getString('action')) {
            'provider_status' => $this->providerStatus($request),
            'register_install' => $this->registerInstall($request),
            'test_connection' => $this->testConnection($request),
            'preview_rates' => $this->previewRates($request),
            default => new JsonResponse(['error' => 'Unknown provider action.'], 400),
        };
    }

    private function listProviders(): JsonResponse
    {
        $providers = [];
        foreach ($this->registry->all() as $connector) {
            $providers[] = [
                'code' => $connector->getProviderCode(),
                'name' => $connector->getDisplayName(),
                'support_email' => $connector->getSupportEmail(),
                'api_base_url' => $connector->getApiBaseUrl(),
            ];
        }

        return new JsonResponse(['providers' => $providers]);
    }

    private function testConnection(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }

        $result = $connector->testConnection($this->credentialsFromRequest($request));

        return new JsonResponse([
            'success' => $result->isSuccessful(),
            'message' => $result->getMessage(),
            'details' => $result->getDetails(),
        ], $result->isSuccessful() ? 200 : 422);
    }

    private function previewRates(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }

        $importer = $connector->getRateImporter($this->credentialsFromRequest($request));
        $preview = $importer->preview(new RateImportRequest(
            $request->getString('currency', 'USD'),
            $request->getString('rate_deck', 'default'),
            $this->stringMap($request->getArray('filters')),
            true
        ));

        return new JsonResponse([
            'total_rows' => $preview->getTotalRows(),
            'sample_rows' => $preview->getSampleRows(),
            'message' => $preview->getMessage(),
        ]);
    }

    private function providerStatus(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }

        return new JsonResponse([
            'provider' => $connector->getProviderCode(),
            'registered' => $this->envString('VECTAVOIP_API_KEY') !== '',
            'installation_id' => $this->envString('VECTAVOIP_INSTALLATION_ID'),
            'api_base_url' => $this->envString('VECTAVOIP_API_BASE_URL', $connector->getApiBaseUrl()),
            'support_email' => $connector->getSupportEmail(),
        ]);
    }

    private function registerInstall(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }

        if ($connector->getProviderCode() !== 'vectavoip') {
            return new JsonResponse(['error' => 'Provider registration is not implemented for this provider.'], 422);
        }

        $installKey = $request->getString('install_key', $this->generateInstallKey());
        $apiBaseUrl = $request->getString('base_url', $connector->getApiBaseUrl());
        $client = $this->registrationClient($apiBaseUrl);

        $result = $client->register(new VectaVoIPRegistrationRequest(
            $installKey,
            $request->getString('company_name'),
            $request->getString('company_domain'),
            $request->getString('contact_name'),
            $request->getString('contact_email'),
            $request->getString('contact_phone'),
            $request->getString('details'),
            $request->getString('app_name', 'A2BillingPlus'),
            $request->getString('app_version', '0.1.0-alpha')
        ));

        if (!$result->isSuccessful()) {
            return new JsonResponse([
                'success' => false,
                'message' => $result->getMessage(),
            ], 422);
        }

        return new JsonResponse([
            'success' => true,
            'message' => $result->getMessage(),
            'provider' => $connector->getProviderCode(),
            'install_key' => $installKey,
            'installation_id' => $result->getInstallationId(),
            'api_key' => $result->getApiKey(),
            'api_secret' => $result->getApiSecret(),
            'metadata' => $result->getMetadata(),
        ], 201);
    }

    /**
     * @return \A2BillingPlus\Module\Provider\ProviderConnectorInterface|JsonResponse
     */
    private function getConnector(JsonRequest $request): object
    {
        $providerCode = $request->getString('provider', 'vectavoip');
        $connector = $this->registry->get($providerCode);

        if (!$connector) {
            return new JsonResponse(['error' => 'Provider not found.'], 404);
        }

        return $connector;
    }

    private function credentialsFromRequest(JsonRequest $request): ProviderCredentials
    {
        return new ProviderCredentials(
            $request->getString('base_url', $this->envString('VECTAVOIP_API_BASE_URL', VectaVoIPConnector::API_BASE_URL)),
            $request->getString('api_key', $this->envString('VECTAVOIP_API_KEY')),
            $request->getString('api_secret', $this->envString('VECTAVOIP_API_SECRET')),
            $this->stringMap($request->getArray('metadata'))
        );
    }

    private function envString(string $key, string $default = ''): string
    {
        $value = getenv($key);
        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function registrationClient(string $apiBaseUrl): VectaVoIPRegistrationClient
    {
        if (is_callable($this->registrationClientFactory)) {
            return ($this->registrationClientFactory)($apiBaseUrl);
        }

        return new VectaVoIPRegistrationClient($apiBaseUrl);
    }

    private function generateInstallKey(): string
    {
        try {
            return 'a2bp_' . bin2hex(random_bytes(32));
        } catch (\Throwable $exception) {
            return 'a2bp_' . hash('sha256', uniqid('', true) . microtime(true));
        }
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    private function stringMap(array $values): array
    {
        $mapped = [];
        foreach ($values as $key => $value) {
            if (is_scalar($value)) {
                $mapped[(string)$key] = (string)$value;
            }
        }

        return $mapped;
    }
}

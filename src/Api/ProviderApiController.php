<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\ProviderRegistry;
use A2BillingPlus\Module\Provider\RateImportRequest;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPConnector;

final class ProviderApiController
{
    public function __construct(private readonly ProviderRegistry $registry)
    {
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

<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Provider\Didww\DidwwConnector;
use A2BillingPlus\Module\Provider\ProviderAccessPolicy;
use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\ProviderImportLogRepository;
use A2BillingPlus\Module\Provider\ProviderRegistry;
use A2BillingPlus\Module\Provider\RateImportRequest;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPConnector;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPRegistrationClient;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPRegistrationRequest;
use A2BillingPlus\Module\Rate\RatecardImportService;

final class ProviderApiController
{
    /**
     * @param null|callable(string): VectaVoIPRegistrationClient $registrationClientFactory
     * @param null|callable(): \PDO $pdoFactory
     */
    public function __construct(
        private readonly ProviderRegistry $registry,
        private $registrationClientFactory = null,
        private $pdoFactory = null,
        ?ProviderAccessPolicy $accessPolicy = null,
        private readonly string $actor = ''
    ) {
        $this->accessPolicy = $accessPolicy ?? new ProviderAccessPolicy(AppConfig::fromEnvironment());
    }

    private readonly ProviderAccessPolicy $accessPolicy;

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
            'import_preview_rates' => $this->importPreviewRates($request),
            default => new JsonResponse(['error' => 'Unknown provider action.'], 400),
        };
    }

    private function listProviders(): JsonResponse
    {
        $providers = [];
        foreach ($this->registry->all() as $connector) {
            if (!$this->accessPolicy->isAllowed($connector->getProviderCode(), $this->actor)) {
                continue;
            }
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
            'registered' => $this->envString($this->providerEnvKey($connector->getProviderCode(), 'API_KEY')) !== '',
            'installation_id' => $this->envString($this->providerEnvKey($connector->getProviderCode(), 'INSTALLATION_ID')),
            'api_base_url' => $this->envString($this->providerEnvKey($connector->getProviderCode(), 'API_BASE_URL'), $connector->getApiBaseUrl()),
            'support_email' => $connector->getSupportEmail(),
            'locked' => $this->accessPolicy->isLocked($connector->getProviderCode()),
        ]);
    }

    private function importPreviewRates(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }

        $targetRatecardId = (int)$request->getString('target_ratecard_id');
        $dryRun = $request->getString('dry_run', '1') !== '0';
        $updateExisting = $request->getString('update_existing', '0') === '1';
        $rateDeck = $request->getString('rate_deck', 'default');

        $importer = $connector->getRateImporter($this->credentialsFromRequest($request));
        $preview = $importer->preview(new RateImportRequest(
            $request->getString('currency', 'USD'),
            $rateDeck,
            $this->stringMap($request->getArray('filters')),
            true
        ));

        if ($preview->getTotalRows() === 0 || $preview->getSampleRows() === []) {
            return new JsonResponse([
                'success' => false,
                'message' => $preview->getMessage() !== '' ? $preview->getMessage() : 'No provider rates were available to import.',
                'imported_rows' => 0,
                'skipped_rows' => 0,
                'dry_run' => $dryRun,
            ], 422);
        }

        try {
            $pdo = $this->pdo();
            $service = new RatecardImportService($pdo);
            $summary = $service->importRows(
                $preview->getSampleRows(),
                $targetRatecardId,
                $connector->getDisplayName() . ':' . $rateDeck,
                $dryRun,
                $updateExisting
            );
            $this->recordImportLog($pdo, $connector->getProviderCode(), $rateDeck, $targetRatecardId, $dryRun, $summary);
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Rate import failed: ' . $exception->getMessage(),
                'imported_rows' => 0,
                'skipped_rows' => 0,
                'dry_run' => $dryRun,
                'update_existing' => $updateExisting,
            ], 500);
        }

        return new JsonResponse([
            'success' => $summary->isSuccessful(),
            'message' => $summary->getMessage(),
            'imported_rows' => $summary->getImportedRows(),
            'skipped_rows' => $summary->getSkippedRows(),
            'dry_run' => $dryRun,
            'update_existing' => $updateExisting,
        ], $summary->isSuccessful() ? 200 : 422);
    }

    private function recordImportLog(
        \PDO $pdo,
        string $provider,
        string $rateDeck,
        int $targetRatecardId,
        bool $dryRun,
        \A2BillingPlus\Module\Rate\RatecardImportSummary $summary
    ): void {
        $repository = new ProviderImportLogRepository($pdo);
        $repository->record(
            $provider,
            $rateDeck,
            $targetRatecardId,
            $dryRun,
            $summary->isSuccessful(),
            $summary->getImportedRows(),
            $summary->getSkippedRows(),
            $summary->getMessage()
        );
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
        if (!$this->accessPolicy->isAllowed($providerCode, $this->actor)) {
            return new JsonResponse(['error' => $this->accessPolicy->denialMessage($providerCode)], 403);
        }
        $connector = $this->registry->get($providerCode);

        if (!$connector) {
            return new JsonResponse(['error' => 'Provider not found.'], 404);
        }

        return $connector;
    }

    private function credentialsFromRequest(JsonRequest $request): ProviderCredentials
    {
        $providerCode = $request->getString('provider', 'vectavoip');
        return new ProviderCredentials(
            $request->getString('base_url', $this->envString($this->providerEnvKey($providerCode, 'API_BASE_URL'), $this->defaultBaseUrl($providerCode))),
            $request->getString('api_key', $this->envString($this->providerEnvKey($providerCode, 'API_KEY'))),
            $request->getString('api_secret', $this->envString($this->providerEnvKey($providerCode, 'API_SECRET'))),
            array_merge(
                $this->stringMap($request->getArray('metadata')),
                ['api_version' => $request->getString('api_version', $this->envString($this->providerEnvKey($providerCode, 'API_VERSION')))]
            )
        );
    }

    private function defaultBaseUrl(string $providerCode): string
    {
        return match ($providerCode) {
            'vectavoip' => VectaVoIPConnector::API_BASE_URL,
            'didww' => DidwwConnector::API_BASE_URL,
            default => '',
        };
    }

    private function providerEnvKey(string $providerCode, string $suffix): string
    {
        return strtoupper($providerCode) . '_' . $suffix;
    }

    private function envString(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $file = getenv($key . '_FILE');
        if (is_string($file) && $file !== '' && is_readable($file)) {
            $contents = file_get_contents($file);
            if (is_string($contents)) {
                return trim($contents);
            }
        }

        $fileValues = $this->envFileValues();
        $filePath = $fileValues[$key . '_FILE'] ?? '';
        if ($filePath !== '' && is_readable($filePath)) {
            $contents = file_get_contents($filePath);
            if (is_string($contents)) {
                return trim($contents);
            }
        }
        if (($fileValues[$key] ?? '') !== '') {
            return $fileValues[$key];
        }

        return $default;
    }

    private function registrationClient(string $apiBaseUrl): VectaVoIPRegistrationClient
    {
        if (is_callable($this->registrationClientFactory)) {
            return ($this->registrationClientFactory)($apiBaseUrl);
        }

        return new VectaVoIPRegistrationClient($apiBaseUrl);
    }

    private function pdo(): \PDO
    {
        if (is_callable($this->pdoFactory)) {
            return ($this->pdoFactory)();
        }

        $dsn = $this->envString('A2BP_DB_DSN');
        if ($dsn === '') {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $this->envString('A2BP_DB_HOST', 'db'),
                $this->envString('A2BP_DB_NAME', 'mya2billing')
            );
        }

        $pdo = new \PDO($dsn, $this->envString('A2BP_DB_USER', 'a2billinguser'), $this->envString('A2BP_DB_PASSWORD', 'a2billing'), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        return $pdo;
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

    /**
     * @return array<string,string>
     */
    private function envFileValues(): array
    {
        static $values = null;
        if (is_array($values)) {
            return $values;
        }

        $values = [];
        $envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        if (!is_readable($envPath)) {
            return $values;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return $values;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }

            if (
                strlen($value) >= 2
                && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
        }

        return $values;
    }
}

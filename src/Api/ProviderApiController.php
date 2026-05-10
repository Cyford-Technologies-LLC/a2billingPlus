<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Config\RuntimeSettingRepository;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Provider\ProviderAccessPolicy;
use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\ProviderImportLogRepository;
use A2BillingPlus\Module\Provider\ProviderRegistry;
use A2BillingPlus\Module\Provider\RateImportRequest;
use A2BillingPlus\Module\Provider\Twilio\TwilioApiClient;
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
        ?ProviderAccessPolicy $accessPolicy = null
    ) {
        $this->accessPolicy = $accessPolicy ?? new ProviderAccessPolicy(AppConfig::fromEnvironment());
    }

    private readonly ProviderAccessPolicy $accessPolicy;

    public function handle(JsonRequest $request): JsonResponse
    {
        if ($request->getMethod() === 'GET') {
            return $this->listProviders($request);
        }

        if ($request->getMethod() !== 'POST') {
            return new JsonResponse(['error' => 'Method not allowed.'], 405);
        }

        return match ($request->getString('action')) {
            'provider_status' => $this->providerStatus($request),
            'register_install' => $this->registerInstall($request),
            'test_connection' => $this->testConnection($request),
            'twilio_inventory_snapshot' => $this->twilioInventorySnapshot($request),
            'twilio_search_available_numbers' => $this->twilioSearchAvailableNumbers($request),
            'twilio_purchase_number' => $this->twilioPurchaseNumber($request),
            'twilio_create_trunk' => $this->twilioCreateTrunk($request),
            'twilio_register_existing_trunk' => $this->twilioRegisterExistingTrunk($request),
            'twilio_sync_inventory' => $this->twilioSyncInventory($request),
            'preview_rates' => $this->previewRates($request),
            'import_preview_rates' => $this->importPreviewRates($request),
            default => new JsonResponse(['error' => 'Unknown provider action.'], 400),
        };
    }

    private function listProviders(JsonRequest $request): JsonResponse
    {
        $providers = [];
        foreach ($this->registry->all() as $connector) {
            if (!$this->accessPolicy->isAllowed($connector->getProviderCode(), '', $this->requestUnlockToken($request))) {
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
            'registered' => $this->envString('VECTAVOIP_API_KEY') !== '',
            'installation_id' => $this->envString('VECTAVOIP_INSTALLATION_ID'),
            'api_base_url' => $this->envString('VECTAVOIP_API_BASE_URL', $connector->getApiBaseUrl()),
            'support_email' => $connector->getSupportEmail(),
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
                'VectaVoIP:' . $rateDeck,
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
            $request->getString('registration_username'),
            $request->getString('registration_password'),
            $request->getString('company_name', $request->getString('registration_username')),
            $request->getString('company_domain'),
            $request->getString('contact_email'),
            $request->getString('request_ip'),
            $request->getString('app_name', 'A2BillingPlus'),
            $request->getString('app_version', '0.1.0-alpha'),
            $request->getString('contact_name', $request->getString('registration_username'))
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

        if (!$this->accessPolicy->isAllowed($providerCode, '', $this->requestUnlockToken($request))) {
            return new JsonResponse(['error' => $this->accessPolicy->denialMessage($providerCode)], 403);
        }

        return $connector;
    }

    private function twilioInventorySnapshot(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }
        if ($connector->getProviderCode() !== 'twilio') {
            return new JsonResponse(['error' => 'Twilio inventory is only available for the Twilio provider.'], 422);
        }

        try {
            $credentials = $this->credentialsFromRequest($request);
            $client = new TwilioApiClient();
            $payload = [
                'incoming_numbers' => $client->listIncomingPhoneNumbers($credentials, [
                    'PageSize' => $this->boundedPageSize($request->getString('page_size', '25')),
                ]),
                'trunks' => $client->listTrunks($credentials, [
                    'PageSize' => $this->boundedPageSize($request->getString('trunks_page_size', '25')),
                ]),
                'byoc_trunks' => $client->listByocTrunks($credentials, [
                    'PageSize' => $this->boundedPageSize($request->getString('trunks_page_size', '25')),
                ]),
            ];
            $trunkSid = $request->getString('byoc_trunk_sid', $credentials->getMetadataValue('byoc_trunk_sid'));
            if ($trunkSid !== '' && str_starts_with($trunkSid, 'TK')) {
                $payload['trunk_numbers'] = $client->listTrunkPhoneNumbers($credentials, $trunkSid, [
                    'PageSize' => $this->boundedPageSize($request->getString('trunk_numbers_page_size', '25')),
                ]);
            }
        } catch (\Throwable $exception) {
            return new JsonResponse(['success' => false, 'message' => 'Twilio inventory failed: ' . $exception->getMessage()], 422);
        }

        return new JsonResponse($payload + ['success' => true, 'message' => 'Twilio inventory loaded.']);
    }

    private function twilioSearchAvailableNumbers(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }
        if ($connector->getProviderCode() !== 'twilio') {
            return new JsonResponse(['error' => 'Twilio number search is only available for the Twilio provider.'], 422);
        }

        $filters = [
            'PageSize' => $this->boundedPageSize($request->getString('page_size', '20')),
        ];
        foreach ([
            'contains' => 'Contains',
            'area_code' => 'AreaCode',
            'sms_enabled' => 'SmsEnabled',
            'voice_enabled' => 'VoiceEnabled',
        ] as $input => $twilioKey) {
            $value = $request->getString($input);
            if ($value !== '') {
                $filters[$twilioKey] = $value;
            }
        }

        try {
            $numbers = (new TwilioApiClient())->searchAvailableLocalNumbers(
                $this->credentialsFromRequest($request),
                $request->getString('country_code', 'US'),
                $filters
            );
        } catch (\Throwable $exception) {
            return new JsonResponse(['success' => false, 'message' => 'Twilio number search failed: ' . $exception->getMessage()], 422);
        }

        return new JsonResponse(['success' => true, 'message' => 'Twilio number search completed.', 'available_numbers' => $numbers]);
    }

    private function twilioPurchaseNumber(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }
        if ($connector->getProviderCode() !== 'twilio') {
            return new JsonResponse(['error' => 'Twilio number purchase is only available for the Twilio provider.'], 422);
        }

        $phoneNumber = $request->getString('phone_number');
        if ($phoneNumber === '') {
            return new JsonResponse(['success' => false, 'message' => 'Phone number is required.'], 422);
        }

        $payload = ['PhoneNumber' => $phoneNumber];
        foreach ([
            'voice_url' => 'VoiceUrl',
            'sms_url' => 'SmsUrl',
        ] as $input => $twilioKey) {
            $value = $request->getString($input);
            if ($value !== '') {
                $payload[$twilioKey] = $value;
            }
        }

        try {
            $credentials = $this->credentialsFromRequest($request);
            $client = new TwilioApiClient();
            $purchase = $client->purchaseIncomingPhoneNumber($credentials, $payload);
            $attach = null;
            $trunkSid = $request->getString('byoc_trunk_sid', $credentials->getMetadataValue('byoc_trunk_sid'));
            $phoneNumberSid = is_scalar($purchase['sid'] ?? null) ? (string)$purchase['sid'] : '';
            if ($trunkSid !== '' && str_starts_with($trunkSid, 'TK') && $phoneNumberSid !== '') {
                $attach = $client->attachPhoneNumberToTrunk($credentials, $trunkSid, $phoneNumberSid);
            }
        } catch (\Throwable $exception) {
            return new JsonResponse(['success' => false, 'message' => 'Twilio number purchase failed: ' . $exception->getMessage()], 422);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Twilio number purchased.',
            'purchase' => $purchase,
            'trunk_attachment' => $attach,
        ]);
    }

    private function twilioCreateTrunk(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }
        if ($connector->getProviderCode() !== 'twilio') {
            return new JsonResponse(['error' => 'Twilio trunk setup is only available for the Twilio provider.'], 422);
        }

        $payload = [];
        foreach ([
            'friendly_name' => 'FriendlyName',
            'domain_name' => 'DomainName',
            'cnam_lookup_enabled' => 'CnamLookupEnabled',
        ] as $input => $twilioKey) {
            $value = $request->getString($input);
            if ($value !== '') {
                $payload[$twilioKey] = $value;
            }
        }
        if ($payload === []) {
            $payload['FriendlyName'] = 'A2BillingPlus Trunk';
        }

        try {
            $trunk = (new TwilioApiClient())->createTrunk($this->credentialsFromRequest($request), $payload);
        } catch (\Throwable $exception) {
            return new JsonResponse(['success' => false, 'message' => 'Twilio trunk creation failed: ' . $exception->getMessage()], 422);
        }

        return new JsonResponse(['success' => true, 'message' => 'Twilio trunk created.', 'trunk' => $trunk]);
    }

    private function twilioRegisterExistingTrunk(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }
        if ($connector->getProviderCode() !== 'twilio') {
            return new JsonResponse(['error' => 'Twilio trunk setup is only available for the Twilio provider.'], 422);
        }

        $credentials = $this->credentialsFromRequest($request);
        $trunkSid = $request->getString('byoc_trunk_sid', $credentials->getMetadataValue('byoc_trunk_sid'));
        if ($trunkSid === '') {
            return new JsonResponse(['success' => false, 'message' => 'Twilio trunk SID is required.'], 422);
        }

        try {
            $client = new TwilioApiClient();
            $trunk = (str_starts_with($trunkSid, 'BY') || str_starts_with($trunkSid, 'SIDBY'))
                ? $client->getByocTrunk($credentials, $trunkSid)
                : $client->getTrunk($credentials, $trunkSid);
        } catch (\Throwable $exception) {
            return new JsonResponse(['success' => false, 'message' => 'Twilio trunk lookup failed: ' . $exception->getMessage()], 422);
        }

        return new JsonResponse(['success' => true, 'message' => 'Twilio trunk verified.', 'trunk' => $trunk]);
    }

    private function twilioSyncInventory(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }
        if ($connector->getProviderCode() !== 'twilio') {
            return new JsonResponse(['error' => 'Twilio inventory sync is only available for the Twilio provider.'], 422);
        }

        try {
            $credentials = $this->credentialsFromRequest($request);
            $client = new TwilioApiClient();
            $payload = [
                'incoming_numbers' => $client->listIncomingPhoneNumbers($credentials, [
                    'PageSize' => $this->boundedPageSize($request->getString('page_size', '100')),
                ]),
                'trunks' => $client->listTrunks($credentials, [
                    'PageSize' => $this->boundedPageSize($request->getString('trunks_page_size', '100')),
                ]),
                'byoc_trunks' => $client->listByocTrunks($credentials, [
                    'PageSize' => $this->boundedPageSize($request->getString('trunks_page_size', '100')),
                ]),
            ];
            $trunkSid = $request->getString('byoc_trunk_sid', $credentials->getMetadataValue('byoc_trunk_sid'));
            if ($trunkSid !== '' && str_starts_with($trunkSid, 'TK')) {
                $payload['trunk_numbers'] = $client->listTrunkPhoneNumbers($credentials, $trunkSid, [
                    'PageSize' => $this->boundedPageSize($request->getString('trunk_numbers_page_size', '100')),
                ]);
            }
        } catch (\Throwable $exception) {
            return new JsonResponse(['success' => false, 'message' => 'Twilio inventory sync failed: ' . $exception->getMessage()], 422);
        }

        return new JsonResponse($payload + ['success' => true, 'message' => 'Twilio inventory sync completed.']);
    }

    private function requestUnlockToken(JsonRequest $request): string
    {
        $token = $request->getString('provider_unlock_token');
        if ($token !== '') {
            return $token;
        }

        return $request->getHeader('X-A2BP-Provider-Unlock-Token');
    }

    private function credentialsFromRequest(JsonRequest $request): ProviderCredentials
    {
        $provider = $request->getString('provider', 'vectavoip');
        $metadata = $this->stringMap($request->getArray('metadata'));
        foreach (['account_sid', 'byoc_trunk_sid'] as $metadataKey) {
            $value = $request->getString($metadataKey);
            if ($value !== '') {
                $metadata[$metadataKey] = $value;
            }
        }

        $baseUrlDefault = $provider === 'twilio'
            ? TwilioApiClient::API_BASE_URL
            : $this->envString('VECTAVOIP_API_BASE_URL', VectaVoIPConnector::API_BASE_URL);
        $apiKeyDefault = $provider === 'twilio'
            ? $this->envString('TWILIO_API_KEY', $this->envString('TWILIO_ACCOUNT_SID'))
            : $this->envString('VECTAVOIP_API_KEY');
        $apiSecretDefault = $provider === 'twilio'
            ? $this->envString('TWILIO_API_SECRET', $this->envString('TWILIO_AUTH_TOKEN'))
            : $this->envString('VECTAVOIP_API_SECRET');

        if ($provider === 'twilio' && ($metadata['account_sid'] ?? '') === '') {
            $metadata['account_sid'] = $this->envString('TWILIO_ACCOUNT_SID');
        }
        if ($provider === 'twilio' && ($metadata['byoc_trunk_sid'] ?? '') === '') {
            $metadata['byoc_trunk_sid'] = $this->envString('TWILIO_BYOC_TRUNK_SID');
        }

        $apiKey = $request->getString('api_key', $apiKeyDefault);
        if ($provider === 'twilio' && $apiKey === '') {
            $apiKey = $metadata['account_sid'] ?? '';
        }

        return new ProviderCredentials(
            $request->getString('base_url', $baseUrlDefault),
            $apiKey,
            $request->getString('api_secret', $apiSecretDefault),
            $metadata
        );
    }

    private function boundedPageSize(string $value): string
    {
        if (!ctype_digit($value)) {
            return '25';
        }

        return (string)max(1, min(1000, (int)$value));
    }

    private function envString(string $key, string $default = ''): string
    {
        if (!str_starts_with($key, 'A2BP_DB_') && is_callable($this->pdoFactory)) {
            try {
                $values = (new RuntimeSettingRepository(($this->pdoFactory)()))->all();
                if (($values[$key] ?? '') !== '') {
                    return $values[$key];
                }
            } catch (\Throwable) {
            }
        }

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
}

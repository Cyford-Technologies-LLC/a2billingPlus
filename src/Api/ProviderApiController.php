<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Provider\Didww\DidwwApiClient;
use A2BillingPlus\Module\Provider\Didww\DidwwConnector;
use A2BillingPlus\Module\Provider\Didww\DidwwProvisioningService;
use A2BillingPlus\Module\Provider\ProviderAccessPolicy;
use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\ProviderImportLogRepository;
use A2BillingPlus\Module\Provider\ProviderRegistry;
use A2BillingPlus\Module\Provider\RateImportRequest;
use A2BillingPlus\Module\Provider\Twilio\TwilioApiClient;
use A2BillingPlus\Module\Provider\Twilio\TwilioConnector;
use A2BillingPlus\Module\Provider\Twilio\TwilioProvisioningService;
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
        private readonly string $actor = '',
        private $didwwClientFactory = null,
        private $twilioClientFactory = null
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
            'didww_inventory_snapshot' => $this->didwwInventorySnapshot($request),
            'didww_search_available_dids' => $this->didwwSearchAvailableDids($request),
            'didww_order_did' => $this->didwwOrderDid($request),
            'didww_create_inbound_trunk' => $this->didwwCreateInboundTrunk($request),
            'didww_sync_inventory' => $this->didwwSyncInventory($request),
            'didww_sync_completed_orders' => $this->didwwSyncCompletedOrders($request),
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
            'registered' => $this->providerConfigured($connector->getProviderCode()),
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
            $request->getString('registration_username'),
            $request->getString('registration_password'),
            $request->getString('company_name', $request->getString('registration_username')),
            $request->getString('company_domain'),
            $request->getString('contact_email'),
            $request->getString('request_ip'),
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

    private function didwwInventorySnapshot(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }

        if ($connector->getProviderCode() !== 'didww') {
            return new JsonResponse(['error' => 'DIDWW inventory actions require the DIDWW provider.'], 422);
        }

        try {
            $client = $this->didwwClient();
            $credentials = $this->credentialsFromRequest($request);
            $dids = $client->listDids($credentials, ['page[size]' => (string) max(1, min(100, $request->getInt('page_size', 25)))]);
            $trunks = $client->listInboundTrunks($credentials, ['page[size]' => (string) max(1, min(100, $request->getInt('page_size', 25)))]);
            $orders = $client->listOrders($credentials, ['page[size]' => (string) max(1, min(50, $request->getInt('orders_page_size', 10)))]);
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return new JsonResponse([
            'success' => true,
            'dids' => $this->normalizeDidwwDids($dids),
            'inbound_trunks' => $this->normalizeDidwwInboundTrunks($trunks),
            'orders' => $this->normalizeDidwwOrders($orders),
        ]);
    }

    private function didwwSearchAvailableDids(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }

        if ($connector->getProviderCode() !== 'didww') {
            return new JsonResponse(['error' => 'DIDWW number search requires the DIDWW provider.'], 422);
        }

        $filters = [];
        foreach ([
            'filter[number_contains]',
            'filter[country.id]',
            'filter[region.id]',
            'filter[city.id]',
            'filter[did_group.features]',
            'filter[did_group.needs_registration]',
        ] as $key) {
            $value = $request->getString($key);
            if ($value !== '') {
                $filters[$key] = $value;
            }
        }
        $filters['page[size]'] = (string) max(1, min(100, $request->getInt('page_size', 20)));

        try {
            $client = $this->didwwClient();
            $results = $client->searchAvailableDids($this->credentialsFromRequest($request), $filters);
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return new JsonResponse([
            'success' => true,
            'available_dids' => $this->normalizeDidwwAvailableDids($results),
            'message' => 'DIDWW available DID search completed.',
        ]);
    }

    private function didwwOrderDid(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }

        if ($connector->getProviderCode() !== 'didww') {
            return new JsonResponse(['error' => 'DIDWW ordering requires the DIDWW provider.'], 422);
        }

        $availableDidId = $request->getString('available_did_id');
        $skuId = $request->getString('sku_id');
        if ($availableDidId === '' || $skuId === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Both available DID ID and SKU ID are required.',
            ], 422);
        }

        try {
            $client = $this->didwwClient();
            $result = $client->createOrder(
                $this->credentialsFromRequest($request),
                $availableDidId,
                $skuId,
                $request->getString('callback_url'),
                $request->getString('allow_back_ordering') === '1'
            );
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'DIDWW order submitted.',
            'order' => $this->normalizeDidwwOrder($result['data'] ?? []),
        ], 201);
    }

    private function didwwCreateInboundTrunk(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }
        if ($connector->getProviderCode() !== 'didww') {
            return new JsonResponse(['error' => 'DIDWW trunk provisioning requires the DIDWW provider.'], 422);
        }

        $name = $request->getString('trunk_name');
        $host = $request->getString('trunk_host');
        $username = $request->getString('trunk_username');
        if ($name === '' || $host === '' || $username === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Trunk name, host, and username are required.',
            ], 422);
        }

        $attributes = [
            'name' => $name,
            'priority' => max(0, min(65535, $request->getInt('trunk_priority', 10))),
            'weight' => max(0, min(65535, $request->getInt('trunk_weight', 10))),
            'capacity_limit' => max(1, $request->getInt('trunk_capacity_limit', 10)),
            'cli_format' => $request->getString('trunk_cli_format', 'e164'),
            'cli_prefix' => $request->getString('trunk_cli_prefix'),
            'configuration' => [
                'type' => 'sip_configurations',
                'attributes' => [
                    'username' => $username,
                    'host' => $host,
                    'codec_ids' => [9, 7],
                    'rx_dtmf_format_id' => 1,
                    'tx_dtmf_format_id' => 1,
                    'resolve_ruri' => $request->getString('trunk_resolve_ruri', '1') === '1',
                    'auth_enabled' => $request->getString('trunk_auth_enabled') === '1',
                    'enabled_sip_registration' => $request->getString('trunk_enabled_sip_registration') === '1',
                    'use_did_in_ruri' => $request->getString('trunk_use_did_in_ruri', '1') === '1',
                ],
            ],
        ];
        if ($attributes['configuration']['attributes']['auth_enabled']) {
            $attributes['configuration']['attributes']['auth_user'] = $request->getString('trunk_auth_user', $username);
            $attributes['configuration']['attributes']['auth_password'] = $request->getString('trunk_auth_password');
        }

        try {
            $client = $this->didwwClient();
            $credentials = $this->credentialsFromRequest($request);
            $result = $client->createInboundTrunk($credentials, $attributes);
            $local = (new DidwwProvisioningService($this->pdo()))->materializeInboundTrunk(
                is_array($result['data'] ?? null) ? $result['data'] : [],
                $attributes
            );
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'DIDWW inbound trunk created.',
            'remote_trunk' => $this->normalizeDidwwInboundTrunks(['data' => [$result['data'] ?? []]])[0] ?? [],
            'local_trunk' => $local,
        ], 201);
    }

    private function didwwSyncInventory(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }
        if ($connector->getProviderCode() !== 'didww') {
            return new JsonResponse(['error' => 'DIDWW inventory sync requires the DIDWW provider.'], 422);
        }

        try {
            $client = $this->didwwClient();
            $credentials = $this->credentialsFromRequest($request);
            $dids = $client->listDids($credentials, ['page[size]' => (string) max(1, min(100, $request->getInt('page_size', 100)))]);
            $normalized = $this->normalizeDidwwDids($dids);
            $result = (new DidwwProvisioningService($this->pdo()))->syncOwnedDids($normalized);
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return new JsonResponse($result);
    }

    private function didwwSyncCompletedOrders(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }
        if ($connector->getProviderCode() !== 'didww') {
            return new JsonResponse(['error' => 'DIDWW completed-order sync requires the DIDWW provider.'], 422);
        }

        try {
            $client = $this->didwwClient();
            $credentials = $this->credentialsFromRequest($request);
            $provisioning = new DidwwProvisioningService($this->pdo());
            $ordersPayload = $client->listOrders($credentials, [
                'page[size]' => (string) max(1, min(100, $request->getInt('orders_page_size', 25))),
            ]);
            $orders = $this->normalizeDidwwOrders($ordersPayload);
            $checked = 0;
            $completed = 0;
            $upserted = 0;
            $syncedOrders = [];

            foreach ($orders as $order) {
                $orderId = $this->stringValue($order, 'id');
                if ($orderId === '') {
                    continue;
                }
                $checked++;

                $detail = $client->getOrder($credentials, $orderId);
                $detailOrder = $this->normalizeDidwwOrder(is_array($detail['data'] ?? null) ? $detail['data'] : []);
                if ($this->stringValue($detailOrder, 'status') !== 'completed') {
                    continue;
                }

                $completed++;
                $dids = $client->listDids($credentials, [
                    'filter[order.id]' => $orderId,
                    'page[size]' => (string) max(1, min(100, $request->getInt('page_size', 100))),
                ]);
                $normalizedDids = $this->normalizeDidwwDids($dids);
                $sync = $provisioning->syncOwnedDids($normalizedDids);
                $upserted += (int) ($sync['upserted'] ?? 0);
                $syncedOrders[] = [
                    'id' => $orderId,
                    'reference' => $this->stringValue($detailOrder, 'reference'),
                    'status' => $this->stringValue($detailOrder, 'status'),
                    'upserted' => (string) ($sync['upserted'] ?? 0),
                ];
            }
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return new JsonResponse([
            'success' => true,
            'checked_orders' => $checked,
            'completed_orders' => $completed,
            'upserted' => $upserted,
            'synced_orders' => $syncedOrders,
            'message' => $completed > 0
                ? 'Completed DIDWW orders were synchronized into local inventory.'
                : 'No completed DIDWW orders were ready to synchronize.',
        ]);
    }

    private function twilioInventorySnapshot(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }
        if ($connector->getProviderCode() !== 'twilio') {
            return new JsonResponse(['error' => 'Twilio inventory actions require the Twilio provider.'], 422);
        }

        try {
            $client = $this->twilioClient();
            $credentials = $this->credentialsFromRequest($request);
            $numbers = $client->listIncomingPhoneNumbers($credentials, [
                'PageSize' => (string) max(1, min(100, $request->getInt('page_size', 25))),
            ]);
            $trunks = $client->listTrunks($credentials, [
                'PageSize' => (string) max(1, min(100, $request->getInt('trunks_page_size', 25))),
            ]);
            $byocTrunks = [];
            $preferredTrunkSid = $this->preferredTwilioTrunkSid($request);
            if ($preferredTrunkSid !== '') {
                $byocTrunks[] = $this->normalizeTwilioTrunk(
                    $this->findTwilioTrunkBySid($client, $credentials, $preferredTrunkSid)
                );
            }
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return new JsonResponse([
            'success' => true,
            'numbers' => $this->normalizeTwilioIncomingNumbers($numbers),
            'trunks' => $this->normalizeTwilioTrunks($trunks),
            'byoc_trunks' => $byocTrunks,
        ]);
    }

    private function twilioSearchAvailableNumbers(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }
        if ($connector->getProviderCode() !== 'twilio') {
            return new JsonResponse(['error' => 'Twilio number search requires the Twilio provider.'], 422);
        }

        $filters = [
            'PageSize' => (string) max(1, min(100, $request->getInt('page_size', 20))),
        ];
        foreach ([
            'Contains' => 'contains',
            'AreaCode' => 'area_code',
            'SmsEnabled' => 'sms_enabled',
            'VoiceEnabled' => 'voice_enabled',
        ] as $twilioKey => $requestKey) {
            $value = $request->getString($requestKey);
            if ($value !== '') {
                $filters[$twilioKey] = $value;
            }
        }

        try {
            $client = $this->twilioClient();
            $results = $client->searchAvailableLocalNumbers(
                $this->credentialsFromRequest($request),
                $request->getString('country_code', 'US'),
                $filters
            );
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return new JsonResponse([
            'success' => true,
            'available_numbers' => $this->normalizeTwilioAvailableNumbers($results),
            'message' => 'Twilio available number search completed.',
        ]);
    }

    private function twilioPurchaseNumber(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }
        if ($connector->getProviderCode() !== 'twilio') {
            return new JsonResponse(['error' => 'Twilio number purchase requires the Twilio provider.'], 422);
        }

        $phoneNumber = $request->getString('phone_number');
        if ($phoneNumber === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Twilio phone number is required.',
            ], 422);
        }

        $payload = ['PhoneNumber' => $phoneNumber];
        foreach ([
            'VoiceUrl' => 'voice_url',
            'SmsUrl' => 'sms_url',
        ] as $twilioKey => $requestKey) {
            $value = $request->getString($requestKey);
            if ($value !== '') {
                $payload[$twilioKey] = $value;
            }
        }

        try {
            $client = $this->twilioClient();
            $credentials = $this->credentialsFromRequest($request);
            $result = $client->purchaseIncomingPhoneNumber($credentials, $payload);
            $preferredTrunkSid = $this->preferredTwilioTrunkSid($request);
            $attachedTrunk = [];
            if ($preferredTrunkSid !== '' && $this->stringValue($result, 'sid') !== '') {
                $client->attachPhoneNumberToTrunk($credentials, $preferredTrunkSid, $this->stringValue($result, 'sid'));
                $attachedTrunk = $this->normalizeTwilioTrunk(
                    $this->findTwilioTrunkBySid($client, $credentials, $preferredTrunkSid)
                );
                $result['trunk_sid'] = $this->stringValue($attachedTrunk, 'sid');
                $result['trunk_name'] = $this->stringValue($attachedTrunk, 'friendly_name');
            }
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return new JsonResponse([
            'success' => true,
            'message' => $attachedTrunk === []
                ? 'Twilio phone number purchased.'
                : 'Twilio phone number purchased and attached to the preferred BYOC trunk.',
            'number' => $this->normalizeTwilioIncomingNumber($result),
            'attached_trunk' => $attachedTrunk,
        ], 201);
    }

    private function twilioCreateTrunk(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }
        if ($connector->getProviderCode() !== 'twilio') {
            return new JsonResponse(['error' => 'Twilio trunk provisioning requires the Twilio provider.'], 422);
        }

        $friendlyName = $request->getString('friendly_name');
        if ($friendlyName === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Twilio friendly name is required.',
            ], 422);
        }

        $payload = ['FriendlyName' => $friendlyName];
        if ($request->getString('domain_name') !== '') {
            $payload['DomainName'] = $request->getString('domain_name');
        }
        if ($request->getString('cnam_lookup_enabled') !== '') {
            $payload['CnamLookupEnabled'] = $request->getString('cnam_lookup_enabled');
        }

        try {
            $client = $this->twilioClient();
            $result = $client->createTrunk($this->credentialsFromRequest($request), $payload);
            $local = (new TwilioProvisioningService($this->pdo()))->materializeTrunk($result);
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Twilio SIP trunk created.',
            'remote_trunk' => $this->normalizeTwilioTrunk($result),
            'local_trunk' => $local,
        ], 201);
    }

    private function twilioRegisterExistingTrunk(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }
        if ($connector->getProviderCode() !== 'twilio') {
            return new JsonResponse(['error' => 'Twilio trunk registration requires the Twilio provider.'], 422);
        }

        $trunkSid = $this->preferredTwilioTrunkSid($request);
        if ($trunkSid === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Twilio BYOC trunk SID is required.',
            ], 422);
        }

        try {
            $client = $this->twilioClient();
            $credentials = $this->credentialsFromRequest($request);
            $result = $this->findTwilioTrunkBySid($client, $credentials, $trunkSid);
            $local = (new TwilioProvisioningService($this->pdo()))->materializeTrunk($result);
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Twilio BYOC trunk linked locally.',
            'remote_trunk' => $this->normalizeTwilioTrunk($result),
            'local_trunk' => $local,
        ]);
    }

    private function twilioSyncInventory(JsonRequest $request): JsonResponse
    {
        $connector = $this->getConnector($request);
        if ($connector instanceof JsonResponse) {
            return $connector;
        }
        if ($connector->getProviderCode() !== 'twilio') {
            return new JsonResponse(['error' => 'Twilio inventory sync requires the Twilio provider.'], 422);
        }

        try {
            $client = $this->twilioClient();
            $credentials = $this->credentialsFromRequest($request);
            $numbersPayload = $client->listIncomingPhoneNumbers($credentials, [
                'PageSize' => (string) max(1, min(100, $request->getInt('page_size', 100))),
            ]);
            $numbers = $this->normalizeTwilioIncomingNumbers($numbersPayload);

            $trunksPayload = $client->listTrunks($credentials, [
                'PageSize' => (string) max(1, min(100, $request->getInt('trunks_page_size', 100))),
            ]);
            $trunks = [];
            foreach ($this->normalizeTwilioTrunks($trunksPayload) as $trunk) {
                $sid = $this->stringValue($trunk, 'sid');
                if ($sid !== '') {
                    $trunks[$sid] = $trunk;
                }
            }

            foreach ($trunks as $sid => $trunk) {
                $phonesPayload = $client->listTrunkPhoneNumbers($credentials, $sid, [
                    'PageSize' => (string) max(1, min(100, $request->getInt('trunk_numbers_page_size', 100))),
                ]);
                foreach ($this->normalizeTwilioTrunkPhoneNumbers($phonesPayload) as $attached) {
                    $numberSid = $this->stringValue($attached, 'phone_number_sid');
                    foreach ($numbers as &$number) {
                        if ($this->stringValue($number, 'sid') === $numberSid) {
                            $number['trunk_sid'] = $sid;
                            $number['trunk_name'] = $this->stringValue($trunk, 'friendly_name');
                            break;
                        }
                    }
                    unset($number);
                }
            }

            $preferredTrunkSid = $this->preferredTwilioTrunkSid($request);
            $preferredTrunk = [];
            if ($preferredTrunkSid !== '') {
                $preferredTrunk = $trunks[$preferredTrunkSid]
                    ?? $this->normalizeTwilioTrunk($this->findTwilioTrunkBySid($client, $credentials, $preferredTrunkSid));
            }

            $provisioning = new TwilioProvisioningService($this->pdo());
            if ($preferredTrunk !== []) {
                $provisioning->materializeTrunk($preferredTrunk);
            }
            $result = $provisioning->syncOwnedNumbers($numbers);
            if ($preferredTrunk !== []) {
                $result['preferred_trunk'] = $preferredTrunk;
            }
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return new JsonResponse($result);
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
                [
                    'api_version' => $request->getString('api_version', $this->envString($this->providerEnvKey($providerCode, 'API_VERSION'))),
                    'account_sid' => $request->getString('account_sid', $this->envString($this->providerEnvKey($providerCode, 'ACCOUNT_SID'))),
                    'byoc_trunk_sid' => $request->getString('byoc_trunk_sid', $this->envString($this->providerEnvKey($providerCode, 'BYOC_TRUNK_SID'))),
                ]
            )
        );
    }

    private function defaultBaseUrl(string $providerCode): string
    {
        return match ($providerCode) {
            'vectavoip' => VectaVoIPConnector::API_BASE_URL,
            'didww' => DidwwConnector::API_BASE_URL,
            'twilio' => TwilioConnector::API_BASE_URL,
            default => '',
        };
    }

    private function providerEnvKey(string $providerCode, string $suffix): string
    {
        return strtoupper($providerCode) . '_' . $suffix;
    }

    private function providerConfigured(string $providerCode): bool
    {
        return match ($providerCode) {
            'twilio' => $this->envString($this->providerEnvKey($providerCode, 'ACCOUNT_SID')) !== ''
                && $this->envString($this->providerEnvKey($providerCode, 'API_SECRET')) !== '',
            default => $this->envString($this->providerEnvKey($providerCode, 'API_KEY')) !== '',
        };
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

    private function didwwClient(): DidwwApiClient
    {
        if (is_callable($this->didwwClientFactory)) {
            return ($this->didwwClientFactory)();
        }

        return new DidwwApiClient();
    }

    private function twilioClient(): TwilioApiClient
    {
        if (is_callable($this->twilioClientFactory)) {
            return ($this->twilioClientFactory)();
        }

        return new TwilioApiClient();
    }

    private function preferredTwilioTrunkSid(JsonRequest $request): string
    {
        return trim($request->getString('byoc_trunk_sid', $this->envString($this->providerEnvKey('twilio', 'BYOC_TRUNK_SID'))));
    }

    /**
     * @return array<string, mixed>
     */
    private function findTwilioTrunkBySid(
        TwilioApiClient $client,
        ProviderCredentials $credentials,
        string $trunkSid
    ): array {
        $trunkSid = trim($trunkSid);
        if ($trunkSid === '') {
            throw new \RuntimeException('Twilio trunk SID is required.');
        }

        if (preg_match('/^BY[0-9A-Fa-f]{32}$/', $trunkSid) === 1) {
            try {
                $trunk = $client->getByocTrunk($credentials, $trunkSid);
                if ($this->stringValue($trunk, 'sid') !== '') {
                    return $trunk;
                }
            } catch (\Throwable) {
                // Fall back to the BYOC collection API if the direct fetch is unavailable.
            }

            $payload = $client->listByocTrunks($credentials, ['PageSize' => '100']);
            foreach ($payload['byoc_trunks'] ?? [] as $item) {
                if (is_array($item) && $this->stringValue($item, 'sid') === $trunkSid) {
                    return $item;
                }
            }

            throw new \RuntimeException('Twilio BYOC trunk SID was not found on this account.');
        }

        try {
            $trunk = $client->getTrunk($credentials, $trunkSid);
            if ($this->stringValue($trunk, 'sid') !== '') {
                return $trunk;
            }
        } catch (\Throwable) {
            // Fall back to the collection API because some accounts return trunk
            // details only through the list route.
        }

        $payload = $client->listTrunks($credentials, ['PageSize' => '100']);
        foreach ($payload['trunks'] ?? [] as $item) {
            if (is_array($item) && $this->stringValue($item, 'sid') === $trunkSid) {
                return $item;
            }
        }

        throw new \RuntimeException('Twilio trunk SID was not found on this account.');
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
     * @param array<string, mixed> $payload
     * @return list<array<string, string>>
     */
    private function normalizeDidwwDids(array $payload): array
    {
        $included = $this->jsonApiIncludedMap($payload['included'] ?? []);
        $rows = [];
        foreach ($payload['data'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
            $relationships = is_array($item['relationships'] ?? null) ? $item['relationships'] : [];
            $didGroup = $this->jsonApiRelationshipResource($relationships, 'did_group', $included);
            $voiceInTrunk = $this->jsonApiRelationshipResource($relationships, 'voice_in_trunk', $included);
            $order = $this->jsonApiRelationshipResource($relationships, 'order', $included);

            $rows[] = [
                'id' => $this->stringValue($item, 'id'),
                'number' => $this->stringValue($attributes, 'number'),
                'description' => $this->stringValue($attributes, 'description'),
                'blocked' => $this->boolString($attributes, 'blocked'),
                'awaiting_registration' => $this->boolString($attributes, 'awaiting_registration'),
                'terminated' => $this->boolString($attributes, 'terminated'),
                'voice_in_trunk_reference' => $this->stringValue($voiceInTrunk, 'id'),
                'voice_in_trunk' => $this->stringValue($voiceInTrunk['attributes'] ?? [], 'name', $this->stringValue($voiceInTrunk, 'id')),
                'did_group' => $this->stringValue($didGroup['attributes'] ?? [], 'name', $this->stringValue($didGroup, 'id')),
                'order_id' => $this->stringValue($order, 'id'),
                'order_reference' => $this->stringValue($order['attributes'] ?? [], 'reference', $this->stringValue($order, 'id')),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, string>>
     */
    private function normalizeDidwwInboundTrunks(array $payload): array
    {
        $rows = [];
        foreach ($payload['data'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
            $configuration = is_array($attributes['configuration'] ?? null) ? $attributes['configuration'] : [];
            $configurationAttributes = is_array($configuration['attributes'] ?? null) ? $configuration['attributes'] : [];
            $rows[] = [
                'id' => $this->stringValue($item, 'id'),
                'name' => $this->stringValue($attributes, 'name'),
                'priority' => $this->stringValue($attributes, 'priority'),
                'weight' => $this->stringValue($attributes, 'weight'),
                'capacity_limit' => $this->stringValue($attributes, 'capacity_limit'),
                'configuration_type' => $this->stringValue($configuration, 'type'),
                'host' => $this->stringValue($configurationAttributes, 'host'),
                'username' => $this->stringValue($configurationAttributes, 'username'),
                'dst' => $this->stringValue($configurationAttributes, 'dst'),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, string>>
     */
    private function normalizeDidwwOrders(array $payload): array
    {
        $rows = [];
        foreach ($payload['data'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rows[] = $this->normalizeDidwwOrder($item);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, string>
     */
    private function normalizeDidwwOrder(array $item): array
    {
        $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
        $items = is_array($attributes['items'] ?? null) ? $attributes['items'] : [];

        return [
            'id' => $this->stringValue($item, 'id'),
            'reference' => $this->stringValue($attributes, 'reference'),
            'status' => $this->stringValue($attributes, 'status'),
            'created_at' => $this->stringValue($attributes, 'created_at'),
            'items_count' => (string) count($items),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, mixed>>
     */
    private function normalizeDidwwAvailableDids(array $payload): array
    {
        $included = $this->jsonApiIncludedMap($payload['included'] ?? []);
        $rows = [];
        foreach ($payload['data'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
            $relationships = is_array($item['relationships'] ?? null) ? $item['relationships'] : [];
            $didGroup = $this->jsonApiRelationshipResource($relationships, 'did_group', $included);
            $skuOptions = [];
            $skuRelationship = $didGroup['relationships']['stock_keeping_units']['data'] ?? [];
            if (is_array($skuRelationship)) {
                foreach ($skuRelationship as $skuReference) {
                    if (!is_array($skuReference)) {
                        continue;
                    }
                    $sku = $included[$this->jsonApiKey($this->stringValue($skuReference, 'type'), $this->stringValue($skuReference, 'id'))] ?? null;
                    if (!is_array($sku)) {
                        continue;
                    }
                    $skuAttributes = is_array($sku['attributes'] ?? null) ? $sku['attributes'] : [];
                    $price = $this->stringValue($skuAttributes, 'monthly_price');
                    $setup = $this->stringValue($skuAttributes, 'setup_price');
                    $currency = $this->stringValue($skuAttributes, 'currency');
                    $label = $this->stringValue($skuAttributes, 'name', $this->stringValue($sku, 'id'));
                    $summary = trim($price . ($currency !== '' ? ' ' . $currency : ''));
                    if ($setup !== '') {
                        $summary .= ($summary !== '' ? ' / ' : '') . 'setup ' . $setup . ($currency !== '' ? ' ' . $currency : '');
                    }
                    if ($summary !== '') {
                        $label .= ' (' . $summary . ')';
                    }
                    $skuOptions[] = [
                        'id' => $this->stringValue($sku, 'id'),
                        'label' => $label,
                    ];
                }
            }

            $rows[] = [
                'id' => $this->stringValue($item, 'id'),
                'number' => $this->stringValue($attributes, 'number'),
                'did_group' => $this->stringValue($didGroup['attributes'] ?? [], 'name', $this->stringValue($didGroup, 'id')),
                'did_group_id' => $this->stringValue($didGroup, 'id'),
                'sku_options' => $skuOptions,
            ];
        }

        return $rows;
    }

    /**
     * @param mixed $included
     * @return array<string, array<string, mixed>>
     */
    private function jsonApiIncludedMap(mixed $included): array
    {
        if (!is_array($included)) {
            return [];
        }

        $map = [];
        foreach ($included as $item) {
            if (!is_array($item)) {
                continue;
            }
            $map[$this->jsonApiKey($this->stringValue($item, 'type'), $this->stringValue($item, 'id'))] = $item;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $relationships
     * @param array<string, array<string, mixed>> $included
     * @return array<string, mixed>
     */
    private function jsonApiRelationshipResource(array $relationships, string $name, array $included): array
    {
        $relationship = $relationships[$name]['data'] ?? null;
        if (!is_array($relationship)) {
            return [];
        }

        return $included[$this->jsonApiKey($this->stringValue($relationship, 'type'), $this->stringValue($relationship, 'id'))] ?? $relationship;
    }

    private function jsonApiKey(string $type, string $id): string
    {
        return $type . ':' . $id;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? $default;
        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function boolString(array $values, string $key): string
    {
        return !empty($values[$key]) ? 'Yes' : 'No';
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, string>>
     */
    private function normalizeTwilioIncomingNumbers(array $payload): array
    {
        $rows = [];
        foreach ($payload['incoming_phone_numbers'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rows[] = $this->normalizeTwilioIncomingNumber($item);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, string>
     */
    private function normalizeTwilioIncomingNumber(array $item): array
    {
        return [
            'sid' => $this->stringValue($item, 'sid'),
            'friendly_name' => $this->stringValue($item, 'friendly_name'),
            'phone_number' => $this->stringValue($item, 'phone_number'),
            'country_code' => $this->stringValue($item, 'iso_country', $this->stringValue($item, 'country_code')),
            'voice_url' => $this->stringValue($item, 'voice_url'),
            'sms_url' => $this->stringValue($item, 'sms_url'),
            'trunk_sid' => $this->stringValue($item, 'trunk_sid'),
            'trunk_name' => $this->stringValue($item, 'trunk_name'),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, string>>
     */
    private function normalizeTwilioAvailableNumbers(array $payload): array
    {
        $rows = [];
        foreach ($payload['available_phone_numbers'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $capabilities = is_array($item['capabilities'] ?? null) ? $item['capabilities'] : [];
            $rows[] = [
                'friendly_name' => $this->stringValue($item, 'friendly_name'),
                'phone_number' => $this->stringValue($item, 'phone_number'),
                'locality' => $this->stringValue($item, 'locality'),
                'region' => $this->stringValue($item, 'region'),
                'postal_code' => $this->stringValue($item, 'postal_code'),
                'beta' => $this->boolString($item, 'beta'),
                'capabilities' => implode(', ', array_keys(array_filter($capabilities))),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, string>>
     */
    private function normalizeTwilioTrunks(array $payload): array
    {
        $rows = [];
        foreach ($payload['trunks'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rows[] = $this->normalizeTwilioTrunk($item);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, string>
     */
    private function normalizeTwilioTrunk(array $item): array
    {
        return [
            'sid' => $this->stringValue($item, 'sid'),
            'friendly_name' => $this->stringValue($item, 'friendly_name'),
            'domain_name' => $this->stringValue($item, 'domain_name'),
            'date_created' => $this->stringValue($item, 'date_created'),
            'connection_policy_sid' => $this->stringValue($item, 'connection_policy_sid'),
            'type' => preg_match('/^BY[0-9A-Fa-f]{32}$/', $this->stringValue($item, 'sid')) === 1 ? 'byoc' : 'sip',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, string>>
     */
    private function normalizeTwilioTrunkPhoneNumbers(array $payload): array
    {
        $rows = [];
        foreach ($payload['phone_numbers'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rows[] = [
                'sid' => $this->stringValue($item, 'sid'),
                'phone_number_sid' => $this->stringValue($item, 'phone_number_sid'),
            ];
        }

        return $rows;
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

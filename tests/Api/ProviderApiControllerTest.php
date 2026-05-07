<?php

declare(strict_types=1);

use A2BillingPlus\Api\ProviderApiController;
use A2BillingPlus\Bootstrap\ProviderRegistryFactory;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Module\Provider\Didww\DidwwApiClient;
use A2BillingPlus\Module\Provider\ProviderConnectionResult;
use A2BillingPlus\Module\Provider\ProviderAccessPolicy;
use A2BillingPlus\Module\Provider\ProviderConnectorInterface;
use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\ProviderRegistry;
use A2BillingPlus\Module\Provider\RateImporterInterface;
use A2BillingPlus\Module\Provider\RateImportPreview;
use A2BillingPlus\Module\Provider\RateImportRequest;
use A2BillingPlus\Module\Provider\RateImportResult;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPRegistrationClient;
use PHPUnit\Framework\TestCase;

final class ProviderApiControllerTest extends TestCase
{
    public function testListsProviders(): void
    {
        $controller = new ProviderApiController(
            ProviderRegistryFactory::createDefault(),
            null,
            null,
            new ProviderAccessPolicy(new AppConfig()),
            'root'
        );
        $response = $controller->handle(new JsonRequest('GET'));
        $providers = $response->getPayload()['providers'];
        $codes = array_column($providers, 'code');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains('vectavoip', $codes);
        $this->assertContains('didww', $codes);
    }

    public function testTestsProviderConnection(): void
    {
        $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
        $response = $controller->handle(new JsonRequest('POST', [], [
            'action' => 'test_connection',
            'provider' => 'vectavoip',
            'base_url' => 'https://api.vectavoip.com',
            'api_key' => 'test-key',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getPayload()['success']);
    }

    public function testUsesRegisteredProviderCredentialsFromEnvironment(): void
    {
        putenv('VECTAVOIP_API_KEY=registered-key');

        try {
            $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
            $response = $controller->handle(new JsonRequest('POST', [], [
                'action' => 'test_connection',
                'provider' => 'vectavoip',
            ]));

            $this->assertSame(200, $response->getStatusCode());
            $this->assertTrue($response->getPayload()['success']);
            $this->assertSame('https://api.vectavoip.com', $response->getPayload()['details']['base_url']);
        } finally {
            putenv('VECTAVOIP_API_KEY');
        }
    }

    public function testReportsProviderRegistrationStatus(): void
    {
        putenv('VECTAVOIP_API_KEY=registered-key');
        putenv('VECTAVOIP_INSTALLATION_ID=inst_123');
        putenv('VECTAVOIP_API_BASE_URL=https://api.vectavoip.com');

        try {
            $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
            $response = $controller->handle(new JsonRequest('POST', [], [
                'action' => 'provider_status',
                'provider' => 'vectavoip',
            ]));

            $this->assertSame(200, $response->getStatusCode());
            $this->assertTrue($response->getPayload()['registered']);
            $this->assertSame('inst_123', $response->getPayload()['installation_id']);
            $this->assertSame('https://api.vectavoip.com', $response->getPayload()['api_base_url']);
        } finally {
            putenv('VECTAVOIP_API_KEY');
            putenv('VECTAVOIP_INSTALLATION_ID');
            putenv('VECTAVOIP_API_BASE_URL');
        }
    }

    public function testReadsProviderCredentialsFromSecretFiles(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'a2bp-provider-key-');
        $this->assertIsString($file);
        file_put_contents($file, "registered-file-key\n");
        putenv('VECTAVOIP_API_KEY');
        putenv('VECTAVOIP_API_KEY_FILE=' . $file);

        try {
            $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
            $response = $controller->handle(new JsonRequest('POST', [], [
                'action' => 'provider_status',
                'provider' => 'vectavoip',
            ]));

            $this->assertSame(200, $response->getStatusCode());
            $this->assertTrue($response->getPayload()['registered']);
        } finally {
            putenv('VECTAVOIP_API_KEY_FILE');
            @unlink($file);
        }
    }

    public function testRegistersProviderInstall(): void
    {
        $controller = new ProviderApiController(
            ProviderRegistryFactory::createDefault(),
            fn (string $baseUrl): VectaVoIPRegistrationClient => new VectaVoIPRegistrationClient($baseUrl, function (string $url, array $payload): array {
                $this->assertSame('http://localhost:8080/api/sandbox/v1/installations/register', $url);
                $this->assertSame('a2bp_test', $payload['install_key']);
                $this->assertSame('janeadmin', $payload['username']);
                $this->assertSame('secret-pass', $payload['password']);

                return [
                    'status' => 201,
                    'body' => json_encode([
                        'message' => 'registered',
                        'installation_id' => 'inst_123',
                        'api_key' => 'key_123',
                        'api_secret' => 'secret_123',
                        'metadata' => [
                            'account_number' => 'VV12345',
                            'registered_ip' => '74.208.7.156',
                            'allowed_ips' => '74.208.7.156/32',
                        ],
                    ], JSON_THROW_ON_ERROR),
                ];
            })
        );

        $response = $controller->handle(new JsonRequest('POST', [], [
            'action' => 'register_install',
            'provider' => 'vectavoip',
            'base_url' => 'http://localhost:8080/api/sandbox',
            'install_key' => 'a2bp_test',
            'registration_username' => 'janeadmin',
            'registration_password' => 'secret-pass',
            'company_name' => 'ExampleCo',
            'company_domain' => 'example.test',
            'contact_email' => 'jane@example.test',
            'app_name' => 'A2BillingPlus',
            'app_version' => '0.1.0-alpha',
        ]));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($response->getPayload()['success']);
        $this->assertSame('a2bp_test', $response->getPayload()['install_key']);
        $this->assertSame('inst_123', $response->getPayload()['installation_id']);
        $this->assertSame('key_123', $response->getPayload()['api_key']);
        $this->assertSame('VV12345', $response->getPayload()['metadata']['account_number']);
    }

    public function testDryRunsPreviewRateImport(): void
    {
        $pdo = $this->ratecardPdo();
        $controller = new ProviderApiController($this->previewProviderRegistry(), null, fn (): PDO => $pdo);

        $response = $controller->handle(new JsonRequest('POST', [], [
            'action' => 'import_preview_rates',
            'provider' => 'vectavoip',
            'target_ratecard_id' => '5',
            'rate_deck' => 'retail',
            'dry_run' => '1',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getPayload()['success']);
        $this->assertSame(2, $response->getPayload()['imported_rows']);
        $this->assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM cc_ratecard')->fetchColumn());
    }

    public function testImportsPreviewRatesIntoRatecard(): void
    {
        $pdo = $this->ratecardPdo();
        $controller = new ProviderApiController($this->previewProviderRegistry(), null, fn (): PDO => $pdo);

        $response = $controller->handle(new JsonRequest('POST', [], [
            'action' => 'import_preview_rates',
            'provider' => 'vectavoip',
            'target_ratecard_id' => '5',
            'rate_deck' => 'retail',
            'dry_run' => '0',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getPayload()['success']);
        $this->assertSame(2, $response->getPayload()['imported_rows']);
        $this->assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM cc_ratecard')->fetchColumn());
        $this->assertSame('VectaVoIP:retail', $pdo->query('SELECT tag FROM cc_ratecard LIMIT 1')->fetchColumn());
    }

    public function testRejectsUnknownProvider(): void
    {
        $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
        $response = $controller->handle(new JsonRequest('POST', [], [
            'action' => 'test_connection',
            'provider' => 'missing',
        ]));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testHidesLockedProviderFromPublicList(): void
    {
        putenv('A2BP_LOCKED_PROVIDERS=didww');

        try {
            $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
            $response = $controller->handle(new JsonRequest('GET'));
            $codes = array_column($response->getPayload()['providers'], 'code');

            $this->assertContains('vectavoip', $codes);
            $this->assertNotContains('didww', $codes);
        } finally {
            putenv('A2BP_LOCKED_PROVIDERS');
        }
    }

    public function testDidwwInventorySnapshotNormalizesInventoryData(): void
    {
        $controller = new ProviderApiController(
            ProviderRegistryFactory::createDefault(),
            null,
            null,
            new ProviderAccessPolicy(new AppConfig()),
            'root',
            fn (): DidwwApiClient => new DidwwApiClient(function (string $method, string $url): array {
                if ($method === 'GET' && str_contains($url, '/v3/dids')) {
                    return [
                        'status' => 200,
                        'body' => json_encode([
                            'data' => [[
                                'id' => 'did-1',
                                'type' => 'dids',
                                'attributes' => [
                                    'number' => '12125550100',
                                    'blocked' => false,
                                    'awaiting_registration' => false,
                                    'terminated' => false,
                                ],
                                'relationships' => [
                                    'voice_in_trunk' => ['data' => ['type' => 'voice_in_trunks', 'id' => 'trunk-1']],
                                    'did_group' => ['data' => ['type' => 'did_groups', 'id' => 'group-1']],
                                    'order' => ['data' => ['type' => 'orders', 'id' => 'order-1']],
                                ],
                            ]],
                            'included' => [
                                ['id' => 'trunk-1', 'type' => 'voice_in_trunks', 'attributes' => ['name' => 'Main trunk']],
                                ['id' => 'group-1', 'type' => 'did_groups', 'attributes' => ['name' => 'US Local']],
                                ['id' => 'order-1', 'type' => 'orders', 'attributes' => ['reference' => 'ORD-1']],
                            ],
                        ], JSON_THROW_ON_ERROR),
                    ];
                }

                if ($method === 'GET' && str_contains($url, '/v3/voice_in_trunks')) {
                    return [
                        'status' => 200,
                        'body' => json_encode([
                            'data' => [[
                                'id' => 'trunk-1',
                                'type' => 'voice_in_trunks',
                                'attributes' => [
                                    'name' => 'Main trunk',
                                    'priority' => 1,
                                    'weight' => 1,
                                    'capacity_limit' => 10,
                                    'configuration' => [
                                        'type' => 'sip_configurations',
                                        'attributes' => [
                                            'host' => 'pbx.example.test',
                                            'username' => '{DID}',
                                        ],
                                    ],
                                ],
                            ]],
                        ], JSON_THROW_ON_ERROR),
                    ];
                }

                return [
                    'status' => 200,
                    'body' => json_encode([
                        'data' => [[
                            'id' => 'order-1',
                            'type' => 'orders',
                            'attributes' => [
                                'reference' => 'ORD-1',
                                'status' => 'completed',
                                'created_at' => '2026-05-06T00:00:00Z',
                                'items' => [['type' => 'did_order_items']],
                            ],
                        ]],
                    ], JSON_THROW_ON_ERROR),
                ];
            })
        );

        $response = $controller->handle(new JsonRequest('POST', [], [
            'action' => 'didww_inventory_snapshot',
            'provider' => 'didww',
            'base_url' => 'https://api.didww.com',
            'api_key' => 'didww-key',
            'api_version' => '2026-04-16',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getPayload()['success']);
        $this->assertSame('12125550100', $response->getPayload()['dids'][0]['number']);
        $this->assertSame('Main trunk', $response->getPayload()['dids'][0]['voice_in_trunk']);
        $this->assertSame('pbx.example.test', $response->getPayload()['inbound_trunks'][0]['host']);
        $this->assertSame('completed', $response->getPayload()['orders'][0]['status']);
    }

    public function testDidwwAvailableDidSearchReturnsSkuOptions(): void
    {
        $controller = new ProviderApiController(
            ProviderRegistryFactory::createDefault(),
            null,
            null,
            new ProviderAccessPolicy(new AppConfig()),
            'root',
            fn (): DidwwApiClient => new DidwwApiClient(function (): array {
                return [
                    'status' => 200,
                    'body' => json_encode([
                        'data' => [[
                            'id' => 'available-1',
                            'type' => 'available_dids',
                            'attributes' => ['number' => '12125550101'],
                            'relationships' => [
                                'did_group' => ['data' => ['type' => 'did_groups', 'id' => 'group-1']],
                            ],
                        ]],
                        'included' => [
                            [
                                'id' => 'group-1',
                                'type' => 'did_groups',
                                'attributes' => ['name' => 'US Local'],
                                'relationships' => [
                                    'stock_keeping_units' => [
                                        'data' => [['type' => 'stock_keeping_units', 'id' => 'sku-1']],
                                    ],
                                ],
                            ],
                            [
                                'id' => 'sku-1',
                                'type' => 'stock_keeping_units',
                                'attributes' => [
                                    'name' => 'Monthly',
                                    'monthly_price' => '1.25',
                                    'setup_price' => '0.50',
                                    'currency' => 'USD',
                                ],
                            ],
                        ],
                    ], JSON_THROW_ON_ERROR),
                ];
            })
        );

        $response = $controller->handle(new JsonRequest('POST', [], [
            'action' => 'didww_search_available_dids',
            'provider' => 'didww',
            'base_url' => 'https://api.didww.com',
            'api_key' => 'didww-key',
            'api_version' => '2026-04-16',
            'filter[country.id]' => 'US',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getPayload()['success']);
        $this->assertSame('12125550101', $response->getPayload()['available_dids'][0]['number']);
        $this->assertSame('sku-1', $response->getPayload()['available_dids'][0]['sku_options'][0]['id']);
    }

    public function testDidwwOrderDidReturnsCreatedOrder(): void
    {
        $controller = new ProviderApiController(
            ProviderRegistryFactory::createDefault(),
            null,
            null,
            new ProviderAccessPolicy(new AppConfig()),
            'root',
            fn (): DidwwApiClient => new DidwwApiClient(function (string $method, string $url, ProviderCredentials $credentials, ?array $payload): array {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.didww.com/v3/orders', $url);
                $this->assertSame('didww-key', $credentials->getApiKey());
                $this->assertSame('available-1', $payload['data']['attributes']['items'][0]['attributes']['available_did_id'] ?? null);
                $this->assertSame('sku-1', $payload['data']['attributes']['items'][0]['attributes']['sku_id'] ?? null);

                return [
                    'status' => 201,
                    'body' => json_encode([
                        'data' => [
                            'id' => 'order-1',
                            'type' => 'orders',
                            'attributes' => [
                                'reference' => 'ORD-1',
                                'status' => 'pending',
                                'created_at' => '2026-05-06T00:00:00Z',
                                'items' => [['type' => 'did_order_items']],
                            ],
                        ],
                    ], JSON_THROW_ON_ERROR),
                ];
            })
        );

        $response = $controller->handle(new JsonRequest('POST', [], [
            'action' => 'didww_order_did',
            'provider' => 'didww',
            'base_url' => 'https://api.didww.com',
            'api_key' => 'didww-key',
            'api_version' => '2026-04-16',
            'available_did_id' => 'available-1',
            'sku_id' => 'sku-1',
        ]));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($response->getPayload()['success']);
        $this->assertSame('order-1', $response->getPayload()['order']['id']);
        $this->assertSame('pending', $response->getPayload()['order']['status']);
    }

    private function previewProviderRegistry(): ProviderRegistry
    {
        $importer = new class implements RateImporterInterface {
            public function preview(RateImportRequest $request): RateImportPreview
            {
                return new RateImportPreview(2, [
                    ['destination' => 'United States', 'prefix' => '1', 'rate' => '0.0100', 'increment' => 60],
                    ['destination' => 'United Kingdom', 'prefix' => '44', 'rate' => '0.0180', 'increment' => 60],
                ], 'preview ok');
            }

            public function import(RateImportRequest $request): RateImportResult
            {
                return new RateImportResult(false, 0, 0, 'not used');
            }
        };

        $connector = new class($importer) implements ProviderConnectorInterface {
            public function __construct(private readonly RateImporterInterface $importer)
            {
            }

            public function getProviderCode(): string
            {
                return 'vectavoip';
            }

            public function getDisplayName(): string
            {
                return 'VectaVoIP';
            }

            public function getSupportEmail(): string
            {
                return 'info@VectaVoIP.com';
            }

            public function getApiBaseUrl(): string
            {
                return 'https://api.vectavoip.com';
            }

            public function testConnection(ProviderCredentials $credentials): ProviderConnectionResult
            {
                return new ProviderConnectionResult(true, 'ok');
            }

            public function getRateImporter(ProviderCredentials $credentials): RateImporterInterface
            {
                return $this->importer;
            }
        };

        return new ProviderRegistry([$connector]);
    }

    private function ratecardPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cc_ratecard (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                idtariffplan INTEGER,
                dialprefix TEXT,
                destination INTEGER,
                buyrate TEXT,
                buyrateinitblock INTEGER,
                buyrateincrement INTEGER,
                rateinitial TEXT,
                initblock INTEGER,
                billingblock INTEGER,
                tag TEXT
            )'
        );

        return $pdo;
    }
}

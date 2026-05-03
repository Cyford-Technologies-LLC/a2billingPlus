<?php

declare(strict_types=1);

use A2BillingPlus\Api\ProviderApiController;
use A2BillingPlus\Bootstrap\ProviderRegistryFactory;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Module\Provider\ProviderConnectionResult;
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
        $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
        $response = $controller->handle(new JsonRequest('GET'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('vectavoip', $response->getPayload()['providers'][0]['code']);
        $this->assertSame('info@VectaVoIP.com', $response->getPayload()['providers'][0]['support_email']);
        $this->assertSame('https://api.VectaVoIP.com', $response->getPayload()['providers'][0]['api_base_url']);
    }

    public function testTestsProviderConnection(): void
    {
        $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
        $response = $controller->handle(new JsonRequest('POST', [], [
            'action' => 'test_connection',
            'provider' => 'vectavoip',
            'base_url' => 'https://api.VectaVoIP.com',
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
            $this->assertSame('https://api.VectaVoIP.com', $response->getPayload()['details']['base_url']);
        } finally {
            putenv('VECTAVOIP_API_KEY');
        }
    }

    public function testReportsProviderRegistrationStatus(): void
    {
        putenv('VECTAVOIP_API_KEY=registered-key');
        putenv('VECTAVOIP_INSTALLATION_ID=inst_123');
        putenv('VECTAVOIP_API_BASE_URL=https://api.VectaVoIP.com');

        try {
            $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
            $response = $controller->handle(new JsonRequest('POST', [], [
                'action' => 'provider_status',
                'provider' => 'vectavoip',
            ]));

            $this->assertSame(200, $response->getStatusCode());
            $this->assertTrue($response->getPayload()['registered']);
            $this->assertSame('inst_123', $response->getPayload()['installation_id']);
            $this->assertSame('https://api.VectaVoIP.com', $response->getPayload()['api_base_url']);
        } finally {
            putenv('VECTAVOIP_API_KEY');
            putenv('VECTAVOIP_INSTALLATION_ID');
            putenv('VECTAVOIP_API_BASE_URL');
        }
    }

    public function testRegistersProviderInstall(): void
    {
        $controller = new ProviderApiController(
            ProviderRegistryFactory::createDefault(),
            fn (string $baseUrl): VectaVoIPRegistrationClient => new VectaVoIPRegistrationClient($baseUrl, function (string $url, array $payload): array {
                $this->assertSame('http://localhost:8080/api/sandbox/v1/installations/register', $url);
                $this->assertSame('a2bp_test', $payload['install_key']);
                $this->assertSame('Jane Admin', $payload['contact_name']);

                return [
                    'status' => 201,
                    'body' => json_encode([
                        'message' => 'registered',
                        'installation_id' => 'inst_123',
                        'api_key' => 'key_123',
                        'api_secret' => 'secret_123',
                    ], JSON_THROW_ON_ERROR),
                ];
            })
        );

        $response = $controller->handle(new JsonRequest('POST', [], [
            'action' => 'register_install',
            'provider' => 'vectavoip',
            'base_url' => 'http://localhost:8080/api/sandbox',
            'install_key' => 'a2bp_test',
            'company_name' => 'ExampleCo',
            'company_domain' => 'example.test',
            'contact_name' => 'Jane Admin',
            'contact_email' => 'jane@example.test',
            'app_name' => 'A2BillingPlus',
            'app_version' => '0.1.0-alpha',
        ]));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($response->getPayload()['success']);
        $this->assertSame('a2bp_test', $response->getPayload()['install_key']);
        $this->assertSame('inst_123', $response->getPayload()['installation_id']);
        $this->assertSame('key_123', $response->getPayload()['api_key']);
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
                return 'https://api.VectaVoIP.com';
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

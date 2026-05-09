<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPInstallationRepository;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPProviderApiService;
use PHPUnit\Framework\TestCase;

final class VectaVoIPProviderApiServiceTest extends TestCase
{
    public function testRegistersInstallationAndPersistsCredentials(): void
    {
        $service = $this->service();

        $result = $service->registerInstallation($this->registrationPayload());

        $this->assertSame(201, $result['status']);
        $this->assertSame('Registration completed.', $result['body']['message']);
        $this->assertStringStartsWith('inst_', $result['body']['installation_id']);
        $this->assertStringStartsWith('vvp_', $result['body']['api_key']);
        $this->assertStringStartsWith('vvs_', $result['body']['api_secret']);
        $this->assertSame('production', $result['body']['metadata']['mode']);
    }

    public function testDuplicateInstallKeyReturnsExistingRegistrationWithoutSecret(): void
    {
        $service = $this->service();
        $first = $service->registerInstallation($this->registrationPayload());
        $second = $service->registerInstallation($this->registrationPayload());

        $this->assertSame(200, $second['status']);
        $this->assertSame($first['body']['installation_id'], $second['body']['installation_id']);
        $this->assertSame($first['body']['api_key'], $second['body']['api_key']);
        $this->assertSame('', $second['body']['api_secret']);
    }

    public function testRejectsInvalidRegistrationEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('contact_email is invalid.');

        $payload = $this->registrationPayload();
        $payload['contact_email'] = 'not-an-email';

        $this->service()->registerInstallation($payload);
    }

    public function testInstallationStatusRequiresValidCredentials(): void
    {
        $service = $this->service();
        $registration = $service->registerInstallation($this->registrationPayload());

        $rejected = $service->installationStatus((string)$registration['body']['api_key'], 'wrong-secret');
        $missingSecret = $service->installationStatus((string)$registration['body']['api_key'], '');
        $accepted = $service->installationStatus(
            (string)$registration['body']['api_key'],
            (string)$registration['body']['api_secret']
        );

        $this->assertSame(401, $rejected['status']);
        $this->assertSame(401, $missingSecret['status']);
        $this->assertSame(200, $accepted['status']);
        $this->assertSame('active', $accepted['body']['status']);
    }

    public function testRatePreviewRequiresValidCredentials(): void
    {
        $service = $this->service();
        $registration = $service->registerInstallation($this->registrationPayload());

        $result = $service->ratePreview(
            ['rate_deck' => 'retail', 'currency' => 'USD'],
            (string)$registration['body']['api_key'],
            (string)$registration['body']['api_secret']
        );

        $this->assertSame(200, $result['status']);
        $this->assertSame(3, $result['body']['total_rows']);
        $this->assertSame('retail', $result['body']['sample_rows'][0]['rate_deck']);
    }

    public function testRotatesApiSecret(): void
    {
        $service = $this->service();
        $registration = $service->registerInstallation($this->registrationPayload());

        $rotated = $service->rotateCredentials(
            (string)$registration['body']['api_key'],
            (string)$registration['body']['api_secret']
        );
        $oldStatus = $service->installationStatus(
            (string)$registration['body']['api_key'],
            (string)$registration['body']['api_secret']
        );
        $newStatus = $service->installationStatus(
            (string)$registration['body']['api_key'],
            (string)$rotated['body']['api_secret']
        );

        $this->assertSame(200, $rotated['status']);
        $this->assertStringStartsWith('vvs_', $rotated['body']['api_secret']);
        $this->assertNotSame($registration['body']['api_secret'], $rotated['body']['api_secret']);
        $this->assertSame(401, $oldStatus['status']);
        $this->assertSame(200, $newStatus['status']);
    }

    public function testCreatesAccountPurchasesDidAndRecordsSms(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $service = new VectaVoIPProviderApiService(new VectaVoIPInstallationRepository($pdo));
        $registration = $service->registerInstallation($this->registrationPayload());
        $apiKey = (string)$registration['body']['api_key'];
        $apiSecret = (string)$registration['body']['api_secret'];

        $account = $service->createAccount([
            'name' => 'Acme Main',
            'email' => 'ops@example.test',
            'phone' => '+15557654321',
            'contact_methods' => ['email', 'text', 'sip'],
        ], $apiKey, $apiSecret);
        $pdo->exec(
            "INSERT INTO cc_vectavoip_did_inventory
                (did, country, region, monthly_rate, setup_rate, currency, status, provider_reference, created_at, updated_at)
             VALUES
                ('+15551230000', 'US', 'NY', '1.25000', '0.00000', 'USD', 'available', 'did-1', '2026-05-09', '2026-05-09')"
        );
        $available = $service->availableDids(['country' => 'US'], $apiKey, $apiSecret);
        $purchase = $service->purchaseDid([
            'account_id' => (int)$account['body']['account']['id'],
            'did' => '+15551230000',
            'features' => ['voice', 'sms'],
            'routing_destination' => 'sip:main@example.test',
        ], $apiKey, $apiSecret);
        $outbound = $service->sendSms([
            'account_id' => (int)$account['body']['account']['id'],
            'from' => '+15551230000',
            'to' => '+15557654321',
            'body' => 'hello',
        ], $apiKey, $apiSecret);
        $inbound = $service->inboundSms([
            'from' => '+15557654321',
            'to' => '+15551230000',
            'body' => 'reply',
            'message_id' => 'carrier-msg-1',
        ], $apiKey, $apiSecret);

        $this->assertSame(201, $account['status']);
        $this->assertSame(['email', 'text', 'sip'], $account['body']['account']['contact_methods']);
        $this->assertSame(200, $available['status']);
        $this->assertSame(1, $available['body']['total']);
        $this->assertSame(201, $purchase['status']);
        $this->assertSame('assigned', $pdo->query("SELECT status FROM cc_vectavoip_did_inventory WHERE did = '+15551230000'")->fetchColumn());
        $this->assertSame(202, $outbound['status']);
        $this->assertStringStartsWith('vvsms_', $outbound['body']['message_id']);
        $this->assertSame(201, $inbound['status']);
        $this->assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM cc_vectavoip_sms_messages')->fetchColumn());
    }

    public function testCanSetDefaultUpstreamAndPurchaseDidFromTwilio(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $capturedPayload = [];
        $service = new VectaVoIPProviderApiService(
            new VectaVoIPInstallationRepository($pdo),
            function () use (&$capturedPayload) {
                return new class ($capturedPayload) {
                    /** @var array<string, string> */
                    private array $capturedPayload;

                    /**
                     * @param array<string, string> $capturedPayload
                     */
                    public function __construct(array &$capturedPayload)
                    {
                        $this->capturedPayload = &$capturedPayload;
                    }

                    /**
                     * @param array<string, string> $payload
                     * @return array<string, string>
                     */
                    public function purchaseIncomingPhoneNumber(object $credentials, array $payload): array
                    {
                        $this->capturedPayload = $payload;
                        return [
                            'sid' => 'PN123',
                            'phone_number' => $payload['PhoneNumber'],
                            'friendly_name' => 'Twilio DID',
                        ];
                    }
                };
            }
        );
        $registration = $service->registerInstallation($this->registrationPayload());
        $apiKey = (string)$registration['body']['api_key'];
        $apiSecret = (string)$registration['body']['api_secret'];
        $account = $service->createAccount([
            'name' => 'Acme Main',
            'email' => 'ops@example.test',
        ], $apiKey, $apiSecret);

        $setDefault = $service->setUpstreamDefault(['provider' => 'twilio'], $apiKey, $apiSecret);
        $default = $service->upstreamDefault($apiKey, $apiSecret);
        $purchase = $service->purchaseDid([
            'account_id' => (int)$account['body']['account']['id'],
            'did' => '+12125550100',
            'features' => ['voice', 'sms'],
            'voice_url' => 'https://voice.example.test/twilio',
        ], $apiKey, $apiSecret);

        $this->assertSame(200, $setDefault['status']);
        $this->assertSame('twilio', $default['body']['default_upstream_provider']);
        $this->assertSame(201, $purchase['status']);
        $this->assertSame('twilio', $purchase['body']['upstream_provider']);
        $this->assertSame('+12125550100', $capturedPayload['PhoneNumber']);
        $this->assertSame('https://voice.example.test/twilio', $capturedPayload['VoiceUrl']);
        $this->assertSame('twilio', $pdo->query("SELECT provider_code FROM cc_vectavoip_did_inventory WHERE did = '+12125550100'")->fetchColumn());
        $this->assertSame('assigned', $pdo->query("SELECT status FROM cc_vectavoip_did_inventory WHERE did = '+12125550100'")->fetchColumn());
    }

    private function service(): VectaVoIPProviderApiService
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return new VectaVoIPProviderApiService(new VectaVoIPInstallationRepository($pdo));
    }

    /**
     * @return array<string, string>
     */
    private function registrationPayload(): array
    {
        return [
            'install_key' => 'a2bp_test',
            'username' => 'jane-admin',
            'password' => 'SecretPass123!',
            'company_name' => 'Example Co',
            'company_domain' => 'example.test',
            'contact_email' => 'jane@example.test',
            'request_ip' => '127.0.0.1',
            'app_name' => 'A2BillingPlus',
            'app_version' => '0.1.0-alpha',
        ];
    }
}

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
        $accepted = $service->installationStatus(
            (string)$registration['body']['api_key'],
            (string)$registration['body']['api_secret']
        );

        $this->assertSame(401, $rejected['status']);
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

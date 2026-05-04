<?php

declare(strict_types=1);

use A2BillingPlus\Config\AppConfig;
use PHPUnit\Framework\TestCase;

final class AppConfigTest extends TestCase
{
    public function testReturnsConfiguredStringWithDefault(): void
    {
        $config = new AppConfig(['A2BP_DB_HOST' => 'database']);

        $this->assertSame('database', $config->string('A2BP_DB_HOST'));
        $this->assertSame('fallback', $config->string('MISSING', 'fallback'));
    }

    public function testBuildsDatabaseDsnFromParts(): void
    {
        $config = new AppConfig([
            'A2BP_DB_HOST' => 'db',
            'A2BP_DB_NAME' => 'mya2billing',
        ]);

        $this->assertSame('mysql:host=db;dbname=mya2billing;charset=utf8mb4', $config->databaseDsn());
    }

    public function testUsesExplicitDatabaseDsn(): void
    {
        $config = new AppConfig(['A2BP_DB_DSN' => 'sqlite::memory:']);

        $this->assertSame('sqlite::memory:', $config->databaseDsn());
    }

    public function testMapsCyfordStripeTestAliases(): void
    {
        $config = new AppConfig([
            'MODE' => 'test',
            'STRIPE_TEST_SECRET_KEY' => 'sk_test_alias',
            'STRIPE_TEST_WEBHOOK_SECRET' => 'whsec_test_alias',
        ]);

        $this->assertSame('sk_test_alias', $config->string('STRIPE_SECRET_KEY'));
        $this->assertSame('whsec_test_alias', $config->string('STRIPE_WEBHOOK_SECRET'));
    }

    public function testMapsCyfordStripeLiveAliases(): void
    {
        $config = new AppConfig([
            'MODE' => 'live',
            'STRIPE_TEST_SECRET_KEY' => 'sk_test_alias',
            'STRIPE_LIVE_SECRET_KEY' => 'sk_live_alias',
            'STRIPE_LIVE_WEBHOOK_SECRET' => 'whsec_live_alias',
        ]);

        $this->assertSame('sk_live_alias', $config->string('STRIPE_SECRET_KEY'));
        $this->assertSame('whsec_live_alias', $config->string('STRIPE_WEBHOOK_SECRET'));
    }

    public function testMapsStripeRestrictedKeyWhenSecretKeyIsNotConfigured(): void
    {
        $config = new AppConfig([
            'MODE' => 'test',
            'STRIPE_TEST_RESTRICTED_KEY' => 'rk_test_alias',
            'STRIPE_TEST_WEBHOOK_SECRET' => 'whsec_test_alias',
        ]);

        $this->assertSame('rk_test_alias', $config->string('STRIPE_SECRET_KEY'));
        $this->assertSame('whsec_test_alias', $config->string('STRIPE_WEBHOOK_SECRET'));
    }

    public function testReadsSecretFileEnvironmentValues(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'a2bp-secret-');
        $this->assertIsString($file);
        file_put_contents($file, "file-secret\n");
        putenv('VECTAVOIP_API_SECRET');
        putenv('VECTAVOIP_API_SECRET_FILE=' . $file);

        try {
            $config = AppConfig::fromEnvironment();

            $this->assertSame('file-secret', $config->string('VECTAVOIP_API_SECRET'));
        } finally {
            putenv('VECTAVOIP_API_SECRET_FILE');
            @unlink($file);
        }
    }

    public function testReadsApiServiceKeyFromSecretFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'a2bp-api-key-');
        $this->assertIsString($file);
        file_put_contents($file, "service-key\n");
        putenv('A2BP_API_SERVICE_KEY');
        putenv('A2BP_API_SERVICE_KEY_FILE=' . $file);

        try {
            $config = AppConfig::fromEnvironment();

            $this->assertSame('service-key', $config->string('A2BP_API_SERVICE_KEY'));
        } finally {
            putenv('A2BP_API_SERVICE_KEY_FILE');
            @unlink($file);
        }
    }
}

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
}

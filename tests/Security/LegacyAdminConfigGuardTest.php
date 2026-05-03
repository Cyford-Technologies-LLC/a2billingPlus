<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../common/lib/a2bp_legacy_admin_guard.php';

final class LegacyAdminConfigGuardTest extends TestCase
{
    private string|false $originalAppEnv = false;
    private string|false $originalA2bpAppEnv = false;
    private string|false $originalFeatureFlag = false;

    protected function setUp(): void
    {
        $this->originalAppEnv = getenv('APP_ENV');
        $this->originalA2bpAppEnv = getenv('A2BP_APP_ENV');
        $this->originalFeatureFlag = getenv('A2BP_FEATURE_LEGACY_CONFIG_EDITORS');
    }

    protected function tearDown(): void
    {
        $this->restoreEnv('APP_ENV', $this->originalAppEnv);
        $this->restoreEnv('A2BP_APP_ENV', $this->originalA2bpAppEnv);
        $this->restoreEnv('A2BP_FEATURE_LEGACY_CONFIG_EDITORS', $this->originalFeatureFlag);
    }

    public function testDetectsProductionEnvironment(): void
    {
        putenv('APP_ENV=production');

        $this->assertTrue(a2bp_is_production_environment());
    }

    public function testLegacyConfigEditorsRequireExplicitFeatureFlag(): void
    {
        putenv('A2BP_FEATURE_LEGACY_CONFIG_EDITORS');
        $this->assertFalse(a2bp_legacy_admin_config_editors_enabled());

        putenv('A2BP_FEATURE_LEGACY_CONFIG_EDITORS=true');
        $this->assertTrue(a2bp_legacy_admin_config_editors_enabled());
    }

    public function testLegacyConfigPagesCallProductionGuard(): void
    {
        foreach ([
            'A2B_entity_config.php',
            'A2B_entity_config_group.php',
            'A2B_entity_config_generate_confirm.php',
        ] as $file) {
            $source = file_get_contents(__DIR__ . '/../../admin/Public/' . $file);

            $this->assertIsString($source);
            $this->assertStringContainsString('a2bp_legacy_admin_guard.php', $source, $file);
            $this->assertStringContainsString('a2bp_assert_legacy_admin_config_editor_allowed();', $source, $file);
        }
    }

    private function restoreEnv(string $name, string|false $value): void
    {
        if ($value === false) {
            putenv($name);
            return;
        }

        putenv($name . '=' . $value);
    }
}

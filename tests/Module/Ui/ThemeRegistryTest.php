<?php

declare(strict_types=1);

namespace Tests\Module\Ui;

use A2BillingPlus\Module\Ui\Theme;
use A2BillingPlus\Module\Ui\ThemeRegistry;
use A2BillingPlus\Module\Ui\ThemeRenderer;
use PHPUnit\Framework\TestCase;

final class ThemeRegistryTest extends TestCase
{
    public function testResolvesDefaultThemeWhenRequestedThemeIsMissing(): void
    {
        $registry = ThemeRegistry::default();

        self::assertSame(ThemeRegistry::DEFAULT_THEME, $registry->resolve('missing')->id());
    }

    public function testResolvesRegisteredTheme(): void
    {
        $registry = ThemeRegistry::default();

        self::assertSame('legacy', $registry->resolve('legacy')->id());
    }

    public function testResolvesClassicTheme(): void
    {
        $registry = ThemeRegistry::default();

        self::assertSame('ui/themes/classic/theme.css', $registry->resolve('classic')->asset('stylesheet'));
        self::assertSame('compact', $registry->resolve('classic')->defaultMenuStyle());
    }

    public function testCanRegisterCustomTheme(): void
    {
        $registry = ThemeRegistry::default();
        $registry->register(new Theme('tenant-blue', 'Tenant Blue', 'Tenant theme.', [
            'stylesheet' => 'ui/themes/tenant-blue/theme.css',
        ]));

        self::assertSame('tenant-blue', $registry->resolve('tenant-blue')->id());
    }

    public function testDiscoversFilesystemThemeFromProjectRoot(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'a2bp-theme-registry-' . bin2hex(random_bytes(4));
        $themeDir = $root . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'Public' . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'tenant-green';
        mkdir($themeDir, 0775, true);
        file_put_contents($themeDir . DIRECTORY_SEPARATOR . 'theme.css', '.tenant-green{}');
        file_put_contents($themeDir . DIRECTORY_SEPARATOR . 'theme.json', json_encode([
            'id' => 'tenant-green',
            'name' => 'Tenant Green',
            'description' => 'Custom tenant theme.',
            'version' => '1.0.0',
            'ui_contract_version' => '1',
            'stylesheet' => 'theme.css',
            'menu_style' => 'topbar',
        ], JSON_PRETTY_PRINT));

        try {
            $registry = ThemeRegistry::forProjectRoot($root);
            self::assertSame('tenant-green', $registry->resolve('tenant-green')->id());
            self::assertSame('ui/themes/tenant-green/theme.css', $registry->resolve('tenant-green')->asset('stylesheet'));
            self::assertSame('topbar', $registry->resolve('tenant-green')->defaultMenuStyle());
        } finally {
            @unlink($themeDir . DIRECTORY_SEPARATOR . 'theme.json');
            @unlink($themeDir . DIRECTORY_SEPARATOR . 'theme.css');
            @rmdir($themeDir);
            @rmdir(dirname($themeDir));
            @rmdir(dirname(dirname($themeDir)));
            @rmdir(dirname(dirname(dirname($themeDir))));
            @rmdir(dirname(dirname(dirname(dirname($themeDir)))));
            @rmdir($root);
        }
    }

    public function testRendererEscapesStylesheetHref(): void
    {
        $renderer = new ThemeRenderer();
        $theme = new Theme('custom', 'Custom', 'Unsafe asset test.', [
            'stylesheet' => 'ui/themes/custom/theme.css?x="y"',
        ]);

        self::assertSame(
            '<link rel="stylesheet" href="/admin/Public/ui/themes/custom/theme.css?x=&quot;y&quot;">',
            $renderer->stylesheetLink($theme, '/admin/Public')
        );
    }
}

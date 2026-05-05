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

    public function testCanRegisterCustomTheme(): void
    {
        $registry = ThemeRegistry::default();
        $registry->register(new Theme('tenant-blue', 'Tenant Blue', 'Tenant theme.', [
            'stylesheet' => 'ui/themes/tenant-blue/theme.css',
        ]));

        self::assertSame('tenant-blue', $registry->resolve('tenant-blue')->id());
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

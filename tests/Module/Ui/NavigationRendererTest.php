<?php

declare(strict_types=1);

namespace Tests\Module\Ui;

use A2BillingPlus\Module\Ui\NavigationRegistry;
use A2BillingPlus\Module\Ui\NavigationRenderer;
use A2BillingPlus\Module\Ui\ThemeRegistry;
use PHPUnit\Framework\TestCase;

final class NavigationRendererTest extends TestCase
{
    public function testRendersAdminNavigationWithActiveItemAndThemeSelector(): void
    {
        $themes = ThemeRegistry::default();
        $renderer = new NavigationRenderer();

        $html = $renderer->render(
            NavigationRegistry::admin(),
            'provider-setup',
            $themes->resolve('classic'),
            $themes->all()
        );

        self::assertStringContainsString('Provider Setup', $html);
        self::assertStringContainsString('a2bp-nav__item--active', $html);
        self::assertStringContainsString('name="form_action" value="set_ui_preferences"', $html);
        self::assertStringContainsString('<option value="classic" selected>', $html);
        self::assertStringContainsString('name="ui_menu_style"', $html);
    }
}

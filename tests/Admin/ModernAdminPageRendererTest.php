<?php

declare(strict_types=1);

namespace Tests\Admin;

use A2BillingPlus\Admin\ModernAdminPageRenderer;
use A2BillingPlus\Module\Ui\ThemeRegistry;
use PHPUnit\Framework\TestCase;

final class ModernAdminPageRendererTest extends TestCase
{
    public function testRendersSharedAdminChromeAndAlerts(): void
    {
        $themes = ThemeRegistry::default();
        $renderer = new ModernAdminPageRenderer();

        $html = $renderer->begin(
            $themes->resolve('classic'),
            $themes,
            'payments',
            'Payment Workspace',
            'Shared modular admin shell.'
        );
        $html .= $renderer->renderAlerts(['Saved.'], ['Problem.']);
        $html .= $renderer->end();

        self::assertStringContainsString('A2BillingPlus navigation', $html);
        self::assertStringContainsString('Payment Workspace', $html);
        self::assertStringContainsString('Shared modular admin shell.', $html);
        self::assertStringContainsString('a2bp-alert--success', $html);
        self::assertStringContainsString('a2bp-alert--error', $html);
        self::assertStringContainsString('a2bp-nav__item--active', $html);
    }
}

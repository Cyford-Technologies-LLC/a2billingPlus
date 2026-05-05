<?php

declare(strict_types=1);

namespace Tests\Admin;

use A2BillingPlus\Admin\ModernAdminRuntime;
use PHPUnit\Framework\TestCase;

final class ModernAdminRuntimeTest extends TestCase
{
    public function testReadsAndUpdatesThemeFromLocalEnvFile(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'a2bp-runtime-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . DIRECTORY_SEPARATOR . '.env', "A2BP_UI_THEME=classic\n");

        try {
            $runtime = new ModernAdminRuntime($root);
            self::assertSame('classic', $runtime->activeTheme()->id());

            $theme = $runtime->saveUiTheme('a2billingplus');
            self::assertSame('a2billingplus', $theme->id());
            self::assertStringContainsString('A2BP_UI_THEME=a2billingplus', (string)file_get_contents($root . DIRECTORY_SEPARATOR . '.env'));
            self::assertStringContainsString('A2BP_UI_MENU_STYLE=side-rail', (string)file_get_contents($root . DIRECTORY_SEPARATOR . '.env'));

            $style = $runtime->saveUiMenuStyle('topbar');
            self::assertSame('topbar', $style);
            self::assertSame('topbar', $runtime->activeMenuStyle($theme));
        } finally {
            @unlink($root . DIRECTORY_SEPARATOR . '.env');
            @rmdir($root);
        }
    }
}

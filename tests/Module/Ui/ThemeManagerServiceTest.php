<?php

declare(strict_types=1);

namespace Tests\Module\Ui;

use A2BillingPlus\Module\Ui\ThemeManagerService;
use A2BillingPlus\Module\Ui\ThemeRegistry;
use PHPUnit\Framework\TestCase;

final class ThemeManagerServiceTest extends TestCase
{
    public function testInstallsUploadedThemePackage(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive is not available.');
        }

        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'a2bp-theme-manager-' . bin2hex(random_bytes(4));
        $themeRoot = $root . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'Public' . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'themes';
        mkdir($themeRoot, 0775, true);

        $zipPath = $root . DIRECTORY_SEPARATOR . 'tenant-slate.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('tenant-slate/theme.json', json_encode([
            'id' => 'tenant-slate',
            'name' => 'Tenant Slate',
            'description' => 'Custom uploaded theme.',
            'version' => '1.2.3',
            'ui_contract_version' => '1',
            'stylesheet' => 'theme.css',
            'menu_style' => 'split',
        ], JSON_PRETTY_PRINT));
        $zip->addFromString('tenant-slate/theme.css', '.tenant-slate{}');
        $zip->close();

        try {
            $service = new ThemeManagerService($root, ThemeRegistry::forProjectRoot($root));
            $installed = $service->installUploadedTheme($zipPath, 'tenant-slate.zip');

            self::assertSame('tenant-slate', $installed['id']);
            self::assertSame('1.2.3', $installed['version']);
            self::assertFileExists($themeRoot . DIRECTORY_SEPARATOR . 'tenant-slate' . DIRECTORY_SEPARATOR . 'theme.json');

            $registry = ThemeRegistry::forProjectRoot($root);
            self::assertSame('tenant-slate', $registry->resolve('tenant-slate')->id());
            self::assertSame('split', $registry->resolve('tenant-slate')->defaultMenuStyle());
        } finally {
            @unlink($zipPath);
            @unlink($themeRoot . DIRECTORY_SEPARATOR . 'tenant-slate' . DIRECTORY_SEPARATOR . 'theme.json');
            @unlink($themeRoot . DIRECTORY_SEPARATOR . 'tenant-slate' . DIRECTORY_SEPARATOR . 'theme.css');
            @rmdir($themeRoot . DIRECTORY_SEPARATOR . 'tenant-slate');
            @rmdir($themeRoot);
            @rmdir(dirname($themeRoot));
            @rmdir(dirname(dirname($themeRoot)));
            @rmdir(dirname(dirname(dirname($themeRoot))));
            @rmdir(dirname(dirname(dirname(dirname($themeRoot)))));
            @rmdir($root);
        }
    }
}

<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Ui;

final class ThemeRenderer
{
    public function stylesheetLink(Theme $theme, string $basePath = ''): string
    {
        $stylesheet = $theme->asset('stylesheet');
        if ($stylesheet === '') {
            return '';
        }

        $href = $this->joinPath($basePath, $stylesheet);

        return sprintf(
            '<link rel="stylesheet" href="%s">',
            htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }

    private function joinPath(string $basePath, string $assetPath): string
    {
        $basePath = trim($basePath);
        if ($basePath === '') {
            return $assetPath;
        }

        return rtrim($basePath, '/') . '/' . ltrim($assetPath, '/');
    }
}

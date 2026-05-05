<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Ui;

final class ThemeRegistry
{
    public const DEFAULT_THEME = 'a2billingplus';
    /** @var list<string> */
    public const BUILT_IN_THEME_IDS = ['a2billingplus', 'classic', 'legacy'];

    /** @var array<string, Theme> */
    private array $themes = [];

    /**
     * @param iterable<Theme> $themes
     */
    public function __construct(iterable $themes = [])
    {
        foreach ($themes as $theme) {
            $this->register($theme);
        }
    }

    public static function default(): self
    {
        return new self(self::builtInThemes());
    }

    public static function forProjectRoot(string $projectRoot): self
    {
        $registry = new self(self::builtInThemes());
        $themeRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'Public' . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'themes';
        if (!is_dir($themeRoot)) {
            return $registry;
        }

        $manifestPaths = glob($themeRoot . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'theme.json');
        if (!is_array($manifestPaths)) {
            return $registry;
        }

        foreach ($manifestPaths as $manifestPath) {
            $theme = self::themeFromManifest($manifestPath, $themeRoot);
            if ($theme === null || isset($registry->themes[$theme->id()])) {
                continue;
            }

            $registry->register($theme);
        }

        return $registry;
    }

    /**
     * @return list<Theme>
     */
    private static function builtInThemes(): array
    {
        return [
            new Theme(
                self::DEFAULT_THEME,
                'A2BillingPlus',
                'Default modular A2BillingPlus operations theme.',
                ['stylesheet' => 'ui/themes/a2billingplus/theme.css', 'menu_style' => 'side-rail']
            ),
            new Theme(
                'classic',
                'A2Billing Classic',
                'Modern modular theme that keeps the compact gray, blue, and red A2Billing visual style.',
                ['stylesheet' => 'ui/themes/classic/theme.css', 'menu_style' => 'compact']
            ),
            new Theme(
                'legacy',
                'Legacy A2Billing',
                'Compatibility theme for legacy screens while pages are replaced workflow by workflow.',
                ['stylesheet' => 'templates/default/css/main.css', 'menu_style' => 'side-rail']
            ),
        ];
    }

    public function register(Theme $theme): void
    {
        $this->themes[$theme->id()] = $theme;
    }

    public function resolve(string $requestedTheme = ''): Theme
    {
        $requestedTheme = trim($requestedTheme);
        if ($requestedTheme !== '' && isset($this->themes[$requestedTheme])) {
            return $this->themes[$requestedTheme];
        }

        return $this->themes[self::DEFAULT_THEME];
    }

    /**
     * @return array<string, Theme>
     */
    public function all(): array
    {
        return $this->themes;
    }

    private static function themeFromManifest(string $manifestPath, string $themeRoot): ?Theme
    {
        $json = file_get_contents($manifestPath);
        if (!is_string($json) || $json === '') {
            return null;
        }

        $manifest = json_decode($json, true);
        if (!is_array($manifest)) {
            return null;
        }

        $id = trim((string)($manifest['id'] ?? basename(dirname($manifestPath))));
        $name = trim((string)($manifest['name'] ?? ''));
        $description = trim((string)($manifest['description'] ?? ''));
        $stylesheet = trim((string)($manifest['stylesheet'] ?? 'theme.css'));
        $menuStyle = MenuStyleRegistry::resolve((string)($manifest['menu_style'] ?? ''), 'side-rail');
        if ($id === '' || $name === '' || preg_match('/^[a-z0-9_-]+$/', $id) !== 1) {
            return null;
        }

        if (str_contains($stylesheet, '..') || str_starts_with($stylesheet, '/') || str_starts_with($stylesheet, '\\')) {
            return null;
        }

        $themeDir = dirname($manifestPath);
        $stylesheetPath = $themeDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $stylesheet);
        if (!is_file($stylesheetPath)) {
            return null;
        }

        $relativeStylesheet = 'ui/themes/' . basename($themeDir) . '/' . ltrim(str_replace('\\', '/', $stylesheet), '/');

        return new Theme($id, $name, $description, ['stylesheet' => $relativeStylesheet, 'menu_style' => $menuStyle]);
    }
}

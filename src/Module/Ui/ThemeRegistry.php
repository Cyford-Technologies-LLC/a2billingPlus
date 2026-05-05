<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Ui;

final class ThemeRegistry
{
    public const DEFAULT_THEME = 'a2billingplus';

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
        return new self([
            new Theme(
                self::DEFAULT_THEME,
                'A2BillingPlus',
                'Default modular A2BillingPlus operations theme.',
                ['stylesheet' => 'ui/themes/a2billingplus/theme.css']
            ),
            new Theme(
                'legacy',
                'Legacy A2Billing',
                'Compatibility theme for legacy screens while pages are replaced workflow by workflow.',
                ['stylesheet' => 'templates/default/css/main.css']
            ),
        ]);
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
}

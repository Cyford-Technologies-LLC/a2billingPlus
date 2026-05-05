<?php

declare(strict_types=1);

namespace A2BillingPlus\Admin;

use A2BillingPlus\Module\Ui\NavigationRegistry;
use A2BillingPlus\Module\Ui\NavigationRenderer;
use A2BillingPlus\Module\Ui\Theme;
use A2BillingPlus\Module\Ui\ThemeRegistry;
use A2BillingPlus\Module\Ui\ThemeRenderer;
use A2BillingPlus\Module\Ui\MenuStyleRegistry;

final class ModernAdminPageRenderer
{
    public function __construct(
        private readonly NavigationRenderer $navigationRenderer = new NavigationRenderer(),
        private readonly ThemeRenderer $themeRenderer = new ThemeRenderer()
    ) {
    }

    public function begin(Theme $theme, ThemeRegistry $themeRegistry, string $activeItemId, string $title, string $description = '', string $menuStyle = ''): string
    {
        $menuStyle = MenuStyleRegistry::resolve($menuStyle, $theme->defaultMenuStyle());
        $menuClass = MenuStyleRegistry::bodyClass($menuStyle);
        $html = $this->themeRenderer->stylesheetLink($theme);
        $html .= PHP_EOL . '<link rel="stylesheet" href="ui/menu-styles.css">';
        $html .= PHP_EOL . '<script>document.documentElement.classList.add("' . $this->escape($menuClass) . '");document.body.classList.add("' . $this->escape($menuClass) . '");</script>';
        $html .= PHP_EOL . '<br>' . PHP_EOL;
        $html .= '<div class="' . $this->escape($theme->bodyClass() . ' ' . $menuClass) . '">' . PHP_EOL;
        $html .= '<div class="a2bp-page">' . PHP_EOL;
        $html .= $this->navigationRenderer->render(NavigationRegistry::admin(), $activeItemId, $theme, $themeRegistry->all(), MenuStyleRegistry::all(), $menuStyle) . PHP_EOL;
        $html .= $this->panel($title, $description, $description !== '' ? 'a2bp-muted' : '');

        return $html;
    }

    /**
     * @param list<string> $messages
     * @param list<string> $errors
     */
    public function renderAlerts(array $messages, array $errors): string
    {
        $html = '';
        foreach ($messages as $message) {
            $html .= '<div class="a2bp-alert a2bp-alert--success">' . $this->escape($message) . '</div>' . PHP_EOL;
        }

        foreach ($errors as $error) {
            $html .= '<div class="a2bp-alert a2bp-alert--error">' . $this->escape($error) . '</div>' . PHP_EOL;
        }

        return $html;
    }

    public function panel(string $title, string $body, string $bodyClass = ''): string
    {
        $bodyClassAttribute = 'a2bp-panel__body';
        if ($bodyClass !== '') {
            $bodyClassAttribute .= ' ' . $bodyClass;
        }

        return sprintf(
            '<div class="a2bp-panel"><div class="a2bp-panel__header"><h1 class="a2bp-panel__title">%s</h1></div><div class="%s">%s</div></div>' . PHP_EOL,
            $this->escape($title),
            $this->escape($bodyClassAttribute),
            $this->escape($body)
        );
    }

    public function end(): string
    {
        return '</div>' . PHP_EOL . '</div>' . PHP_EOL;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

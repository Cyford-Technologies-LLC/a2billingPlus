<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Ui;

final class NavigationRenderer
{
    /**
     * @param list<NavigationSection> $sections
     * @param array<string, Theme> $themes
     * @param array<string, string> $menuStyles
     */
    public function render(array $sections, string $activeItemId, Theme $activeTheme, array $themes, array $menuStyles = [], string $activeMenuStyle = ''): string
    {
        $menuStyles = $menuStyles !== [] ? $menuStyles : MenuStyleRegistry::all();
        $activeMenuStyle = MenuStyleRegistry::resolve($activeMenuStyle, $activeTheme->defaultMenuStyle());
        $html = '<nav class="a2bp-nav" aria-label="A2BillingPlus navigation">';
        foreach ($sections as $section) {
            $html .= '<div class="a2bp-nav__section">';
            $html .= '<div class="a2bp-nav__section-title">' . $this->escape($section->label()) . '</div>';
            $html .= '<div class="a2bp-nav__items">';
            foreach ($section->items() as $item) {
                $class = 'a2bp-nav__item';
                if ($item->id() === $activeItemId) {
                    $class .= ' a2bp-nav__item--active';
                }

                $html .= sprintf(
                    '<a class="%s" href="%s">%s</a>',
                    $this->escape($class),
                    $this->escape($item->href()),
                    $this->escape($item->label())
                );
            }
            $html .= '</div></div>';
        }

        $html .= $this->renderUiSelector($activeTheme, $themes, $menuStyles, $activeMenuStyle);
        $html .= '</nav>';

        return $html;
    }

    /**
     * @param array<string, Theme> $themes
     */
    private function renderUiSelector(Theme $activeTheme, array $themes, array $menuStyles, string $activeMenuStyle): string
    {
        $html = '<form class="a2bp-theme-selector" method="post">';
        $html .= '<input type="hidden" name="form_action" value="set_ui_preferences">';
        $html .= '<label for="a2bp-ui-theme">Theme</label>';
        $html .= '<select id="a2bp-ui-theme" name="ui_theme">';
        foreach ($themes as $theme) {
            $selected = $theme->id() === $activeTheme->id() ? ' selected' : '';
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                $this->escape($theme->id()),
                $selected,
                $this->escape($theme->name())
            );
        }
        $html .= '</select>';
        $html .= '<label for="a2bp-ui-menu-style">Menu</label>';
        $html .= '<select id="a2bp-ui-menu-style" name="ui_menu_style">';
        foreach ($menuStyles as $styleId => $label) {
            $selected = $styleId === $activeMenuStyle ? ' selected' : '';
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                $this->escape((string)$styleId),
                $selected,
                $this->escape((string)$label)
            );
        }
        $html .= '</select>';
        $html .= '<button class="a2bp-button" type="submit">Apply</button>';
        $html .= '</form>';

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

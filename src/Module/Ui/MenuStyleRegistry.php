<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Ui;

final class MenuStyleRegistry
{
    /** @return array<string, string> */
    public static function all(): array
    {
        return [
            'side-rail' => 'Side Rail',
            'topbar' => 'Top Bar',
            'compact' => 'Compact',
            'split' => 'Split',
        ];
    }

    public static function resolve(string $requestedStyle, string $fallback = 'side-rail'): string
    {
        $requestedStyle = trim($requestedStyle);
        if (isset(self::all()[$requestedStyle])) {
            return $requestedStyle;
        }

        return isset(self::all()[$fallback]) ? $fallback : 'side-rail';
    }

    public static function bodyClass(string $style): string
    {
        return 'a2bp-menu-' . self::resolve($style);
    }
}

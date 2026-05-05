<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Ui;

final class Theme
{
    /**
     * @param array<string, string> $assets
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $description,
        private readonly array $assets,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function asset(string $name): string
    {
        return $this->assets[$name] ?? '';
    }

    public function defaultMenuStyle(): string
    {
        $style = $this->asset('menu_style');
        return $style !== '' ? $style : 'side-rail';
    }

    public function bodyClass(): string
    {
        return 'a2bp-ui a2bp-theme-' . preg_replace('/[^a-z0-9_-]/', '-', strtolower($this->id));
    }
}

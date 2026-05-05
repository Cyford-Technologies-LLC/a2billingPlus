<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Ui;

final class NavigationItem
{
    public function __construct(
        private readonly string $label,
        private readonly string $href,
        private readonly string $id = '',
    ) {
    }

    public function label(): string
    {
        return $this->label;
    }

    public function href(): string
    {
        return $this->href;
    }

    public function id(): string
    {
        return $this->id;
    }
}

<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Ui;

final class NavigationSection
{
    /**
     * @param list<NavigationItem> $items
     */
    public function __construct(
        private readonly string $label,
        private readonly array $items,
        private readonly string $id = '',
    ) {
    }

    public function label(): string
    {
        return $this->label;
    }

    /**
     * @return list<NavigationItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function id(): string
    {
        return $this->id;
    }
}

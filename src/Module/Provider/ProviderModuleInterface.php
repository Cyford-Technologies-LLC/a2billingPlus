<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider;

interface ProviderModuleInterface
{
    /**
     * @return iterable<ProviderConnectorInterface>
     */
    public function connectors(): iterable;
}

<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

final class ApiCustomerContext
{
    public function __construct(public readonly int $customerId)
    {
    }
}

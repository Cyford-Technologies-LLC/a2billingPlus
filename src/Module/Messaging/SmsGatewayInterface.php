<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Messaging;

interface SmsGatewayInterface
{
    public function send(string $from, string $to, string $body): SmsResult;
}

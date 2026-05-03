<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Payment;

final class PaymentGatewayInventory
{
    /**
     * @return list<array{code:string,name:string,status:string,reason:string}>
     */
    public function gateways(): array
    {
        return [
            [
                'code' => 'stripe',
                'name' => 'Stripe',
                'status' => 'modern',
                'reason' => 'Launch target for card and hosted checkout payments.',
            ],
            [
                'code' => 'braintree',
                'name' => 'Braintree',
                'status' => 'optional',
                'reason' => 'Keep as a future module only when a customer requires it.',
            ],
            [
                'code' => 'paypal',
                'name' => 'PayPal legacy form flow',
                'status' => 'deprecated',
                'reason' => 'Legacy form-post flow remains disabled until rebuilt behind the payment module.',
            ],
            [
                'code' => 'moneybookers',
                'name' => 'Moneybookers/Skrill legacy flow',
                'status' => 'deprecated',
                'reason' => 'Legacy flow is dated and not a launch payment target.',
            ],
            [
                'code' => 'plugnpay',
                'name' => 'PlugnPay legacy direct-card flow',
                'status' => 'unsafe_disabled',
                'reason' => 'Legacy code accepts raw card/CVV values and must not be enabled.',
            ],
            [
                'code' => 'iridium',
                'name' => 'Iridium legacy direct-card flow',
                'status' => 'unsafe_disabled',
                'reason' => 'Legacy code stores raw card/CVV fields in cc_epayment_log.',
            ],
            [
                'code' => 'authorize',
                'name' => 'Authorize.Net legacy flow',
                'status' => 'deprecated',
                'reason' => 'Use a modern hosted/tokenized implementation before enabling.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function disabledLegacyGatewayCodes(): array
    {
        return array_values(array_map(
            static fn (array $gateway): string => $gateway['code'],
            array_filter($this->gateways(), static fn (array $gateway): bool => $gateway['status'] !== 'modern')
        ));
    }
}

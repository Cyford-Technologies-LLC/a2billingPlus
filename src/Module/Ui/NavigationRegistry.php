<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Ui;

final class NavigationRegistry
{
    /**
     * @return list<NavigationSection>
     */
    public static function admin(): array
    {
        return [
            new NavigationSection('Operations', [
                new NavigationItem('Home', 'PP_intro.php', 'home'),
                new NavigationItem('Provider Connection', 'A2B_provider_setup.php?section=7', 'provider-setup'),
                new NavigationItem('Payments', 'A2B_entity_payment.php?atmenu=payment&section=10', 'payments'),
                new NavigationItem('DIDs', 'A2B_entity_did.php?section=7', 'dids'),
                new NavigationItem('Trunks', 'A2B_entity_trunk.php?section=7', 'trunks'),
            ], 'operations'),
            new NavigationSection('Customers', [
                new NavigationItem('Customers', 'A2B_entity_card.php?section=1', 'customers'),
                new NavigationItem('VoIP Settings', 'A2B_entity_friend.php?atmenu=sip&section=1', 'voip-settings'),
                new NavigationItem('Invoices', 'A2B_entity_invoice.php?section=11', 'invoices'),
            ], 'customers'),
            new NavigationSection('Rates and Reports', [
                new NavigationItem('Ratecards', 'A2B_entity_tariffplan.php?atmenu=tariffplan&section=6', 'ratecards'),
                new NavigationItem('Rates', 'A2B_entity_def_ratecard.php?atmenu=ratecard&section=6', 'rates'),
                new NavigationItem('CDRs', 'call-log-customers.php?nodisplay=1&posted=1&section=5', 'cdrs'),
            ], 'rates-reports'),
        ];
    }
}

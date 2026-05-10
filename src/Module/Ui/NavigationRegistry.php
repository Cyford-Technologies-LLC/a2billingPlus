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
                new NavigationItem('Payments', 'A2B_payment_workspace.php?section=10', 'payments'),
                new NavigationItem('Themes', 'A2B_ui_theme_manager.php?section=9', 'themes'),
                new NavigationItem('Telephony', 'A2B_telephony_workspace.php?section=7', 'telephony'),
                new NavigationItem('Legacy DIDs', 'A2B_entity_did.php?section=7', 'dids'),
                new NavigationItem('Legacy Trunks', 'A2B_entity_trunk.php?section=7', 'trunks'),
            ], 'operations'),
            new NavigationSection('Customers', [
                new NavigationItem('Customers', 'A2B_customer_workspace.php?section=1', 'customers'),
                new NavigationItem('Legacy VoIP Settings', 'A2B_entity_friend.php?atmenu=sip&section=1', 'voip-settings'),
                new NavigationItem('Invoices', 'A2B_entity_invoice.php?section=11', 'invoices'),
            ], 'customers'),
            new NavigationSection('Rates and Reports', [
                new NavigationItem('Rate Workspace', 'A2B_rate_workspace.php?section=6', 'rates'),
                new NavigationItem('Legacy Ratecards', 'A2B_entity_tariffplan.php?atmenu=tariffplan&section=6', 'ratecards'),
                new NavigationItem('CDRs', 'call-log-customers.php?nodisplay=1&posted=1&section=5', 'cdrs'),
            ], 'rates-reports'),
        ];
    }
}

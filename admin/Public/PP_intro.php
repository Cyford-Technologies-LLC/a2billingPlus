<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * This file is part of A2Billing (http://www.a2billing.net/)
 *
 * A2Billing, Commercial Open Source Telecom Billing platform,
 * powered by Star2billing S.L. <http://www.star2billing.com/>
 *
 * @copyright   Copyright (C) 2004-2015 - Star2billing S.L.
 * @author      Belaid Arezqui <areski@gmail.com>
 * @license     http://www.fsf.org/licensing/licenses/agpl-3.0.html
 * @package     A2Billing
 *
 * Software License Agreement (GNU Affero General Public License)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
**/

include_once '../lib/admin.defines.php';
include_once '../lib/admin.module.access.php';
include_once '../lib/admin.smarty.php';

if (!$ACXACCESS) {
    Header ("HTTP/1.0 401 Unauthorized");
    Header ("Location: PP_error.php?c=accessdenied");
    die();
}

$smarty->display('main.tpl');

?>
<div class="a2bp-intro-page">
    <section class="a2bp-intro-band">
        <div class="a2bp-intro-brand">
            <img src="templates/default/images/a2billingplus-logo.svg" alt="A2BillingPlus" class="a2bp-intro-logo">
            <div>
                <h1>A2BillingPlus</h1>
                <p>VectaVoIP Billing Platform</p>
            </div>
        </div>
        <div class="a2bp-intro-summary">
            <p>This platform includes AGPL-licensed software components.</p>
            <p><a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank">GNU Affero General Public License v3</a></p>
            <p>For VoIP termination, please visit <a href="https://vectavoip.com/" target="_blank">https://vectavoip.com/</a></p>
        </div>
    </section>

    <section class="a2bp-intro-actions" aria-label="Admin shortcuts">
        <a href="A2B_provider_setup.php" class="a2bp-intro-action">
            <span>Provider Setup</span>
            <small>Register, check status, preview rates, and provision VectaVoIP services.</small>
        </a>
        <a href="A2B_ui_theme_manager.php?section=18" class="a2bp-intro-action">
            <span>Theme Manager</span>
            <small>Install modular theme packages and switch the active admin theme.</small>
        </a>
        <a href="dashboard.php" class="a2bp-intro-action">
            <span>Dashboard</span>
            <small>Review traffic, balance, and billing activity from the legacy dashboard.</small>
        </a>
        <a href="A2B_entity_card.php?section=1" class="a2bp-intro-action">
            <span>Customers</span>
            <small>Create, search, activate, and manage customer accounts.</small>
        </a>
        <a href="A2B_customer_workspace.php?section=1" class="a2bp-intro-action">
            <span>Customer Workspace</span>
            <small>Use the modular customer list and detail screens for customer account operations.</small>
        </a>
        <a href="A2B_rate_workspace.php?section=6" class="a2bp-intro-action">
            <span>Rate Workspace</span>
            <small>Search rates, tariff plans, tariff groups, and destination coverage in the modular UI.</small>
        </a>
        <a href="A2B_telephony_workspace.php?section=7" class="a2bp-intro-action">
            <span>Telephony Workspace</span>
            <small>Review trunks, DIDs, SIP and IAX accounts, and Asterisk readiness from the modular UI.</small>
        </a>
        <a href="A2B_entity_trunk.php?section=7" class="a2bp-intro-action">
            <span>Trunks</span>
            <small>Manage provider trunks and routing configuration.</small>
        </a>
    </section>

    <?php if (SHOW_DONATION) { ?>
    <section class="a2bp-intro-support">
        <strong><?php echo gettext("For VectaVoIP platform support, please contact VectaVoIP.");?></strong>
        <a href="https://vectavoip.com/" target="_blank">https://vectavoip.com/</a>
    </section>
    <?php } ?>

    <section class="a2bp-intro-license">
        <h2>License Notice</h2>
        <div class="scroll">
<pre><?php echo (file_get_contents("../lib/COPYING")); ?></pre>
        </div>
    </section>
</div>

<?php

$smarty->display('footer.tpl');

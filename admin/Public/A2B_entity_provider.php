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

include '../lib/admin.defines.php';
include '../lib/admin.module.access.php';
include '../lib/Form/Class.FormHandler.inc.php';
include './form_data/FG_var_provider.inc';
include '../lib/admin.smarty.php';

if (!has_rights(ACX_TRUNK)) {
    Header("HTTP/1.0 401 Unauthorized");
    Header("Location: PP_error.php?c=accessdenied");
    die();
}

$projectRoot = realpath(__DIR__ . '/../..');
$autoloadPath = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_string($autoloadPath) && is_file($autoloadPath)) {
    require_once $autoloadPath;
}

$HD_Form->setDBHandler(DbConnect());
$HD_Form->init();

if ($id != "" || !is_null($id)) {
    $HD_Form->FG_EDITION_CLAUSE = str_replace("%id", "$id", $HD_Form->FG_EDITION_CLAUSE);
}

if (!isset ($form_action)) {
    $form_action = "list"; //ask-add
}
if (!isset ($action)) {
    $action = $form_action;
}

$list = $HD_Form->perform_action($form_action);

// #### HEADER SECTION
$smarty->display('main.tpl');
if ($popup_select) {
?>
<SCRIPT LANGUAGE="javascript">
function sendValue(selvalue) {
    window.opener.document.<?php echo $popup_formname ?>.<?php echo $popup_fieldname ?>.value = selvalue;
    window.close();
}
</script>
<?php

}

// #### HELP SECTION
if (!$popup_select) {
    echo $CC_help_provider;
}

echo $CALL_LABS;

// #### TOP SECTION PAGE
$HD_Form->create_toppage($form_action);

if (!$popup_select && is_file($autoloadPath)) {
    renderBuiltInProvidersPanel();
}

echo "<br/>";

$HD_Form->create_form($form_action, $list, $id = null);

// #### FOOTER SECTION
if (!$popup_select) {
    $smarty->display('footer.tpl');
}

function renderBuiltInProvidersPanel(): void
{
    $providers = builtInProviders();
    if ($providers === []) {
        return;
    }

    ?>
    <style>
        .a2bp-provider-panel { width: 95%; margin: 0 0 16px; border: 1px solid #c7cfdd; background: #f8fafc; }
        .a2bp-provider-panel summary { cursor: pointer; padding: 10px 12px; font-weight: bold; background: #e9eef7; }
        .a2bp-provider-panel summary::-webkit-details-marker { margin-right: 6px; }
        .a2bp-provider-panel-body { padding: 12px; }
        .a2bp-provider-grid { width: 100%; border-collapse: collapse; }
        .a2bp-provider-grid th, .a2bp-provider-grid td { padding: 8px; border-bottom: 1px solid #d9e1ee; text-align: left; vertical-align: top; }
        .a2bp-provider-grid th { background: #f1f5fb; }
        .a2bp-provider-badge { display: inline-block; padding: 2px 8px; border: 1px solid #9fb3d1; background: #eef4ff; font-size: 11px; }
        .a2bp-provider-token { font-family: monospace; word-break: break-all; }
        .a2bp-provider-token[hidden] { display: none; }
        .a2bp-provider-note { margin: 0 0 10px; color: #44536b; }
        .a2bp-provider-action { display: inline-block; padding: 5px 10px; border: 1px solid #7d94b8; background: #eef4ff; color: #24344d; text-decoration: none; }
        .a2bp-provider-action:hover { background: #dde8fb; }
    </style>
    <details class="a2bp-provider-panel" open>
        <summary>Built-in Providers</summary>
        <div class="a2bp-provider-panel-body">
            <p class="a2bp-provider-note">
                Built-in providers are already shipped in code. Use the action button to install, register, or manage the provider for this system.
            </p>
            <table class="a2bp-provider-grid">
                <tr>
                    <th>Provider</th>
                    <th>Status</th>
                    <th>API</th>
                    <th>Configured Token</th>
                    <th>Access</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($providers as $index => $provider): ?>
                <tr>
                    <td>
                        <strong><?php echo h((string)$provider['name']); ?></strong>
                    </td>
                    <td>
                        <span class="a2bp-provider-badge"><?php echo h((string)$provider['status']); ?></span>
                    </td>
                    <td>
                        <?php echo h((string)$provider['api_base_url']); ?><br>
                        <?php echo h((string)$provider['support_email']); ?>
                    </td>
                    <td>
                        <?php if ((string)$provider['token_masked'] !== ''): ?>
                            <button type="button" onclick="document.getElementById('provider-token-<?php echo (int)$index; ?>').hidden = !document.getElementById('provider-token-<?php echo (int)$index; ?>').hidden;">
                                Show Token
                            </button>
                            <div class="a2bp-provider-token" id="provider-token-<?php echo (int)$index; ?>" hidden><?php echo h((string)$provider['token_masked']); ?></div>
                        <?php else: ?>
                            Not configured
                        <?php endif; ?>
                    </td>
                    <td><?php echo h((string)$provider['access_label']); ?></td>
                    <td><a class="a2bp-provider-action" href="<?php echo h((string)$provider['setup_url']); ?>"><?php echo h((string)$provider['action_label']); ?></a></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </details>
    <?php
}

/**
 * @return list<array<string,string>>
 */
function builtInProviders(): array
{
    $actor = trim((string)($_SESSION['pr_login'] ?? ''));
    $config = \A2BillingPlus\Config\AppConfig::fromEnvironment();
    $policy = new \A2BillingPlus\Module\Provider\ProviderAccessPolicy($config);
    $providers = [];

    foreach (\A2BillingPlus\Bootstrap\ProviderRegistryFactory::createDefault()->all() as $connector) {
        $code = $connector->getProviderCode();
        if (!$policy->isAllowed($code, $actor)) {
            continue;
        }

        $token = providerPrimaryToken($config, $code);
        $providers[] = [
            'name' => $connector->getDisplayName(),
            'status' => $token !== '' ? 'Configured' : 'Not configured',
            'api_base_url' => $config->string(strtoupper($code) . '_API_BASE_URL', $connector->getApiBaseUrl()),
            'support_email' => $connector->getSupportEmail(),
            'token_masked' => $token !== '' ? maskProviderToken($token) : '',
            'access_label' => $policy->isLocked($code) ? 'Licensed / company only' : 'Standard',
            'setup_url' => 'A2B_provider_setup.php?provider=' . rawurlencode($code),
            'action_label' => providerActionLabel($code, $token !== ''),
        ];
    }

    return $providers;
}

function providerActionLabel(string $providerCode, bool $configured): string
{
    if ($configured) {
        return 'Manage';
    }

    return match ($providerCode) {
        'vectavoip' => 'Install / Register',
        'didww' => 'Configure / Activate',
        default => 'Open Setup',
    };
}

function providerPrimaryToken(\A2BillingPlus\Config\AppConfig $config, string $providerCode): string
{
    $prefix = strtoupper($providerCode);
    foreach ([$prefix . '_API_KEY', $prefix . '_API_SECRET'] as $key) {
        $value = trim($config->string($key));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function maskProviderToken(string $token): string
{
    $length = strlen($token);
    if ($length <= 8) {
        return $token;
    }

    return substr($token, 0, 4) . str_repeat('*', max(4, $length - 8)) . substr($token, -4);
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

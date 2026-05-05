<?php

declare(strict_types=1);

include_once '../lib/admin.defines.php';
include_once '../lib/admin.module.access.php';
include_once '../lib/admin.smarty.php';

use A2BillingPlus\Admin\ModernAdminPageRenderer;
use A2BillingPlus\Admin\ModernAdminRuntime;
use A2BillingPlus\Module\Telephony\AdminTelephonyWorkspaceService;
use A2BillingPlus\Module\Telephony\AsteriskConfigCheckService;
use A2BillingPlus\Module\Telephony\DidRepository;
use A2BillingPlus\Module\Telephony\DidService;
use A2BillingPlus\Module\Telephony\TelephonyAccountRepository;
use A2BillingPlus\Module\Telephony\TelephonyAccountService;
use A2BillingPlus\Module\Telephony\TrunkRepository;
use A2BillingPlus\Module\Telephony\TrunkService;

$canDid = has_rights(ACX_DID);
$canTrunk = has_rights(ACX_TRUNK);
$canAccounts = has_rights(ACX_CUSTOMER);
if (!$canDid && !$canTrunk && !$canAccounts) {
    Header('HTTP/1.0 401 Unauthorized');
    Header('Location: PP_error.php?c=accessdenied');
    die();
}

$projectRoot = realpath(__DIR__ . '/../..') ?: dirname(__DIR__, 2);
$runtime = new ModernAdminRuntime($projectRoot);
$pageRenderer = new ModernAdminPageRenderer();
$messages = [];
$errors = [];
$theme = $runtime->activeTheme();
$menuStyle = $runtime->activeMenuStyle($theme);
$pdo = null;
$didService = null;
$trunkService = null;
$accountService = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(trim((string)($_POST['form_action'] ?? '')), ['set_ui_theme', 'set_ui_preferences'], true)) {
    try {
        $theme = $runtime->saveUiTheme(trim((string)($_POST['ui_theme'] ?? '')));
        $menuStyle = trim((string)($_POST['form_action'] ?? '')) === 'set_ui_preferences'
            ? $runtime->saveUiMenuStyle(trim((string)($_POST['ui_menu_style'] ?? '')))
            : $runtime->activeMenuStyle($theme);
        $messages[] = 'Saved UI theme: ' . $theme->id() . '.';
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$customerId = trim((string)($_GET['customer_id'] ?? ''));
$trunkStatus = trim((string)($_GET['trunk_status'] ?? ''));
$didReserved = trim((string)($_GET['did_reserved'] ?? ''));
$didActivated = trim((string)($_GET['did_activated'] ?? ''));
$limit = (int)($_GET['limit'] ?? 25);

$workspace = [
    'filters' => ['customer_id' => $customerId, 'trunk_status' => $trunkStatus, 'did_reserved' => $didReserved, 'did_activated' => $didActivated, 'limit' => 25],
    'summary' => ['dids' => 0, 'trunks' => 0, 'sip_accounts' => 0, 'iax_accounts' => 0, 'asterisk_ready' => false],
    'dids' => ['items' => [], 'columns' => []],
    'trunks' => ['items' => [], 'columns' => []],
    'sip_accounts' => ['items' => [], 'columns' => []],
    'iax_accounts' => ['items' => [], 'columns' => []],
    'asterisk' => ['success' => false, 'checks' => []],
];

try {
    $pdo = $runtime->pdo();
    $didService = new DidService(new DidRepository($pdo), $pdo);
    $trunkService = new TrunkService(new TrunkRepository($pdo));
    $accountService = new TelephonyAccountService(new TelephonyAccountRepository($pdo));
    $service = new AdminTelephonyWorkspaceService(
        $didService,
        $trunkService,
        $accountService,
        new AsteriskConfigCheckService()
    );

    $action = trim((string)($_POST['form_action'] ?? ''));
    $actor = 'admin:' . trim((string)($_SESSION['pr_login'] ?? 'system'));
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, ['set_ui_theme', 'set_ui_preferences'], true)) {
        if ($action === 'assign_did' && $canDid) {
            $result = $didService->assign((int)($_POST['did_id'] ?? 0), (int)($_POST['customer_id'] ?? 0), $actor);
            if (($result['body']['success'] ?? false) === true) {
                $messages[] = 'Assigned DID ' . ((string)($result['body']['did']['did'] ?? '')) . '.';
            } else {
                $errors[] = (string)($result['body']['message'] ?? 'Could not assign DID.');
            }
        } elseif ($action === 'release_did' && $canDid) {
            $result = $didService->release((int)($_POST['did_id'] ?? 0), $actor);
            if (($result['body']['success'] ?? false) === true) {
                $messages[] = 'Released DID ' . ((string)($result['body']['did']['did'] ?? '')) . '.';
            } else {
                $errors[] = (string)($result['body']['message'] ?? 'Could not release DID.');
            }
        } elseif ($action === 'update_did_routing' && $canDid) {
            $routingPayload = [
                'customer_id' => trim((string)($_POST['customer_id'] ?? '')),
                'destinations' => [[
                    'destination' => trim((string)($_POST['destination'] ?? '')),
                    'priority' => trim((string)($_POST['priority'] ?? '1')),
                    'voip_call' => trim((string)($_POST['voip_call'] ?? '1')),
                    'activated' => '1',
                    'validated' => '1',
                ]],
            ];
            $result = $didService->updateRouting((int)($_POST['did_id'] ?? 0), $routingPayload, $actor);
            if (($result['body']['success'] ?? false) === true) {
                $messages[] = 'Updated DID routing for ' . ((string)($result['body']['did']['did'] ?? '')) . '.';
            } else {
                $errors[] = (string)($result['body']['message'] ?? 'Could not update DID routing.');
            }
        } elseif ($action === 'update_trunk' && $canTrunk) {
            $trunkPayload = [
                'providerip' => trim((string)($_POST['providerip'] ?? '')),
                'status' => trim((string)($_POST['status'] ?? '')),
                'maxuse' => trim((string)($_POST['maxuse'] ?? '')),
            ];
            $result = $trunkService->update((int)($_POST['trunk_id'] ?? 0), $trunkPayload, $actor);
            if (($result['body']['success'] ?? false) === true) {
                $messages[] = 'Updated trunk ' . ((string)($result['body']['trunk']['trunkcode'] ?? '')) . '.';
            } else {
                $errors[] = (string)($result['body']['message'] ?? 'Could not update trunk.');
            }
        } elseif ($action === 'update_account' && $canAccounts) {
            $accountPayload = [
                'callerid' => trim((string)($_POST['callerid'] ?? '')),
                'context' => trim((string)($_POST['context'] ?? '')),
                'host' => trim((string)($_POST['host'] ?? '')),
                'type' => trim((string)($_POST['type'] ?? '')),
            ];
            $result = $accountService->update(trim((string)($_POST['technology'] ?? '')), (int)($_POST['account_id'] ?? 0), $accountPayload, $actor);
            if (($result['body']['success'] ?? false) === true) {
                $messages[] = 'Updated ' . strtoupper((string)($_POST['technology'] ?? '')) . ' account ' . ((string)($result['body']['account']['username'] ?? '')) . '.';
            } else {
                $errors[] = (string)($result['body']['message'] ?? 'Could not update telephony account.');
            }
        }
    }

    $workspace = $service->workspace(
        $customerId,
        $trunkStatus,
        $didReserved,
        $didActivated,
        $limit,
        ['did' => $canDid, 'trunk' => $canTrunk, 'accounts' => $canAccounts],
        [
            'version' => $runtime->envString('A2BP_ASTERISK_VERSION', '20.0.0'),
            'ami_user' => $runtime->envString('ASTERISK_AMI_USER'),
            'ami_password' => $runtime->envString('ASTERISK_AMI_PASSWORD'),
            'ari_user' => $runtime->envString('ASTERISK_ARI_USER'),
            'ari_password' => $runtime->envString('ASTERISK_ARI_PASSWORD'),
            'channel_driver' => $runtime->envString('A2BP_ASTERISK_CHANNEL_DRIVER', 'pjsip'),
            'realtime_enabled' => $runtime->envString('A2BP_ASTERISK_REALTIME', 'yes'),
        ]
    );
} catch (Throwable $exception) {
    $errors[] = $exception->getMessage();
}

$smarty->display('main.tpl');
echo $pageRenderer->begin(
    $theme,
    $runtime->themeRegistry(),
    'telephony',
    'Telephony Workspace',
    'Review trunks, DIDs, SIP and IAX accounts, and Asterisk launch-readiness checks from the modular telephony services.',
    $menuStyle
);
echo $pageRenderer->renderAlerts($messages, $errors);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rowValue(array $row, string $column): string
{
    $value = $row[$column] ?? '';
    return is_scalar($value) ? (string)$value : '';
}

?>
<div class="a2bp-panel">
    <div class="a2bp-panel__header">
        <h2 class="a2bp-panel__title">Filters</h2>
    </div>
    <div class="a2bp-panel__body">
        <form class="a2bp-form-row" method="get">
            <input type="hidden" name="section" value="7">
            <label>
                Customer ID
                <input type="text" name="customer_id" value="<?php echo h($workspace['filters']['customer_id']); ?>">
            </label>
            <label>
                DID Reserved
                <select name="did_reserved">
                    <option value="" <?php echo $workspace['filters']['did_reserved'] === '' ? 'selected' : ''; ?>>All</option>
                    <option value="1" <?php echo $workspace['filters']['did_reserved'] === '1' ? 'selected' : ''; ?>>Reserved</option>
                    <option value="0" <?php echo $workspace['filters']['did_reserved'] === '0' ? 'selected' : ''; ?>>Available</option>
                </select>
            </label>
            <label>
                DID Activated
                <select name="did_activated">
                    <option value="" <?php echo $workspace['filters']['did_activated'] === '' ? 'selected' : ''; ?>>All</option>
                    <option value="1" <?php echo $workspace['filters']['did_activated'] === '1' ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo $workspace['filters']['did_activated'] === '0' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </label>
            <label>
                Trunk Status
                <select name="trunk_status">
                    <option value="" <?php echo $workspace['filters']['trunk_status'] === '' ? 'selected' : ''; ?>>All</option>
                    <option value="1" <?php echo $workspace['filters']['trunk_status'] === '1' ? 'selected' : ''; ?>>Enabled</option>
                    <option value="0" <?php echo $workspace['filters']['trunk_status'] === '0' ? 'selected' : ''; ?>>Disabled</option>
                </select>
            </label>
            <label>
                Limit
                <select name="limit">
                    <?php foreach ([25, 50, 100] as $option): ?>
                        <option value="<?php echo $option; ?>" <?php echo $workspace['filters']['limit'] === $option ? 'selected' : ''; ?>><?php echo $option; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="a2bp-button" type="submit">Apply</button>
        </form>
    </div>
</div>

<div class="a2bp-metrics">
    <?php if ($canDid): ?>
    <div class="a2bp-metric">
        <span class="a2bp-metric__label">DIDs</span>
        <strong><?php echo h((string)$workspace['summary']['dids']); ?></strong>
    </div>
    <?php endif; ?>
    <?php if ($canTrunk): ?>
    <div class="a2bp-metric">
        <span class="a2bp-metric__label">Trunks</span>
        <strong><?php echo h((string)$workspace['summary']['trunks']); ?></strong>
    </div>
    <?php endif; ?>
    <?php if ($canAccounts): ?>
    <div class="a2bp-metric">
        <span class="a2bp-metric__label">SIP Accounts</span>
        <strong><?php echo h((string)$workspace['summary']['sip_accounts']); ?></strong>
    </div>
    <div class="a2bp-metric">
        <span class="a2bp-metric__label">IAX Accounts</span>
        <strong><?php echo h((string)$workspace['summary']['iax_accounts']); ?></strong>
    </div>
    <?php endif; ?>
    <div class="a2bp-metric">
        <span class="a2bp-metric__label">Asterisk</span>
        <strong><?php echo $workspace['summary']['asterisk_ready'] ? 'Ready' : 'Review'; ?></strong>
    </div>
</div>

<div class="a2bp-panel">
    <div class="a2bp-panel__header">
        <h2 class="a2bp-panel__title">Asterisk Readiness</h2>
    </div>
    <div class="a2bp-panel__body">
        <table class="a2bp-table">
            <thead>
            <tr>
                <th>Check</th>
                <th>Status</th>
                <th>Message</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($workspace['asterisk']['checks'] as $check): ?>
                <tr>
                    <td><?php echo h((string)$check['name']); ?></td>
                    <td><?php echo $check['success'] ? 'OK' : 'Review'; ?></td>
                    <td><?php echo h((string)$check['message']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="a2bp-grid-two">
    <?php if ($canTrunk): ?>
    <div class="a2bp-panel">
        <div class="a2bp-panel__header">
            <h2 class="a2bp-panel__title">Trunks</h2>
        </div>
        <div class="a2bp-panel__body">
            <table class="a2bp-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Code</th>
                    <th>Tech</th>
                    <th>Host</th>
                    <th>Status</th>
                    <th>Max Use</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($workspace['trunks']['items'] as $trunk): ?>
                    <tr>
                        <td><?php echo h(rowValue($trunk, 'id_trunk')); ?></td>
                        <td><?php echo h(rowValue($trunk, 'trunkcode')); ?></td>
                        <td><?php echo h(rowValue($trunk, 'providertech')); ?></td>
                        <td><?php echo h(rowValue($trunk, 'providerip')); ?></td>
                        <td><?php echo rowValue($trunk, 'status') === '1' ? 'Enabled' : 'Disabled'; ?></td>
                        <td><?php echo h(rowValue($trunk, 'maxuse')); ?></td>
                        <td>
                            <form method="post" class="a2bp-form-row">
                                <input type="hidden" name="form_action" value="update_trunk">
                                <input type="hidden" name="trunk_id" value="<?php echo h(rowValue($trunk, 'id_trunk')); ?>">
                                <input type="text" name="providerip" value="<?php echo h(rowValue($trunk, 'providerip')); ?>" placeholder="Provider host">
                                <select name="status">
                                    <option value="1" <?php echo rowValue($trunk, 'status') === '1' ? 'selected' : ''; ?>>Enabled</option>
                                    <option value="0" <?php echo rowValue($trunk, 'status') === '0' ? 'selected' : ''; ?>>Disabled</option>
                                </select>
                                <input type="text" name="maxuse" value="<?php echo h(rowValue($trunk, 'maxuse')); ?>" placeholder="Max use">
                                <button class="a2bp-button" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$workspace['trunks']['items']): ?>
                    <tr>
                        <td colspan="7" class="a2bp-muted">No trunks matched the current filters.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canDid): ?>
    <div class="a2bp-panel">
        <div class="a2bp-panel__header">
            <h2 class="a2bp-panel__title">DIDs</h2>
        </div>
        <div class="a2bp-panel__body">
            <table class="a2bp-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>DID</th>
                    <th>Customer</th>
                    <th>Reserved</th>
                    <th>Active</th>
                    <th>Rate</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($workspace['dids']['items'] as $did): ?>
                    <tr>
                        <td><?php echo h(rowValue($did, 'id')); ?></td>
                        <td><?php echo h(rowValue($did, 'did')); ?></td>
                        <td><?php echo h(rowValue($did, 'iduser')); ?></td>
                        <td><?php echo rowValue($did, 'reserved') === '1' ? 'Reserved' : 'Available'; ?></td>
                        <td><?php echo rowValue($did, 'activated') === '1' ? 'Active' : 'Inactive'; ?></td>
                        <td><?php echo h(rowValue($did, 'fixrate')); ?></td>
                        <td>
                            <?php if (rowValue($did, 'reserved') === '1'): ?>
                                <form method="post" class="a2bp-form-row">
                                    <input type="hidden" name="form_action" value="release_did">
                                    <input type="hidden" name="did_id" value="<?php echo h(rowValue($did, 'id')); ?>">
                                    <button class="a2bp-button" type="submit">Release</button>
                                </form>
                                <form method="post" class="a2bp-form-row">
                                    <input type="hidden" name="form_action" value="update_did_routing">
                                    <input type="hidden" name="did_id" value="<?php echo h(rowValue($did, 'id')); ?>">
                                    <input type="hidden" name="customer_id" value="<?php echo h(rowValue($did, 'iduser')); ?>">
                                    <input type="text" name="destination" value="" placeholder="Route destination">
                                    <input type="text" name="priority" value="1" placeholder="Priority">
                                    <select name="voip_call">
                                        <option value="1">VoIP</option>
                                        <option value="0">PSTN</option>
                                    </select>
                                    <button class="a2bp-button" type="submit">Route</button>
                                </form>
                            <?php else: ?>
                                <form method="post" class="a2bp-form-row">
                                    <input type="hidden" name="form_action" value="assign_did">
                                    <input type="hidden" name="did_id" value="<?php echo h(rowValue($did, 'id')); ?>">
                                    <input type="text" name="customer_id" value="" placeholder="Customer ID">
                                    <button class="a2bp-button" type="submit">Assign</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$workspace['dids']['items']): ?>
                    <tr>
                        <td colspan="7" class="a2bp-muted">No DIDs matched the current filters.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($canAccounts): ?>
<div class="a2bp-grid-two">
    <div class="a2bp-panel">
        <div class="a2bp-panel__header">
            <h2 class="a2bp-panel__title">SIP Accounts</h2>
        </div>
        <div class="a2bp-panel__body">
            <table class="a2bp-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Username</th>
                    <th>Context</th>
                    <th>Host</th>
                    <th>Type</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($workspace['sip_accounts']['items'] as $account): ?>
                    <tr>
                        <td><?php echo h(rowValue($account, 'id')); ?></td>
                        <td><?php echo h(rowValue($account, 'id_cc_card')); ?></td>
                        <td><?php echo h(rowValue($account, 'username')); ?></td>
                        <td><?php echo h(rowValue($account, 'context')); ?></td>
                        <td><?php echo h(rowValue($account, 'host')); ?></td>
                        <td><?php echo h(rowValue($account, 'type')); ?></td>
                        <td>
                            <form method="post" class="a2bp-form-row">
                                <input type="hidden" name="form_action" value="update_account">
                                <input type="hidden" name="technology" value="sip">
                                <input type="hidden" name="account_id" value="<?php echo h(rowValue($account, 'id')); ?>">
                                <input type="text" name="callerid" value="<?php echo h(rowValue($account, 'callerid')); ?>" placeholder="Caller ID">
                                <input type="text" name="context" value="<?php echo h(rowValue($account, 'context')); ?>" placeholder="Context">
                                <input type="text" name="host" value="<?php echo h(rowValue($account, 'host')); ?>" placeholder="Host">
                                <select name="type">
                                    <?php foreach (['friend', 'peer', 'user'] as $type): ?>
                                        <option value="<?php echo $type; ?>" <?php echo rowValue($account, 'type') === $type ? 'selected' : ''; ?>><?php echo strtoupper($type); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="a2bp-button" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$workspace['sip_accounts']['items']): ?>
                    <tr>
                        <td colspan="7" class="a2bp-muted">No SIP accounts matched the current filters.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="a2bp-panel">
        <div class="a2bp-panel__header">
            <h2 class="a2bp-panel__title">IAX Accounts</h2>
        </div>
        <div class="a2bp-panel__body">
            <table class="a2bp-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Username</th>
                    <th>Context</th>
                    <th>Host</th>
                    <th>Type</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($workspace['iax_accounts']['items'] as $account): ?>
                    <tr>
                        <td><?php echo h(rowValue($account, 'id')); ?></td>
                        <td><?php echo h(rowValue($account, 'id_cc_card')); ?></td>
                        <td><?php echo h(rowValue($account, 'username')); ?></td>
                        <td><?php echo h(rowValue($account, 'context')); ?></td>
                        <td><?php echo h(rowValue($account, 'host')); ?></td>
                        <td><?php echo h(rowValue($account, 'type')); ?></td>
                        <td>
                            <form method="post" class="a2bp-form-row">
                                <input type="hidden" name="form_action" value="update_account">
                                <input type="hidden" name="technology" value="iax">
                                <input type="hidden" name="account_id" value="<?php echo h(rowValue($account, 'id')); ?>">
                                <input type="text" name="callerid" value="<?php echo h(rowValue($account, 'callerid')); ?>" placeholder="Caller ID">
                                <input type="text" name="context" value="<?php echo h(rowValue($account, 'context')); ?>" placeholder="Context">
                                <input type="text" name="host" value="<?php echo h(rowValue($account, 'host')); ?>" placeholder="Host">
                                <select name="type">
                                    <?php foreach (['friend', 'peer', 'user'] as $type): ?>
                                        <option value="<?php echo $type; ?>" <?php echo rowValue($account, 'type') === $type ? 'selected' : ''; ?>><?php echo strtoupper($type); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="a2bp-button" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$workspace['iax_accounts']['items']): ?>
                    <tr>
                        <td colspan="7" class="a2bp-muted">No IAX accounts matched the current filters.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="a2bp-panel">
    <div class="a2bp-panel__header">
        <h2 class="a2bp-panel__title">Legacy Fallbacks</h2>
    </div>
    <div class="a2bp-panel__body">
        <p class="a2bp-muted">Use the modular workspace for review and filtering, and fall back to the legacy pages for full edit flows that have not been replaced yet.</p>
        <p>
            <a href="A2B_entity_trunk.php?section=7">Legacy Trunks</a>
            |
            <a href="A2B_entity_did.php?section=7">Legacy DIDs</a>
            |
            <a href="A2B_entity_friend.php?atmenu=sip&section=1">Legacy VoIP Settings</a>
        </p>
    </div>
</div>
<?php

echo $pageRenderer->end();
$smarty->display('footer.tpl');

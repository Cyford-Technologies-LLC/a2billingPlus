<?php

declare(strict_types=1);

include_once '../lib/admin.defines.php';
include_once '../lib/admin.module.access.php';
include_once '../lib/admin.smarty.php';

use A2BillingPlus\Admin\ModernAdminPageRenderer;
use A2BillingPlus\Admin\ModernAdminRuntime;
use A2BillingPlus\Module\Customer\AdminCustomerWorkspaceService;
use A2BillingPlus\Module\Customer\CustomerAccountRepository;
use A2BillingPlus\Module\Customer\CustomerAccountService;

if (!has_rights(ACX_CUSTOMER)) {
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

$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$limit = (int)($_GET['limit'] ?? 25);
$limit = $limit > 0 && $limit <= 100 ? $limit : 25;

$workspace = [
    'filters' => ['search' => $search, 'status' => $status, 'limit' => $limit, 'offset' => 0],
    'summary' => ['total' => 0, 'active' => 0, 'blocked' => 0],
    'customers' => ['items' => [], 'columns' => []],
    'groups' => [],
];

try {
    $service = new AdminCustomerWorkspaceService(
        new CustomerAccountService(new CustomerAccountRepository($runtime->pdo()))
    );
    $workspace = $service->workspace($search, $status, $limit);
} catch (Throwable $exception) {
    $errors[] = $exception->getMessage();
}

$smarty->display('main.tpl');
echo $pageRenderer->begin(
    $theme,
    $runtime->themeRegistry(),
    'customers',
    'Customer Workspace',
    'Search customers, review account status and balance, and open modular customer detail screens.',
    $menuStyle
);
echo $pageRenderer->renderAlerts($messages, $errors);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?>
<div class="a2bp-panel">
    <div class="a2bp-panel__header">
        <h2 class="a2bp-panel__title">Filters</h2>
    </div>
    <div class="a2bp-panel__body">
        <form class="a2bp-form-row" method="get">
            <input type="hidden" name="section" value="1">
            <label>
                Search
                <input type="text" name="search" value="<?php echo h($workspace['filters']['search']); ?>" placeholder="username, alias, name, email">
            </label>
            <label>
                Status
                <select name="status">
                    <option value="" <?php echo $workspace['filters']['status'] === '' ? 'selected' : ''; ?>>All</option>
                    <option value="1" <?php echo $workspace['filters']['status'] === '1' ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo $workspace['filters']['status'] === '0' ? 'selected' : ''; ?>>Blocked</option>
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
    <div class="a2bp-metric">
        <span class="a2bp-metric__label">Customers</span>
        <strong><?php echo h((string)$workspace['summary']['total']); ?></strong>
    </div>
    <div class="a2bp-metric">
        <span class="a2bp-metric__label">Active</span>
        <strong><?php echo h((string)$workspace['summary']['active']); ?></strong>
    </div>
    <div class="a2bp-metric">
        <span class="a2bp-metric__label">Blocked</span>
        <strong><?php echo h((string)$workspace['summary']['blocked']); ?></strong>
    </div>
</div>

<div class="a2bp-panel">
    <div class="a2bp-panel__header">
        <h2 class="a2bp-panel__title">Customer Accounts</h2>
    </div>
    <div class="a2bp-panel__body">
        <table class="a2bp-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Name</th>
                <th>Email</th>
                <th>Credit</th>
                <th>Currency</th>
                <th>Status</th>
                <th>Group</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($workspace['customers']['items'] as $customer): ?>
                <tr>
                    <td><?php echo h((string)($customer['id'] ?? '')); ?></td>
                    <td><?php echo h((string)($customer['username'] ?? '')); ?></td>
                    <td><?php echo h(trim((string)($customer['firstname'] ?? '') . ' ' . (string)($customer['lastname'] ?? ''))); ?></td>
                    <td><?php echo h((string)($customer['email'] ?? '')); ?></td>
                    <td><?php echo h((string)($customer['credit'] ?? '')); ?></td>
                    <td><?php echo h((string)($customer['currency'] ?? '')); ?></td>
                    <td><?php echo ((int)($customer['status'] ?? 0) === 1) ? 'Active' : 'Blocked'; ?></td>
                    <td><?php echo h((string)($customer['id_group'] ?? '')); ?></td>
                    <td><a href="A2B_customer_detail.php?id=<?php echo urlencode((string)($customer['id'] ?? '')); ?>&section=1">Open</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$workspace['customers']['items']): ?>
                <tr>
                    <td colspan="9" class="a2bp-muted">No customers matched the current filters.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php

echo $pageRenderer->end();
$smarty->display('footer.tpl');

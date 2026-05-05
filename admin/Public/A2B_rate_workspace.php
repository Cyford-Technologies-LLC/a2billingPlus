<?php

declare(strict_types=1);

include_once '../lib/admin.defines.php';
include_once '../lib/admin.module.access.php';
include_once '../lib/admin.smarty.php';

use A2BillingPlus\Admin\ModernAdminPageRenderer;
use A2BillingPlus\Admin\ModernAdminRuntime;
use A2BillingPlus\Module\Rate\AdminRateWorkspaceService;
use A2BillingPlus\Module\Rate\RatecardRepository;
use A2BillingPlus\Module\Rate\RatecardSearchService;
use A2BillingPlus\Module\Rate\TariffRepository;
use A2BillingPlus\Module\Rate\TariffService;

if (!has_rights(ACX_RATECARD)) {
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

$prefix = trim((string)($_GET['prefix'] ?? ''));
$tariffPlanId = trim((string)($_GET['tariff_plan_id'] ?? ''));
$tag = trim((string)($_GET['tag'] ?? ''));
$destination = trim((string)($_GET['destination'] ?? ''));
$limit = (int)($_GET['limit'] ?? 25);

$workspace = [
    'filters' => ['prefix' => $prefix, 'tariff_plan_id' => $tariffPlanId, 'tag' => $tag, 'destination' => $destination, 'limit' => 25],
    'summary' => ['rate_rows' => 0, 'tariff_plans' => 0, 'tariff_groups' => 0, 'destinations' => 0],
    'ratecards' => ['items' => [], 'columns' => []],
    'destinations' => ['items' => [], 'columns' => []],
    'tariff_plans' => ['items' => [], 'columns' => []],
    'tariff_groups' => ['items' => [], 'columns' => []],
];

try {
    $pdo = $runtime->pdo();
    $service = new AdminRateWorkspaceService(
        new RatecardSearchService(new RatecardRepository($pdo)),
        new TariffService(new TariffRepository($pdo))
    );
    $workspace = $service->workspace($prefix, $tariffPlanId, $tag, $destination, $limit);
} catch (Throwable $exception) {
    $errors[] = $exception->getMessage();
}

$smarty->display('main.tpl');
echo $pageRenderer->begin(
    $theme,
    $runtime->themeRegistry(),
    'rates',
    'Rate Workspace',
    'Search active rate rows, review tariff plans and groups, and inspect destination coverage from the modular rates service.',
    $menuStyle
);
echo $pageRenderer->renderAlerts($messages, $errors);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function columnValue(array $row, string $column): string
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
            <input type="hidden" name="section" value="6">
            <label>
                Prefix
                <input type="text" name="prefix" value="<?php echo h($workspace['filters']['prefix']); ?>" placeholder="1, 44, 011">
            </label>
            <label>
                Tariff Plan ID
                <input type="text" name="tariff_plan_id" value="<?php echo h($workspace['filters']['tariff_plan_id']); ?>">
            </label>
            <label>
                Tag
                <input type="text" name="tag" value="<?php echo h($workspace['filters']['tag']); ?>" placeholder="VectaVoIP:retail">
            </label>
            <label>
                Destination
                <input type="text" name="destination" value="<?php echo h($workspace['filters']['destination']); ?>" placeholder="United">
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
        <span class="a2bp-metric__label">Rate Rows</span>
        <strong><?php echo h((string)$workspace['summary']['rate_rows']); ?></strong>
    </div>
    <div class="a2bp-metric">
        <span class="a2bp-metric__label">Tariff Plans</span>
        <strong><?php echo h((string)$workspace['summary']['tariff_plans']); ?></strong>
    </div>
    <div class="a2bp-metric">
        <span class="a2bp-metric__label">Tariff Groups</span>
        <strong><?php echo h((string)$workspace['summary']['tariff_groups']); ?></strong>
    </div>
    <div class="a2bp-metric">
        <span class="a2bp-metric__label">Destinations</span>
        <strong><?php echo h((string)$workspace['summary']['destinations']); ?></strong>
    </div>
</div>

<div class="a2bp-panel">
    <div class="a2bp-panel__header">
        <h2 class="a2bp-panel__title">Rate Rows</h2>
    </div>
    <div class="a2bp-panel__body">
        <table class="a2bp-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Plan</th>
                <th>Prefix</th>
                <th>Destination</th>
                <th>Buy Rate</th>
                <th>Initial Rate</th>
                <th>Blocks</th>
                <th>Tag</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($workspace['ratecards']['items'] as $rate): ?>
                <tr>
                    <td><?php echo h(columnValue($rate, 'id')); ?></td>
                    <td><?php echo h(columnValue($rate, 'idtariffplan')); ?></td>
                    <td><?php echo h(columnValue($rate, 'dialprefix')); ?></td>
                    <td><?php echo h(columnValue($rate, 'destination')); ?></td>
                    <td><?php echo h(columnValue($rate, 'buyrate')); ?></td>
                    <td><?php echo h(columnValue($rate, 'rateinitial')); ?></td>
                    <td><?php echo h(columnValue($rate, 'initblock') . '/' . columnValue($rate, 'billingblock')); ?></td>
                    <td><?php echo h(columnValue($rate, 'tag')); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$workspace['ratecards']['items']): ?>
                <tr>
                    <td colspan="8" class="a2bp-muted">No rate rows matched the current filters.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="a2bp-grid-two">
    <div class="a2bp-panel">
        <div class="a2bp-panel__header">
            <h2 class="a2bp-panel__title">Tariff Plans</h2>
        </div>
        <div class="a2bp-panel__body">
            <table class="a2bp-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Trunk</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($workspace['tariff_plans']['items'] as $plan): ?>
                    <tr>
                        <td><?php echo h(columnValue($plan, 'id')); ?></td>
                        <td><?php echo h(columnValue($plan, 'tariffname')); ?></td>
                        <td><?php echo h(columnValue($plan, 'description')); ?></td>
                        <td><?php echo h(columnValue($plan, 'id_trunk')); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$workspace['tariff_plans']['items']): ?>
                    <tr>
                        <td colspan="4" class="a2bp-muted">No tariff plans found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="a2bp-panel">
        <div class="a2bp-panel__header">
            <h2 class="a2bp-panel__title">Tariff Groups</h2>
        </div>
        <div class="a2bp-panel__body">
            <table class="a2bp-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Plan</th>
                    <th>LCR</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($workspace['tariff_groups']['items'] as $group): ?>
                    <tr>
                        <td><?php echo h(columnValue($group, 'id')); ?></td>
                        <td><?php echo h(columnValue($group, 'tariffgroupname')); ?></td>
                        <td><?php echo h(columnValue($group, 'idtariffplan')); ?></td>
                        <td><?php echo h(columnValue($group, 'lcrtype')); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$workspace['tariff_groups']['items']): ?>
                    <tr>
                        <td colspan="4" class="a2bp-muted">No tariff groups found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="a2bp-panel">
    <div class="a2bp-panel__header">
        <h2 class="a2bp-panel__title">Destination Coverage</h2>
    </div>
    <div class="a2bp-panel__body">
        <table class="a2bp-table">
            <thead>
            <tr>
                <th>Destination</th>
                <th>Prefix</th>
                <th>Plan</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($workspace['destinations']['items'] as $destinationRow): ?>
                <tr>
                    <td><?php echo h(columnValue($destinationRow, 'destination')); ?></td>
                    <td><?php echo h(columnValue($destinationRow, 'dialprefix')); ?></td>
                    <td><?php echo h(columnValue($destinationRow, 'idtariffplan')); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$workspace['destinations']['items']): ?>
                <tr>
                    <td colspan="3" class="a2bp-muted">No destinations matched the current filters.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php

echo $pageRenderer->end();
$smarty->display('footer.tpl');

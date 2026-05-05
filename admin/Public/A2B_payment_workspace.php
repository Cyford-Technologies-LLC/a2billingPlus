<?php

declare(strict_types=1);

include_once '../lib/admin.defines.php';
include_once '../lib/admin.module.access.php';
include_once '../lib/admin.smarty.php';

use A2BillingPlus\Admin\ModernAdminRuntime;
use A2BillingPlus\Admin\ModernAdminPageRenderer;
use A2BillingPlus\Module\Payment\AdminPaymentWorkspaceService;
use A2BillingPlus\Module\Payment\PaymentLedgerRepository;
use A2BillingPlus\Module\Payment\PaymentLedgerService;
use A2BillingPlus\Module\Payment\PaymentReconciliationService;

if (!has_rights(ACX_BILLING)) {
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

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$customerId = trim((string)($_GET['customer_id'] ?? ''));
$workspace = [
    'filters' => ['from' => $from, 'to' => $to, 'customer_id' => $customerId],
    'summary' => ['payments' => 0, 'total_paid' => '0.00000', 'total_refilled' => '0.00000', 'difference' => '0.00000'],
    'ledger' => ['items' => [], 'columns' => []],
];

try {
    $pdo = $runtime->pdo();
    $service = new AdminPaymentWorkspaceService(
        new PaymentLedgerService(new PaymentLedgerRepository($pdo)),
        new PaymentReconciliationService($pdo)
    );
    $workspace = $service->workspace($from, $to, $customerId);
} catch (Throwable $exception) {
    $errors[] = $exception->getMessage();
}

$smarty->display('main.tpl');
echo $pageRenderer->begin(
    $theme,
    $runtime->themeRegistry(),
    'payments',
    'Payment Workspace',
    'Hosted/tokenized provider payments, recent ledger activity, and reconciliation totals.',
    $menuStyle
);
echo $pageRenderer->renderAlerts($messages, $errors);

?>
    <div class="a2bp-panel">
        <div class="a2bp-panel__header">
            <h2 class="a2bp-panel__title">Filters</h2>
        </div>
        <div class="a2bp-panel__body">
            <form class="a2bp-form-row" method="get">
                <input type="hidden" name="section" value="10">
                <label>
                    From
                    <input type="text" name="from" value="<?php echo h($workspace['filters']['from']); ?>" placeholder="YYYY-MM-DD">
                </label>
                <label>
                    To
                    <input type="text" name="to" value="<?php echo h($workspace['filters']['to']); ?>" placeholder="YYYY-MM-DD">
                </label>
                <label>
                    Customer ID
                    <input type="text" name="customer_id" value="<?php echo h($workspace['filters']['customer_id']); ?>">
                </label>
                <button class="a2bp-button" type="submit">Apply</button>
            </form>
        </div>
    </div>

    <div class="a2bp-metrics">
        <div class="a2bp-metric">
            <span class="a2bp-metric__label">Payments</span>
            <strong><?php echo h((string)$workspace['summary']['payments']); ?></strong>
        </div>
        <div class="a2bp-metric">
            <span class="a2bp-metric__label">Paid</span>
            <strong><?php echo h($workspace['summary']['total_paid']); ?></strong>
        </div>
        <div class="a2bp-metric">
            <span class="a2bp-metric__label">Refilled</span>
            <strong><?php echo h($workspace['summary']['total_refilled']); ?></strong>
        </div>
        <div class="a2bp-metric">
            <span class="a2bp-metric__label">Difference</span>
            <strong><?php echo h($workspace['summary']['difference']); ?></strong>
        </div>
    </div>

    <div class="a2bp-panel">
        <div class="a2bp-panel__header">
            <h2 class="a2bp-panel__title">Recent Payments</h2>
        </div>
        <div class="a2bp-panel__body">
            <table class="a2bp-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Payment</th>
                        <th>Refill</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($workspace['ledger']['items'] as $payment): ?>
                    <tr>
                        <td><?php echo h((string)($payment['date'] ?? '')); ?></td>
                        <td><?php echo h((string)($payment['card_id'] ?? '')); ?></td>
                        <td><?php echo h((string)($payment['payment'] ?? '')); ?></td>
                        <td><?php echo h((string)($payment['added_refill'] ?? '')); ?></td>
                        <td><?php echo h((string)($payment['description'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$workspace['ledger']['items']): ?>
                    <tr>
                        <td colspan="5" class="a2bp-muted">No payments found for the selected filters.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php

echo $pageRenderer->end();

$smarty->display('footer.tpl');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

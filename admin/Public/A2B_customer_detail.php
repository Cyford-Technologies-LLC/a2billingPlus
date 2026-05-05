<?php

declare(strict_types=1);

include_once '../lib/admin.defines.php';
include_once '../lib/admin.module.access.php';
include_once '../lib/admin.smarty.php';

use A2BillingPlus\Admin\ModernAdminPageRenderer;
use A2BillingPlus\Admin\ModernAdminRuntime;
use A2BillingPlus\Module\Customer\CustomerAccountRepository;
use A2BillingPlus\Module\Customer\CustomerAccountService;
use A2BillingPlus\Module\Security\AuditLogRepository;

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
$customer = null;
$groups = [];
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

try {
    $pdo = $runtime->pdo();
    $service = new CustomerAccountService(
        new CustomerAccountRepository($pdo),
        new AuditLogRepository($pdo)
    );
    $groups = $service->groups();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $formAction = trim((string)($_POST['form_action'] ?? ''));
        if (in_array($formAction, ['set_ui_theme', 'set_ui_preferences'], true)) {
            $theme = $runtime->saveUiTheme(trim((string)($_POST['ui_theme'] ?? '')));
            $menuStyle = $formAction === 'set_ui_preferences'
                ? $runtime->saveUiMenuStyle(trim((string)($_POST['ui_menu_style'] ?? '')))
                : $runtime->activeMenuStyle($theme);
            $messages[] = 'Saved UI theme: ' . $theme->id() . '.';
        } elseif ($id > 0 && $formAction === 'save_customer') {
            $result = $service->update($id, [
                'firstname' => trim((string)($_POST['firstname'] ?? '')),
                'lastname' => trim((string)($_POST['lastname'] ?? '')),
                'email' => trim((string)($_POST['email'] ?? '')),
                'address' => trim((string)($_POST['address'] ?? '')),
                'city' => trim((string)($_POST['city'] ?? '')),
                'state' => trim((string)($_POST['state'] ?? '')),
                'country' => trim((string)($_POST['country'] ?? '')),
                'zipcode' => trim((string)($_POST['zipcode'] ?? '')),
                'phone' => trim((string)($_POST['phone'] ?? '')),
                'company_name' => trim((string)($_POST['company_name'] ?? '')),
                'company_website' => trim((string)($_POST['company_website'] ?? '')),
                'currency' => trim((string)($_POST['currency'] ?? '')),
                'id_group' => trim((string)($_POST['id_group'] ?? '')),
                'status' => trim((string)($_POST['status'] ?? '')),
            ], 'admin:ui');
            if ($result['status'] !== 200) {
                $errors[] = (string)($result['body']['message'] ?? 'Customer update failed.');
            } else {
                $messages[] = 'Customer details saved.';
            }
        } elseif ($id > 0 && in_array($formAction, ['activate_customer', 'block_customer'], true)) {
            $status = $formAction === 'activate_customer' ? 1 : 0;
            $customer = $service->changeStatus($id, $status, 'admin:ui');
            if ($customer === null) {
                $errors[] = 'Customer was not found.';
            } else {
                $messages[] = $status === 1 ? 'Customer activated.' : 'Customer blocked.';
            }
        }
    }

    if ($id > 0 && $customer === null) {
        $customer = $service->detail($id);
    }
} catch (Throwable $exception) {
    $errors[] = $exception->getMessage();
}

$smarty->display('main.tpl');
echo $pageRenderer->begin(
    $theme,
    $runtime->themeRegistry(),
    'customers',
    'Customer Detail',
    'Review and update customer account profile, billing contact data, and account status.',
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
        <h2 class="a2bp-panel__title">Account</h2>
    </div>
    <div class="a2bp-panel__body">
        <?php if (!$customer): ?>
            <div class="a2bp-alert a2bp-alert--error">Customer was not found.</div>
        <?php else: ?>
            <div class="a2bp-metrics">
                <div class="a2bp-metric">
                    <span class="a2bp-metric__label">Username</span>
                    <strong><?php echo h((string)($customer['username'] ?? '')); ?></strong>
                </div>
                <div class="a2bp-metric">
                    <span class="a2bp-metric__label">Status</span>
                    <strong><?php echo ((int)($customer['status'] ?? 0) === 1) ? 'Active' : 'Blocked'; ?></strong>
                </div>
                <div class="a2bp-metric">
                    <span class="a2bp-metric__label">Credit</span>
                    <strong><?php echo h((string)($customer['credit'] ?? '')); ?></strong>
                </div>
                <div class="a2bp-metric">
                    <span class="a2bp-metric__label">Currency</span>
                    <strong><?php echo h((string)($customer['currency'] ?? '')); ?></strong>
                </div>
            </div>

            <form method="post">
                <input type="hidden" name="id" value="<?php echo h((string)$id); ?>">
                <input type="hidden" name="form_action" value="save_customer">
                <table class="a2bp-table">
                    <tbody>
                    <tr>
                        <td>First Name</td>
                        <td><input type="text" name="firstname" value="<?php echo h((string)($customer['firstname'] ?? '')); ?>"></td>
                        <td>Last Name</td>
                        <td><input type="text" name="lastname" value="<?php echo h((string)($customer['lastname'] ?? '')); ?>"></td>
                    </tr>
                    <tr>
                        <td>Email</td>
                        <td><input type="text" name="email" value="<?php echo h((string)($customer['email'] ?? '')); ?>"></td>
                        <td>Phone</td>
                        <td><input type="text" name="phone" value="<?php echo h((string)($customer['phone'] ?? '')); ?>"></td>
                    </tr>
                    <tr>
                        <td>Address</td>
                        <td><input type="text" name="address" value="<?php echo h((string)($customer['address'] ?? '')); ?>"></td>
                        <td>City</td>
                        <td><input type="text" name="city" value="<?php echo h((string)($customer['city'] ?? '')); ?>"></td>
                    </tr>
                    <tr>
                        <td>State</td>
                        <td><input type="text" name="state" value="<?php echo h((string)($customer['state'] ?? '')); ?>"></td>
                        <td>Country</td>
                        <td><input type="text" name="country" value="<?php echo h((string)($customer['country'] ?? '')); ?>"></td>
                    </tr>
                    <tr>
                        <td>Zipcode</td>
                        <td><input type="text" name="zipcode" value="<?php echo h((string)($customer['zipcode'] ?? '')); ?>"></td>
                        <td>Currency</td>
                        <td><input type="text" name="currency" value="<?php echo h((string)($customer['currency'] ?? '')); ?>"></td>
                    </tr>
                    <tr>
                        <td>Company</td>
                        <td><input type="text" name="company_name" value="<?php echo h((string)($customer['company_name'] ?? '')); ?>"></td>
                        <td>Website</td>
                        <td><input type="text" name="company_website" value="<?php echo h((string)($customer['company_website'] ?? '')); ?>"></td>
                    </tr>
                    <tr>
                        <td>Group</td>
                        <td>
                            <select name="id_group">
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo h($group['id']); ?>" <?php echo (string)($customer['id_group'] ?? '') === $group['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($group['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>Status</td>
                        <td>
                            <select name="status">
                                <option value="1" <?php echo (int)($customer['status'] ?? 0) === 1 ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo (int)($customer['status'] ?? 0) === 0 ? 'selected' : ''; ?>>Blocked</option>
                            </select>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <div style="margin-top:12px;">
                    <button class="a2bp-button" type="submit">Save Customer</button>
                    <a href="A2B_customer_workspace.php?section=1">Back to Workspace</a>
                </div>
            </form>

            <form method="post" style="margin-top:12px;">
                <input type="hidden" name="id" value="<?php echo h((string)$id); ?>">
                <?php if ((int)($customer['status'] ?? 0) === 1): ?>
                    <input type="hidden" name="form_action" value="block_customer">
                    <button class="a2bp-button" type="submit">Block Customer</button>
                <?php else: ?>
                    <input type="hidden" name="form_action" value="activate_customer">
                    <button class="a2bp-button" type="submit">Activate Customer</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php

echo $pageRenderer->end();
$smarty->display('footer.tpl');

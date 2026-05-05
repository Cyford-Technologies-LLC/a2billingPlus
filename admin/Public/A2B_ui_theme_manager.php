<?php

declare(strict_types=1);

include_once '../lib/admin.defines.php';
include_once '../lib/admin.module.access.php';
include_once '../lib/admin.smarty.php';

use A2BillingPlus\Admin\ModernAdminPageRenderer;
use A2BillingPlus\Admin\ModernAdminRuntime;
use A2BillingPlus\Module\Ui\ThemeManagerService;

if (!has_rights(ACX_ACXSETTING)) {
    Header('HTTP/1.0 401 Unauthorized');
    Header('Location: PP_error.php?c=accessdenied');
    die();
}

$projectRoot = realpath(__DIR__ . '/../..') ?: dirname(__DIR__, 2);
$runtime = new ModernAdminRuntime($projectRoot);
$pageRenderer = new ModernAdminPageRenderer();
$themeManager = new ThemeManagerService($projectRoot, $runtime->themeRegistry());
$messages = [];
$errors = [];
$theme = $runtime->activeTheme();
$menuStyle = $runtime->activeMenuStyle($theme);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = trim((string)($_POST['form_action'] ?? ''));

    if (in_array($formAction, ['set_ui_theme', 'set_ui_preferences'], true)) {
        try {
            $theme = $runtime->saveUiTheme(trim((string)($_POST['ui_theme'] ?? '')));
            $menuStyle = $formAction === 'set_ui_preferences'
                ? $runtime->saveUiMenuStyle(trim((string)($_POST['ui_menu_style'] ?? '')))
                : $runtime->activeMenuStyle($theme);
            $messages[] = 'Saved UI theme: ' . $theme->id() . '.';
            $themeManager = new ThemeManagerService($projectRoot, $runtime->themeRegistry());
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    } elseif ($formAction === 'install_theme_package') {
        $upload = $_FILES['theme_package'] ?? null;
        if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Choose a zip theme package to upload.';
        } else {
            try {
                $installed = $themeManager->installUploadedTheme((string)$upload['tmp_name'], (string)$upload['name']);
                $messages[] = sprintf(
                    'Installed theme %s (%s) version %s.',
                    $installed['name'],
                    $installed['id'],
                    $installed['version']
                );
                $runtime = new ModernAdminRuntime($projectRoot);
                $theme = $runtime->activeTheme();
                $menuStyle = $runtime->activeMenuStyle($theme);
                $themeManager = new ThemeManagerService($projectRoot, $runtime->themeRegistry());
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }
    }
}

$themes = $themeManager->listThemes($theme->id());

$smarty->display('main.tpl');
echo $pageRenderer->begin(
    $theme,
    $runtime->themeRegistry(),
    'themes',
    'UI Theme Manager',
    'Install modular theme packages, review installed themes, and switch the active admin theme.',
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
        <h2 class="a2bp-panel__title">Install Theme Package</h2>
    </div>
    <div class="a2bp-panel__body">
        <form method="post" enctype="multipart/form-data" class="a2bp-form-row">
            <input type="hidden" name="form_action" value="install_theme_package">
            <label>
                Theme Package
                <input type="file" name="theme_package" accept=".zip,application/zip">
            </label>
            <button class="a2bp-button" type="submit">Upload and Install</button>
        </form>
        <p class="a2bp-muted">Theme packages must include a `theme.json` manifest with `id`, `name`, `version`, `ui_contract_version`, and `stylesheet`.</p>
    </div>
</div>

<div class="a2bp-panel">
    <div class="a2bp-panel__header">
        <h2 class="a2bp-panel__title">Installed Themes</h2>
    </div>
    <div class="a2bp-panel__body">
        <table class="a2bp-table">
            <thead>
            <tr>
                <th>Name</th>
                <th>ID</th>
                <th>Version</th>
                <th>Type</th>
                <th>Default Menu</th>
                <th>Stylesheet</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($themes as $installedTheme): ?>
                <tr>
                    <td>
                        <strong><?php echo h($installedTheme['name']); ?></strong>
                        <?php if ($installedTheme['description'] !== ''): ?>
                            <div class="a2bp-muted"><?php echo h($installedTheme['description']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo h($installedTheme['id']); ?></td>
                    <td><?php echo h($installedTheme['version'] !== '' ? $installedTheme['version'] : '-'); ?></td>
                    <td><?php echo $installedTheme['built_in'] ? 'Built-in' : 'Custom'; ?></td>
                    <td><?php echo h($installedTheme['menu_style']); ?></td>
                    <td><?php echo h($installedTheme['stylesheet']); ?></td>
                    <td><?php echo $installedTheme['active'] ? 'Active' : 'Installed'; ?></td>
                    <td>
                        <?php if (!$installedTheme['active']): ?>
                            <form method="post">
                                <input type="hidden" name="form_action" value="set_ui_theme">
                                <input type="hidden" name="ui_theme" value="<?php echo h($installedTheme['id']); ?>">
                                <button class="a2bp-button" type="submit">Activate</button>
                            </form>
                        <?php else: ?>
                            <span class="a2bp-muted">Current</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php

echo $pageRenderer->end();
$smarty->display('footer.tpl');

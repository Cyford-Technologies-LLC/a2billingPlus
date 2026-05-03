<?php

declare(strict_types=1);

include_once '../lib/admin.defines.php';
include_once '../lib/admin.module.access.php';
include_once '../lib/admin.smarty.php';

use A2BillingPlus\Api\ProviderApiController;
use A2BillingPlus\Bootstrap\ProviderRegistryFactory;
use A2BillingPlus\Module\Provider\ProviderSetupService;

if (!has_rights(ACX_ACXSETTING)) {
    Header('HTTP/1.0 401 Unauthorized');
    Header('Location: PP_error.php?c=accessdenied');
    die();
}

$projectRoot = realpath(__DIR__ . '/../..');
$autoloadPath = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}

$messages = [];
$errors = [];
$registration = [];
$ratePreview = [];
$rateImport = [];
$envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';

$defaults = [
    'base_url' => getenv('VECTAVOIP_API_BASE_URL') ?: 'https://api.vectavoip.com',
    'api_key' => getenv('VECTAVOIP_API_KEY') ?: '',
    'api_secret' => getenv('VECTAVOIP_API_SECRET') ?: '',
    'company_name' => 'VectaVoIP',
    'company_domain' => 'VectaVoIP.com',
    'contact_name' => '',
    'contact_email' => '',
    'contact_phone' => '',
    'details' => '',
    'install_key' => getenv('VECTAVOIP_INSTALL_KEY') ?: '',
    'target_ratecard_id' => '',
    'rate_deck' => 'retail',
    'currency' => 'USD',
    'destination_filter' => '',
    'update_existing' => '',
    'save_credentials' => '1',
];

$input = $defaults;
$providerSetup = providerSetupService();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = trim((string)($_POST['form_action'] ?? ''));

    foreach ($defaults as $key => $default) {
        $input[$key] = trim((string)($_POST[$key] ?? ''));
    }
    $input['save_credentials'] = isset($_POST['save_credentials']) ? '1' : '';
    $input['update_existing'] = isset($_POST['update_existing']) ? '1' : '';

    if ($input['base_url'] === '') {
        $errors[] = 'Provider API base URL is required.';
    }

    if ($formAction === 'register_provider') {
        if ($input['company_name'] === '') {
            $errors[] = 'Company name is required.';
        }
        if ($input['contact_name'] === '') {
            $errors[] = 'Contact name is required.';
        }
        if ($input['contact_email'] === '' || !filter_var($input['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid contact email is required.';
        }
    }

    if (!$errors && $formAction === 'register_provider') {
        $registration = $providerSetup->registerInstall($input);
        if (($registration['success'] ?? false) !== true) {
            $errors[] = (string)($registration['message'] ?? 'Provider registration failed.');
        } else {
            $messages[] = (string)($registration['message'] ?? 'Provider registration completed.');

            if ($input['save_credentials'] === '1') {
                saveProviderCredentials($envPath, $input['base_url'], $registration, $messages, $errors);
            }
        }
    }

    if (!$errors && $formAction === 'preview_rates') {
        $ratePreview = $providerSetup->previewRates($input);
        if (isset($ratePreview['error'])) {
            $errors[] = (string)$ratePreview['error'];
        } else {
            $messages[] = (string)($ratePreview['message'] ?? 'Rate preview completed.');
        }
    }

    if (!$errors && in_array($formAction, ['dry_run_import_rates', 'import_rates'], true)) {
        if ((int)$input['target_ratecard_id'] <= 0) {
            $errors[] = 'Target ratecard ID is required for import.';
        }
        if (!$errors) {
            $rateImport = $providerSetup->importPreviewRates($input, $formAction === 'dry_run_import_rates');
            if (($rateImport['success'] ?? false) !== true) {
                $errors[] = (string)($rateImport['message'] ?? 'Rate import failed.');
            } else {
                $messages[] = (string)($rateImport['message'] ?? 'Rate import completed.');
            }
        }
    }
}

$status = $providerSetup->providerStatus();
$ratecards = $providerSetup->ratecards();
$recentImports = $providerSetup->recentImports();

$smarty->display('main.tpl');

function providerSetupService(): ProviderSetupService
{
    $pdoFactory = fn (): PDO => providerSetupPdo();

    return new ProviderSetupService(
        new ProviderApiController(ProviderRegistryFactory::createDefault(), null, $pdoFactory),
        $pdoFactory
    );
}

function providerSetupPdo(): PDO
{
    $dsn = envString('A2BP_DB_DSN');
    if ($dsn === '') {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            envString('A2BP_DB_HOST', 'db'),
            envString('A2BP_DB_NAME', 'mya2billing')
        );
    }

    return new PDO($dsn, envString('A2BP_DB_USER', 'a2billinguser'), envString('A2BP_DB_PASSWORD', 'a2billing'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function envString(string $key, string $default = ''): string
{
    $value = getenv($key);
    return is_string($value) && $value !== '' ? $value : $default;
}

function saveProviderCredentials(string $envPath, string $baseUrl, array $registration, array &$messages, array &$errors): void
{
    if (!is_writable(dirname($envPath)) || (is_file($envPath) && !is_writable($envPath))) {
        $errors[] = '.env is not writable. Provider credentials were not saved.';
        return;
    }

    $values = [
        'VECTAVOIP_API_BASE_URL' => $baseUrl,
        'VECTAVOIP_INSTALL_KEY' => (string)($registration['install_key'] ?? ''),
        'VECTAVOIP_INSTALLATION_ID' => (string)($registration['installation_id'] ?? ''),
        'VECTAVOIP_API_KEY' => (string)($registration['api_key'] ?? ''),
        'VECTAVOIP_API_SECRET' => (string)($registration['api_secret'] ?? ''),
    ];

    $contents = is_file($envPath) ? (string)file_get_contents($envPath) : '';
    $contents = mergeEnvValues($contents, $values);

    if (@file_put_contents($envPath, $contents) === false) {
        $errors[] = 'Could not write .env. Provider credentials were not saved.';
        return;
    }

    foreach ($values as $key => $value) {
        putenv($key . '=' . $value);
    }

    $messages[] = 'Saved VectaVoIP provider credentials to .env.';
}

function mergeEnvValues(string $contents, array $values): string
{
    $lines = preg_split('/\r\n|\r|\n/', $contents);
    $seen = [];

    foreach ($lines as $index => $line) {
        if (!preg_match('/^([A-Z0-9_]+)=/', $line, $matches)) {
            continue;
        }

        $key = $matches[1];
        if (array_key_exists($key, $values)) {
            $lines[$index] = $key . '=' . envValue((string)$values[$key]);
            $seen[$key] = true;
        }
    }

    foreach ($values as $key => $value) {
        if (!isset($seen[$key])) {
            $lines[] = $key . '=' . envValue((string)$value);
        }
    }

    return rtrim(implode(PHP_EOL, $lines)) . PHP_EOL;
}

function envValue(string $value): string
{
    if ($value === '' || preg_match('/[\s#="\']/', $value)) {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    return $value;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?>
<br>
<div class="toggle_show2hide">
    <div class="tohide" style="display:visible;">
        <div class="msg_info">
            Register this A2BillingPlus install with VectaVoIP and store provider API credentials for rate imports.
        </div>
    </div>
</div>

<table width="95%" class="toppage_customaction">
    <tr>
        <td class="form_head">VectaVoIP Provider Setup</td>
    </tr>
    <tr>
        <td class="tdstyle_001">
            <?php foreach ($messages as $message): ?>
                <div style="margin:10px 0;padding:10px;border:1px solid #abefc6;background:#ecfdf3;color:#065f46;">
                    <?php echo h($message); ?>
                </div>
            <?php endforeach; ?>

            <?php foreach ($errors as $error): ?>
                <div style="margin:10px 0;padding:10px;border:1px solid #fecdca;background:#fef3f2;color:#912018;">
                    <?php echo h($error); ?>
                </div>
            <?php endforeach; ?>

            <table width="100%" cellspacing="0" cellpadding="8">
                <tr>
                    <td width="220"><strong>Status</strong></td>
                    <td><?php echo !empty($status['registered']) ? 'Registered' : 'Not registered'; ?></td>
                </tr>
                <tr>
                    <td><strong>API Base URL</strong></td>
                    <td><?php echo h((string)($status['api_base_url'] ?? '')); ?></td>
                </tr>
                <tr>
                    <td><strong>Installation ID</strong></td>
                    <td><?php echo h((string)($status['installation_id'] ?? '')); ?></td>
                </tr>
                <tr>
                    <td><strong>Support Email</strong></td>
                    <td><?php echo h((string)($status['support_email'] ?? 'info@VectaVoIP.com')); ?></td>
                </tr>
            </table>

            <br>
            <form method="post">
                <input type="hidden" name="form_action" value="register_provider">
                <table width="100%" cellspacing="0" cellpadding="8">
                    <tr>
                        <td width="220"><label for="base_url">API Base URL</label></td>
                        <td>
                            <input id="base_url" name="base_url" type="text" size="70" value="<?php echo h($input['base_url']); ?>">
                            <br><span style="color:#666;">Sandbox inside Docker: http://localhost/api/sandbox</span>
                            <br><span style="color:#666;">Production-compatible local API: http://localhost/api/vectavoip</span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="company_name">Company Name</label></td>
                        <td><input id="company_name" name="company_name" type="text" size="70" value="<?php echo h($input['company_name']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="company_domain">Company Domain</label></td>
                        <td><input id="company_domain" name="company_domain" type="text" size="70" value="<?php echo h($input['company_domain']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="contact_name">Contact Name</label></td>
                        <td><input id="contact_name" name="contact_name" type="text" size="70" value="<?php echo h($input['contact_name']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="contact_email">Contact Email</label></td>
                        <td><input id="contact_email" name="contact_email" type="text" size="70" value="<?php echo h($input['contact_email']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="contact_phone">Contact Phone</label></td>
                        <td><input id="contact_phone" name="contact_phone" type="text" size="70" value="<?php echo h($input['contact_phone']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="install_key">Install Key</label></td>
                        <td>
                            <input id="install_key" name="install_key" type="text" size="70" value="<?php echo h($input['install_key']); ?>">
                            <br><span style="color:#666;">Leave blank to generate a new key.</span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="details">Details</label></td>
                        <td><textarea id="details" name="details" rows="4" cols="72"><?php echo h($input['details']); ?></textarea></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                            <label>
                                <input name="save_credentials" type="checkbox" value="1" <?php echo $input['save_credentials'] === '1' ? 'checked' : ''; ?>>
                                Save returned credentials to .env
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td><input class="form_input_button" type="submit" value="Register Provider"></td>
                    </tr>
                </table>
            </form>

            <br>
            <table width="100%" cellspacing="0" cellpadding="8" style="border-top:1px solid #ddd;">
                <tr>
                    <td class="form_head" colspan="2">VectaVoIP Rate Preview and Import</td>
                </tr>
            </table>
            <form method="post">
                <table width="100%" cellspacing="0" cellpadding="8">
                    <tr>
                        <td width="220"><label for="rate_base_url">API Base URL</label></td>
                        <td>
                            <input id="rate_base_url" name="base_url" type="text" size="70" value="<?php echo h($input['base_url']); ?>">
                            <br><span style="color:#666;">Sandbox inside Docker: http://localhost/api/sandbox</span>
                            <br><span style="color:#666;">Production-compatible local API: http://localhost/api/vectavoip</span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="api_key">API Key</label></td>
                        <td><input id="api_key" name="api_key" type="text" size="70" value="<?php echo h($input['api_key']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="api_secret">API Secret</label></td>
                        <td><input id="api_secret" name="api_secret" type="password" size="70" value="<?php echo h($input['api_secret']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="rate_deck">Rate Deck</label></td>
                        <td><input id="rate_deck" name="rate_deck" type="text" size="35" value="<?php echo h($input['rate_deck']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="currency">Currency</label></td>
                        <td><input id="currency" name="currency" type="text" size="10" value="<?php echo h($input['currency']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="destination_filter">Destination Filter</label></td>
                        <td><input id="destination_filter" name="destination_filter" type="text" size="35" value="<?php echo h($input['destination_filter']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="target_ratecard_id">Target Ratecard ID</label></td>
                        <td>
                            <?php if ($ratecards): ?>
                                <select id="target_ratecard_id" name="target_ratecard_id">
                                    <option value="">Select a ratecard</option>
                                    <?php foreach ($ratecards as $ratecard): ?>
                                        <option value="<?php echo h($ratecard['id']); ?>" <?php echo $input['target_ratecard_id'] === $ratecard['id'] ? 'selected' : ''; ?>>
                                            <?php echo h($ratecard['name'] . ' (#' . $ratecard['id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input id="target_ratecard_id" name="target_ratecard_id" type="text" size="10" value="<?php echo h($input['target_ratecard_id']); ?>">
                            <?php endif; ?>
                            <br><span style="color:#666;">Required for dry-run import and import. Create ratecards under Rates &gt; RateCards.</span>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                            <label>
                                <input name="update_existing" type="checkbox" value="1" <?php echo $input['update_existing'] === '1' ? 'checked' : ''; ?>>
                                Update existing rows with the same ratecard, prefix, and VectaVoIP tag
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                            <button class="form_input_button" name="form_action" type="submit" value="preview_rates">Preview Rates</button>
                            <button class="form_input_button" name="form_action" type="submit" value="dry_run_import_rates">Dry Run Import</button>
                            <button class="form_input_button" name="form_action" type="submit" value="import_rates" onclick="return confirm('Import provider rates into cc_ratecard now?');">Import Rates</button>
                        </td>
                    </tr>
                </table>
            </form>

            <?php if ($ratePreview): ?>
                <br>
                <table width="100%" cellspacing="0" cellpadding="6" border="0">
                    <tr>
                        <td class="form_head" colspan="6">Rate Preview</td>
                    </tr>
                    <tr>
                        <td colspan="6">
                            <?php echo h((string)($ratePreview['message'] ?? '')); ?>
                            Total rows: <?php echo h((string)($ratePreview['total_rows'] ?? 0)); ?>
                        </td>
                    </tr>
                    <tr style="font-weight:bold;">
                        <td>Destination</td>
                        <td>Prefix</td>
                        <td>Rate</td>
                        <td>Currency</td>
                        <td>Increment</td>
                        <td>Deck</td>
                    </tr>
                    <?php foreach (($ratePreview['sample_rows'] ?? []) as $row): ?>
                        <?php if (is_array($row)): ?>
                            <tr>
                                <td><?php echo h((string)($row['destination'] ?? '')); ?></td>
                                <td><?php echo h((string)($row['prefix'] ?? '')); ?></td>
                                <td><?php echo h((string)($row['rate'] ?? '')); ?></td>
                                <td><?php echo h((string)($row['currency'] ?? '')); ?></td>
                                <td><?php echo h((string)($row['increment'] ?? '')); ?></td>
                                <td><?php echo h((string)($row['rate_deck'] ?? '')); ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <?php if ($rateImport): ?>
                <br>
                <table width="100%" cellspacing="0" cellpadding="6" border="0">
                    <tr>
                        <td class="form_head" colspan="2">Import Result</td>
                    </tr>
                    <tr>
                        <td width="220">Mode</td>
                        <td><?php echo !empty($rateImport['dry_run']) ? 'Dry run' : 'Write'; ?></td>
                    </tr>
                    <tr>
                        <td>Duplicate Handling</td>
                        <td><?php echo !empty($rateImport['update_existing']) ? 'Update existing' : 'Skip existing'; ?></td>
                    </tr>
                    <tr>
                        <td>Imported Rows</td>
                        <td><?php echo h((string)($rateImport['imported_rows'] ?? 0)); ?></td>
                    </tr>
                    <tr>
                        <td>Skipped Rows</td>
                        <td><?php echo h((string)($rateImport['skipped_rows'] ?? 0)); ?></td>
                    </tr>
                </table>
            <?php endif; ?>

            <?php if ($recentImports): ?>
                <br>
                <table width="100%" cellspacing="0" cellpadding="6" border="0">
                    <tr>
                        <td class="form_head" colspan="8">Recent Provider Imports</td>
                    </tr>
                    <tr style="font-weight:bold;">
                        <td>Date</td>
                        <td>Provider</td>
                        <td>Deck</td>
                        <td>Ratecard</td>
                        <td>Mode</td>
                        <td>Status</td>
                        <td>Rows</td>
                        <td>Message</td>
                    </tr>
                    <?php foreach ($recentImports as $recentImport): ?>
                        <tr>
                            <td><?php echo h((string)($recentImport['created_at'] ?? '')); ?></td>
                            <td><?php echo h((string)($recentImport['provider'] ?? '')); ?></td>
                            <td><?php echo h((string)($recentImport['rate_deck'] ?? '')); ?></td>
                            <td><?php echo h((string)($recentImport['target_ratecard_id'] ?? '')); ?></td>
                            <td><?php echo !empty($recentImport['dry_run']) ? 'Dry run' : 'Write'; ?></td>
                            <td><?php echo !empty($recentImport['success']) ? 'OK' : 'Failed'; ?></td>
                            <td><?php echo h((string)($recentImport['imported_rows'] ?? 0)); ?> / <?php echo h((string)($recentImport['skipped_rows'] ?? 0)); ?></td>
                            <td><?php echo h((string)($recentImport['message'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </td>
    </tr>
</table>

<?php

$smarty->display('footer.tpl');

<?php

declare(strict_types=1);

include_once '../lib/admin.defines.php';
include_once '../lib/admin.module.access.php';
include_once '../lib/admin.smarty.php';

use A2BillingPlus\Api\ProviderApiController;
use A2BillingPlus\Admin\ModernAdminPageRenderer;
use A2BillingPlus\Admin\ModernAdminRuntime;
use A2BillingPlus\Bootstrap\ProviderRegistryFactory;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Module\Provider\ProviderAccessPolicy;
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

$runtime = new ModernAdminRuntime($projectRoot);
$pageRenderer = new ModernAdminPageRenderer();
$envPath = $runtime->envPath();
$theme = $runtime->activeTheme();
$menuStyle = $runtime->activeMenuStyle($theme);
$actor = trim((string)($_SESSION['pr_login'] ?? ''));

$messages = [];
$errors = [];
$registration = [];
$ratePreview = [];
$rateImport = [];

$providerSetup = providerSetupService($actor);
$providers = $providerSetup->providers();
$providersByCode = [];
foreach ($providers as $providerOption) {
    $code = (string)($providerOption['code'] ?? '');
    if ($code !== '') {
        $providersByCode[$code] = $providerOption;
    }
}

$defaultProvider = isset($providersByCode['vectavoip'])
    ? 'vectavoip'
    : ((string)array_key_first($providersByCode) !== '' ? (string)array_key_first($providersByCode) : 'vectavoip');
$didwwAvailable = isset($providersByCode['didww']);
$requestedProvider = trim((string)($_POST['provider'] ?? $_GET['provider'] ?? $defaultProvider));
$provider = $requestedProvider === 'didww' && $didwwAvailable ? 'didww' : $defaultProvider;
if (!isset($providersByCode[$provider])) {
    $provider = $defaultProvider;
}

$defaults = providerDefaults($provider);
$input = $defaults;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = trim((string)($_POST['form_action'] ?? ''));

    if (in_array($formAction, ['set_ui_theme', 'set_ui_preferences'], true)) {
        try {
            $theme = $runtime->saveUiTheme(trim((string)($_POST['ui_theme'] ?? '')));
            $menuStyle = $formAction === 'set_ui_preferences'
                ? $runtime->saveUiMenuStyle(trim((string)($_POST['ui_menu_style'] ?? '')))
                : $runtime->activeMenuStyle($theme);
            $messages[] = 'Saved UI theme: ' . $theme->id() . '.';
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    $provider = trim((string)($_POST['provider'] ?? $provider));
    if ($provider === 'didww' && !$didwwAvailable) {
        $provider = $defaultProvider;
    }
    if (!isset($providersByCode[$provider])) {
        $provider = $defaultProvider;
    }
    $defaults = providerDefaults($provider);
    $input = $defaults;
    foreach ($defaults as $key => $default) {
        $input[$key] = trim((string)($_POST[$key] ?? ''));
    }
    $input['provider'] = $provider;
    $input['save_credentials'] = isset($_POST['save_credentials']) ? '1' : '';
    $input['update_existing'] = isset($_POST['update_existing']) ? '1' : '';

    if (!in_array($formAction, ['set_ui_theme', 'set_ui_preferences'], true) && $input['base_url'] === '') {
        $errors[] = 'Provider API base URL is required.';
    }

    if ($formAction === 'test_provider_connection' && !$errors) {
        $connection = $providerSetup->testConnection($input);
        if (($connection['success'] ?? false) !== true) {
            $errors[] = (string)($connection['message'] ?? 'Provider connection failed.');
        } else {
            $messages[] = (string)($connection['message'] ?? 'Provider connection succeeded.');
        }
    }

    if ($formAction === 'save_provider_credentials' && !$errors) {
        if ($input['api_key'] === '') {
            $errors[] = 'API key is required.';
        } else {
            saveProviderCredentials($envPath, $provider, $input, $messages, $errors);
        }
    }

    if ($provider === 'vectavoip' && $formAction === 'register_provider') {
        if ($input['registration_username'] === '') {
            $errors[] = 'Registration username is required.';
        }
        if ($input['registration_password'] === '') {
            $errors[] = 'Registration password is required.';
        }
    }

    if ($provider === 'vectavoip' && !$errors && $formAction === 'register_provider') {
        $registration = $providerSetup->registerInstall($input);
        if (($registration['success'] ?? false) !== true) {
            $errors[] = (string)($registration['message'] ?? 'Provider registration failed.');
        } else {
            $messages[] = (string)($registration['message'] ?? 'Provider registration completed.');

            if ($input['save_credentials'] === '1') {
                saveProviderRegistrationCredentials($envPath, $provider, $input['base_url'], $registration, $messages, $errors);
            }
        }
    }

    if ($provider === 'vectavoip' && !$errors && $formAction === 'save_selected_package') {
        if ($input['selected_package'] === '') {
            $errors[] = 'Select a VectaVoIP plan before saving.';
        } else {
            saveProviderPackageSelection($envPath, $provider, $input['selected_package'], $messages, $errors);
        }
    }

    if ($provider === 'vectavoip' && !$errors && $formAction === 'save_package_provisioning') {
        if ($input['selected_package'] === '') {
            $errors[] = 'Select a VectaVoIP plan before saving provisioning options.';
        }
        if (!ctype_digit($input['package_did_count']) || (int)$input['package_did_count'] < 0) {
            $errors[] = 'DID quantity must be zero or greater.';
        }
        if (!ctype_digit($input['package_channels']) || (int)$input['package_channels'] <= 0) {
            $errors[] = 'Concurrent channels must be greater than zero.';
        }
        if (!$errors) {
            saveProviderPackageProvisioning($envPath, $provider, $input, $messages, $errors);
        }
    }

    if ($provider === 'vectavoip' && !$errors && $formAction === 'preview_rates') {
        $ratePreview = $providerSetup->previewRates($input);
        if (isset($ratePreview['error'])) {
            $errors[] = (string)$ratePreview['error'];
        } else {
            $messages[] = (string)($ratePreview['message'] ?? 'Rate preview completed.');
        }
    }

    if ($provider === 'vectavoip' && !$errors && in_array($formAction, ['dry_run_import_rates', 'import_rates'], true)) {
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

$status = $providerSetup->providerStatus($provider);
$ratecards = $provider === 'vectavoip' ? $providerSetup->ratecards() : [];
$recentImports = $provider === 'vectavoip' ? $providerSetup->recentImports() : [];
$providerName = providerName($providers, $provider);
$providerLocked = (new ProviderAccessPolicy(AppConfig::fromEnvironment()))->isLocked($provider);

$smarty->display('main.tpl');
echo $pageRenderer->begin(
    $theme,
    $runtime->themeRegistry(),
    'provider-setup',
    $providerName . ' Provider Setup',
    'Manage provider credentials and provider-specific setup flows from the modular admin shell.',
    $menuStyle
);
echo $pageRenderer->renderAlerts($messages, $errors);

function providerSetupService(string $actor): ProviderSetupService
{
    $pdoFactory = fn (): PDO => providerSetupPdo();

    return new ProviderSetupService(
        new ProviderApiController(
            ProviderRegistryFactory::createDefault(),
            null,
            $pdoFactory,
            new ProviderAccessPolicy(AppConfig::fromEnvironment()),
            $actor
        ),
        $pdoFactory
    );
}

function providerSetupPdo(): PDO
{
    global $runtime;

    return $runtime->pdo();
}

/**
 * @return array<string,string>
 */
function providerDefaults(string $provider): array
{
    $defaults = [
        'provider' => $provider,
        'base_url' => providerEnvString($provider, 'API_BASE_URL', $provider === 'didww' ? 'https://api.didww.com' : 'https://api.vectavoip.com'),
        'api_key' => providerEnvString($provider, 'API_KEY'),
        'api_secret' => providerEnvString($provider, 'API_SECRET'),
        'api_version' => providerEnvString($provider, 'API_VERSION', $provider === 'didww' ? '2026-04-16' : ''),
        'company_name' => 'VectaVoIP',
        'company_domain' => 'VectaVoIP.com',
        'registration_username' => '',
        'registration_password' => '',
        'contact_email' => '',
        'install_key' => providerEnvString($provider, 'INSTALL_KEY'),
        'account_number' => providerEnvString($provider, 'ACCOUNT_NUMBER'),
        'registered_ip' => providerEnvString($provider, 'REGISTERED_IP'),
        'allowed_ips' => providerEnvString($provider, 'ALLOWED_IPS'),
        'portal_username' => providerEnvString($provider, 'PORTAL_USERNAME'),
        'available_packages_json' => providerEnvString($provider, 'AVAILABLE_PACKAGES_JSON'),
        'selected_package' => providerEnvString($provider, 'SELECTED_PACKAGE'),
        'package_did_count' => providerEnvString($provider, 'PACKAGE_DID_COUNT', '1'),
        'package_channels' => providerEnvString($provider, 'PACKAGE_CHANNELS', '2'),
        'package_sms_enabled' => providerEnvString($provider, 'PACKAGE_SMS_ENABLED'),
        'package_911_enabled' => providerEnvString($provider, 'PACKAGE_911_ENABLED'),
        'package_ratecard_id' => providerEnvString($provider, 'PACKAGE_RATECARD_ID'),
        'package_trunk_label' => providerEnvString($provider, 'PACKAGE_TRUNK_LABEL'),
        'package_notes' => providerEnvString($provider, 'PACKAGE_NOTES'),
        'target_ratecard_id' => '',
        'rate_deck' => 'retail',
        'currency' => 'USD',
        'destination_filter' => '',
        'update_existing' => '',
        'save_credentials' => '1',
    ];

    if ($provider === 'didww') {
        $defaults['company_name'] = 'DIDWW';
        $defaults['company_domain'] = 'didww.com';
        $defaults['rate_deck'] = '';
        $defaults['currency'] = '';
    }

    return $defaults;
}

function providerName(array $providers, string $providerCode): string
{
    foreach ($providers as $provider) {
        if (($provider['code'] ?? '') === $providerCode) {
            return (string)($provider['name'] ?? strtoupper($providerCode));
        }
    }

    return strtoupper($providerCode);
}

function providerEnvString(string $provider, string $suffix, string $default = ''): string
{
    return envString(strtoupper($provider) . '_' . $suffix, $default);
}

function envString(string $key, string $default = ''): string
{
    $value = getenv($key);
    if (is_string($value) && $value !== '') {
        return $value;
    }

    $file = getenv($key . '_FILE');
    if (is_string($file) && $file !== '' && is_readable($file)) {
        $contents = file_get_contents($file);
        if (is_string($contents)) {
            return trim($contents);
        }
    }

    $fileValues = envFileValues();
    $filePath = $fileValues[$key . '_FILE'] ?? '';
    if ($filePath !== '' && is_readable($filePath)) {
        $contents = file_get_contents($filePath);
        if (is_string($contents)) {
            return trim($contents);
        }
    }
    if (($fileValues[$key] ?? '') !== '') {
        return $fileValues[$key];
    }

    return $default;
}

/**
 * @return array<string, string>
 */
function envFileValues(): array
{
    static $values = null;
    if (is_array($values)) {
        return $values;
    }

    global $envPath;
    $values = [];
    if (!is_string($envPath) || !is_readable($envPath)) {
        return $values;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return $values;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }

        if (
            strlen($value) >= 2
            && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $values[$key] = str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
    }

    return $values;
}

function saveProviderCredentials(string $envPath, string $provider, array $input, array &$messages, array &$errors): void
{
    if (!is_writable(dirname($envPath)) || (is_file($envPath) && !is_writable($envPath))) {
        $errors[] = '.env is not writable. Provider credentials were not saved.';
        return;
    }

    $prefix = strtoupper($provider);
    $values = [
        $prefix . '_API_BASE_URL' => $input['base_url'],
        $prefix . '_API_KEY' => $input['api_key'],
    ];
    if ($input['api_secret'] !== '') {
        $values[$prefix . '_API_SECRET'] = $input['api_secret'];
    }
    if ($input['api_version'] !== '') {
        $values[$prefix . '_API_VERSION'] = $input['api_version'];
    }

    writeSecretFileValues($values, [$prefix . '_API_KEY', $prefix . '_API_SECRET'], $messages, $errors);
    if ($errors) {
        return;
    }

    $contents = is_file($envPath) ? (string)file_get_contents($envPath) : '';
    $contents = mergeEnvValues($contents, $values);

    if (@file_put_contents($envPath, $contents) === false) {
        $errors[] = 'Could not write .env. Provider credentials were not saved.';
        return;
    }

    foreach ($values as $key => $value) {
        putenv($key . '=' . $value);
    }

    $messages[] = 'Saved ' . strtoupper($provider) . ' provider credentials to .env.';
}

function saveProviderRegistrationCredentials(string $envPath, string $provider, string $baseUrl, array $registration, array &$messages, array &$errors): void
{
    if (!is_writable(dirname($envPath)) || (is_file($envPath) && !is_writable($envPath))) {
        $errors[] = '.env is not writable. Provider credentials were not saved.';
        return;
    }

    $prefix = strtoupper($provider);
    $values = [
        $prefix . '_API_BASE_URL' => $baseUrl,
        $prefix . '_INSTALL_KEY' => (string)($registration['install_key'] ?? ''),
        $prefix . '_INSTALLATION_ID' => (string)($registration['installation_id'] ?? ''),
        $prefix . '_API_KEY' => (string)($registration['api_key'] ?? ''),
        $prefix . '_API_SECRET' => (string)($registration['api_secret'] ?? ''),
    ];
    $metadata = is_array($registration['metadata'] ?? null) ? $registration['metadata'] : [];
    foreach ([
        'account_number' => 'ACCOUNT_NUMBER',
        'registered_ip' => 'REGISTERED_IP',
        'allowed_ips' => 'ALLOWED_IPS',
        'portal_username' => 'PORTAL_USERNAME',
        'available_packages_json' => 'AVAILABLE_PACKAGES_JSON',
    ] as $metadataKey => $suffix) {
        $value = (string)($metadata[$metadataKey] ?? '');
        if ($value !== '') {
            $values[$prefix . '_' . $suffix] = $value;
        }
    }

    writeSecretFileValues($values, [$prefix . '_API_KEY', $prefix . '_API_SECRET'], $messages, $errors);
    if ($errors) {
        return;
    }

    $contents = is_file($envPath) ? (string)file_get_contents($envPath) : '';
    $contents = mergeEnvValues($contents, $values);

    if (@file_put_contents($envPath, $contents) === false) {
        $errors[] = 'Could not write .env. Provider credentials were not saved.';
        return;
    }

    foreach ($values as $key => $value) {
        putenv($key . '=' . $value);
    }

    $messages[] = 'Saved ' . strtoupper($provider) . ' registration credentials to .env.';
}

function writeSecretFileValues(array &$values, array $secretKeys, array &$messages, array &$errors): void
{
    $secretDir = envString('A2BP_SECRET_DIR');
    if ($secretDir === '') {
        return;
    }

    if (!is_dir($secretDir) || !is_writable($secretDir)) {
        $errors[] = 'A2BP_SECRET_DIR is set but is not writable. Provider credentials were not saved.';
        return;
    }

    foreach ($secretKeys as $key) {
        $value = (string)($values[$key] ?? '');
        if ($value === '') {
            continue;
        }

        $path = rtrim($secretDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . strtolower($key);
        if (@file_put_contents($path, $value . PHP_EOL) === false) {
            $errors[] = 'Could not write provider secret file: ' . $path;
            return;
        }

        unset($values[$key]);
        $values[$key . '_FILE'] = $path;
    }

    $messages[] = 'Saved provider API keys/secrets to A2BP_SECRET_DIR.';
}

function saveProviderPackageSelection(string $envPath, string $provider, string $selectedPackage, array &$messages, array &$errors): void
{
    if (!is_writable(dirname($envPath)) || (is_file($envPath) && !is_writable($envPath))) {
        $errors[] = '.env is not writable. Package selection was not saved.';
        return;
    }

    $key = strtoupper($provider) . '_SELECTED_PACKAGE';
    $values = [$key => $selectedPackage];
    $contents = is_file($envPath) ? (string)file_get_contents($envPath) : '';
    $contents = mergeEnvValues($contents, $values);

    if (@file_put_contents($envPath, $contents) === false) {
        $errors[] = 'Could not write .env. Package selection was not saved.';
        return;
    }

    putenv($key . '=' . $selectedPackage);
    $messages[] = 'Saved selected ' . strtoupper($provider) . ' package: ' . $selectedPackage . '.';
}

function saveProviderPackageProvisioning(string $envPath, string $provider, array $input, array &$messages, array &$errors): void
{
    if (!is_writable(dirname($envPath)) || (is_file($envPath) && !is_writable($envPath))) {
        $errors[] = '.env is not writable. Package provisioning options were not saved.';
        return;
    }

    $prefix = strtoupper($provider);
    $values = [
        $prefix . '_SELECTED_PACKAGE' => $input['selected_package'],
        $prefix . '_PACKAGE_DID_COUNT' => $input['package_did_count'],
        $prefix . '_PACKAGE_CHANNELS' => $input['package_channels'],
        $prefix . '_PACKAGE_SMS_ENABLED' => $input['package_sms_enabled'] === '1' ? '1' : '0',
        $prefix . '_PACKAGE_911_ENABLED' => $input['package_911_enabled'] === '1' ? '1' : '0',
        $prefix . '_PACKAGE_RATECARD_ID' => $input['package_ratecard_id'],
        $prefix . '_PACKAGE_TRUNK_LABEL' => $input['package_trunk_label'],
        $prefix . '_PACKAGE_NOTES' => $input['package_notes'],
    ];

    $contents = is_file($envPath) ? (string)file_get_contents($envPath) : '';
    $contents = mergeEnvValues($contents, $values);

    if (@file_put_contents($envPath, $contents) === false) {
        $errors[] = 'Could not write .env. Package provisioning options were not saved.';
        return;
    }

    foreach ($values as $key => $value) {
        putenv($key . '=' . $value);
    }

    $messages[] = 'Saved VectaVoIP provisioning options for package: ' . $input['selected_package'] . '.';
}

function mergeEnvValues(string $contents, array $values): string
{
    $lines = preg_split('/\r\n|\r|\n/', $contents);
    $lines = is_array($lines) ? $lines : [];
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

/**
 * @return list<array<string,string>>
 */
function packageOptions(array $input, array $registration): array
{
    $json = '';
    if (is_array($registration['metadata'] ?? null) && is_string(($registration['metadata']['available_packages_json'] ?? null))) {
        $json = (string)$registration['metadata']['available_packages_json'];
    }
    if ($json === '') {
        $json = (string)($input['available_packages_json'] ?? '');
    }
    if ($json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    $options = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }
        $code = is_scalar($row['code'] ?? null) ? trim((string)$row['code']) : '';
        if ($code === '') {
            continue;
        }
        $options[] = [
            'code' => $code,
            'name' => is_scalar($row['name'] ?? null) ? (string)$row['name'] : $code,
            'billing' => is_scalar($row['billing'] ?? null) ? (string)$row['billing'] : '',
            'price' => is_scalar($row['price'] ?? null) ? (string)$row['price'] : '',
        ];
    }

    return $options;
}

/**
 * @return array<string,string>
 */
function selectedPackageOption(array $packageOptions, string $selectedPackage): array
{
    foreach ($packageOptions as $packageOption) {
        if (($packageOption['code'] ?? '') === $selectedPackage) {
            return $packageOption;
        }
    }

    return [];
}

?>
<table width="95%" class="provider_setup_page">
    <tr>
        <td class="form_head"><?php echo h($providerName); ?> Provider Setup</td>
    </tr>
    <tr>
        <td class="tdstyle_001">
            <table width="100%" cellspacing="0" cellpadding="8">
                <tr>
                    <td width="220"><strong>Provider</strong></td>
                    <td><?php echo h($providerName); ?></td>
                </tr>
                <?php if ($provider === 'vectavoip' && $didwwAvailable): ?>
                <tr>
                    <td><strong>Restricted Provider</strong></td>
                    <td><a href="?provider=didww">Open DIDWW licensed setup</a></td>
                </tr>
                <?php elseif ($provider === 'didww'): ?>
                <tr>
                    <td><strong>Default Provider</strong></td>
                    <td><a href="?provider=vectavoip">Return to VectaVoIP setup</a></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><strong>Status</strong></td>
                    <td><?php echo !empty($status['registered']) ? 'Configured' : 'Not configured'; ?></td>
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
                    <td><?php echo h((string)($status['support_email'] ?? '')); ?></td>
                </tr>
                <?php if ($providerLocked): ?>
                <tr>
                    <td><strong>Access Policy</strong></td>
                    <td>Locked to company admins and licensed individuals.</td>
                </tr>
                <?php endif; ?>
            </table>

            <br>
            <form method="post">
                <input type="hidden" name="provider" value="<?php echo h($provider); ?>">
                <table width="100%" cellspacing="0" cellpadding="8">
                    <tr>
                        <td width="220"><strong>Provider</strong></td>
                        <td><?php echo h($providerName); ?></td>
                    </tr>
                    <tr>
                        <td><label for="base_url">API Base URL</label></td>
                        <td><input id="base_url" name="base_url" type="text" size="70" value="<?php echo h($input['base_url']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="api_key">API Key</label></td>
                        <td><input id="api_key" name="api_key" type="password" size="70" value="<?php echo h($input['api_key']); ?>"></td>
                    </tr>
                    <?php if ($provider === 'vectavoip'): ?>
                    <tr>
                        <td><label for="api_secret">API Secret</label></td>
                        <td><input id="api_secret" name="api_secret" type="password" size="70" value="<?php echo h($input['api_secret']); ?>"></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td><label for="api_version">API Version</label></td>
                        <td><input id="api_version" name="api_version" type="text" size="20" value="<?php echo h($input['api_version']); ?>"></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td></td>
                        <td>
                            <label>
                                <input name="save_credentials" type="checkbox" value="1" <?php echo $input['save_credentials'] === '1' ? 'checked' : ''; ?>>
                                Save credentials to .env
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                            <button class="form_input_button" name="form_action" type="submit" value="test_provider_connection">Test Connection</button>
                            <button class="form_input_button" name="form_action" type="submit" value="save_provider_credentials">Save Credentials</button>
                        </td>
                    </tr>
                </table>
            </form>

            <?php if ($provider === 'vectavoip'): ?>
            <br>
            <form method="post">
                <input type="hidden" name="provider" value="vectavoip">
                <input type="hidden" name="base_url" value="<?php echo h($input['base_url']); ?>">
                <table width="100%" cellspacing="0" cellpadding="8">
                    <tr>
                        <td class="form_head" colspan="2">VectaVoIP Registration</td>
                    </tr>
                    <tr>
                        <td width="220"><label for="registration_username">Username</label></td>
                        <td><input id="registration_username" name="registration_username" type="text" size="40" value="<?php echo h($input['registration_username']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="registration_password">Password</label></td>
                        <td><input id="registration_password" name="registration_password" type="password" size="40" value="<?php echo h($input['registration_password']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="company_name">Company Label</label></td>
                        <td><input id="company_name" name="company_name" type="text" size="70" value="<?php echo h($input['company_name']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="company_domain">Company Domain</label></td>
                        <td><input id="company_domain" name="company_domain" type="text" size="70" value="<?php echo h($input['company_domain']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="contact_email">Contact Email</label></td>
                        <td><input id="contact_email" name="contact_email" type="text" size="70" value="<?php echo h($input['contact_email']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="install_key">Install Key</label></td>
                        <td><input id="install_key" name="install_key" type="text" size="70" value="<?php echo h($input['install_key']); ?>"></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>This registration is opt-in. The VectaVoIP server will create the installation ID, account number, allowed IP entry, and API credentials after successful registration.</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td><button class="form_input_button" name="form_action" type="submit" value="register_provider">Register Provider</button></td>
                    </tr>
                </table>
            </form>

            <?php $packageOptions = packageOptions($input, $registration); ?>
            <?php $selectedPackage = (string)($input['selected_package'] ?? ''); ?>
            <?php $selectedPackageOption = selectedPackageOption($packageOptions, $selectedPackage); ?>
            <?php if ($registration || $input['account_number'] !== '' || $packageOptions): ?>
            <br>
            <table width="100%" cellspacing="0" cellpadding="8">
                <tr>
                    <td class="form_head" colspan="2">VectaVoIP Installation Details</td>
                </tr>
                <tr>
                    <td width="220">Account Number</td>
                    <td><?php echo h((string)(($registration['metadata']['account_number'] ?? '') ?: $input['account_number'])); ?></td>
                </tr>
                <tr>
                    <td>Registered IP</td>
                    <td><?php echo h((string)(($registration['metadata']['registered_ip'] ?? '') ?: $input['registered_ip'])); ?></td>
                </tr>
                <tr>
                    <td>Allowed IPs</td>
                    <td><?php echo h((string)(($registration['metadata']['allowed_ips'] ?? '') ?: $input['allowed_ips'])); ?></td>
                </tr>
                <tr>
                    <td>Portal Username</td>
                    <td><?php echo h((string)(($registration['metadata']['portal_username'] ?? '') ?: $input['portal_username'] ?: $input['registration_username'])); ?></td>
                </tr>
                <?php if ($packageOptions): ?>
                <tr>
                    <td>Available Plans</td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="provider" value="vectavoip">
                            <select name="selected_package">
                                <option value="">Select a plan</option>
                                <?php foreach ($packageOptions as $packageOption): ?>
                                    <option value="<?php echo h($packageOption['code']); ?>" <?php echo $selectedPackage === $packageOption['code'] ? 'selected' : ''; ?>>
                                        <?php echo h($packageOption['name'] . ' [' . $packageOption['code'] . '] ' . $packageOption['price'] . ' ' . $packageOption['billing']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="form_input_button" name="form_action" type="submit" value="save_selected_package">Save Plan</button>
                        </form>
                        <div class="a2bp-muted">The selected plan controls the provisioning options below.</div>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <?php if ($selectedPackage !== '' && $selectedPackageOption): ?>
            <br>
            <form method="post">
                <input type="hidden" name="provider" value="vectavoip">
                <input type="hidden" name="selected_package" value="<?php echo h($selectedPackage); ?>">
                <table width="100%" cellspacing="0" cellpadding="8">
                    <tr>
                        <td class="form_head" colspan="2">VectaVoIP Package Provisioning</td>
                    </tr>
                    <tr>
                        <td width="220">Selected Plan</td>
                        <td><?php echo h($selectedPackageOption['name'] . ' [' . $selectedPackageOption['code'] . ']'); ?></td>
                    </tr>
                    <tr>
                        <td><label for="package_did_count">DID Quantity</label></td>
                        <td><input id="package_did_count" name="package_did_count" type="number" min="0" step="1" size="8" value="<?php echo h($input['package_did_count']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="package_channels">Concurrent Channels</label></td>
                        <td><input id="package_channels" name="package_channels" type="number" min="1" step="1" size="8" value="<?php echo h($input['package_channels']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="package_ratecard_id">Target Ratecard ID</label></td>
                        <td>
                            <?php if ($ratecards): ?>
                                <select id="package_ratecard_id" name="package_ratecard_id">
                                    <option value="">Select a ratecard</option>
                                    <?php foreach ($ratecards as $ratecard): ?>
                                        <option value="<?php echo h($ratecard['id']); ?>" <?php echo $input['package_ratecard_id'] === $ratecard['id'] ? 'selected' : ''; ?>>
                                            <?php echo h($ratecard['name'] . ' (#' . $ratecard['id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input id="package_ratecard_id" name="package_ratecard_id" type="text" size="10" value="<?php echo h($input['package_ratecard_id']); ?>">
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="package_trunk_label">Trunk Label</label></td>
                        <td><input id="package_trunk_label" name="package_trunk_label" type="text" size="45" value="<?php echo h($input['package_trunk_label']); ?>"></td>
                    </tr>
                    <tr>
                        <td>Options</td>
                        <td>
                            <label><input name="package_sms_enabled" type="checkbox" value="1" <?php echo $input['package_sms_enabled'] === '1' ? 'checked' : ''; ?>> SMS enabled</label>
                            &nbsp;
                            <label><input name="package_911_enabled" type="checkbox" value="1" <?php echo $input['package_911_enabled'] === '1' ? 'checked' : ''; ?>> E911 enabled</label>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="package_notes">Provisioning Notes</label></td>
                        <td><textarea id="package_notes" name="package_notes" rows="3" cols="72"><?php echo h($input['package_notes']); ?></textarea></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td><button class="form_input_button" name="form_action" type="submit" value="save_package_provisioning">Save Provisioning Options</button></td>
                    </tr>
                </table>
            </form>
            <?php endif; ?>
            <?php endif; ?>

            <br>
            <form method="post">
                <input type="hidden" name="provider" value="vectavoip">
                <table width="100%" cellspacing="0" cellpadding="8">
                    <tr>
                        <td class="form_head" colspan="2">VectaVoIP Rate Preview and Import</td>
                    </tr>
                    <tr>
                        <td width="220"><label for="rate_base_url">API Base URL</label></td>
                        <td><input id="rate_base_url" name="base_url" type="text" size="70" value="<?php echo h($input['base_url']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="rate_api_key">API Key</label></td>
                        <td><input id="rate_api_key" name="api_key" type="password" size="70" value="<?php echo h($input['api_key']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="rate_api_secret">API Secret</label></td>
                        <td><input id="rate_api_secret" name="api_secret" type="password" size="70" value="<?php echo h($input['api_secret']); ?>"></td>
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
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                            <label>
                                <input name="update_existing" type="checkbox" value="1" <?php echo $input['update_existing'] === '1' ? 'checked' : ''; ?>>
                                Update existing rows with the same provider tag
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
            <?php else: ?>
            <br>
            <table width="100%" cellspacing="0" cellpadding="8">
                <tr>
                    <td class="form_head">DIDWW Integration Notes</td>
                </tr>
                <tr>
                    <td>
                        DIDWW support is configured through API-key authentication and is locked to company admins and licensed users when `A2BP_LOCKED_PROVIDERS=didww`.
                        This provider integration currently covers credential validation and controlled access first. DID inventory and provisioning flows can be layered onto the telephony workspace after the provider lock is in place.
                    </td>
                </tr>
            </table>
            <?php endif; ?>

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

echo $pageRenderer->end();
$smarty->display('footer.tpl');

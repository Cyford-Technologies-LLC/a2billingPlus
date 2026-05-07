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
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPProvisioningService;

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
$provisioningResult = [];
$didwwSnapshot = [];
$didwwSearch = [];
$didwwOrder = [];
$didwwLocalSync = [];
$didwwTrunkProvision = [];

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

    if ($formAction === 'save_locked_provider_admins' && !$errors) {
        saveLockedProviderAdmins($envPath, $input['licensed_admins'], $messages, $errors);
    }

    if ($provider === 'didww' && !$errors && $formAction === 'didww_refresh_inventory') {
        $didwwSnapshot = $providerSetup->didwwInventorySnapshot($input);
        if (($didwwSnapshot['success'] ?? false) !== true) {
            $errors[] = formatDidwwErrorMessage((string)($didwwSnapshot['message'] ?? 'DIDWW inventory refresh failed.'));
        } else {
            $messages[] = 'DIDWW inventory refreshed.';
        }
    }

    if ($provider === 'didww' && !$errors && $formAction === 'didww_search_available_dids') {
        $didwwSearch = $providerSetup->didwwSearchAvailableDids($input);
        if (($didwwSearch['success'] ?? false) !== true) {
            $errors[] = formatDidwwErrorMessage((string)($didwwSearch['message'] ?? 'DIDWW DID search failed.'));
        } else {
            $messages[] = (string)($didwwSearch['message'] ?? 'DIDWW available DID search completed.');
        }
    }

    if ($provider === 'didww' && !$errors && $formAction === 'didww_order_did') {
        if ($input['didww_available_did_id'] === '' || $input['didww_sku_id'] === '') {
            $errors[] = 'Choose a DIDWW number and SKU before ordering.';
        } else {
            $didwwOrder = $providerSetup->didwwOrderDid($input);
            if (($didwwOrder['success'] ?? false) !== true) {
                $errors[] = formatDidwwErrorMessage((string)($didwwOrder['message'] ?? 'DIDWW order failed.'));
            } else {
                $messages[] = (string)($didwwOrder['message'] ?? 'DIDWW order submitted.');
                $didwwSnapshot = $providerSetup->didwwInventorySnapshot($input);
            }
        }
    }

    if ($provider === 'didww' && !$errors && $formAction === 'didww_sync_inventory') {
        $didwwLocalSync = $providerSetup->didwwSyncInventory($input);
        if (($didwwLocalSync['success'] ?? false) !== true) {
            $errors[] = formatDidwwErrorMessage((string)($didwwLocalSync['message'] ?? 'DIDWW inventory sync failed.'));
        } else {
            $messages[] = (string)($didwwLocalSync['message'] ?? 'DIDWW inventory synchronized.');
            $didwwSnapshot = $providerSetup->didwwInventorySnapshot($input);
        }
    }

    if ($provider === 'didww' && !$errors && $formAction === 'didww_create_inbound_trunk') {
        if ($input['didww_trunk_name'] === '' || $input['didww_trunk_host'] === '' || $input['didww_trunk_username'] === '') {
            $errors[] = 'DIDWW trunk name, host, and username are required.';
        } else {
            $didwwTrunkProvision = $providerSetup->didwwCreateInboundTrunk($input);
            if (($didwwTrunkProvision['success'] ?? false) !== true) {
                $errors[] = formatDidwwErrorMessage((string)($didwwTrunkProvision['message'] ?? 'DIDWW inbound trunk provisioning failed.'));
            } else {
                $messages[] = (string)($didwwTrunkProvision['message'] ?? 'DIDWW inbound trunk created.');
                $didwwSnapshot = $providerSetup->didwwInventorySnapshot($input);
            }
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

    if ($provider === 'vectavoip' && !$errors && $formAction === 'apply_package_provisioning') {
        if ($input['selected_package'] === '') {
            $errors[] = 'Select a VectaVoIP plan before applying provisioning.';
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
        if (!$errors) {
            try {
                $provisioningResult = providerProvisioningService()->applyPackageProvisioning([
                    'selected_package' => $input['selected_package'],
                    'package_did_count' => $input['package_did_count'],
                    'package_channels' => $input['package_channels'],
                    'package_sms_enabled' => $input['package_sms_enabled'],
                    'package_911_enabled' => $input['package_911_enabled'],
                    'package_ratecard_id' => $input['package_ratecard_id'],
                    'package_trunk_label' => $input['package_trunk_label'],
                    'package_notes' => $input['package_notes'],
                    'account_number' => $input['account_number'],
                    'portal_username' => $input['portal_username'] !== '' ? $input['portal_username'] : $input['registration_username'],
                    'api_secret' => $input['api_secret'],
                    'registered_ip' => $input['registered_ip'],
                ]);
                $messages[] = (string)($provisioningResult['message'] ?? 'VectaVoIP package provisioning applied.');
            } catch (Throwable $exception) {
                $errors[] = 'Package provisioning failed: ' . $exception->getMessage();
            }
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
$didwwConfigured = $provider === 'didww' && !empty($status['registered']);
if ($didwwConfigured && $didwwSnapshot === []) {
    $didwwSnapshot = $providerSetup->didwwInventorySnapshot($input);
    if (($didwwSnapshot['success'] ?? false) !== true) {
        if (($didwwSnapshot['message'] ?? '') !== '') {
            $errors[] = (string)$didwwSnapshot['message'];
        }
        $didwwSnapshot = [];
    }
}
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

function providerProvisioningService(): VectaVoIPProvisioningService
{
    return new VectaVoIPProvisioningService(providerSetupPdo());
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
        'didww_page_size' => '25',
        'didww_orders_page_size' => '10',
        'didww_search_page_size' => '20',
        'didww_number_contains' => '',
        'didww_country_id' => '',
        'didww_region_id' => '',
        'didww_city_id' => '',
        'didww_features' => 'voice_in',
        'didww_needs_registration' => '',
        'didww_available_did_id' => '',
        'didww_sku_id' => '',
        'didww_order_callback_url' => '',
        'didww_allow_back_ordering' => '',
        'didww_sync_page_size' => '100',
        'didww_trunk_name' => '',
        'didww_trunk_host' => '',
        'didww_trunk_username' => '',
        'didww_trunk_auth_enabled' => '',
        'didww_trunk_auth_user' => '',
        'didww_trunk_auth_password' => '',
        'didww_trunk_capacity_limit' => '10',
        'didww_trunk_priority' => '10',
        'didww_trunk_weight' => '10',
        'didww_trunk_cli_format' => 'e164',
        'didww_trunk_cli_prefix' => '',
        'didww_trunk_resolve_ruri' => '1',
        'didww_trunk_enabled_sip_registration' => '',
        'didww_trunk_use_did_in_ruri' => '1',
        'owner_admins' => envString('A2BP_PROVIDER_OWNER_ADMINS'),
        'licensed_admins' => envString('A2BP_PROVIDER_LICENSED_ADMINS'),
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

function saveLockedProviderAdmins(string $envPath, string $licensedAdmins, array &$messages, array &$errors): void
{
    if (!is_writable(dirname($envPath)) || (is_file($envPath) && !is_writable($envPath))) {
        $errors[] = '.env is not writable. Locked provider admin list was not saved.';
        return;
    }

    $normalized = implode(',', csvActors($licensedAdmins));
    $values = ['A2BP_PROVIDER_LICENSED_ADMINS' => $normalized];
    $contents = is_file($envPath) ? (string)file_get_contents($envPath) : '';
    $contents = mergeEnvValues($contents, $values);

    if (@file_put_contents($envPath, $contents) === false) {
        $errors[] = 'Could not write .env. Locked provider admin list was not saved.';
        return;
    }

    putenv('A2BP_PROVIDER_LICENSED_ADMINS=' . $normalized);
    $messages[] = 'Saved licensed admin access for locked providers.';
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

function formatDidwwErrorMessage(string $message): string
{
    $normalized = strtolower(trim($message));
    if ($normalized === '') {
        return 'DIDWW request failed.';
    }
    if (str_contains($normalized, 'access for customer is denied')) {
        return 'DIDWW API denied this request for the current account. The account/API key can read inventory but is not allowed to use this operation yet.';
    }
    if (str_contains($normalized, 'endpoint not enabled')) {
        return 'DIDWW API endpoint is not enabled on this account: ' . $message;
    }
    if (str_contains($normalized, 'forbidden') || str_contains($normalized, 'permission') || str_contains($normalized, 'denied')) {
        return 'DIDWW API permission denied: ' . $message;
    }

    return $message;
}

/**
 * @return list<string>
 */
function csvActors(string $value): array
{
    $parts = preg_split('/[\r\n,]+/', $value) ?: [];
    $parts = array_map(static fn (string $item): string => strtolower(trim($item)), $parts);
    $parts = array_values(array_filter($parts, static fn (string $item): bool => $item !== ''));
    return array_values(array_unique($parts));
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

/**
 * @param array<string, string> $input
 */
function renderProviderCredentialFields(array $input): void
{
    foreach (['provider', 'base_url', 'api_key', 'api_secret', 'api_version'] as $key) {
        echo '<input type="hidden" name="' . h($key) . '" value="' . h((string)($input[$key] ?? '')) . '">';
    }
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

            <?php if ($providerLocked): ?>
            <br>
            <form method="post">
                <input type="hidden" name="provider" value="<?php echo h($provider); ?>">
                <table width="100%" cellspacing="0" cellpadding="8">
                    <tr>
                        <td class="form_head" colspan="2">Locked Provider Admin Access</td>
                    </tr>
                    <tr>
                        <td width="220">Owner Admins</td>
                        <td><?php echo h($input['owner_admins']); ?></td>
                    </tr>
                    <tr>
                        <td><label for="licensed_admins">Licensed Admins</label></td>
                        <td>
                            <textarea id="licensed_admins" name="licensed_admins" rows="3" cols="72"><?php echo h($input['licensed_admins']); ?></textarea>
                            <div class="a2bp-muted">Enter admin logins separated by commas or new lines. These admins will be allowed to use locked providers such as DIDWW.</div>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td><button class="form_input_button" name="form_action" type="submit" value="save_locked_provider_admins">Save Locked Provider Access</button></td>
                    </tr>
                </table>
            </form>
            <?php endif; ?>

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
                        <td>
                            <button class="form_input_button" name="form_action" type="submit" value="save_package_provisioning">Save Provisioning Options</button>
                            <button class="form_input_button" name="form_action" type="submit" value="apply_package_provisioning">Apply Provisioning</button>
                        </td>
                    </tr>
                </table>
            </form>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($provisioningResult): ?>
            <br>
            <table width="100%" cellspacing="0" cellpadding="8">
                <tr>
                    <td class="form_head" colspan="2">VectaVoIP Provisioning Result</td>
                </tr>
                <tr>
                    <td width="220">Provider ID</td>
                    <td><?php echo h((string)($provisioningResult['provider_id'] ?? '')); ?></td>
                </tr>
                <tr>
                    <td>Trunk ID</td>
                    <td><?php echo h((string)($provisioningResult['trunk_id'] ?? '')); ?></td>
                </tr>
                <tr>
                    <td>Ratecard ID</td>
                    <td><?php echo h((string)($provisioningResult['ratecard_id'] ?? '')); ?></td>
                </tr>
                <tr>
                    <td>DID Request ID</td>
                    <td><?php echo h((string)($provisioningResult['did_request_id'] ?? '')); ?></td>
                </tr>
                <tr>
                    <td>PJSIP Endpoint</td>
                    <td><?php echo h((string)($provisioningResult['pjsip_endpoint'] ?? '')); ?></td>
                </tr>
            </table>
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
                    <td class="form_head" colspan="2">DIDWW Operations</td>
                </tr>
                <tr>
                    <td width="220"><strong>Access Model</strong></td>
                    <td>Locked to company admins and licensed users when `A2BP_LOCKED_PROVIDERS=didww`.</td>
                </tr>
                <tr>
                    <td><strong>Account State</strong></td>
                    <td><?php echo $didwwConfigured ? 'Configured and ready for DIDWW API operations.' : 'Save valid DIDWW credentials first.'; ?></td>
                </tr>
                <tr>
                    <td><strong>Available DID Search</strong></td>
                    <td>The official `/v3/available_dids` endpoint is disabled by default on DIDWW accounts. If search fails, ask DIDWW support or sales to enable it.</td>
                </tr>
            </table>

            <?php if ($didwwConfigured): ?>
            <br>
            <form method="post">
                <?php renderProviderCredentialFields($input); ?>
                <input type="hidden" name="didww_page_size" value="<?php echo h($input['didww_page_size']); ?>">
                <input type="hidden" name="didww_orders_page_size" value="<?php echo h($input['didww_orders_page_size']); ?>">
                <input type="hidden" name="didww_sync_page_size" value="<?php echo h($input['didww_sync_page_size']); ?>">
                <table width="100%" cellspacing="0" cellpadding="8">
                    <tr>
                        <td class="form_head" colspan="2">DIDWW Inventory Snapshot</td>
                    </tr>
                    <tr>
                        <td width="220">Owned DIDs</td>
                        <td><?php echo h((string)count((array)($didwwSnapshot['dids'] ?? []))); ?></td>
                    </tr>
                    <tr>
                        <td>Inbound Trunks</td>
                        <td><?php echo h((string)count((array)($didwwSnapshot['inbound_trunks'] ?? []))); ?></td>
                    </tr>
                    <tr>
                        <td>Recent Orders</td>
                        <td><?php echo h((string)count((array)($didwwSnapshot['orders'] ?? []))); ?></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                            <button class="form_input_button" name="form_action" type="submit" value="didww_refresh_inventory">Refresh DIDWW Data</button>
                            <button class="form_input_button" name="form_action" type="submit" value="didww_sync_inventory">Sync to Local Inventory</button>
                        </td>
                    </tr>
                </table>
            </form>

            <?php if ($didwwLocalSync): ?>
            <br>
            <table width="100%" cellspacing="0" cellpadding="8">
                <tr>
                    <td class="form_head" colspan="2">DIDWW Local Inventory Sync</td>
                </tr>
                <tr>
                    <td width="220">Upserted DIDs</td>
                    <td><?php echo h((string)($didwwLocalSync['upserted'] ?? '0')); ?></td>
                </tr>
            </table>
            <?php endif; ?>

            <?php if (!empty($didwwSnapshot['dids'])): ?>
            <br>
            <table width="100%" cellspacing="0" cellpadding="6" border="0">
                <tr>
                    <td class="form_head" colspan="6">DIDWW Owned DIDs</td>
                </tr>
                <tr style="font-weight:bold;">
                    <td>Number</td>
                    <td>DID Group</td>
                    <td>Inbound Trunk</td>
                    <td>Blocked</td>
                    <td>Awaiting Registration</td>
                    <td>Order</td>
                </tr>
                <?php foreach (($didwwSnapshot['dids'] ?? []) as $didwwDid): ?>
                    <?php if (is_array($didwwDid)): ?>
                            <tr>
                                <td><?php echo h((string)($didwwDid['number'] ?? '')); ?></td>
                                <td><?php echo h((string)($didwwDid['did_group'] ?? '')); ?></td>
                                <td><?php echo h((string)($didwwDid['voice_in_trunk'] ?? '')); ?></td>
                                <td><?php echo h((string)($didwwDid['blocked'] ?? '')); ?></td>
                                <td><?php echo h((string)($didwwDid['awaiting_registration'] ?? '')); ?></td>
                                <td><?php echo h((string)($didwwDid['order_reference'] ?? '')); ?></td>
                            </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>

            <?php if (!empty($didwwSnapshot['inbound_trunks'])): ?>
            <br>
            <table width="100%" cellspacing="0" cellpadding="6" border="0">
                <tr>
                    <td class="form_head" colspan="6">DIDWW Inbound Trunks</td>
                </tr>
                <tr style="font-weight:bold;">
                    <td>Name</td>
                    <td>Type</td>
                    <td>Host</td>
                    <td>Username</td>
                    <td>Capacity</td>
                    <td>Priority / Weight</td>
                </tr>
                <?php foreach (($didwwSnapshot['inbound_trunks'] ?? []) as $didwwTrunk): ?>
                    <?php if (is_array($didwwTrunk)): ?>
                            <tr>
                                <td><?php echo h((string)($didwwTrunk['name'] ?? '')); ?></td>
                                <td><?php echo h((string)($didwwTrunk['configuration_type'] ?? '')); ?></td>
                                <td><?php echo h((string)($didwwTrunk['host'] ?? '')); ?></td>
                                <td><?php echo h((string)($didwwTrunk['username'] ?? '')); ?></td>
                                <td><?php echo h((string)($didwwTrunk['capacity_limit'] ?? '')); ?></td>
                                <td><?php echo h((string)($didwwTrunk['priority'] ?? '')); ?> / <?php echo h((string)($didwwTrunk['weight'] ?? '')); ?></td>
                            </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>

            <?php if (!empty($didwwSnapshot['orders'])): ?>
            <br>
            <table width="100%" cellspacing="0" cellpadding="6" border="0">
                <tr>
                    <td class="form_head" colspan="5">DIDWW Recent Orders</td>
                </tr>
                <tr style="font-weight:bold;">
                    <td>Reference</td>
                    <td>Status</td>
                    <td>Created</td>
                    <td>Items</td>
                    <td>Order ID</td>
                </tr>
                <?php foreach (($didwwSnapshot['orders'] ?? []) as $didwwOrderRow): ?>
                    <?php if (is_array($didwwOrderRow)): ?>
                            <tr>
                                <td><?php echo h((string)($didwwOrderRow['reference'] ?? '')); ?></td>
                                <td><?php echo h((string)($didwwOrderRow['status'] ?? '')); ?></td>
                                <td><?php echo h((string)($didwwOrderRow['created_at'] ?? '')); ?></td>
                                <td><?php echo h((string)($didwwOrderRow['items_count'] ?? '')); ?></td>
                                <td><?php echo h((string)($didwwOrderRow['id'] ?? '')); ?></td>
                            </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>

            <br>
            <form method="post">
                <?php renderProviderCredentialFields($input); ?>
                <table width="100%" cellspacing="0" cellpadding="8">
                    <tr>
                        <td class="form_head" colspan="2">Search DIDWW Available DIDs</td>
                    </tr>
                    <tr>
                        <td width="220"><label for="didww_number_contains">Number Contains</label></td>
                        <td><input id="didww_number_contains" name="didww_number_contains" type="text" size="24" value="<?php echo h($input['didww_number_contains']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="didww_country_id">Country ID</label></td>
                        <td><input id="didww_country_id" name="didww_country_id" type="text" size="24" value="<?php echo h($input['didww_country_id']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="didww_region_id">Region ID</label></td>
                        <td><input id="didww_region_id" name="didww_region_id" type="text" size="24" value="<?php echo h($input['didww_region_id']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="didww_city_id">City ID</label></td>
                        <td><input id="didww_city_id" name="didww_city_id" type="text" size="24" value="<?php echo h($input['didww_city_id']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="didww_features">Features</label></td>
                        <td><input id="didww_features" name="didww_features" type="text" size="32" value="<?php echo h($input['didww_features']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="didww_needs_registration">Needs Registration</label></td>
                        <td>
                            <select id="didww_needs_registration" name="didww_needs_registration">
                                <option value="" <?php echo $input['didww_needs_registration'] === '' ? 'selected' : ''; ?>>Any</option>
                                <option value="true" <?php echo $input['didww_needs_registration'] === 'true' ? 'selected' : ''; ?>>Yes</option>
                                <option value="false" <?php echo $input['didww_needs_registration'] === 'false' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="didww_search_page_size">Result Limit</label></td>
                        <td><input id="didww_search_page_size" name="didww_search_page_size" type="number" min="1" max="100" step="1" size="8" value="<?php echo h($input['didww_search_page_size']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="didww_order_callback_url">Order Callback URL</label></td>
                        <td><input id="didww_order_callback_url" name="didww_order_callback_url" type="text" size="70" value="<?php echo h($input['didww_order_callback_url']); ?>"></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td><label><input name="didww_allow_back_ordering" type="checkbox" value="1" <?php echo $input['didww_allow_back_ordering'] === '1' ? 'checked' : ''; ?>> Allow back ordering</label></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td><button class="form_input_button" name="form_action" type="submit" value="didww_search_available_dids">Search Available DIDs</button></td>
                    </tr>
                </table>
            </form>

            <br>
            <form method="post">
                <?php renderProviderCredentialFields($input); ?>
                <table width="100%" cellspacing="0" cellpadding="8">
                    <tr>
                        <td class="form_head" colspan="2">Create DIDWW Inbound SIP Trunk</td>
                    </tr>
                    <tr>
                        <td width="220"><label for="didww_trunk_name">Trunk Name</label></td>
                        <td><input id="didww_trunk_name" name="didww_trunk_name" type="text" size="40" value="<?php echo h($input['didww_trunk_name']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="didww_trunk_host">SIP Host</label></td>
                        <td><input id="didww_trunk_host" name="didww_trunk_host" type="text" size="50" value="<?php echo h($input['didww_trunk_host']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="didww_trunk_username">Username</label></td>
                        <td><input id="didww_trunk_username" name="didww_trunk_username" type="text" size="32" value="<?php echo h($input['didww_trunk_username']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="didww_trunk_capacity_limit">Capacity Limit</label></td>
                        <td><input id="didww_trunk_capacity_limit" name="didww_trunk_capacity_limit" type="number" min="1" max="10000" step="1" size="8" value="<?php echo h($input['didww_trunk_capacity_limit']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="didww_trunk_priority">Priority</label></td>
                        <td><input id="didww_trunk_priority" name="didww_trunk_priority" type="number" min="0" max="65535" step="1" size="8" value="<?php echo h($input['didww_trunk_priority']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="didww_trunk_weight">Weight</label></td>
                        <td><input id="didww_trunk_weight" name="didww_trunk_weight" type="number" min="0" max="65535" step="1" size="8" value="<?php echo h($input['didww_trunk_weight']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="didww_trunk_cli_format">CLI Format</label></td>
                        <td><input id="didww_trunk_cli_format" name="didww_trunk_cli_format" type="text" size="16" value="<?php echo h($input['didww_trunk_cli_format']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="didww_trunk_cli_prefix">CLI Prefix</label></td>
                        <td><input id="didww_trunk_cli_prefix" name="didww_trunk_cli_prefix" type="text" size="16" value="<?php echo h($input['didww_trunk_cli_prefix']); ?>"></td>
                    </tr>
                    <tr>
                        <td>Options</td>
                        <td>
                            <label><input name="didww_trunk_resolve_ruri" type="checkbox" value="1" <?php echo $input['didww_trunk_resolve_ruri'] === '1' ? 'checked' : ''; ?>> Resolve R-URI</label>
                            &nbsp;
                            <label><input name="didww_trunk_use_did_in_ruri" type="checkbox" value="1" <?php echo $input['didww_trunk_use_did_in_ruri'] === '1' ? 'checked' : ''; ?>> Use DID in R-URI</label>
                            &nbsp;
                            <label><input name="didww_trunk_enabled_sip_registration" type="checkbox" value="1" <?php echo $input['didww_trunk_enabled_sip_registration'] === '1' ? 'checked' : ''; ?>> Enable SIP registration</label>
                        </td>
                    </tr>
                    <tr>
                        <td>Authentication</td>
                        <td>
                            <label><input name="didww_trunk_auth_enabled" type="checkbox" value="1" <?php echo $input['didww_trunk_auth_enabled'] === '1' ? 'checked' : ''; ?>> Require auth</label>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="didww_trunk_auth_user">Auth User</label></td>
                        <td><input id="didww_trunk_auth_user" name="didww_trunk_auth_user" type="text" size="32" value="<?php echo h($input['didww_trunk_auth_user']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="didww_trunk_auth_password">Auth Password</label></td>
                        <td><input id="didww_trunk_auth_password" name="didww_trunk_auth_password" type="password" size="32" value="<?php echo h($input['didww_trunk_auth_password']); ?>"></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td><button class="form_input_button" name="form_action" type="submit" value="didww_create_inbound_trunk">Create Inbound Trunk</button></td>
                    </tr>
                </table>
            </form>

            <?php if ($didwwTrunkProvision): ?>
            <br>
            <table width="100%" cellspacing="0" cellpadding="8">
                <tr>
                    <td class="form_head" colspan="2">DIDWW Inbound Trunk Result</td>
                </tr>
                <tr>
                    <td width="220">Remote Trunk</td>
                    <td><?php echo h((string)($didwwTrunkProvision['remote_trunk']['name'] ?? '')); ?></td>
                </tr>
                <tr>
                    <td>Remote Trunk ID</td>
                    <td><?php echo h((string)($didwwTrunkProvision['remote_trunk']['id'] ?? '')); ?></td>
                </tr>
                <tr>
                    <td>Local Provider ID</td>
                    <td><?php echo h((string)($didwwTrunkProvision['local_trunk']['provider_id'] ?? '')); ?></td>
                </tr>
                <tr>
                    <td>Local Trunk ID</td>
                    <td><?php echo h((string)($didwwTrunkProvision['local_trunk']['trunk_id'] ?? '')); ?></td>
                </tr>
            </table>
            <?php endif; ?>

            <?php if ($didwwOrder): ?>
            <br>
            <table width="100%" cellspacing="0" cellpadding="8">
                <tr>
                    <td class="form_head" colspan="2">DIDWW Order Result</td>
                </tr>
                <tr>
                    <td width="220">Order ID</td>
                    <td><?php echo h((string)($didwwOrder['order']['id'] ?? '')); ?></td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td><?php echo h((string)($didwwOrder['order']['status'] ?? '')); ?></td>
                </tr>
                <tr>
                    <td>Reference</td>
                    <td><?php echo h((string)($didwwOrder['order']['reference'] ?? '')); ?></td>
                </tr>
            </table>
            <?php endif; ?>

            <?php if (!empty($didwwSearch['available_dids'])): ?>
            <br>
            <table width="100%" cellspacing="0" cellpadding="6" border="0">
                <tr>
                    <td class="form_head" colspan="5">DIDWW Available DID Results</td>
                </tr>
                <tr style="font-weight:bold;">
                    <td>Number</td>
                    <td>DID Group</td>
                    <td>SKU</td>
                    <td>DID ID</td>
                    <td>Action</td>
                </tr>
                <?php foreach (($didwwSearch['available_dids'] ?? []) as $availableDidRow): ?>
                    <?php if (is_array($availableDidRow)): ?>
                            <tr>
                                <td><?php echo h((string)($availableDidRow['number'] ?? '')); ?></td>
                                <td><?php echo h((string)($availableDidRow['did_group'] ?? '')); ?></td>
                                <td>
                                    <?php $skuOptions = is_array($availableDidRow['sku_options'] ?? null) ? $availableDidRow['sku_options'] : []; ?>
                                    <?php echo $skuOptions ? h((string)($skuOptions[0]['label'] ?? $skuOptions[0]['id'] ?? '')) : 'No SKU returned'; ?>
                                </td>
                                <td><?php echo h((string)($availableDidRow['id'] ?? '')); ?></td>
                                <td>
                                    <?php if ($skuOptions): ?>
                                    <form method="post" style="margin:0;">
                                        <?php renderProviderCredentialFields($input); ?>
                                        <input type="hidden" name="didww_number_contains" value="<?php echo h($input['didww_number_contains']); ?>">
                                        <input type="hidden" name="didww_country_id" value="<?php echo h($input['didww_country_id']); ?>">
                                        <input type="hidden" name="didww_region_id" value="<?php echo h($input['didww_region_id']); ?>">
                                        <input type="hidden" name="didww_city_id" value="<?php echo h($input['didww_city_id']); ?>">
                                        <input type="hidden" name="didww_features" value="<?php echo h($input['didww_features']); ?>">
                                        <input type="hidden" name="didww_needs_registration" value="<?php echo h($input['didww_needs_registration']); ?>">
                                        <input type="hidden" name="didww_search_page_size" value="<?php echo h($input['didww_search_page_size']); ?>">
                                        <input type="hidden" name="didww_order_callback_url" value="<?php echo h($input['didww_order_callback_url']); ?>">
                                        <input type="hidden" name="didww_allow_back_ordering" value="<?php echo h($input['didww_allow_back_ordering']); ?>">
                                        <input type="hidden" name="didww_available_did_id" value="<?php echo h((string)($availableDidRow['id'] ?? '')); ?>">
                                        <input type="hidden" name="didww_sku_id" value="<?php echo h((string)($skuOptions[0]['id'] ?? '')); ?>">
                                        <button class="form_input_button" name="form_action" type="submit" value="didww_order_did">Order DID</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
            <?php endif; ?>
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

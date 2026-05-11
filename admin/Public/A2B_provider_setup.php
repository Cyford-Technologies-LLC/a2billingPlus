<?php

declare(strict_types=1);

include_once '../lib/admin.defines.php';
include_once '../lib/admin.module.access.php';
include_once '../lib/admin.smarty.php';

use A2BillingPlus\Api\ProviderApiController;
use A2BillingPlus\Bootstrap\ProviderRegistryFactory;
use A2BillingPlus\Config\RuntimeSettingRepository;
use A2BillingPlus\Module\Provider\ProviderSetupService;
use A2BillingPlus\Module\Ui\NavigationRegistry;
use A2BillingPlus\Module\Ui\NavigationRenderer;
use A2BillingPlus\Module\Ui\ThemeRegistry;
use A2BillingPlus\Module\Ui\ThemeRenderer;

if (!has_rights(ACX_ACXSETTING)) {
    Header('HTTP/1.0 401 Unauthorized');
    Header('Location: PP_error.php?c=accessdenied');
    die();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$projectRoot = realpath(__DIR__ . '/../..');
$autoloadPath = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
$themeRegistry = ThemeRegistry::default();
$theme = $themeRegistry->resolve(envString('A2BP_UI_THEME'));
$themeRenderer = new ThemeRenderer();
$navigationRenderer = new NavigationRenderer();

$messages = [];
$errors = [];
$registration = [];
$ratePreview = [];
$rateImport = [];
$twilioConnection = [];
$twilioInventory = [];
$twilioSearch = [];
$twilioPurchase = [];
$twilioTrunk = [];
$twilioSync = [];
$twilioRoutingMode = twilioRoutingMode(envString('TWILIO_ROUTING_MODE', 'elastic'));
$twilioElasticTrunkSid = envString('TWILIO_ELASTIC_TRUNK_SID', envString('TWILIO_TRUNK_SID'));
$twilioByocTrunkSid = envString('TWILIO_BYOC_TRUNK_SID');

$defaults = [
    'base_url' => envString('VECTAVOIP_API_BASE_URL', 'https://api.vectavoip.com'),
    'api_key' => envString('VECTAVOIP_API_KEY'),
    'api_secret' => envString('VECTAVOIP_API_SECRET'),
    'default_upstream_provider' => envString('VECTAVOIP_DEFAULT_UPSTREAM_PROVIDER', 'local'),
    'twilio_sandbox_mode' => envString('TWILIO_SANDBOX_MODE', '0'),
    'twilio_account_sid' => envString('TWILIO_ACCOUNT_SID'),
    'twilio_auth_mode' => envString('TWILIO_AUTH_MODE', twilioDefaultAuthMode()),
    'twilio_api_key' => envString('TWILIO_API_KEY'),
    'twilio_api_secret' => envString('TWILIO_API_SECRET'),
    'twilio_auth_token' => envString('TWILIO_AUTH_TOKEN'),
    'twilio_default_voice_url' => envString('TWILIO_DEFAULT_VOICE_URL'),
    'twilio_default_sms_url' => envString('TWILIO_DEFAULT_SMS_URL'),
    'twilio_routing_mode' => $twilioRoutingMode,
    'twilio_elastic_trunk_sid' => $twilioElasticTrunkSid,
    'twilio_elastic_termination_uri' => envString('TWILIO_ELASTIC_TERMINATION_URI', 'vectavoip.pstn.twilio.com'),
    'twilio_elastic_origination_uri' => envString('TWILIO_ELASTIC_ORIGINATION_URI', 'sip:sip.vectavoip.com'),
    'twilio_sip_domain' => envString('TWILIO_SIP_DOMAIN', 'vectavoip.sip.twilio.com'),
    'twilio_byoc_trunk_sid' => $twilioByocTrunkSid,
    'twilio_trunk_technology' => twilioDefaultTrunkTechnology($twilioRoutingMode, $twilioElasticTrunkSid, $twilioByocTrunkSid),
    'twilio_default_caller_id' => envString('TWILIO_DEFAULT_CALLER_ID'),
    'twilio_page_size' => '25',
    'twilio_trunks_page_size' => '25',
    'twilio_trunk_numbers_page_size' => '25',
    'twilio_search_page_size' => '20',
    'twilio_country_code' => 'US',
    'twilio_contains' => '',
    'twilio_area_code' => '',
    'twilio_sms_enabled' => 'true',
    'twilio_voice_enabled' => 'true',
    'twilio_phone_number' => '',
    'twilio_voice_url' => envString('TWILIO_DEFAULT_VOICE_URL'),
    'twilio_sms_url' => envString('TWILIO_DEFAULT_SMS_URL'),
    'twilio_trunk_friendly_name' => 'A2BillingPlus Twilio Trunk',
    'twilio_trunk_domain_name' => '',
    'twilio_trunk_cnam_lookup_enabled' => '',
    'twilio_sync_page_size' => '100',
    'company_name' => 'VectaVoIP',
    'company_domain' => 'VectaVoIP.com',
    'contact_name' => '',
    'contact_email' => '',
    'contact_phone' => '',
    'details' => '',
    'install_key' => envString('VECTAVOIP_INSTALL_KEY'),
    'target_ratecard_id' => '',
    'target_trunk_id' => '',
    'rate_deck' => 'retail',
    'currency' => 'USD',
    'destination_filter' => '',
    'country_filter' => 'US',
    'prefix_filter' => '',
    'markup_percent' => '35',
    'auto_create_ratecard' => '1',
    'twilio_ratecard_name' => 'Twilio Retail',
    'twilio_callplan_name' => 'Twilio Retail Call Plan',
    'update_existing' => '',
    'save_credentials' => '1',
];

$input = $defaults;
$providerSetup = providerSetupService();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = trim((string)($_POST['form_action'] ?? ''));

    if ($formAction === 'set_ui_theme') {
        $selectedTheme = $themeRegistry->resolve(trim((string)($_POST['ui_theme'] ?? '')));
        saveUiTheme($envPath, $selectedTheme->id(), $messages, $errors);
        $theme = $selectedTheme;
    }

    if ($formAction === 'unlock_provider_modules') {
        unlockProviderModules(trim((string)($_POST['provider_unlock_token'] ?? '')), $messages, $errors);
    }

    if ($formAction === 'lock_provider_modules') {
        $_SESSION['a2bp_provider_modules_unlocked'] = false;
        saveRuntimeSettings(['A2BP_PROVIDER_MODULES_UNLOCKED' => '0'], [], $messages, $errors);
        if (!$errors) {
            $messages[] = 'Locked non-VectaVoIP provider modules.';
        }
    }

    foreach ($defaults as $key => $default) {
        $input[$key] = array_key_exists($key, $_POST) ? trim((string)$_POST[$key]) : (string)$default;
    }
    $input['save_credentials'] = isset($_POST['save_credentials']) ? '1' : '';
    $input['update_existing'] = isset($_POST['update_existing']) ? '1' : '';
    $input['auto_create_ratecard'] = isset($_POST['auto_create_ratecard']) ? '1' : '';
    $input['twilio_sandbox_mode'] = isset($_POST['twilio_sandbox_mode']) ? '1' : '0';
    $input['twilio_routing_mode'] = twilioRoutingMode($input['twilio_routing_mode']);
    $input['twilio_elastic_termination_uri'] = twilioNormalizeHost($input['twilio_elastic_termination_uri']);
    $input['twilio_elastic_origination_uri'] = twilioNormalizeSipUri($input['twilio_elastic_origination_uri']);
    $input['twilio_sip_domain'] = twilioNormalizeHost($input['twilio_sip_domain']);
    $input['twilio_trunk_technology'] = twilioOutboundTrunkTechnology($input['twilio_trunk_technology']);
    $input['provider'] = trim((string)($_POST['provider_context'] ?? $_POST['provider'] ?? 'vectavoip'));
    if (providerModulesUnlocked()) {
        $input['provider_unlock_token'] = providerUnlockToken();
    }
    if ($input['provider'] === 'twilio') {
        $input = twilioApiInput($input);
    }

    if (!in_array($formAction, ['set_ui_theme', 'save_upstream_settings'], true) && $input['base_url'] === '') {
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

    if (!$errors && $formAction === 'save_upstream_settings') {
        if (!providerModulesUnlocked()) {
            $errors[] = 'Enter the provider unlock token before changing locked upstream carrier settings.';
        }
        if (!in_array($input['default_upstream_provider'], ['local', 'twilio'], true)) {
            $errors[] = 'Default upstream provider must be local or twilio.';
        }
        if ($input['provider'] === 'twilio' && $input['default_upstream_provider'] === 'twilio' && $input['twilio_sandbox_mode'] !== '1') {
            foreach (twilioCredentialValidationErrors($input) as $credentialError) {
                $errors[] = $credentialError;
            }
        }
        if ($input['provider'] === 'twilio' && twilioOutboundTrunkHost($input) === '') {
            $errors[] = 'Twilio outbound routing needs a termination host or SIP domain.';
        }
        if (!$errors) {
            saveUpstreamSettings($envPath, $input, $messages, $errors);
            if (!$errors && $input['provider'] === 'twilio') {
                $trunkId = ensureTwilioOutboundTrunk(providerSetupPdo(), $input);
                if ($trunkId > 0) {
                    $messages[] = 'Created or updated A2Billing Twilio outbound trunk #' . $trunkId . ' for ' . twilioRoutingModeLabel($input['twilio_routing_mode']) . '.';
                }
            }
            if (!$errors && $input['provider'] === 'twilio' && $input['twilio_sandbox_mode'] !== '1' && twilioCanTest($input)) {
                $twilioConnection = $providerSetup->testConnection($input);
                if (($twilioConnection['success'] ?? false) !== true) {
                    $errors[] = 'Saved settings, but Twilio verification failed.';
                    $errors[] = (string)($twilioConnection['message'] ?? $twilioConnection['error'] ?? 'Twilio connection failed.');
                    foreach (twilioDiagnosticMessages($twilioConnection['details'] ?? []) as $diagnosticMessage) {
                        $errors[] = $diagnosticMessage;
                    }
                } else {
                    $messages[] = 'Saved settings and verified Twilio credentials.';
                    foreach (twilioDiagnosticMessages($twilioConnection['details'] ?? []) as $diagnosticMessage) {
                        $messages[] = $diagnosticMessage;
                    }
                }
            }
        }
    }

    if (!$errors && $formAction === 'test_twilio_connection') {
        foreach (twilioCredentialValidationErrors($input) as $credentialError) {
            $errors[] = $credentialError;
        }
    }

    if (!$errors && $formAction === 'test_twilio_connection') {
        $twilioConnection = $providerSetup->testConnection($input);
        if (($twilioConnection['success'] ?? false) !== true) {
            $errors[] = (string)($twilioConnection['message'] ?? $twilioConnection['error'] ?? 'Twilio connection failed.');
            foreach (twilioDiagnosticMessages($twilioConnection['details'] ?? []) as $diagnosticMessage) {
                $errors[] = $diagnosticMessage;
            }
        } else {
            $messages[] = (string)($twilioConnection['message'] ?? 'Twilio connection verified.');
            foreach (twilioDiagnosticMessages($twilioConnection['details'] ?? []) as $diagnosticMessage) {
                $messages[] = $diagnosticMessage;
            }
        }
    }

    if (!$errors && $formAction === 'twilio_inventory_snapshot') {
        $twilioInventory = $providerSetup->twilioInventorySnapshot($input);
        if (isset($twilioInventory['error']) || (($twilioInventory['success'] ?? true) !== true)) {
            $errors[] = (string)($twilioInventory['message'] ?? $twilioInventory['error'] ?? 'Twilio inventory failed.');
        } else {
            $messages[] = (string)($twilioInventory['message'] ?? 'Twilio inventory loaded.');
        }
    }

    if (!$errors && $formAction === 'twilio_search_available_numbers') {
        $twilioSearch = $providerSetup->twilioSearchAvailableNumbers($input);
        if (isset($twilioSearch['error']) || (($twilioSearch['success'] ?? true) !== true)) {
            $errors[] = (string)($twilioSearch['message'] ?? $twilioSearch['error'] ?? 'Twilio number search failed.');
        } else {
            $messages[] = (string)($twilioSearch['message'] ?? 'Twilio number search completed.');
        }
    }

    if (!$errors && $formAction === 'twilio_purchase_number') {
        $twilioPurchase = $providerSetup->twilioPurchaseNumber($input);
        if (isset($twilioPurchase['error']) || (($twilioPurchase['success'] ?? true) !== true)) {
            $errors[] = (string)($twilioPurchase['message'] ?? $twilioPurchase['error'] ?? 'Twilio number purchase failed.');
        } else {
            $messages[] = (string)($twilioPurchase['message'] ?? 'Twilio number purchased.');
        }
    }

    if (!$errors && in_array($formAction, ['twilio_create_trunk', 'twilio_register_existing_trunk'], true)) {
        $twilioTrunk = $formAction === 'twilio_create_trunk'
            ? $providerSetup->twilioCreateTrunk($input)
            : $providerSetup->twilioRegisterExistingTrunk($input);
        if (isset($twilioTrunk['error']) || (($twilioTrunk['success'] ?? true) !== true)) {
            $errors[] = (string)($twilioTrunk['message'] ?? $twilioTrunk['error'] ?? 'Twilio trunk setup failed.');
        } else {
            $messages[] = (string)($twilioTrunk['message'] ?? 'Twilio trunk setup completed.');
        }
    }

    if (!$errors && $formAction === 'twilio_sync_inventory') {
        $twilioSync = $providerSetup->twilioSyncInventory($input);
        if (isset($twilioSync['error']) || (($twilioSync['success'] ?? true) !== true)) {
            $errors[] = (string)($twilioSync['message'] ?? $twilioSync['error'] ?? 'Twilio sync failed.');
        } else {
            $messages[] = (string)($twilioSync['message'] ?? 'Twilio sync completed.');
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
        $rateImportPdo = providerSetupPdo();
        $twilioCidGroupId = 0;
        if (($input['provider'] ?? '') === 'twilio') {
            $twilioTrunkId = ensureTwilioOutboundTrunk($rateImportPdo, $input);
            if ($twilioTrunkId > 0) {
                $input['target_trunk_id'] = (string)$twilioTrunkId;
            }
            if ($formAction === 'import_rates' && $input['twilio_default_caller_id'] !== '') {
                $twilioCidGroupId = ensureTwilioOutboundCidGroup($rateImportPdo, $input['twilio_default_caller_id']);
            }
        }
        if (($input['provider'] ?? '') === 'twilio' && (int)$input['target_ratecard_id'] <= 0) {
            try {
                $created = ensureTwilioOutboundRatePlan($rateImportPdo, $input['twilio_ratecard_name'], $input['twilio_callplan_name'], (int)$input['target_trunk_id']);
                $input['target_ratecard_id'] = (string)$created['tariff_plan_id'];
                $messages[] = 'Using ratecard ' . $created['tariff_plan_name'] . ' (#' . $created['tariff_plan_id'] . ') and call plan ' . $created['tariff_group_name'] . ' (#' . $created['tariff_group_id'] . ').';
            } catch (Throwable $exception) {
                $errors[] = 'Could not create or find the Twilio outbound ratecard: ' . $exception->getMessage();
            }
        } elseif (($input['provider'] ?? '') === 'twilio' && (int)$input['target_ratecard_id'] > 0 && (int)$input['target_trunk_id'] > 0) {
            bindTwilioOutboundRatePlan($rateImportPdo, (int)$input['target_ratecard_id'], (int)$input['target_trunk_id']);
        }
        if ((int)$input['target_ratecard_id'] <= 0) {
            $errors[] = 'Rate import needs a target ratecard. Choose one above or leave it blank so A2BillingPlus creates the Twilio Retail ratecard.';
        }
        if (!$errors) {
            $rateImport = $providerSetup->importPreviewRates($input, $formAction === 'dry_run_import_rates');
            if (($rateImport['success'] ?? false) !== true) {
                $errors[] = (string)($rateImport['message'] ?? 'Rate import failed.');
            } else {
                if (($input['provider'] ?? '') === 'twilio' && $formAction === 'import_rates' && (int)$input['target_trunk_id'] > 0) {
                    bindTwilioImportedRateRows($rateImportPdo, (int)$input['target_ratecard_id'], (int)$input['target_trunk_id'], 'Twilio:' . $input['rate_deck'], $twilioCidGroupId);
                    $messages[] = 'Bound Twilio ratecard and imported rates to trunk #' . (int)$input['target_trunk_id'] . ($twilioCidGroupId > 0 ? ' and outbound CID group #' . $twilioCidGroupId : '') . '.';
                }
                $messages[] = (string)($rateImport['message'] ?? 'Rate import completed.');
            }
        }
    }
}

$status = $providerSetup->providerStatus();
$ratecards = $providerSetup->ratecards();
$recentImports = $providerSetup->recentImports();
$providerModulesUnlocked = providerModulesUnlocked();
$selectedProvider = selectedProvider();
$providerCards = providerCards($status, $providerModulesUnlocked, $selectedProvider);

$smarty->display('main.tpl');
echo $themeRenderer->stylesheetLink($theme);

function providerSetupService(): ProviderSetupService
{
    $pdoFactory = fn (): PDO => providerSetupPdo();
    $accessPolicy = new \A2BillingPlus\Module\Provider\ProviderAccessPolicy(
        \A2BillingPlus\Config\AppConfig::fromEnvironment(),
        providerModulesUnlocked()
    );

    return new ProviderSetupService(
        new ProviderApiController(ProviderRegistryFactory::createDefault(), null, $pdoFactory, $accessPolicy),
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
    if (!str_starts_with($key, 'A2BP_DB_')) {
        $runtimeValues = runtimeSettingValues();
        if (($runtimeValues[$key] ?? '') !== '') {
            return $runtimeValues[$key];
        }
    }

    $fileValues = envFileValues();
    if (($fileValues[$key . '_FILE'] ?? '') !== '' && is_readable($fileValues[$key . '_FILE'])) {
        $contents = file_get_contents($fileValues[$key . '_FILE']);
        if (is_string($contents)) {
            return trim($contents);
        }
    }

    if (($fileValues[$key] ?? '') !== '') {
        return $fileValues[$key];
    }

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

    return $default;
}

/**
 * @return array<string,string>
 */
function runtimeSettingValues(): array
{
    static $values = null;
    if (is_array($values)) {
        return $values;
    }

    try {
        $values = (new RuntimeSettingRepository(providerSetupPdo()))->all();
    } catch (Throwable) {
        $values = [];
    }

    return $values;
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

    saveRuntimeSettings($values, ['VECTAVOIP_API_KEY', 'VECTAVOIP_API_SECRET'], $messages, $errors);
    if ($errors) {
        return;
    }

    writeSecretFileValues($values, ['VECTAVOIP_API_KEY', 'VECTAVOIP_API_SECRET'], $messages, $errors);
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

    $messages[] = 'Saved VectaVoIP provider credentials to DB and .env backup.';
}

function saveUiTheme(string $envPath, string $themeId, array &$messages, array &$errors): void
{
    saveRuntimeSettings(['A2BP_UI_THEME' => $themeId], [], $messages, $errors);
    if ($errors) {
        return;
    }

    if (!is_writable(dirname($envPath)) || (is_file($envPath) && !is_writable($envPath))) {
        $messages[] = '.env backup is not writable. UI theme was saved in DB only.';
        return;
    }

    $contents = is_file($envPath) ? (string)file_get_contents($envPath) : '';
    $contents = mergeEnvValues($contents, ['A2BP_UI_THEME' => $themeId]);

    if (@file_put_contents($envPath, $contents) === false) {
        $errors[] = 'Could not write .env. UI theme was not saved.';
        return;
    }

    putenv('A2BP_UI_THEME=' . $themeId);
    $messages[] = 'Saved UI theme in DB and .env backup: ' . $themeId . '.';
}

/**
 * @param array<string, string> $input
 */
function saveUpstreamSettings(string $envPath, array $input, array &$messages, array &$errors): void
{
    $values = [
        'VECTAVOIP_DEFAULT_UPSTREAM_PROVIDER' => $input['default_upstream_provider'],
        'TWILIO_SANDBOX_MODE' => $input['twilio_sandbox_mode'] === '1' ? '1' : '0',
        'TWILIO_ACCOUNT_SID' => $input['twilio_account_sid'],
        'TWILIO_AUTH_MODE' => $input['twilio_auth_mode'],
        'TWILIO_API_KEY' => $input['twilio_api_key'],
        'TWILIO_API_SECRET' => $input['twilio_api_secret'],
        'TWILIO_AUTH_TOKEN' => $input['twilio_auth_token'],
        'TWILIO_DEFAULT_VOICE_URL' => $input['twilio_default_voice_url'],
        'TWILIO_DEFAULT_SMS_URL' => $input['twilio_default_sms_url'],
        'TWILIO_ROUTING_MODE' => twilioRoutingMode($input['twilio_routing_mode']),
        'TWILIO_ELASTIC_TRUNK_SID' => $input['twilio_elastic_trunk_sid'],
        'TWILIO_ELASTIC_TERMINATION_URI' => twilioNormalizeHost($input['twilio_elastic_termination_uri']),
        'TWILIO_ELASTIC_ORIGINATION_URI' => twilioNormalizeSipUri($input['twilio_elastic_origination_uri']),
        'TWILIO_SIP_DOMAIN' => twilioNormalizeHost($input['twilio_sip_domain']),
        'TWILIO_BYOC_TRUNK_SID' => $input['twilio_byoc_trunk_sid'],
        'TWILIO_TRUNK_TECHNOLOGY' => twilioOutboundTrunkTechnology($input['twilio_trunk_technology']),
        'TWILIO_DEFAULT_CALLER_ID' => $input['twilio_default_caller_id'],
    ];

    saveRuntimeSettings($values, ['TWILIO_API_KEY', 'TWILIO_API_SECRET', 'TWILIO_AUTH_TOKEN'], $messages, $errors);
    if ($errors) {
        return;
    }

    if (!is_writable(dirname($envPath)) || (is_file($envPath) && !is_writable($envPath))) {
        $messages[] = '.env backup is not writable. Upstream provider settings were saved in DB only.';
        return;
    }

    writeSecretFileValues($values, ['TWILIO_API_KEY', 'TWILIO_API_SECRET', 'TWILIO_AUTH_TOKEN'], $messages, $errors);
    if ($errors) {
        return;
    }

    $contents = is_file($envPath) ? (string)file_get_contents($envPath) : '';
    $contents = mergeEnvValues($contents, $values);

    if (@file_put_contents($envPath, $contents) === false) {
        $errors[] = 'Could not write .env. Upstream provider settings were not saved.';
        return;
    }

    foreach ($values as $key => $value) {
        putenv($key . '=' . $value);
    }

    $messages[] = 'Saved DID upstream provider settings to DB and .env backup.';
}

/**
 * @param array<string,string> $values
 * @param list<string> $secretKeys
 */
function saveRuntimeSettings(array $values, array $secretKeys, array &$messages, array &$errors): void
{
    try {
        (new RuntimeSettingRepository(providerSetupPdo()))->saveMany($values, $secretKeys);
        $GLOBALS['runtimeSettingValues'] = null;
    } catch (Throwable $exception) {
        $errors[] = 'Could not save runtime settings to DB: ' . $exception->getMessage();
        return;
    }

    foreach ($values as $key => $value) {
        putenv($key . '=' . $value);
    }

    $messages[] = 'Saved runtime settings to DB.';
}

function unlockProviderModules(string $token, array &$messages, array &$errors): void
{
    $expected = providerUnlockToken();
    if ($expected === '') {
        $errors[] = 'Provider unlock token is not configured. Set VECTAVOIP_PROVIDER_UNLOCK_TOKEN or A2BP_PROVIDER_UNLOCK_TOKEN in .env.';
        return;
    }

    if ($token === '' || !hash_equals($expected, $token)) {
        $errors[] = 'Provider unlock token is invalid.';
        return;
    }

    $_SESSION['a2bp_provider_modules_unlocked'] = true;
    saveRuntimeSettings(['A2BP_PROVIDER_MODULES_UNLOCKED' => '1'], [], $messages, $errors);
    if ($errors) {
        return;
    }

    $messages[] = 'Unlocked non-VectaVoIP provider modules persistently.';
}

function providerModulesUnlocked(): bool
{
    if (!empty($_SESSION['a2bp_provider_modules_unlocked'])) {
        return true;
    }

    return envString('A2BP_PROVIDER_MODULES_UNLOCKED', '0') === '1';
}

function providerUnlockToken(): string
{
    $token = envString('VECTAVOIP_PROVIDER_UNLOCK_TOKEN');
    if ($token !== '') {
        return $token;
    }

    return envString('A2BP_PROVIDER_UNLOCK_TOKEN');
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

    $messages[] = 'Saved provider secrets to A2BP_SECRET_DIR.';
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

function twilioDefaultAuthMode(): string
{
    return 'auth_token';
}

/**
 * @param array<string,string> $input
 * @return array<string,string>
 */
function twilioApiInput(array $input): array
{
    $authMode = in_array($input['twilio_auth_mode'] ?? '', ['auth_token', 'api_key'], true)
        ? $input['twilio_auth_mode']
        : twilioDefaultAuthMode();

    $input['provider'] = 'twilio';
    $input['twilio_auth_mode'] = $authMode;
    $input['base_url'] = \A2BillingPlus\Module\Provider\Twilio\TwilioApiClient::API_BASE_URL;
    $input['account_sid'] = $input['twilio_account_sid'];
    $input['api_key'] = twilioEffectiveApiKey($input);
    $input['api_secret'] = twilioEffectiveApiSecret($input);
    $input['twilio_voice_url'] = $input['twilio_voice_url'] !== '' ? $input['twilio_voice_url'] : $input['twilio_default_voice_url'];
    $input['twilio_sms_url'] = $input['twilio_sms_url'] !== '' ? $input['twilio_sms_url'] : $input['twilio_default_sms_url'];

    return $input;
}

/**
 * @param array<string,string> $input
 */
function twilioEffectiveApiKey(array $input): string
{
    return ($input['twilio_auth_mode'] ?? 'auth_token') === 'api_key'
        ? ($input['twilio_api_key'] ?? '')
        : ($input['twilio_account_sid'] ?? '');
}

/**
 * @param array<string,string> $input
 */
function twilioEffectiveApiSecret(array $input): string
{
    return ($input['twilio_auth_mode'] ?? 'auth_token') === 'api_key'
        ? ($input['twilio_api_secret'] ?? '')
        : ($input['twilio_auth_token'] ?? '');
}

/**
 * @param array<string,string> $input
 * @return list<string>
 */
function twilioCredentialValidationErrors(array $input): array
{
    $errors = [];
    if (($input['twilio_account_sid'] ?? '') === '') {
        $errors[] = 'Twilio Account SID is required.';
    } elseif (!str_starts_with($input['twilio_account_sid'], 'AC')) {
        $errors[] = 'Twilio Account SID must start with AC.';
    }

    if (($input['twilio_auth_mode'] ?? 'auth_token') === 'api_key') {
        if (($input['twilio_api_key'] ?? '') === '') {
            $errors[] = 'Twilio API Key is required for API Key + API Secret auth.';
        } elseif (!str_starts_with($input['twilio_api_key'], 'SK')) {
            $errors[] = 'Twilio API Key must start with SK.';
        }
        if (($input['twilio_api_secret'] ?? '') === '') {
            $errors[] = 'Twilio API Secret is required for API Key + API Secret auth.';
        }
    } elseif (($input['twilio_auth_token'] ?? '') === '') {
        $errors[] = 'Twilio Auth Token is required for Account SID + Auth Token auth.';
    }

    return $errors;
}

/**
 * @param array<string,string> $input
 */
function twilioCanTest(array $input): bool
{
    return twilioCredentialValidationErrors($input) === [];
}

/**
 * @param array<string,mixed> $result
 * @return list<array{label:string,value:string}>
 */
function twilioResultRows(array $result): array
{
    $rows = [];
    foreach ([
        'incoming_numbers' => ['Incoming Numbers', 'incoming_phone_numbers'],
        'available_numbers' => ['Available Numbers', 'available_phone_numbers'],
        'trunks' => ['Elastic SIP Trunks', 'trunks'],
        'byoc_trunks' => ['BYOC Trunks', 'byoc_trunks'],
        'trunk_numbers' => ['Trunk Numbers', 'phone_numbers'],
    ] as $key => [$label, $listKey]) {
        if (!is_array($result[$key] ?? null)) {
            continue;
        }
        $payload = $result[$key];
        $items = is_array($payload[$listKey] ?? null) ? $payload[$listKey] : [];
        $sample = '';
        if (isset($items[0]) && is_array($items[0])) {
            $sample = (string)($items[0]['phone_number'] ?? $items[0]['phoneNumber'] ?? $items[0]['friendly_name'] ?? $items[0]['friendlyName'] ?? $items[0]['sid'] ?? '');
        }
        $rows[] = [
            'label' => $label,
            'value' => count($items) . ($sample !== '' ? ' found; first: ' . $sample : ' found'),
        ];
    }

    foreach (['purchase' => 'Purchased Number', 'trunk' => 'Trunk'] as $key => $label) {
        if (!is_array($result[$key] ?? null)) {
            continue;
        }
        $item = $result[$key];
        $rows[] = [
            'label' => $label,
            'value' => (string)($item['phone_number'] ?? $item['phoneNumber'] ?? $item['friendly_name'] ?? $item['friendlyName'] ?? $item['sid'] ?? 'OK'),
        ];
    }

    return $rows;
}

/**
 * @param mixed $details
 * @return list<string>
 */
function twilioDiagnosticMessages(mixed $details): array
{
    if (!is_array($details)) {
        return [];
    }

    $messages = [];
    $messages[] = 'Twilio auth mode attempted: ' . (string)($details['auth_mode'] ?? 'unknown') . '.';
    if (($details['account_sid'] ?? '') !== '') {
        $messages[] = 'Account SID used: ' . (string)$details['account_sid'] . ' (' . (string)($details['account_sid_format'] ?? 'unknown') . ').';
    }
    if (($details['auth_user'] ?? '') !== '') {
        $messages[] = 'Auth user used: ' . (string)$details['auth_user'] . ' (' . (string)($details['api_key_format'] ?? $details['auth_user_format'] ?? 'unknown') . ').';
    }
    $messages[] = 'Secret/token present: ' . (!empty($details['api_secret_present']) ? 'yes' : 'no') . '.';
    if (($details['likely_bad_field'] ?? '') !== '') {
        $messages[] = 'Likely field to fix: ' . (string)$details['likely_bad_field'] . '.';
    }
    if (($details['twilio_error'] ?? '') !== '') {
        $messages[] = 'Twilio returned: ' . (string)$details['twilio_error'] . '.';
    }

    return $messages;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function selectedProvider(): string
{
    $provider = strtolower(trim((string)($_GET['provider'] ?? $_POST['provider_context'] ?? '')));
    return in_array($provider, ['vectavoip', 'twilio'], true) ? $provider : '';
}

/**
 * @param array<string,mixed> $status
 * @return list<array<string,string>>
 */
function providerCards(array $status, bool $providerModulesUnlocked, string $selectedProvider): array
{
    $cards = [[
        'code' => 'vectavoip',
        'name' => 'VectaVoIP',
        'kind' => 'Built-in provider',
        'description' => 'Sell service through the VectaVoIP provider API, register this install, import rates, create accounts, assign DIDs, and support SMS.',
        'status' => $selectedProvider === 'vectavoip' ? 'Open' : (!empty($status['registered']) ? 'Registered' : 'Not registered'),
        'image' => 'templates/default/images/a2billingplus-logo.svg',
        'action' => $selectedProvider === 'vectavoip' ? 'Close' : 'Configure',
        'url' => $selectedProvider === 'vectavoip' ? 'A2B_provider_setup.php' : 'A2B_provider_setup.php?provider=vectavoip',
    ]];

    if ($providerModulesUnlocked) {
        $cards[] = [
            'code' => 'twilio',
            'name' => 'Twilio',
            'kind' => 'Unlocked provider module',
            'description' => 'Configure Twilio credentials, sandbox behavior, DID purchasing, voice callbacks, and SMS callbacks.',
            'status' => $selectedProvider === 'twilio' ? 'Open' : twilioModuleStatus(),
            'image' => '',
            'action' => $selectedProvider === 'twilio' ? 'Close' : 'Configure',
            'url' => $selectedProvider === 'twilio' ? 'A2B_provider_setup.php' : 'A2B_provider_setup.php?provider=twilio',
        ];
    }

    return $cards;
}

function twilioModuleStatus(): string
{
    if (envString('TWILIO_ACCOUNT_SID') === '') {
        return 'Not configured';
    }

    if (envString('VECTAVOIP_DEFAULT_UPSTREAM_PROVIDER', 'local') === 'twilio') {
        return 'Configured - ' . twilioRoutingModeLabel(twilioRoutingMode(envString('TWILIO_ROUTING_MODE', 'elastic')));
    }

    return 'Configured';
}

/**
 * @return array{tariff_plan_id:int,tariff_plan_name:string,tariff_group_id:int,tariff_group_name:string}
 */
function ensureTwilioOutboundRatePlan(PDO $pdo, string $ratecardName, string $callplanName, int $trunkId = 0): array
{
    $ratecardName = trim($ratecardName) !== '' ? trim($ratecardName) : 'Twilio Retail';
    $callplanName = trim($callplanName) !== '' ? trim($callplanName) : $ratecardName . ' Call Plan';

    $planId = findNamedId($pdo, 'cc_tariffplan', 'tariffname', $ratecardName);
    if ($planId <= 0) {
        $statement = $pdo->prepare(
            'INSERT INTO cc_tariffplan (iduser, tariffname, creationdate, description, id_trunk, dnidprefix, calleridprefix)
             VALUES (0, ?, ?, ?, ?, "all", "all")'
        );
        $statement->execute([$ratecardName, gmdate('Y-m-d H:i:s'), 'Retail outbound rates imported from Twilio Pricing API.', max(0, $trunkId)]);
        $planId = (int)$pdo->lastInsertId();
    } elseif ($trunkId > 0) {
        bindTwilioOutboundRatePlan($pdo, $planId, $trunkId);
    }

    $groupId = findNamedId($pdo, 'cc_tariffgroup', 'tariffgroupname', $callplanName);
    if ($groupId <= 0) {
        $statement = $pdo->prepare(
            'INSERT INTO cc_tariffgroup (iduser, idtariffplan, tariffgroupname, lcrtype, creationdate, removeinterprefix, id_cc_package_offer)
             VALUES (0, ?, ?, 0, ?, 0, -1)'
        );
        $statement->execute([$planId, $callplanName, gmdate('Y-m-d H:i:s')]);
        $groupId = (int)$pdo->lastInsertId();
    }

    if (tableExists($pdo, 'cc_tariffgroup_plan')) {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM cc_tariffgroup_plan WHERE idtariffgroup = ? AND idtariffplan = ?');
        $statement->execute([$groupId, $planId]);
        if ((int)$statement->fetchColumn() === 0) {
            $insert = $pdo->prepare('INSERT INTO cc_tariffgroup_plan (idtariffgroup, idtariffplan) VALUES (?, ?)');
            $insert->execute([$groupId, $planId]);
        }
    }

    return [
        'tariff_plan_id' => $planId,
        'tariff_plan_name' => $ratecardName,
        'tariff_group_id' => $groupId,
        'tariff_group_name' => $callplanName,
    ];
}

/**
 * @param array<string, string> $input
 */
function ensureTwilioOutboundTrunk(PDO $pdo, array $input): int
{
    $trunkId = findTwilioOutboundTrunkId($pdo, $input);
    if ($trunkId > 0) {
        normalizeTwilioOutboundTrunk($pdo, $trunkId, $input);
        return $trunkId;
    }

    $host = twilioOutboundTrunkHost($input);
    if ($host === '') {
        return 0;
    }
    $syncKey = twilioOutboundTrunkSyncKey($input);

    $providerId = findNamedId($pdo, 'cc_provider', 'provider_name', 'Twilio');
    if ($providerId <= 0) {
        $statement = $pdo->prepare('INSERT INTO cc_provider (provider_name, description) VALUES (?, ?)');
        $statement->execute(['Twilio', 'Twilio automatically provisioned provider.']);
        $providerId = (int)$pdo->lastInsertId();
    }

    $statement = $pdo->prepare(
        'INSERT INTO cc_trunk
            (trunkcode, trunkprefix, providertech, providerip, removeprefix, failover_trunk, addparameter,
             id_provider, inuse, maxuse, status, if_max_use)
         VALUES (?, "", ?, ?, "", 0, ?, ?, 0, -1, 1, 0)'
    );
    $statement->execute([
        twilioTrunkCode($input),
        twilioOutboundTrunkTechnology($input['twilio_trunk_technology'] ?? ''),
        $host,
        $syncKey,
        $providerId,
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * @param array<string, string> $input
 */
function findTwilioOutboundTrunkId(PDO $pdo, array $input): int
{
    foreach (twilioOutboundTrunkSyncKeyCandidates($input) as $syncKey) {
        $statement = $pdo->prepare('SELECT id_trunk FROM cc_trunk WHERE addparameter = ? LIMIT 1');
        $statement->execute([$syncKey]);
        $id = $statement->fetchColumn();
        if ($id !== false) {
            return (int)$id;
        }
    }

    $host = twilioOutboundTrunkHost($input);
    if ($host !== '') {
        $statement = $pdo->prepare(
            "SELECT t.id_trunk
             FROM cc_trunk t
             LEFT JOIN cc_provider p ON p.id = t.id_provider
             WHERE LOWER(t.providerip) = LOWER(?)
               AND (LOWER(p.provider_name) = 'twilio' OR t.addparameter LIKE 'twilio_%:%' OR t.addparameter LIKE 'twilio_trunk:%')
             ORDER BY t.id_trunk DESC
             LIMIT 1"
        );
        $statement->execute([$host]);
        $id = $statement->fetchColumn();
        if ($id !== false) {
            return (int)$id;
        }
    }

    $statement = $pdo->query(
        "SELECT t.id_trunk
         FROM cc_trunk t
         LEFT JOIN cc_provider p ON p.id = t.id_provider
         WHERE t.addparameter = 'twilio_trunk'
            OR t.addparameter LIKE 'twilio_trunk:%'
            OR t.addparameter LIKE 'twilio_elastic_trunk:%'
            OR t.addparameter LIKE 'twilio_elastic_host:%'
            OR t.addparameter LIKE 'twilio_sip_domain:%'
            OR t.addparameter LIKE 'twilio_byoc_trunk:%'
            OR UPPER(t.trunkcode) LIKE 'TWILIO%'
            OR UPPER(t.trunkcode) LIKE 'TW%'
            OR LOWER(p.provider_name) = 'twilio'
         ORDER BY t.id_trunk DESC
         LIMIT 1"
    );
    if ($statement === false) {
        return 0;
    }

    $id = $statement->fetchColumn();
    return $id === false ? 0 : (int)$id;
}

function bindTwilioOutboundRatePlan(PDO $pdo, int $tariffPlanId, int $trunkId): void
{
    if ($tariffPlanId <= 0 || $trunkId <= 0) {
        return;
    }

    $statement = $pdo->prepare('UPDATE cc_tariffplan SET id_trunk = ?, dnidprefix = "all", calleridprefix = "all" WHERE id = ?');
    $statement->execute([$trunkId, $tariffPlanId]);
}

function bindTwilioImportedRateRows(PDO $pdo, int $tariffPlanId, int $trunkId, string $tag, int $cidGroupId = 0): void
{
    if ($tariffPlanId <= 0) {
        return;
    }

    $assignments = [];
    $values = [];
    if ($trunkId > 0 && columnExists($pdo, 'cc_ratecard', 'id_trunk')) {
        $assignments[] = 'id_trunk = ?';
        $values[] = $trunkId;
    }
    if ($cidGroupId > 0 && columnExists($pdo, 'cc_ratecard', 'id_outbound_cidgroup')) {
        $assignments[] = 'id_outbound_cidgroup = ?';
        $values[] = $cidGroupId;
    }
    if (!$assignments) {
        return;
    }

    $values[] = $tariffPlanId;
    $values[] = $tag;
    $statement = $pdo->prepare('UPDATE cc_ratecard SET ' . implode(', ', $assignments) . ' WHERE idtariffplan = ? AND tag = ?');
    $statement->execute($values);
}

function ensureTwilioOutboundCidGroup(PDO $pdo, string $callerId): int
{
    $callerId = trim($callerId);
    if ($callerId === '' || !tableExists($pdo, 'cc_outbound_cid_group') || !tableExists($pdo, 'cc_outbound_cid_list')) {
        return 0;
    }

    foreach (['cid', 'outbound_cid_group', 'activated'] as $column) {
        if (!columnExists($pdo, 'cc_outbound_cid_list', $column)) {
            return 0;
        }
    }

    $groupName = 'Twilio Default Caller ID';
    $groupId = findNamedId($pdo, 'cc_outbound_cid_group', 'group_name', $groupName);
    if ($groupId <= 0) {
        $statement = $pdo->prepare('INSERT INTO cc_outbound_cid_group (group_name) VALUES (?)');
        $statement->execute([$groupName]);
        $groupId = (int)$pdo->lastInsertId();
    }
    if ($groupId <= 0) {
        return 0;
    }

    $statement = $pdo->prepare('SELECT id FROM cc_outbound_cid_list WHERE outbound_cid_group = ? AND cid = ? LIMIT 1');
    $statement->execute([$groupId, $callerId]);
    $cidId = $statement->fetchColumn();
    if ($cidId === false) {
        $insert = $pdo->prepare('INSERT INTO cc_outbound_cid_list (cid, outbound_cid_group, activated) VALUES (?, ?, 1)');
        $insert->execute([$callerId, $groupId]);
    } else {
        $update = $pdo->prepare('UPDATE cc_outbound_cid_list SET activated = 1 WHERE id = ?');
        $update->execute([(int)$cidId]);
    }

    return $groupId;
}

/**
 * @param array<string, string> $input
 */
function normalizeTwilioOutboundTrunk(PDO $pdo, int $trunkId, array $input): void
{
    $host = twilioOutboundTrunkHost($input);
    if ($host === '') {
        return;
    }

    $statement = $pdo->prepare(
        'UPDATE cc_trunk
         SET providertech = ?,
             providerip = ?,
             addparameter = ?,
             status = 1
         WHERE id_trunk = ?'
    );
    $statement->execute([
        twilioOutboundTrunkTechnology($input['twilio_trunk_technology'] ?? ''),
        $host,
        twilioOutboundTrunkSyncKey($input),
        $trunkId,
    ]);
}

function twilioDefaultTrunkTechnology(string $routingMode, string $elasticTrunkSid, string $byocTrunkSid): string
{
    $existing = twilioExistingTrunkTechnology($routingMode, $elasticTrunkSid, $byocTrunkSid);
    if ($existing !== '') {
        return twilioOutboundTrunkTechnology($existing);
    }

    return twilioOutboundTrunkTechnology(envString('TWILIO_TRUNK_TECHNOLOGY'));
}

function twilioExistingTrunkTechnology(string $routingMode, string $elasticTrunkSid, string $byocTrunkSid): string
{
    try {
        $pdo = providerSetupPdo();
        $trunkId = findTwilioOutboundTrunkId($pdo, [
            'twilio_routing_mode' => $routingMode,
            'twilio_elastic_trunk_sid' => $elasticTrunkSid,
            'twilio_elastic_termination_uri' => envString('TWILIO_ELASTIC_TERMINATION_URI', 'vectavoip.pstn.twilio.com'),
            'twilio_sip_domain' => envString('TWILIO_SIP_DOMAIN', 'vectavoip.sip.twilio.com'),
            'twilio_byoc_trunk_sid' => $byocTrunkSid,
        ]);
        if ($trunkId <= 0) {
            return '';
        }

        $statement = $pdo->prepare('SELECT providertech FROM cc_trunk WHERE id_trunk = ? LIMIT 1');
        $statement->execute([$trunkId]);
        $technology = $statement->fetchColumn();
        return is_string($technology) ? trim($technology) : '';
    } catch (Throwable) {
        return '';
    }
}

function twilioOutboundTrunkTechnology(string $technology = ''): string
{
    $configured = strtoupper(trim($technology));
    if (in_array($configured, ['SIP', 'PJSIP', 'IAX2'], true)) {
        return $configured;
    }

    $configured = strtoupper(trim(envString('TWILIO_TRUNK_TECHNOLOGY')));
    if (in_array($configured, ['SIP', 'PJSIP', 'IAX2'], true)) {
        return $configured;
    }

    $driver = strtolower(envString('A2BP_ASTERISK_CHANNEL_DRIVER', 'pjsip'));
    return match ($driver) {
        'sip', 'chan_sip' => 'SIP',
        'iax', 'iax2' => 'IAX2',
        default => 'PJSIP',
    };
}

function twilioRoutingMode(string $mode): string
{
    $mode = strtolower(trim($mode));
    return in_array($mode, ['elastic', 'sip_domain', 'byoc'], true) ? $mode : 'elastic';
}

function twilioRoutingModeLabel(string $mode): string
{
    return match (twilioRoutingMode($mode)) {
        'sip_domain' => 'SIP Domain / TwiML',
        'byoc' => 'BYOC Trunking',
        default => 'Elastic SIP Trunking',
    };
}

function twilioNormalizeHost(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('#^[A-Za-z]+:#', '', $value) ?? $value;
    $value = preg_replace('#^//#', '', $value) ?? $value;
    $value = preg_replace('#^([^@/]+@)#', '', $value) ?? $value;
    $value = preg_replace('#[/?\#].*$#', '', $value) ?? $value;
    return trim($value);
}

function twilioNormalizeSipUri(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return preg_match('#^[A-Za-z][A-Za-z0-9+.-]*:#', $value) === 1 ? $value : 'sip:' . $value;
}

/**
 * @param array<string, string> $input
 */
function twilioOutboundTrunkHost(array $input): string
{
    return match (twilioRoutingMode($input['twilio_routing_mode'] ?? 'elastic')) {
        'sip_domain' => twilioNormalizeHost($input['twilio_sip_domain'] ?? ''),
        'byoc' => twilioNormalizeHost($input['twilio_sip_domain'] ?? '') ?: 'sip.twilio.com',
        default => twilioNormalizeHost($input['twilio_elastic_termination_uri'] ?? '') ?: 'vectavoip.pstn.twilio.com',
    };
}

/**
 * @param array<string, string> $input
 */
function twilioOutboundTrunkSyncKey(array $input): string
{
    $mode = twilioRoutingMode($input['twilio_routing_mode'] ?? 'elastic');
    if ($mode === 'elastic') {
        $sid = trim($input['twilio_elastic_trunk_sid'] ?? '');
        if ($sid !== '') {
            return 'twilio_elastic_trunk:' . $sid;
        }

        return 'twilio_elastic_host:' . twilioOutboundTrunkHost($input);
    }

    if ($mode === 'sip_domain') {
        return 'twilio_sip_domain:' . twilioOutboundTrunkHost($input);
    }

    $sid = twilioPreferredTrunkSid($input['twilio_byoc_trunk_sid'] ?? '');
    if ($sid !== '') {
        return 'twilio_byoc_trunk:' . $sid;
    }

    return 'twilio_byoc_host:' . twilioOutboundTrunkHost($input);
}

/**
 * @param array<string, string> $input
 * @return list<string>
 */
function twilioOutboundTrunkSyncKeyCandidates(array $input): array
{
    $keys = [twilioOutboundTrunkSyncKey($input)];

    foreach (twilioTrunkSidCandidates($input['twilio_elastic_trunk_sid'] ?? '') as $sid) {
        $keys[] = 'twilio_elastic_trunk:' . $sid;
        $keys[] = 'twilio_trunk:' . $sid;
    }
    foreach (twilioTrunkSidCandidates($input['twilio_byoc_trunk_sid'] ?? '') as $sid) {
        $keys[] = 'twilio_byoc_trunk:' . $sid;
        $keys[] = 'twilio_trunk:' . $sid;
    }

    $host = twilioOutboundTrunkHost($input);
    if ($host !== '') {
        $keys[] = 'twilio_elastic_host:' . $host;
        $keys[] = 'twilio_sip_domain:' . $host;
        $keys[] = 'twilio_byoc_host:' . $host;
    }

    return array_values(array_unique(array_filter($keys, static fn (string $value): bool => trim($value) !== '')));
}

/**
 * @return list<string>
 */
function twilioTrunkSidCandidates(string $sid): array
{
    $sid = trim($sid);
    if ($sid === '') {
        return [];
    }

    $candidates = [$sid];
    if (str_starts_with(strtoupper($sid), 'SIDBY')) {
        $candidates[] = substr($sid, 3);
    } elseif (str_starts_with(strtoupper($sid), 'BY')) {
        $candidates[] = 'SID' . $sid;
    }

    return array_values(array_unique(array_filter($candidates, static fn (string $value): bool => trim($value) !== '')));
}

function twilioPreferredTrunkSid(string $sid): string
{
    foreach (twilioTrunkSidCandidates($sid) as $candidate) {
        if (str_starts_with(strtoupper($candidate), 'BY')) {
            return $candidate;
        }
    }

    return trim($sid);
}

/**
 * @param array<string, string> $input
 */
function twilioTrunkCode(array $input): string
{
    $mode = twilioRoutingMode($input['twilio_routing_mode'] ?? 'elastic');
    $seed = match ($mode) {
        'sip_domain' => 'TWILIO_SIP_' . twilioOutboundTrunkHost($input),
        'byoc' => 'TWILIO_BYOC_' . twilioPreferredTrunkSid($input['twilio_byoc_trunk_sid'] ?? ''),
        default => 'TWILIO_ELASTIC_' . (($input['twilio_elastic_trunk_sid'] ?? '') ?: twilioOutboundTrunkHost($input)),
    };
    $safe = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $seed) ?? '');
    return substr($safe !== '' ? $safe : 'TWILIO', 0, 20);
}

function findNamedId(PDO $pdo, string $table, string $nameColumn, string $name): int
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $nameColumn)) {
        return 0;
    }

    $statement = $pdo->prepare('SELECT id FROM `' . $table . '` WHERE `' . $nameColumn . '` = ? LIMIT 1');
    $statement->execute([$name]);
    $id = $statement->fetchColumn();

    return $id === false ? 0 : (int)$id;
}

function tableExists(PDO $pdo, string $table): bool
{
    try {
        $statement = $pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
        return $statement !== false;
    } catch (Throwable) {
        return false;
    }
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return false;
    }

    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->query('PRAGMA table_info(' . $table . ')');
            foreach (($statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
                if ((string)($row['name'] ?? '') === $column) {
                    return true;
                }
            }
            return false;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $statement->execute([$table, $column]);
        return (int)$statement->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

?>
<br>
<div class="<?php echo h($theme->bodyClass()); ?>">
<div class="a2bp-page">
    <?php echo $navigationRenderer->render(NavigationRegistry::admin(), 'provider-setup', $theme, $themeRegistry->all()); ?>
    <div class="a2bp-panel">
        <div class="a2bp-panel__header">
            <h1 class="a2bp-panel__title">Provider Connection Setup</h1>
        </div>
        <div class="a2bp-panel__body a2bp-muted">
            Choose the provider module to manage. VectaVoIP is built in; non-VectaVoIP modules stay hidden until the provider unlock token is entered.
            <form method="post" style="margin-top:12px;">
                <table width="100%" cellspacing="0" cellpadding="8" style="border:1px solid #ccc;background:#fff;">
                    <tr>
                        <td>
                <?php if (!$providerModulesUnlocked): ?>
                    <input type="hidden" name="form_action" value="unlock_provider_modules">
                    <label for="provider_unlock_token"><strong>Unlock Hidden Provider Modules</strong></label>
                    <br>
                    <input id="provider_unlock_token" name="provider_unlock_token" type="password" size="40" value="" style="background:#fff;color:#111;border:1px solid #777;height:28px;line-height:28px;padding:2px 6px;min-width:320px;">
                    <input class="form_input_button" type="submit" value="Unlock Options">
                <?php else: ?>
                    <input type="hidden" name="form_action" value="lock_provider_modules">
                    <strong>Hidden provider modules are persistently unlocked.</strong>
                    <br>
                    <input class="form_input_button" type="submit" value="Lock Again">
                <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
    </div>

<table width="95%" class="provider_setup_page">
    <tr>
        <td class="form_head">Provider Modules</td>
    </tr>
    <tr>
        <td class="tdstyle_001">
            <?php foreach ($messages as $message): ?>
                <div class="a2bp-alert a2bp-alert--success">
                    <?php echo h($message); ?>
                </div>
            <?php endforeach; ?>

            <?php foreach ($errors as $error): ?>
                <div class="a2bp-alert a2bp-alert--error">
                    <?php echo h($error); ?>
                </div>
            <?php endforeach; ?>

            <table width="100%" cellspacing="0" cellpadding="10">
                <tr>
                    <td class="form_head" colspan="3">Available Provider Modules</td>
                </tr>
                <?php foreach ($providerCards as $card): ?>
                    <tr>
                        <td width="96" style="vertical-align:top;">
                            <?php if ($card['image'] !== ''): ?>
                                <img src="<?php echo h($card['image']); ?>" alt="<?php echo h($card['name']); ?>" style="width:72px;max-height:72px;">
                            <?php else: ?>
                                <div style="width:72px;height:72px;line-height:72px;text-align:center;border:1px solid #ccc;background:#f5f5f5;font-weight:bold;">
                                    <?php echo h(substr($card['name'], 0, 2)); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align:top;">
                            <strong><?php echo h($card['name']); ?></strong>
                            <br><span style="color:#666;"><?php echo h($card['kind']); ?> - <?php echo h($card['status']); ?></span>
                            <br><?php echo h($card['description']); ?>
                        </td>
                        <td width="140" style="vertical-align:top;text-align:right;">
                            <a class="form_input_button" href="<?php echo h($card['url']); ?>"><?php echo h($card['action']); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php if ($selectedProvider === 'twilio' && $providerModulesUnlocked): ?>
            <br>
            <table width="100%" cellspacing="0" cellpadding="8" style="border-top:1px solid #ddd;">
                <tr>
                    <td class="form_head" colspan="2">Twilio Module Setup</td>
                </tr>
                <tr>
                    <td colspan="2" style="color:#666;">
                        Configure Twilio for DID purchases, inbound webhooks, SMS, and outbound voice. Elastic SIP Trunking is the default because A2Billing can route directly to the trunk termination URI without a TwiML loop.
                    </td>
                </tr>
            </table>
            <form method="post">
                <input type="hidden" name="form_action" value="save_upstream_settings">
                <input type="hidden" name="provider_context" value="twilio">
                <table width="100%" cellspacing="0" cellpadding="8">
                    <tr>
                        <td width="220"><label for="default_upstream_provider">DID Purchase Mode</label></td>
                        <td>
                            <select id="default_upstream_provider" name="default_upstream_provider">
                                <option value="local" <?php echo $input['default_upstream_provider'] === 'local' ? 'selected' : ''; ?>>Do not purchase DIDs from Twilio</option>
                                <option value="twilio" <?php echo $input['default_upstream_provider'] === 'twilio' ? 'selected' : ''; ?>>Use Twilio for DID purchases</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                            <label>
                                <input name="twilio_sandbox_mode" type="checkbox" value="1" <?php echo $input['twilio_sandbox_mode'] === '1' ? 'checked' : ''; ?>>
                                Local Twilio sandbox mode
                            </label>
                            <br><span style="color:#666;">Use this only when you do not want any Twilio API call. It records a local PN_SANDBOX purchase.</span>
                            <br><span style="color:#666;">For Twilio Console test credentials, leave this unchecked, enter the Test Account SID below, put the Test auth token in Auth Token, and leave API Key/API Secret blank.</span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="twilio_routing_mode">Twilio Call Routing</label></td>
                        <td>
                            <select id="twilio_routing_mode" name="twilio_routing_mode">
                                <option value="elastic" <?php echo $input['twilio_routing_mode'] === 'elastic' ? 'selected' : ''; ?>>Elastic SIP Trunking - recommended for A2Billing</option>
                                <option value="sip_domain" <?php echo $input['twilio_routing_mode'] === 'sip_domain' ? 'selected' : ''; ?>>SIP Domain / TwiML application</option>
                                <option value="byoc" <?php echo $input['twilio_routing_mode'] === 'byoc' ? 'selected' : ''; ?>>BYOC Trunking</option>
                            </select>
                            <br><span style="color:#666;">Changing this changes which Twilio fields are used to create the A2Billing outbound trunk.</span>
                        </td>
                    </tr>
                    <tr data-twilio-mode="elastic">
                        <td><label for="twilio_elastic_trunk_sid">Elastic SIP Trunk SID</label></td>
                        <td>
                            <input id="twilio_elastic_trunk_sid" name="twilio_elastic_trunk_sid" type="text" size="70" value="<?php echo h($input['twilio_elastic_trunk_sid']); ?>" placeholder="TK...">
                            <br><span style="color:#666;">Twilio Console value from Voice &gt; Elastic SIP Trunking &gt; Trunk details. Used to sync/attach DIDs when available.</span>
                        </td>
                    </tr>
                    <tr data-twilio-mode="elastic">
                        <td><label for="twilio_elastic_termination_uri">Elastic Termination URI</label></td>
                        <td>
                            <input id="twilio_elastic_termination_uri" name="twilio_elastic_termination_uri" type="text" size="70" value="<?php echo h($input['twilio_elastic_termination_uri']); ?>" placeholder="vectavoip.pstn.twilio.com">
                            <br><span style="color:#666;">A2Billing outbound calls are sent here. Current default: vectavoip.pstn.twilio.com.</span>
                        </td>
                    </tr>
                    <tr data-twilio-mode="elastic">
                        <td><label for="twilio_elastic_origination_uri">Elastic Origination URI</label></td>
                        <td>
                            <input id="twilio_elastic_origination_uri" name="twilio_elastic_origination_uri" type="text" size="70" value="<?php echo h($input['twilio_elastic_origination_uri']); ?>" placeholder="sip:sip.vectavoip.com">
                            <br><span style="color:#666;">Use this in the Twilio trunk Origination URI so inbound PSTN calls reach this Asterisk install.</span>
                        </td>
                    </tr>
                    <tr data-twilio-mode="sip_domain byoc">
                        <td><label for="twilio_sip_domain">SIP Domain Host</label></td>
                        <td>
                            <input id="twilio_sip_domain" name="twilio_sip_domain" type="text" size="70" value="<?php echo h($input['twilio_sip_domain']); ?>" placeholder="vectavoip.sip.twilio.com">
                            <br><span style="color:#666;">Only use this for SIP Domain/TwiML or BYOC flows. For Elastic outbound, use the termination URI above.</span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="twilio_account_sid">Twilio Account SID</label></td>
                        <td><input id="twilio_account_sid" name="twilio_account_sid" type="text" size="70" value="<?php echo h($input['twilio_account_sid']); ?>" placeholder="AC_SANDBOX"></td>
                    </tr>
                    <tr>
                        <td><label for="twilio_auth_mode">Auth Method</label></td>
                        <td>
                            <select id="twilio_auth_mode" name="twilio_auth_mode">
                                <option value="auth_token" <?php echo $input['twilio_auth_mode'] === 'auth_token' ? 'selected' : ''; ?>>Account SID + Auth Token</option>
                                <option value="api_key" <?php echo $input['twilio_auth_mode'] === 'api_key' ? 'selected' : ''; ?>>API Key + API Secret</option>
                            </select>
                            <br><span style="color:#666;">Use Account SID + Auth Token unless you created a Twilio API key yourself. Save will verify Twilio with this exact method.</span>
                        </td>
                    </tr>
                    <tr class="twilio-auth-api-key" style="<?php echo $input['twilio_auth_mode'] === 'api_key' ? '' : 'display:none;'; ?>">
                        <td><label for="twilio_api_key">Twilio API Key</label></td>
                        <td>
                            <input id="twilio_api_key" name="twilio_api_key" type="text" size="70" value="<?php echo h($input['twilio_api_key']); ?>" placeholder="SK...">
                            <br><span style="color:#666;">Used only with API Key + API Secret auth.</span>
                        </td>
                    </tr>
                    <tr class="twilio-auth-api-key" style="<?php echo $input['twilio_auth_mode'] === 'api_key' ? '' : 'display:none;'; ?>">
                        <td><label for="twilio_api_secret">Twilio API Secret</label></td>
                        <td>
                            <input id="twilio_api_secret" name="twilio_api_secret" type="password" size="70" value="<?php echo h($input['twilio_api_secret']); ?>">
                            <br><span style="color:#666;">Must match the selected SK API Key.</span>
                        </td>
                    </tr>
                    <tr class="twilio-auth-token" style="<?php echo $input['twilio_auth_mode'] === 'auth_token' ? '' : 'display:none;'; ?>">
                        <td><label for="twilio_auth_token">Twilio Auth Token</label></td>
                        <td>
                            <input id="twilio_auth_token" name="twilio_auth_token" type="password" size="70" value="<?php echo h($input['twilio_auth_token']); ?>">
                            <br><span style="color:#666;">Used only with Account SID + Auth Token auth.</span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="twilio_default_voice_url">Default Voice URL</label></td>
                        <td>
                            <input id="twilio_default_voice_url" name="twilio_default_voice_url" type="text" size="70" value="<?php echo h($input['twilio_default_voice_url']); ?>">
                            <br><span style="color:#666;">Used when purchasing numbers with webhook/TwiML routing. Elastic trunk inbound voice uses the trunk Origination URI.</span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="twilio_default_sms_url">Default SMS URL</label></td>
                        <td><input id="twilio_default_sms_url" name="twilio_default_sms_url" type="text" size="70" value="<?php echo h($input['twilio_default_sms_url']); ?>"></td>
                    </tr>
                    <tr data-twilio-mode="byoc">
                        <td><label for="twilio_byoc_trunk_sid">BYOC Trunk SID</label></td>
                        <td>
                            <input id="twilio_byoc_trunk_sid" name="twilio_byoc_trunk_sid" type="text" size="70" value="<?php echo h($input['twilio_byoc_trunk_sid']); ?>" placeholder="BY...">
                            <br><span style="color:#666;">BYOC is not the default A2Billing path. Use Elastic unless you are bringing your own carrier into Twilio Voice.</span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="twilio_trunk_technology">Outbound Trunk Technology</label></td>
                        <td>
                            <select id="twilio_trunk_technology" name="twilio_trunk_technology">
                                <?php foreach (['PJSIP', 'SIP', 'IAX2'] as $technology): ?>
                                    <option value="<?php echo h($technology); ?>" <?php echo $input['twilio_trunk_technology'] === $technology ? 'selected' : ''; ?>><?php echo h($technology); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <br><span style="color:#666;">This writes the trunk Provider Tech field. The AGI honors the trunk value and does not auto-convert SIP to PJSIP.</span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="twilio_default_caller_id">Default Outbound Caller ID</label></td>
                        <td>
                            <input id="twilio_default_caller_id" name="twilio_default_caller_id" type="text" size="35" value="<?php echo h($input['twilio_default_caller_id']); ?>" placeholder="+14045550100">
                            <br><span style="color:#666;">Optional. Rate import stores this as an A2Billing outbound CID group on Twilio rate rows. Leave blank to use the customer or device caller ID.</span>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                            <input class="form_input_button" type="submit" value="Save Settings, Create Trunk, and Verify Twilio">
                            <br><span style="color:#666;">This saves settings, creates or updates the matching A2Billing trunk, then verifies Twilio when live credentials are available.</span>
                        </td>
                    </tr>
                </table>
            </form>
            <script type="text/javascript">
            (function () {
                var mode = document.getElementById('twilio_auth_mode');
                var routingMode = document.getElementById('twilio_routing_mode');
                var routingRows = document.querySelectorAll('[data-twilio-mode]');
                function toggleTwilioAuthRows() {
                    if (!mode) {
                        return;
                    }
                    var apiRows = document.getElementsByClassName('twilio-auth-api-key');
                    var tokenRows = document.getElementsByClassName('twilio-auth-token');
                    var showApi = mode.value === 'api_key';
                    for (var i = 0; i < apiRows.length; i++) {
                        apiRows[i].style.display = showApi ? '' : 'none';
                    }
                    for (var j = 0; j < tokenRows.length; j++) {
                        tokenRows[j].style.display = showApi ? 'none' : '';
                    }
                }
                function toggleTwilioRoutingRows() {
                    if (!routingMode) {
                        return;
                    }
                    var selected = routingMode.value || 'elastic';
                    for (var k = 0; k < routingRows.length; k++) {
                        var modes = (routingRows[k].getAttribute('data-twilio-mode') || '').split(/\s+/);
                        routingRows[k].style.display = modes.indexOf(selected) >= 0 ? '' : 'none';
                    }
                }
                if (mode) {
                    mode.onchange = toggleTwilioAuthRows;
                }
                if (routingMode) {
                    routingMode.onchange = toggleTwilioRoutingRows;
                }
                toggleTwilioAuthRows();
                toggleTwilioRoutingRows();
            }());
            </script>

            <br>
            <table width="100%" cellspacing="0" cellpadding="8" style="border-top:1px solid #ddd;">
                <tr>
                    <td class="form_head" colspan="2">Twilio DID, Voice, SMS, and Trunk Tools</td>
                </tr>
                <tr>
                    <td colspan="2" style="color:#666;">
                        Use these actions after saving credentials. They use the same Twilio Account SID, API/Auth credentials, callback URLs, and BYOC/trunk SID above.
                    </td>
                </tr>
            </table>
            <form method="post">
                <input type="hidden" name="provider_context" value="twilio">
                <input type="hidden" name="twilio_account_sid" value="<?php echo h($input['twilio_account_sid']); ?>">
                <input type="hidden" name="twilio_auth_mode" value="<?php echo h($input['twilio_auth_mode']); ?>">
                <input type="hidden" name="twilio_api_key" value="<?php echo h($input['twilio_api_key']); ?>">
                <input type="hidden" name="twilio_api_secret" value="<?php echo h($input['twilio_api_secret']); ?>">
                <input type="hidden" name="twilio_auth_token" value="<?php echo h($input['twilio_auth_token']); ?>">
                <input type="hidden" name="twilio_default_voice_url" value="<?php echo h($input['twilio_default_voice_url']); ?>">
                <input type="hidden" name="twilio_default_sms_url" value="<?php echo h($input['twilio_default_sms_url']); ?>">
                <input type="hidden" name="twilio_routing_mode" value="<?php echo h($input['twilio_routing_mode']); ?>">
                <input type="hidden" name="twilio_elastic_trunk_sid" value="<?php echo h($input['twilio_elastic_trunk_sid']); ?>">
                <input type="hidden" name="twilio_elastic_termination_uri" value="<?php echo h($input['twilio_elastic_termination_uri']); ?>">
                <input type="hidden" name="twilio_elastic_origination_uri" value="<?php echo h($input['twilio_elastic_origination_uri']); ?>">
                <input type="hidden" name="twilio_sip_domain" value="<?php echo h($input['twilio_sip_domain']); ?>">
                <input type="hidden" name="twilio_byoc_trunk_sid" value="<?php echo h($input['twilio_byoc_trunk_sid']); ?>">
                <table width="100%" cellspacing="0" cellpadding="8">
                    <tr>
                        <td width="220">Connection</td>
                        <td>
                            <button class="form_input_button" name="form_action" type="submit" value="test_twilio_connection">Test Twilio Auth</button>
                            <button class="form_input_button" name="form_action" type="submit" value="twilio_inventory_snapshot">Load Inventory</button>
                            <button class="form_input_button" name="form_action" type="submit" value="twilio_sync_inventory">Sync Inventory</button>
                            Page size <input name="twilio_page_size" type="text" size="5" value="<?php echo h($input['twilio_page_size']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <td><label for="twilio_country_code">Search Available DIDs</label></td>
                        <td>
                            Country <input id="twilio_country_code" name="twilio_country_code" type="text" size="4" value="<?php echo h($input['twilio_country_code']); ?>">
                            Area code <input name="twilio_area_code" type="text" size="8" value="<?php echo h($input['twilio_area_code']); ?>">
                            Contains <input name="twilio_contains" type="text" size="18" value="<?php echo h($input['twilio_contains']); ?>">
                            Limit <input name="twilio_search_page_size" type="text" size="5" value="<?php echo h($input['twilio_search_page_size']); ?>">
                            <label><input name="twilio_voice_enabled" type="checkbox" value="true" <?php echo $input['twilio_voice_enabled'] !== '' ? 'checked' : ''; ?>> Voice</label>
                            <label><input name="twilio_sms_enabled" type="checkbox" value="true" <?php echo $input['twilio_sms_enabled'] !== '' ? 'checked' : ''; ?>> SMS</label>
                            <button class="form_input_button" name="form_action" type="submit" value="twilio_search_available_numbers">Search Numbers</button>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="twilio_phone_number">Purchase DID</label></td>
                        <td>
                            Number <input id="twilio_phone_number" name="twilio_phone_number" type="text" size="20" value="<?php echo h($input['twilio_phone_number']); ?>" placeholder="+15551234567">
                            Voice URL <input name="twilio_voice_url" type="text" size="38" value="<?php echo h($input['twilio_voice_url']); ?>">
                            SMS URL <input name="twilio_sms_url" type="text" size="38" value="<?php echo h($input['twilio_sms_url']); ?>">
                            <button class="form_input_button" name="form_action" type="submit" value="twilio_purchase_number" onclick="return confirm('Purchase this DID from Twilio now?');">Purchase DID</button>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="twilio_trunk_friendly_name">Trunk/BYOC</label></td>
                        <td>
                            Name <input id="twilio_trunk_friendly_name" name="twilio_trunk_friendly_name" type="text" size="28" value="<?php echo h($input['twilio_trunk_friendly_name']); ?>">
                            Domain <input name="twilio_trunk_domain_name" type="text" size="28" value="<?php echo h($input['twilio_trunk_domain_name']); ?>">
                            <label><input name="twilio_trunk_cnam_lookup_enabled" type="checkbox" value="true" <?php echo $input['twilio_trunk_cnam_lookup_enabled'] !== '' ? 'checked' : ''; ?>> CNAM</label>
                            <button class="form_input_button" name="form_action" type="submit" value="twilio_register_existing_trunk">Verify Saved Trunk</button>
                            <button class="form_input_button" name="form_action" type="submit" value="twilio_create_trunk">Create Trunk</button>
                        </td>
                    </tr>
                </table>
            </form>

            <?php $twilioResult = $twilioInventory ?: ($twilioSearch ?: ($twilioPurchase ?: ($twilioTrunk ?: $twilioSync))); ?>
            <?php if ($twilioResult): ?>
                <br>
                <table width="100%" cellspacing="0" cellpadding="6" border="0">
                    <tr><td class="form_head" colspan="6">Twilio Action Result</td></tr>
                    <tr><td colspan="6"><?php echo h((string)($twilioResult['message'] ?? 'Completed.')); ?></td></tr>
                    <?php foreach (twilioResultRows($twilioResult) as $row): ?>
                        <tr>
                            <td width="160"><strong><?php echo h((string)($row['label'] ?? 'Item')); ?></strong></td>
                            <td><?php echo h((string)($row['value'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <br>
            <table width="100%" cellspacing="0" cellpadding="8" style="border-top:1px solid #ddd;">
                <tr>
                    <td class="form_head" colspan="2">Twilio Outbound Voice Rates</td>
                </tr>
                <tr>
                    <td colspan="2" style="color:#666;">
                        Import Twilio Pricing API outbound voice rates into an A2Billing ratecard. The markup percent sets the retail sell rate above Twilio cost.
                    </td>
                </tr>
            </table>
            <form method="post">
                <input type="hidden" name="provider_context" value="twilio">
                <input type="hidden" name="provider" value="twilio">
                <input type="hidden" name="auto_create_ratecard" value="1">
                <input type="hidden" name="base_url" value="<?php echo h(\A2BillingPlus\Module\Provider\Twilio\TwilioApiClient::API_BASE_URL); ?>">
                <input type="hidden" name="account_sid" value="<?php echo h($input['twilio_account_sid']); ?>">
                <input type="hidden" name="twilio_account_sid" value="<?php echo h($input['twilio_account_sid']); ?>">
                <input type="hidden" name="twilio_auth_mode" value="<?php echo h($input['twilio_auth_mode']); ?>">
                <input type="hidden" name="api_key" value="<?php echo h(twilioEffectiveApiKey($input)); ?>">
                <input type="hidden" name="api_secret" value="<?php echo h(twilioEffectiveApiSecret($input)); ?>">
                <input type="hidden" name="twilio_api_key" value="<?php echo h($input['twilio_api_key']); ?>">
                <input type="hidden" name="twilio_api_secret" value="<?php echo h($input['twilio_api_secret']); ?>">
                <input type="hidden" name="twilio_auth_token" value="<?php echo h($input['twilio_auth_token']); ?>">
                <input type="hidden" name="twilio_routing_mode" value="<?php echo h($input['twilio_routing_mode']); ?>">
                <input type="hidden" name="twilio_elastic_trunk_sid" value="<?php echo h($input['twilio_elastic_trunk_sid']); ?>">
                <input type="hidden" name="twilio_elastic_termination_uri" value="<?php echo h($input['twilio_elastic_termination_uri']); ?>">
                <input type="hidden" name="twilio_elastic_origination_uri" value="<?php echo h($input['twilio_elastic_origination_uri']); ?>">
                <input type="hidden" name="twilio_sip_domain" value="<?php echo h($input['twilio_sip_domain']); ?>">
                <input type="hidden" name="twilio_byoc_trunk_sid" value="<?php echo h($input['twilio_byoc_trunk_sid']); ?>">
                <input type="hidden" name="twilio_trunk_technology" value="<?php echo h($input['twilio_trunk_technology']); ?>">
                <input type="hidden" name="twilio_default_caller_id" value="<?php echo h($input['twilio_default_caller_id']); ?>">
                <table width="100%" cellspacing="0" cellpadding="8">
                    <tr>
                        <td width="220"><label for="twilio_rate_deck">Rate Deck Tag</label></td>
                        <td><input id="twilio_rate_deck" name="rate_deck" type="text" size="35" value="<?php echo h($input['rate_deck'] !== '' ? $input['rate_deck'] : 'voice-outbound'); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="twilio_country_filter">Countries</label></td>
                        <td>
                            <input id="twilio_country_filter" name="country_filter" type="text" size="35" value="<?php echo h($input['country_filter']); ?>">
                            <br><span style="color:#666;">Comma-separated ISO country codes. Use US first for testing; blank imports all countries returned by Twilio.</span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="twilio_destination_filter">Destination Filter</label></td>
                        <td><input id="twilio_destination_filter" name="destination_filter" type="text" size="35" value="<?php echo h($input['destination_filter']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="twilio_prefix_filter">Prefix Filter</label></td>
                        <td><input id="twilio_prefix_filter" name="prefix_filter" type="text" size="20" value="<?php echo h($input['prefix_filter']); ?>" placeholder="1"></td>
                    </tr>
                    <tr>
                        <td><label for="twilio_markup_percent">Retail Markup Percent</label></td>
                        <td><input id="twilio_markup_percent" name="markup_percent" type="text" size="10" value="<?php echo h($input['markup_percent']); ?>"> %</td>
                    </tr>
                    <tr>
                        <td>Outbound Trunk</td>
                        <td>
                            Auto-detect or create the Twilio trunk from the selected routing mode: <?php echo h(twilioRoutingModeLabel($input['twilio_routing_mode'])); ?>.
                            <br><span style="color:#666;">Import binds the selected or created ratecard and Twilio rate rows to that trunk.</span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="twilio_target_ratecard_id">Target Ratecard</label></td>
                        <td>
                            <?php if ($ratecards): ?>
                                <select id="twilio_target_ratecard_id" name="target_ratecard_id">
                                    <option value="">Auto-create Twilio Retail ratecard</option>
                                    <?php foreach ($ratecards as $ratecard): ?>
                                        <option value="<?php echo h($ratecard['id']); ?>" <?php echo $input['target_ratecard_id'] === $ratecard['id'] ? 'selected' : ''; ?>>
                                            <?php echo h($ratecard['name'] . ' (#' . $ratecard['id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input id="twilio_target_ratecard_id" name="target_ratecard_id" type="text" size="10" value="<?php echo h($input['target_ratecard_id']); ?>" placeholder="auto">
                            <?php endif; ?>
                            <br><span style="color:#666;">Leave blank to create or reuse the named Twilio ratecard and call plan below.</span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="twilio_ratecard_name">Auto Ratecard Name</label></td>
                        <td><input id="twilio_ratecard_name" name="twilio_ratecard_name" type="text" size="45" value="<?php echo h($input['twilio_ratecard_name']); ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="twilio_callplan_name">Auto Call Plan Name</label></td>
                        <td><input id="twilio_callplan_name" name="twilio_callplan_name" type="text" size="45" value="<?php echo h($input['twilio_callplan_name']); ?>"></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                            <label>
                                <input name="update_existing" type="checkbox" value="1" <?php echo $input['update_existing'] === '1' ? 'checked' : ''; ?>>
                                Update existing rows with the same ratecard, prefix, and Twilio tag
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                            <button class="form_input_button" name="form_action" type="submit" value="preview_rates">Preview Twilio Rates</button>
                            <button class="form_input_button" name="form_action" type="submit" value="dry_run_import_rates">Dry Run Import</button>
                            <button class="form_input_button" name="form_action" type="submit" value="import_rates" onclick="return confirm('Import Twilio outbound rates into the selected or auto-created ratecard now?');">Import Twilio Rates</button>
                        </td>
                    </tr>
                </table>
            </form>
            <?php if ($ratePreview): ?>
                <br>
                <table width="100%" cellspacing="0" cellpadding="6" border="0">
                    <tr><td class="form_head" colspan="7">Twilio Rate Preview</td></tr>
                    <tr>
                        <td colspan="7">
                            <?php echo h((string)($ratePreview['message'] ?? '')); ?>
                            Total rows: <?php echo h((string)($ratePreview['total_rows'] ?? 0)); ?>
                        </td>
                    </tr>
                    <tr style="font-weight:bold;">
                        <td>Destination</td>
                        <td>Prefix</td>
                        <td>Twilio Cost</td>
                        <td>Retail Rate</td>
                        <td>Markup</td>
                        <td>Currency</td>
                        <td>Increment</td>
                    </tr>
                    <?php foreach (($ratePreview['sample_rows'] ?? []) as $row): ?>
                        <?php if (is_array($row)): ?>
                            <tr>
                                <td><?php echo h((string)($row['destination'] ?? '')); ?></td>
                                <td><?php echo h((string)($row['prefix'] ?? '')); ?></td>
                                <td><?php echo h((string)($row['buyrate'] ?? '')); ?></td>
                                <td><?php echo h((string)($row['rate'] ?? '')); ?></td>
                                <td><?php echo h((string)($row['markup_percent'] ?? '')); ?>%</td>
                                <td><?php echo h((string)($row['currency'] ?? '')); ?></td>
                                <td><?php echo h((string)($row['increment'] ?? '')); ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <?php if ($rateImport): ?>
                <br>
                <table width="100%" cellspacing="0" cellpadding="6" border="0">
                    <tr><td class="form_head" colspan="2">Twilio Import Result</td></tr>
                    <tr><td width="220">Mode</td><td><?php echo !empty($rateImport['dry_run']) ? 'Dry run' : 'Write'; ?></td></tr>
                    <tr><td>Duplicate Handling</td><td><?php echo !empty($rateImport['update_existing']) ? 'Update existing' : 'Skip existing'; ?></td></tr>
                    <tr><td>Imported Rows</td><td><?php echo h((string)($rateImport['imported_rows'] ?? 0)); ?></td></tr>
                    <tr><td>Skipped Rows</td><td><?php echo h((string)($rateImport['skipped_rows'] ?? 0)); ?></td></tr>
                </table>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($selectedProvider === 'vectavoip'): ?>
            <br>
            <table width="100%" cellspacing="0" cellpadding="8" style="border-top:1px solid #ddd;">
                <tr>
                    <td class="form_head" colspan="2">Register A2BillingPlus With VectaVoIP</td>
                </tr>
                <tr>
                    <td colspan="2" style="color:#666;">
                        This registers this A2BillingPlus installation as a VectaVoIP provider customer and stores the VectaVoIP API credentials used for rates, provisioning, and provider API calls. This is separate from the upstream carrier selection above.
                    </td>
                </tr>
            </table>
            <form method="post">
                <input type="hidden" name="form_action" value="register_provider">
                <input type="hidden" name="provider_context" value="vectavoip">
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
                <tr>
                    <td colspan="2" style="color:#666;">
                        Use this only when importing VectaVoIP rate rows into an A2Billing ratecard. DID carrier settings above do not require a target ratecard.
                    </td>
                </tr>
            </table>
            <form method="post">
                <input type="hidden" name="provider_context" value="vectavoip">
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
            <?php endif; ?>
        </td>
    </tr>
</table>
</div>
</div>

<?php

$smarty->display('footer.tpl');

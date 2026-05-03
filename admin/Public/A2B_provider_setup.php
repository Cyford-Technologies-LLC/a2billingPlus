<?php

declare(strict_types=1);

include_once '../lib/admin.defines.php';
include_once '../lib/admin.module.access.php';
include_once '../lib/admin.smarty.php';

use A2BillingPlus\Api\ProviderApiController;
use A2BillingPlus\Bootstrap\ProviderRegistryFactory;
use A2BillingPlus\Http\JsonRequest;

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
$envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';

$defaults = [
    'base_url' => getenv('VECTAVOIP_API_BASE_URL') ?: 'https://api.VectaVoIP.com',
    'company_name' => 'VectaVoIP',
    'company_domain' => 'VectaVoIP.com',
    'contact_name' => '',
    'contact_email' => '',
    'contact_phone' => '',
    'details' => '',
    'install_key' => getenv('VECTAVOIP_INSTALL_KEY') ?: '',
    'save_credentials' => '1',
];

$input = $defaults;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($defaults as $key => $default) {
        $input[$key] = trim((string)($_POST[$key] ?? ''));
    }
    $input['save_credentials'] = isset($_POST['save_credentials']) ? '1' : '';

    if ($input['base_url'] === '') {
        $errors[] = 'Provider API base URL is required.';
    }
    if ($input['company_name'] === '') {
        $errors[] = 'Company name is required.';
    }
    if ($input['contact_name'] === '') {
        $errors[] = 'Contact name is required.';
    }
    if ($input['contact_email'] === '' || !filter_var($input['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid contact email is required.';
    }

    if (!$errors) {
        $registration = registerProviderInstall($input);
        if (($registration['success'] ?? false) !== true) {
            $errors[] = (string)($registration['message'] ?? 'Provider registration failed.');
        } else {
            $messages[] = (string)($registration['message'] ?? 'Provider registration completed.');

            if ($input['save_credentials'] === '1') {
                saveProviderCredentials($envPath, $input['base_url'], $registration, $messages, $errors);
            }
        }
    }
}

$status = providerStatus();

$smarty->display('main.tpl');

function providerStatus(): array
{
    $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
    $response = $controller->handle(new JsonRequest('POST', [], [
        'action' => 'provider_status',
        'provider' => 'vectavoip',
    ]));

    return $response->getPayload();
}

function registerProviderInstall(array $input): array
{
    $controller = new ProviderApiController(ProviderRegistryFactory::createDefault());
    $response = $controller->handle(new JsonRequest('POST', [], [
        'action' => 'register_install',
        'provider' => 'vectavoip',
        'base_url' => $input['base_url'],
        'install_key' => $input['install_key'],
        'company_name' => $input['company_name'],
        'company_domain' => $input['company_domain'],
        'contact_name' => $input['contact_name'],
        'contact_email' => $input['contact_email'],
        'contact_phone' => $input['contact_phone'],
        'details' => $input['details'],
        'app_name' => 'A2BillingPlus',
        'app_version' => '0.1.0-alpha',
    ]));

    $payload = $response->getPayload();
    $payload['http_status'] = $response->getStatusCode();

    return $payload;
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
                <table width="100%" cellspacing="0" cellpadding="8">
                    <tr>
                        <td width="220"><label for="base_url">API Base URL</label></td>
                        <td>
                            <input id="base_url" name="base_url" type="text" size="70" value="<?php echo h($input['base_url']); ?>">
                            <br><span style="color:#666;">Sandbox inside Docker: http://localhost/api/sandbox</span>
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
        </td>
    </tr>
</table>

<?php

$smarty->display('footer.tpl');

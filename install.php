<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPConnector;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPRegistrationClient;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPRegistrationRequest;

/*
 * VectaVoIP / A2BillingPlus web installer.
 *
 * This installer configures the local application environment. It does not
 * remove upstream A2Billing copyright or license attribution.
 */

$projectRoot = __DIR__;
$autoloadPath = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}

$envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
$envExamplePath = $projectRoot . DIRECTORY_SEPARATOR . '.env.example';
$configPath = $projectRoot . DIRECTORY_SEPARATOR . 'a2billing.conf';
$schemaPath = $projectRoot . DIRECTORY_SEPARATOR . 'DataBase' . DIRECTORY_SEPARATOR . 'mariadb' . DIRECTORY_SEPARATOR . '11' . DIRECTORY_SEPARATOR . 'mariadb-11.sql';
$migrationsPath = $projectRoot . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'migrations';
$lockPath = $projectRoot . DIRECTORY_SEPARATOR . 'install.lock';
$defaultProviderApiBaseUrl = class_exists(VectaVoIPConnector::class) ? VectaVoIPConnector::API_BASE_URL : 'https://api.VectaVoIP.com';

$defaults = [
    'company_name' => 'VectaVoIP',
    'company_domain' => 'VectaVoIP.com',
    'app_name' => 'A2BillingPlus',
    'db_mode' => 'container',
    'db_host' => getenv('A2BP_DB_HOST') ?: 'db',
    'db_port' => '3306',
    'db_name' => getenv('A2BP_DB_NAME') ?: 'mya2billing',
    'db_user' => getenv('A2BP_DB_USER') ?: 'a2billinguser',
    'db_password' => getenv('A2BP_DB_PASSWORD') ?: 'a2billing',
    'mysql_root_password' => getenv('MYSQL_ROOT_PASSWORD') ?: 'a2billing-root',
    'http_port' => getenv('A2BP_HTTP_PORT') ?: '8080',
    'db_external_port' => getenv('A2BP_DB_PORT') ?: '3307',
    'initialize_schema' => '',
    'run_compose' => '',
    'compose_php84' => '',
    'compose_asterisk' => '',
    'compose_mailpit' => '',
    'admin_login' => 'root',
    'admin_email' => '',
    'admin_name' => 'VectaVoIP Admin',
    'admin_password' => '',
    'admin_password_confirm' => '',
    'setup_admin' => '1',
    'register_provider' => '',
    'provider_api_base_url' => $defaultProviderApiBaseUrl,
    'provider_company_name' => '',
    'provider_company_domain' => '',
    'provider_contact_name' => '',
    'provider_contact_email' => '',
    'provider_contact_phone' => '',
    'provider_details' => '',
    'provider_install_key' => '',
    'provider_installation_id' => '',
    'provider_api_key' => '',
    'provider_api_secret' => '',
    'overwrite_env' => '',
    'write_lock' => '1',
];

$messages = [];
$errors = [];
$warnings = [];
$posted = $_SERVER['REQUEST_METHOD'] === 'POST';
$input = $defaults;

if ($posted) {
    foreach ($defaults as $key => $value) {
        $input[$key] = trim((string)($_POST[$key] ?? ''));
    }

    $input['initialize_schema'] = isset($_POST['initialize_schema']) ? '1' : '';
    $input['run_compose'] = isset($_POST['run_compose']) ? '1' : '';
    $input['compose_php84'] = isset($_POST['compose_php84']) ? '1' : '';
    $input['compose_asterisk'] = isset($_POST['compose_asterisk']) ? '1' : '';
    $input['compose_mailpit'] = isset($_POST['compose_mailpit']) ? '1' : '';
    $input['setup_admin'] = isset($_POST['setup_admin']) ? '1' : '';
    $input['register_provider'] = isset($_POST['register_provider']) ? '1' : '';
    $input['overwrite_env'] = isset($_POST['overwrite_env']) ? '1' : '';
    $input['write_lock'] = isset($_POST['write_lock']) ? '1' : '';

    if (!in_array($input['db_mode'], ['container', 'existing'], true)) {
        $errors[] = 'Database mode is invalid.';
    }
    if ($input['db_host'] === '') {
        $errors[] = 'Database host is required.';
    }
    if ($input['db_name'] === '') {
        $errors[] = 'Database name is required.';
    }
    if ($input['db_user'] === '') {
        $errors[] = 'Database user is required.';
    }
    if ($input['db_port'] === '' || !ctype_digit($input['db_port'])) {
        $errors[] = 'Database port must be numeric.';
    }
    if ($input['db_external_port'] === '' || !ctype_digit($input['db_external_port'])) {
        $errors[] = 'Host database port must be numeric.';
    }
    if ($input['http_port'] === '' || !ctype_digit($input['http_port'])) {
        $errors[] = 'Web port must be numeric.';
    }
    if ($input['db_password'] === '') {
        $errors[] = 'Database password is required.';
    }
    if ($input['mysql_root_password'] === '') {
        $errors[] = 'Container database root password is required.';
    }
    if ($input['register_provider'] === '1' && $input['provider_api_base_url'] === '') {
        $errors[] = 'VectaVoIP API base URL is required for automatic provider registration.';
    }
    if ($input['register_provider'] === '1') {
        if ($input['provider_company_name'] === '') {
            $input['provider_company_name'] = $input['company_name'];
        }
        if ($input['provider_company_domain'] === '') {
            $input['provider_company_domain'] = $input['company_domain'];
        }
        if ($input['provider_contact_name'] === '') {
            $input['provider_contact_name'] = $input['admin_name'];
        }
        if ($input['provider_contact_email'] === '') {
            $input['provider_contact_email'] = $input['admin_email'];
        }
        if ($input['provider_contact_name'] === '') {
            $errors[] = 'Provider registration contact name is required when VectaVoIP registration is enabled.';
        }
        if ($input['provider_contact_email'] === '') {
            $errors[] = 'Provider registration contact email is required when VectaVoIP registration is enabled.';
        } elseif (!filter_var($input['provider_contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Provider registration contact email is invalid.';
        }
    }
    if ($input['setup_admin'] === '1') {
        if ($input['admin_login'] === '') {
            $errors[] = 'Admin username is required.';
        }
        if ($input['admin_password'] === '') {
            $errors[] = 'Admin password is required.';
        }
        if (strlen($input['admin_password']) < 12) {
            $errors[] = 'Admin password must be at least 12 characters.';
        }
        if ($input['admin_password'] !== $input['admin_password_confirm']) {
            $errors[] = 'Admin password confirmation does not match.';
        }
        if ($input['admin_email'] !== '' && !filter_var($input['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Admin email is invalid.';
        }
    }

    collectCredentialWarnings($input, $warnings);

    if (!$errors) {
        validateInstallPrerequisites($input, $envPath, $configPath, $schemaPath, $lockPath, $errors);
    }

    if (!$errors) {
        if ($input['register_provider'] === '1') {
            registerVectaVoIPInstall($input, $messages, $errors);
        }
    }

    if (!$errors) {
        writeEnvFile($envPath, $envExamplePath, $input, $messages, $errors);
        writeA2BillingConfig($configPath, $input, $messages, $errors);
    }

    if (!$errors && $input['run_compose'] === '1') {
        runComposeSetup($projectRoot, $input, $messages, $errors);
    } elseif (!$errors && $input['db_mode'] === 'container') {
        $messages[] = 'Container setup command: ' . composeCommand($input);
    }

    if (!$errors) {
        $pdo = connectDatabase($input, $errors);
        if ($pdo) {
            $messages[] = 'Database connection succeeded.';

            if ($input['initialize_schema'] === '1') {
                initializeSchema($pdo, $schemaPath, $messages, $errors);
            }

            $schemaReady = tableExists($pdo, 'cc_card');
            if ($schemaReady) {
                $messages[] = 'Database schema check succeeded: cc_card exists.';
                applyMigrations($pdo, $migrationsPath, $messages, $errors);
                if ($input['setup_admin'] === '1') {
                    setupFirstAdmin($pdo, $input, $messages, $errors);
                }
            } else {
                $messages[] = 'Database is reachable, but cc_card was not found. Enable schema initialization or import the database manually.';
            }
        }
    }

    if (!$errors && $input['write_lock'] === '1') {
        $lockContents = "Installed by {$input['company_name']} installer at " . gmdate('c') . PHP_EOL;
        if (@file_put_contents($lockPath, $lockContents) === false) {
            $errors[] = 'Could not write install.lock. Check filesystem permissions.';
        } else {
            $messages[] = 'Created install.lock.';
        }
    }
}

$checks = collectChecks($envPath, $configPath, $schemaPath, $lockPath, $input);
$productionChecklist = productionHardeningChecklist();

function validateInstallPrerequisites(
    array $input,
    string $envPath,
    string $configPath,
    string $schemaPath,
    string $lockPath,
    array &$errors
): void {
    if (version_compare(PHP_VERSION, '8.2.0', '<')) {
        $errors[] = 'PHP 8.2 or newer is required.';
    }

    foreach (['pdo_mysql', 'mysqli', 'gettext'] as $extension) {
        if (!extension_loaded($extension)) {
            $errors[] = 'Required PHP extension is missing: ' . $extension;
        }
    }

    if (!is_writable(dirname($envPath)) || (is_file($envPath) && !is_writable($envPath))) {
        $errors[] = '.env is not writable or creatable.';
    }

    if (!is_file($configPath) || !is_writable($configPath)) {
        $errors[] = 'a2billing.conf is missing or not writable.';
    }

    if ($input['initialize_schema'] === '1' && !is_file($schemaPath)) {
        $errors[] = 'MariaDB schema file is missing.';
    }

    if ($input['write_lock'] === '1' && is_file($lockPath)) {
        $errors[] = 'install.lock already exists. Remove it only when you intentionally need to rerun the installer.';
    }

    if ($input['run_compose'] === '1' && !dockerComposeAvailable()) {
        $errors[] = 'Docker Compose is not available to this PHP process. Leave "Run Docker Compose" unchecked and run the displayed command on the host.';
    }
}

function collectCredentialWarnings(array $input, array &$warnings): void
{
    $defaultDatabasePasswords = ['a2billing', 'a2billing-root', 'changepassword', 'password'];

    if (in_array($input['db_password'], $defaultDatabasePasswords, true)) {
        $warnings[] = 'The database password is still a sandbox/default value. Change it before any production use.';
    }

    if (in_array($input['mysql_root_password'], $defaultDatabasePasswords, true)) {
        $warnings[] = 'The container database root password is still a sandbox/default value. Change it before any production use.';
    }

    if ($input['setup_admin'] !== '1') {
        $warnings[] = 'Admin setup is disabled. Confirm the legacy default admin password has already been changed.';
        return;
    }

    if ($input['admin_login'] === 'root') {
        $warnings[] = 'The admin username is still root. This is acceptable for a sandbox, but use a named admin account for production.';
    }

    if (in_array($input['admin_password'], $defaultDatabasePasswords, true)) {
        $warnings[] = 'The admin password is a known default value and must not be used in production.';
    }
}

function connectDatabase(array $input, array &$errors): ?PDO
{
    if (!extension_loaded('pdo_mysql')) {
        $errors[] = 'pdo_mysql is not installed.';
        return null;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $input['db_host'],
        (int)$input['db_port'],
        $input['db_name']
    );

    try {
        return new PDO($dsn, $input['db_user'], $input['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $exception) {
        $errors[] = 'Database connection failed: ' . $exception->getMessage();
        return null;
    }
}

function tableExists(PDO $pdo, string $table): bool
{
    try {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $statement->execute([$table]);
        return (int)$statement->fetchColumn() > 0;
    } catch (Throwable $exception) {
        return false;
    }
}

function initializeSchema(PDO $pdo, string $schemaPath, array &$messages, array &$errors): void
{
    if (!is_file($schemaPath)) {
        $errors[] = 'Schema file was not found: ' . $schemaPath;
        return;
    }

    if (tableExists($pdo, 'cc_card')) {
        $messages[] = 'Schema initialization skipped because cc_card already exists.';
        return;
    }

    $sql = file_get_contents($schemaPath);
    if ($sql === false || trim($sql) === '') {
        $errors[] = 'Schema file is empty or unreadable.';
        return;
    }

    try {
        $pdo->exec($sql);
        $messages[] = 'Schema initialization completed.';
    } catch (Throwable $exception) {
        $errors[] = 'Schema initialization failed: ' . $exception->getMessage();
    }
}

function applyMigrations(PDO $pdo, string $migrationsPath, array &$messages, array &$errors): void
{
    if (!is_dir($migrationsPath)) {
        $messages[] = 'No install migrations directory found.';
        return;
    }

    ensureMigrationsTable($pdo);

    $migrationFiles = glob($migrationsPath . DIRECTORY_SEPARATOR . '*.sql');
    if (!is_array($migrationFiles) || $migrationFiles === []) {
        $messages[] = 'No install migrations found.';
        return;
    }

    sort($migrationFiles);

    foreach ($migrationFiles as $migrationFile) {
        $migrationName = basename($migrationFile);
        if (migrationApplied($pdo, $migrationName)) {
            continue;
        }

        $sql = file_get_contents($migrationFile);
        if ($sql === false || trim($sql) === '') {
            $errors[] = 'Migration is empty or unreadable: ' . $migrationName;
            return;
        }

        try {
            $pdo->exec($sql);
            recordMigration($pdo, $migrationName);
            $messages[] = 'Applied migration: ' . $migrationName;
        } catch (Throwable $exception) {
            $errors[] = 'Migration failed [' . $migrationName . ']: ' . $exception->getMessage();
            return;
        }
    }
}

function ensureMigrationsTable(PDO $pdo): void
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS cc_schema_migrations (
                migration TEXT NOT NULL PRIMARY KEY,
                applied_at TEXT NOT NULL
            )'
        );
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS cc_schema_migrations (
            migration VARCHAR(191) NOT NULL,
            applied_at DATETIME NOT NULL,
            PRIMARY KEY (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function migrationApplied(PDO $pdo, string $migrationName): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM cc_schema_migrations WHERE migration = ?');
    $statement->execute([$migrationName]);
    return (int)$statement->fetchColumn() > 0;
}

function recordMigration(PDO $pdo, string $migrationName): void
{
    $statement = $pdo->prepare('INSERT INTO cc_schema_migrations (migration, applied_at) VALUES (?, ?)');
    $statement->execute([$migrationName, gmdate('Y-m-d H:i:s')]);
}

function setupFirstAdmin(PDO $pdo, array $input, array &$messages, array &$errors): void
{
    if (!tableExists($pdo, 'cc_ui_authen')) {
        $errors[] = 'Cannot set up admin user because cc_ui_authen does not exist.';
        return;
    }

    $login = $input['admin_login'];
    $passwordHash = hash('whirlpool', $input['admin_password']);
    $name = $input['admin_name'] !== '' ? $input['admin_name'] : $login;
    $email = $input['admin_email'] !== '' ? $input['admin_email'] : null;
    $perms = 5242879;

    try {
        $statement = $pdo->prepare('SELECT userid FROM cc_ui_authen WHERE login = ? LIMIT 1');
        $statement->execute([$login]);
        $userId = $statement->fetchColumn();

        if ($userId !== false) {
            $update = $pdo->prepare(
                'UPDATE cc_ui_authen SET pwd_encoded = ?, groupid = 0, perms = ?, name = ?, email = ? WHERE userid = ?'
            );
            $update->execute([$passwordHash, $perms, $name, $email, $userId]);
            $messages[] = 'Updated admin user: ' . $login;
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO cc_ui_authen (login, pwd_encoded, groupid, perms, name, email) VALUES (?, ?, 0, ?, ?, ?)'
        );
        $insert->execute([$login, $passwordHash, $perms, $name, $email]);
        $messages[] = 'Created admin user: ' . $login;
    } catch (Throwable $exception) {
        $errors[] = 'Admin setup failed: ' . $exception->getMessage();
    }
}

function writeEnvFile(string $envPath, string $envExamplePath, array $input, array &$messages, array &$errors): void
{
    if (is_file($envPath) && $input['overwrite_env'] !== '1') {
        $messages[] = '.env already exists. Kept existing file because overwrite was not selected.';
        return;
    }

    $base = is_file($envPath) ? (string)file_get_contents($envPath) : '';
    if ($base === '' && is_file($envExamplePath)) {
        $base = (string)file_get_contents($envExamplePath);
    }

    $values = [
        'COMPOSE_PROJECT_NAME' => 'a2billingplus',
        'A2BP_HTTP_PORT' => $input['http_port'],
        'A2BP_DB_PORT' => $input['db_external_port'],
        'MYSQL_DATABASE' => $input['db_name'],
        'MYSQL_USER' => $input['db_user'],
        'MYSQL_PASSWORD' => $input['db_password'],
        'MYSQL_ROOT_PASSWORD' => $input['mysql_root_password'],
        'A2BP_DB_HOST' => $input['db_host'],
        'A2BP_DB_NAME' => $input['db_name'],
        'A2BP_DB_USER' => $input['db_user'],
        'A2BP_DB_PASSWORD' => $input['db_password'],
        'VECTAVOIP_API_BASE_URL' => $input['provider_api_base_url'],
        'VECTAVOIP_INSTALL_KEY' => $input['provider_install_key'],
        'VECTAVOIP_INSTALLATION_ID' => $input['provider_installation_id'],
        'VECTAVOIP_API_KEY' => $input['provider_api_key'],
        'VECTAVOIP_API_SECRET' => $input['provider_api_secret'],
    ];

    writeSecretFileValues($values, ['VECTAVOIP_API_KEY', 'VECTAVOIP_API_SECRET'], $messages, $errors);
    if ($errors) {
        return;
    }

    $contents = mergeEnv($base, $values);
    if (@file_put_contents($envPath, $contents) === false) {
        $errors[] = 'Could not write .env. Check filesystem permissions.';
        return;
    }

    $messages[] = 'Wrote .env.';
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

    $messages[] = 'Saved VectaVoIP API key/secret to A2BP_SECRET_DIR.';
}

function registerVectaVoIPInstall(array &$input, array &$messages, array &$errors): void
{
    if (!class_exists(VectaVoIPRegistrationClient::class)) {
        $errors[] = 'Cannot register with VectaVoIP because Composer autoload is unavailable. Run composer install first.';
        return;
    }

    if ($input['provider_install_key'] === '') {
        $input['provider_install_key'] = generateInstallKey();
    }

    $client = new VectaVoIPRegistrationClient($input['provider_api_base_url']);
    $result = $client->register(new VectaVoIPRegistrationRequest(
        $input['provider_install_key'],
        $input['provider_company_name'],
        $input['provider_company_domain'],
        $input['provider_contact_name'],
        $input['provider_contact_email'],
        $input['provider_contact_phone'],
        $input['provider_details'],
        $input['app_name'],
        '0.1.0-alpha'
    ));

    if (!$result->isSuccessful()) {
        $errors[] = $result->getMessage() . ' Contact info@VectaVoIP.com if registration keeps failing.';
        return;
    }

    $input['provider_installation_id'] = $result->getInstallationId();
    $input['provider_api_key'] = $result->getApiKey();
    $input['provider_api_secret'] = $result->getApiSecret();
    $messages[] = 'Registered this install with VectaVoIP and stored provider API credentials.';
}

function generateInstallKey(): string
{
    try {
        return 'a2bp_' . bin2hex(random_bytes(32));
    } catch (Throwable $exception) {
        return 'a2bp_' . hash('sha256', uniqid('', true) . microtime(true));
    }
}

function mergeEnv(string $contents, array $values): string
{
    $lines = preg_split('/\r\n|\r|\n/', $contents);
    $seen = [];

    foreach ($lines as $index => $line) {
        if (!preg_match('/^([A-Z0-9_]+)=/', $line, $matches)) {
            continue;
        }

        $key = $matches[1];
        if (array_key_exists($key, $values)) {
            $lines[$index] = $key . '=' . envValue($values[$key]);
            $seen[$key] = true;
        }
    }

    foreach ($values as $key => $value) {
        if (!isset($seen[$key])) {
            $lines[] = $key . '=' . envValue($value);
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

function writeA2BillingConfig(string $configPath, array $input, array &$messages, array &$errors): void
{
    if (!is_file($configPath)) {
        $errors[] = 'a2billing.conf was not found.';
        return;
    }

    $contents = file_get_contents($configPath);
    if ($contents === false) {
        $errors[] = 'Could not read a2billing.conf.';
        return;
    }

    $replacements = [
        '/^hostname\s*=.*$/m' => 'hostname = ' . $input['db_host'],
        '/^port\s*=.*$/m' => 'port = ' . $input['db_port'],
        '/^user\s*=.*$/m' => 'user = ' . $input['db_user'],
        '/^password\s*=.*$/m' => 'password = ' . $input['db_password'],
        '/^dbname\s*=.*$/m' => 'dbname = ' . $input['db_name'],
        '/^dbtype\s*=.*$/m' => 'dbtype = mysql',
    ];

    foreach ($replacements as $pattern => $replacement) {
        $contents = preg_replace($pattern, $replacement, $contents) ?? $contents;
    }

    if (@file_put_contents($configPath, $contents) === false) {
        $errors[] = 'Could not write a2billing.conf. Check filesystem permissions.';
        return;
    }

    $messages[] = 'Updated a2billing.conf.';
}

function composeCommand(array $input): string
{
    $parts = ['docker compose'];
    if ($input['compose_php84'] === '1') {
        $parts[] = '--profile php84';
    }
    if ($input['compose_asterisk'] === '1') {
        $parts[] = '--profile asterisk';
    }
    if ($input['compose_mailpit'] === '1') {
        $parts[] = '--profile tools';
    }
    $parts[] = 'up -d --build';

    return implode(' ', $parts);
}

function runComposeSetup(string $projectRoot, array $input, array &$messages, array &$errors): void
{
    if (!function_exists('shell_exec')) {
        $errors[] = 'Cannot run Docker Compose because shell_exec is disabled.';
        return;
    }

    $version = shell_exec('docker compose version 2>&1');
    if (!is_string($version) || stripos($version, 'Docker Compose') === false) {
        $errors[] = 'Docker Compose is not available to this installer process. Run manually: ' . composeCommand($input);
        return;
    }

    $command = 'cd ' . escapeshellarg($projectRoot) . ' && ' . composeCommand($input) . ' 2>&1';
    $output = shell_exec($command);
    if (!is_string($output)) {
        $errors[] = 'Docker Compose command did not return output.';
        return;
    }

    if (stripos($output, 'error') !== false || stripos($output, 'failed') !== false) {
        $errors[] = 'Docker Compose reported a problem: ' . trim($output);
        return;
    }

    $messages[] = 'Docker Compose setup completed.';
    $messages[] = trim($output) !== '' ? trim($output) : composeCommand($input);
}

function collectChecks(string $envPath, string $configPath, string $schemaPath, string $lockPath, array $input): array
{
    return [
        'PHP ' . PHP_VERSION => version_compare(PHP_VERSION, '8.2.0', '>='),
        'pdo_mysql extension' => extension_loaded('pdo_mysql'),
        'mysqli extension' => extension_loaded('mysqli'),
        'gettext extension' => extension_loaded('gettext'),
        'Apache/PHP health endpoint present' => is_file(__DIR__ . DIRECTORY_SEPARATOR . 'health.php'),
        '.env writable or creatable' => is_writable(dirname($envPath)) && (!is_file($envPath) || is_writable($envPath)),
        'a2billing.conf writable' => is_file($configPath) && is_writable($configPath),
        'MariaDB schema file present' => is_file($schemaPath),
        'install.lock absent' => !is_file($lockPath),
        'Docker Compose available to installer' => dockerComposeAvailable(),
        'Database TCP reachable' => tcpReachable($input['db_host'], (int)$input['db_port']),
        'Redis TCP reachable' => tcpReachable(envString('A2BP_REDIS_HOST', 'redis'), (int)envString('A2BP_REDIS_INTERNAL_PORT', '6379')),
        'Optional Asterisk AMI reachable when enabled' => $input['compose_asterisk'] !== '1'
            || tcpReachable(envString('A2BP_ASTERISK_AMI_HOST', 'asterisk'), (int)envString('A2BP_ASTERISK_AMI_INTERNAL_PORT', '5038')),
    ];
}

function tcpReachable(string $host, int $port): bool
{
    if ($host === '' || $port <= 0) {
        return false;
    }

    $errno = 0;
    $error = '';
    $socket = @fsockopen($host, $port, $errno, $error, 0.35);
    if (!is_resource($socket)) {
        return false;
    }

    fclose($socket);
    return true;
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

    return $default;
}

function dockerComposeAvailable(): bool
{
    if (!function_exists('shell_exec')) {
        return false;
    }

    $version = shell_exec('docker compose version 2>&1');
    return is_string($version) && stripos($version, 'Docker Compose') !== false;
}

function productionHardeningChecklist(): array
{
    return [
        'Remove or block install.php after setup.',
        'Change all sandbox/default database, root, and admin passwords.',
        'Restrict database, AMI, ARI, SIP, and RTP ports with host firewall rules.',
        'Enable TLS before exposing the web, API, admin, agent, or customer portals.',
        'Keep provider API credentials in a secret store where the deployment platform supports it.',
        'Take and restore-test a database backup before migrating customer data.',
        'Confirm VectaVoIP registration is using the production API endpoint before live traffic.',
    ];
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VectaVoIP A2BillingPlus Installer</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f5f7fb;
            --panel: #ffffff;
            --text: #172033;
            --muted: #5f6b7a;
            --line: #d8dee8;
            --accent: #0f766e;
            --bad: #b42318;
            --good: #067647;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        header {
            background: #101828;
            color: #fff;
            padding: 24px;
        }
        header h1 {
            margin: 0 0 6px;
            font-size: 26px;
        }
        header p {
            margin: 0;
            color: #cbd5e1;
        }
        main {
            width: min(1120px, calc(100% - 32px));
            margin: 24px auto 48px;
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 18px;
        }
        section, aside {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 18px;
        }
        h2 {
            margin: 0 0 14px;
            font-size: 18px;
        }
        .check {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 9px 0;
            border-bottom: 1px solid #edf1f6;
            font-size: 14px;
        }
        .check:last-child { border-bottom: 0; }
        .ok { color: var(--good); font-weight: 700; }
        .fail { color: var(--bad); font-weight: 700; }
        .notice {
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .notice.good {
            background: #ecfdf3;
            border: 1px solid #abefc6;
            color: #065f46;
        }
        .notice.bad {
            background: #fef3f2;
            border: 1px solid #fecdca;
            color: #912018;
        }
        .notice.warn {
            background: #fffaeb;
            border: 1px solid #fedf89;
            color: #93370d;
        }
        .finish-checklist {
            margin: 14px 0;
            padding: 14px 16px;
            border: 1px solid #fedf89;
            border-radius: 6px;
            background: #fffaeb;
        }
        .finish-checklist h3 {
            margin: 0 0 8px;
            font-size: 15px;
        }
        .finish-checklist ul {
            margin: 0;
            padding-left: 20px;
        }
        .finish-checklist li {
            margin: 5px 0;
            font-size: 13px;
            line-height: 1.4;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #344054;
            margin-bottom: 6px;
        }
        input[type="text"], input[type="password"], input[type="number"], textarea {
            width: 100%;
            min-height: 40px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
            font-size: 14px;
            font-family: Arial, Helvetica, sans-serif;
        }
        textarea {
            min-height: 90px;
            resize: vertical;
        }
        .full { grid-column: 1 / -1; }
        .hidden { display: none; }
        .option {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 10px;
            border: 1px solid #e4e7ec;
            border-radius: 6px;
            background: #fcfcfd;
        }
        .option label {
            margin: 0;
            font-weight: 400;
        }
        .radio-group {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .radio-card {
            border: 1px solid #d0d5dd;
            border-radius: 6px;
            padding: 12px;
            background: #fff;
        }
        .radio-card label {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            margin: 0;
            font-weight: 700;
        }
        .actions {
            margin-top: 18px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        button {
            background: var(--accent);
            color: #fff;
            border: 0;
            border-radius: 6px;
            padding: 11px 16px;
            font-weight: 700;
            cursor: pointer;
        }
        a {
            color: var(--accent);
        }
        .muted {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }
        @media (max-width: 860px) {
            main { grid-template-columns: 1fr; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header>
    <h1>VectaVoIP A2BillingPlus Installer</h1>
    <p>Configure the database, application environment, and first-run files.</p>
</header>
<main>
    <aside>
        <h2>System Checks</h2>
        <?php foreach ($checks as $label => $passed): ?>
            <div class="check">
                <span><?php echo h((string)$label); ?></span>
                <span class="<?php echo $passed ? 'ok' : 'fail'; ?>"><?php echo $passed ? 'OK' : 'Fix'; ?></span>
            </div>
        <?php endforeach; ?>
        <p class="muted">
            If this installer runs inside Docker, the database host is usually <strong>db</strong>.
            If it runs from the host machine, the database host is usually <strong>127.0.0.1</strong>
            and the port is usually <strong>3307</strong>.
        </p>
        <p class="muted">
            Starting containers from this page only works when PHP can access the Docker CLI.
            Inside a normal app container, use the displayed Docker command on the host.
        </p>
        <p class="muted">
            After install, remove or block <code>install.php</code> in production.
        </p>
    </aside>

    <section>
        <h2>Install Settings</h2>

        <?php foreach ($messages as $message): ?>
            <div class="notice good"><?php echo h($message); ?></div>
        <?php endforeach; ?>

        <?php foreach ($errors as $error): ?>
            <div class="notice bad"><?php echo h($error); ?></div>
        <?php endforeach; ?>

        <?php foreach ($warnings as $warning): ?>
            <div class="notice warn"><?php echo h($warning); ?></div>
        <?php endforeach; ?>

        <?php if ($posted && !$errors): ?>
            <div class="finish-checklist">
                <h3>Production Hardening Before Live Use</h3>
                <ul>
                    <?php foreach ($productionChecklist as $item): ?>
                        <li><?php echo h($item); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="grid">
                <div>
                    <label for="company_name">Company Name</label>
                    <input id="company_name" name="company_name" type="text" value="<?php echo h($input['company_name']); ?>">
                </div>
                <div>
                    <label for="company_domain">Company Domain</label>
                    <input id="company_domain" name="company_domain" type="text" value="<?php echo h($input['company_domain']); ?>">
                </div>
                <div>
                    <label for="app_name">Application Name</label>
                    <input id="app_name" name="app_name" type="text" value="<?php echo h($input['app_name']); ?>">
                </div>
                <div>
                    <label for="http_port">Web Port</label>
                    <input id="http_port" name="http_port" type="number" value="<?php echo h($input['http_port']); ?>">
                </div>
                <div class="full">
                    <label>Database Setup</label>
                    <div class="radio-group">
                        <div class="radio-card">
                            <label for="db_mode_container">
                                <input id="db_mode_container" name="db_mode" type="radio" value="container" <?php echo $input['db_mode'] === 'container' ? 'checked' : ''; ?>>
                                <span>Make a new MariaDB container<br><span class="muted">Use the bundled Docker Compose `db` service.</span></span>
                            </label>
                        </div>
                        <div class="radio-card">
                            <label for="db_mode_existing">
                                <input id="db_mode_existing" name="db_mode" type="radio" value="existing" <?php echo $input['db_mode'] === 'existing' ? 'checked' : ''; ?>>
                                <span>Use an existing database<br><span class="muted">Provide host, port, database, user, and password.</span></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div>
                    <label for="db_host">Database Host</label>
                    <input id="db_host" name="db_host" type="text" value="<?php echo h($input['db_host']); ?>">
                </div>
                <div>
                    <label for="db_port">Database Port</label>
                    <input id="db_port" name="db_port" type="number" value="<?php echo h($input['db_port']); ?>">
                </div>
                <div>
                    <label for="db_name">Database Name</label>
                    <input id="db_name" name="db_name" type="text" value="<?php echo h($input['db_name']); ?>">
                </div>
                <div>
                    <label for="db_external_port">Host Database Port</label>
                    <input id="db_external_port" name="db_external_port" type="number" value="<?php echo h($input['db_external_port']); ?>">
                </div>
                <div>
                    <label for="db_user">Database User</label>
                    <input id="db_user" name="db_user" type="text" value="<?php echo h($input['db_user']); ?>">
                </div>
                <div>
                    <label for="db_password">Database Password</label>
                    <input id="db_password" name="db_password" type="password" value="<?php echo h($input['db_password']); ?>">
                </div>
                <div>
                    <label for="mysql_root_password">New Container DB Root Password</label>
                    <input id="mysql_root_password" name="mysql_root_password" type="password" value="<?php echo h($input['mysql_root_password']); ?>">
                </div>

                <div class="full option">
                    <input id="initialize_schema" name="initialize_schema" type="checkbox" value="1" <?php echo $input['initialize_schema'] === '1' ? 'checked' : ''; ?>>
                    <label for="initialize_schema">Initialize the MariaDB schema if the database is empty.</label>
                </div>
                <div class="full option">
                    <input id="run_compose" name="run_compose" type="checkbox" value="1" <?php echo $input['run_compose'] === '1' ? 'checked' : ''; ?>>
                    <label for="run_compose">Run Docker Compose from this installer when Docker is available. If unavailable, the installer will show the command to run manually.</label>
                </div>
                <div class="full option">
                    <input id="compose_php84" name="compose_php84" type="checkbox" value="1" <?php echo $input['compose_php84'] === '1' ? 'checked' : ''; ?>>
                    <label for="compose_php84">Also start the PHP 8.4 compatibility container on the configured PHP 8.4 port.</label>
                </div>
                <div class="full option">
                    <input id="compose_asterisk" name="compose_asterisk" type="checkbox" value="1" <?php echo $input['compose_asterisk'] === '1' ? 'checked' : ''; ?>>
                    <label for="compose_asterisk">Also start the bundled Asterisk container.</label>
                </div>
                <div class="full option">
                    <input id="compose_mailpit" name="compose_mailpit" type="checkbox" value="1" <?php echo $input['compose_mailpit'] === '1' ? 'checked' : ''; ?>>
                    <label for="compose_mailpit">Also start Mailpit for sandbox email testing.</label>
                </div>
                <div class="full option">
                    <input id="setup_admin" name="setup_admin" type="checkbox" value="1" <?php echo $input['setup_admin'] === '1' ? 'checked' : ''; ?>>
                    <label for="setup_admin">Set up the first admin user and replace the default password.</label>
                </div>
                <div>
                    <label for="admin_login">Admin Username</label>
                    <input id="admin_login" name="admin_login" type="text" value="<?php echo h($input['admin_login']); ?>">
                </div>
                <div>
                    <label for="admin_email">Admin Email</label>
                    <input id="admin_email" name="admin_email" type="text" value="<?php echo h($input['admin_email']); ?>">
                </div>
                <div>
                    <label for="admin_name">Admin Display Name</label>
                    <input id="admin_name" name="admin_name" type="text" value="<?php echo h($input['admin_name']); ?>">
                </div>
                <div>
                    <label for="admin_password">Admin Password</label>
                    <input id="admin_password" name="admin_password" type="password" value="<?php echo h($input['admin_password']); ?>">
                </div>
                <div>
                    <label for="admin_password_confirm">Confirm Admin Password</label>
                    <input id="admin_password_confirm" name="admin_password_confirm" type="password" value="<?php echo h($input['admin_password_confirm']); ?>">
                </div>
                <div class="full option">
                    <input id="register_provider" name="register_provider" type="checkbox" value="1" <?php echo $input['register_provider'] === '1' ? 'checked' : ''; ?>>
                    <label for="register_provider">Automatically register this install with VectaVoIP and store provider API credentials. The installer generates the install key; the user does not need to manually sign up.</label>
                </div>
                <div data-provider-registration>
                    <label for="provider_company_name">Provider Registration Company</label>
                    <input id="provider_company_name" name="provider_company_name" type="text" value="<?php echo h($input['provider_company_name'] !== '' ? $input['provider_company_name'] : $input['company_name']); ?>">
                </div>
                <div data-provider-registration>
                    <label for="provider_company_domain">Provider Registration Domain</label>
                    <input id="provider_company_domain" name="provider_company_domain" type="text" value="<?php echo h($input['provider_company_domain'] !== '' ? $input['provider_company_domain'] : $input['company_domain']); ?>">
                </div>
                <div data-provider-registration>
                    <label for="provider_contact_name">Provider Contact Name</label>
                    <input id="provider_contact_name" name="provider_contact_name" type="text" value="<?php echo h($input['provider_contact_name']); ?>">
                </div>
                <div data-provider-registration>
                    <label for="provider_contact_email">Provider Contact Email</label>
                    <input id="provider_contact_email" name="provider_contact_email" type="text" value="<?php echo h($input['provider_contact_email'] !== '' ? $input['provider_contact_email'] : $input['admin_email']); ?>">
                </div>
                <div data-provider-registration>
                    <label for="provider_contact_phone">Provider Contact Phone</label>
                    <input id="provider_contact_phone" name="provider_contact_phone" type="text" value="<?php echo h($input['provider_contact_phone']); ?>">
                </div>
                <div class="full" data-provider-registration>
                    <label for="provider_api_base_url">VectaVoIP API Base URL</label>
                    <input id="provider_api_base_url" name="provider_api_base_url" type="text" value="<?php echo h($input['provider_api_base_url']); ?>">
                    <p class="muted">Default: <code>https://api.VectaVoIP.com</code>. Registration endpoint: <code>/v1/installations/register</code>.</p>
                </div>
                <div class="full" data-provider-registration>
                    <label for="provider_details">Provider Registration Details</label>
                    <textarea id="provider_details" name="provider_details"><?php echo h($input['provider_details']); ?></textarea>
                    <p class="muted">Optional notes for the VectaVoIP server, such as sandbox, production, expected traffic, or migration source.</p>
                </div>
                <input name="provider_install_key" type="hidden" value="<?php echo h($input['provider_install_key']); ?>">
                <input name="provider_installation_id" type="hidden" value="<?php echo h($input['provider_installation_id']); ?>">
                <input name="provider_api_key" type="hidden" value="<?php echo h($input['provider_api_key']); ?>">
                <input name="provider_api_secret" type="hidden" value="<?php echo h($input['provider_api_secret']); ?>">
                <div class="full option">
                    <input id="overwrite_env" name="overwrite_env" type="checkbox" value="1" <?php echo $input['overwrite_env'] === '1' ? 'checked' : ''; ?>>
                    <label for="overwrite_env">Overwrite `.env` if it already exists.</label>
                </div>
                <div class="full option">
                    <input id="write_lock" name="write_lock" type="checkbox" value="1" <?php echo $input['write_lock'] === '1' ? 'checked' : ''; ?>>
                    <label for="write_lock">Create `install.lock` after successful install.</label>
                </div>
            </div>

            <div class="actions">
                <button type="submit">Run Install</button>
                <a href="admin/Public/index.php">Go to Admin</a>
            </div>
        </form>
    </section>
</main>
<script>
    (function () {
        var toggle = document.getElementById('register_provider');
        var fields = document.querySelectorAll('[data-provider-registration]');
        if (!toggle) {
            return;
        }

        function syncProviderRegistration() {
            fields.forEach(function (field) {
                field.classList.toggle('hidden', !toggle.checked);
            });
        }

        toggle.addEventListener('change', syncProviderRegistration);
        syncProviderRegistration();
    }());
</script>
</body>
</html>

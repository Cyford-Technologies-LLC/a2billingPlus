<?php

declare(strict_types=1);

function envValue(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function legacyConfigPath(): string
{
    $dir = envValue('A2BP_LEGACY_CONFIG_DIR', envValue('A2BP_RUNTIME_CONFIG_DIR', '/etc/cyford/a2bp'));
    return rtrim($dir, '/') . '/a2billing.conf';
}

function normalizeIniValue($value): string
{
    return trim((string) $value, "\"' \t\n\r\0\x0B");
}

$host = envValue('A2BP_DB_HOST', 'host.docker.internal');
$port = envValue('A2BP_DB_PORT', '3306');
$name = envValue('A2BP_DB_NAME', 'mya2billing');
$user = envValue('A2BP_DB_USER', 'a2billinguser');
$pass = envValue('A2BP_DB_PASSWORD', 'a2billing');

if (strpos($host, ':') !== false) {
    [$host, $port] = explode(':', $host, 2);
}

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, (int) $port, $name);
echo "ENV PDO: {$user}@{$host}:{$port}/{$name}\n";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->query('SELECT 1');
    echo "ENV PDO: OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ENV PDO: FAIL " . $e->getMessage() . "\n");
    exit(1);
}

$configPath = legacyConfigPath();
echo "Legacy config: {$configPath}\n";
if (!is_file($configPath)) {
    fwrite(STDERR, "Legacy mysqli: FAIL config file missing\n");
    exit(1);
}

$config = parse_ini_file($configPath, true);
if (!is_array($config) || !isset($config['database']) || !is_array($config['database'])) {
    fwrite(STDERR, "Legacy mysqli: FAIL database section missing\n");
    exit(1);
}

$db = $config['database'];
$legacyHost = normalizeIniValue($db['hostname'] ?? 'host.docker.internal');
$legacyPort = normalizeIniValue($db['port'] ?? '3306');
$legacyUser = normalizeIniValue($db['user'] ?? 'a2billinguser');
$legacyPass = normalizeIniValue($db['password'] ?? 'a2billing');
$legacyName = normalizeIniValue($db['dbname'] ?? 'mya2billing');

if (strpos($legacyHost, ':') !== false) {
    [$legacyHost, $legacyPort] = explode(':', $legacyHost, 2);
}

echo "Legacy mysqli: {$legacyUser}@{$legacyHost}:{$legacyPort}/{$legacyName}\n";
$mysqli = @new mysqli($legacyHost, $legacyUser, $legacyPass, $legacyName, (int) $legacyPort);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "Legacy mysqli: FAIL {$mysqli->connect_errno} {$mysqli->connect_error}\n");
    exit(1);
}

$mysqli->query('SELECT 1');
$mysqli->close();
echo "Legacy mysqli: OK\n";

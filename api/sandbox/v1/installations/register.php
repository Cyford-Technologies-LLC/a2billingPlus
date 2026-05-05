<?php

declare(strict_types=1);

/*
 * Sandbox-only VectaVoIP registration endpoint.
 *
 * Point the installer API base URL at http://localhost:8080/api/sandbox to
 * exercise automatic registration before the production provider API exists.
 */

$rawBody = file_get_contents('php://input');
$payload = is_string($rawBody) ? json_decode($rawBody, true) : null;

if (!is_array($payload)) {
    sendJson(['message' => 'Invalid JSON payload.'], 400);
}

$installKey = stringValue($payload, 'install_key');
$username = stringValue($payload, 'username');
$password = stringValue($payload, 'password');
$requestIp = stringValue($payload, 'request_ip');

if ($installKey === '' || $username === '' || $password === '') {
    sendJson(['message' => 'install_key, username, and password are required.'], 422);
}

$seed = $installKey . '|' . $username . '|' . $password;
$packages = [
    ['code' => 'starter', 'name' => 'Starter SIP', 'billing' => 'monthly', 'price' => '29.00'],
    ['code' => 'business', 'name' => 'Business Voice', 'billing' => 'monthly', 'price' => '79.00'],
    ['code' => 'wholesale', 'name' => 'Wholesale Origination', 'billing' => 'monthly', 'price' => '199.00'],
];

sendJson([
    'message' => 'Sandbox registration completed.',
    'installation_id' => 'sandbox_' . substr(hash('sha256', $seed), 0, 16),
    'api_key' => 'sandbox_key_' . substr(hash('sha256', $seed . '|key'), 0, 24),
    'api_secret' => 'sandbox_secret_' . substr(hash('sha256', $seed . '|secret'), 0, 32),
    'metadata' => [
        'mode' => 'sandbox',
        'provider' => 'vectavoip',
        'account_number' => 'VVSBX' . strtoupper(substr(hash('sha256', $seed . '|acct'), 0, 8)),
        'registered_ip' => $requestIp !== '' ? $requestIp : '127.0.0.1',
        'allowed_ips' => ($requestIp !== '' ? $requestIp : '127.0.0.1') . '/32',
        'portal_username' => $username,
        'available_packages_json' => json_encode($packages, JSON_UNESCAPED_SLASHES),
    ],
], 201);

/**
 * @param array<string, mixed> $payload
 */
function stringValue(array $payload, string $key): string
{
    $value = $payload[$key] ?? '';
    return is_scalar($value) ? trim((string)$value) : '';
}

/**
 * @param array<string, mixed> $payload
 */
function sendJson(array $payload, int $statusCode): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

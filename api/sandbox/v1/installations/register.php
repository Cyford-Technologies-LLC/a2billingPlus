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
$companyName = stringValue($payload, 'company_name');
$contactEmail = stringValue($payload, 'contact_email');

if ($installKey === '' || $companyName === '' || $contactEmail === '') {
    sendJson(['message' => 'install_key, company_name, and contact_email are required.'], 422);
}

$seed = $installKey . '|' . $companyName . '|' . $contactEmail;

sendJson([
    'message' => 'Sandbox registration completed.',
    'installation_id' => 'sandbox_' . substr(hash('sha256', $seed), 0, 16),
    'api_key' => 'sandbox_key_' . substr(hash('sha256', $seed . '|key'), 0, 24),
    'api_secret' => 'sandbox_secret_' . substr(hash('sha256', $seed . '|secret'), 0, 32),
    'metadata' => [
        'mode' => 'sandbox',
        'provider' => 'vectavoip',
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

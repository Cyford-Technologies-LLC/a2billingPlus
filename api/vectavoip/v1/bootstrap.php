<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPInstallationRepository;
use A2BillingPlus\Module\Provider\VectaVoIP\VectaVoIPProviderApiService;

require_once __DIR__ . '/../../../vendor/autoload.php';

function vectavoipService(): VectaVoIPProviderApiService
{
    return new VectaVoIPProviderApiService(new VectaVoIPInstallationRepository(vectavoipPdo()));
}

function vectavoipPdo(): PDO
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

function requestJson(): array
{
    $rawBody = file_get_contents('php://input');
    $payload = is_string($rawBody) ? json_decode($rawBody, true) : null;
    return is_array($payload) ? $payload : [];
}

function bearerToken(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? $_SERVER['Authorization']
        ?? '';

    if ((!is_string($header) || $header === '') && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $name => $value) {
            if (strtolower((string)$name) === 'authorization') {
                $header = (string)$value;
                break;
            }
        }
    }

    if (!is_string($header) || !preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        return '';
    }

    return trim($matches[1]);
}

function apiSecret(): string
{
    $value = $_SERVER['HTTP_X_VECTAVOIP_SECRET'] ?? '';
    if ((!is_scalar($value) || $value === '') && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $name => $headerValue) {
            if (strtolower((string)$name) === 'x-vectavoip-secret') {
                $value = $headerValue;
                break;
            }
        }
    }

    return is_scalar($value) ? trim((string)$value) : '';
}

function sendJson(array $payload, int $statusCode): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function envString(string $key, string $default = ''): string
{
    $value = getenv($key);
    return is_string($value) && $value !== '' ? $value : $default;
}

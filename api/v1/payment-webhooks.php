<?php

declare(strict_types=1);

use A2BillingPlus\Module\Payment\PaymentWebhookRepository;
use A2BillingPlus\Module\Payment\PaymentWebhookService;
use A2BillingPlus\Module\Payment\StripeWebhookVerifier;

require_once __DIR__ . '/../../vendor/autoload.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$provider = strtolower(trim((string)($_GET['provider'] ?? 'stripe')));
$payload = file_get_contents('php://input');
if (!is_string($payload)) {
    sendJson(['success' => false, 'message' => 'Could not read request body.'], 400);
}

if ($provider !== 'stripe') {
    sendJson(['success' => false, 'message' => 'Unsupported payment webhook provider.'], 404);
}

$service = new PaymentWebhookService(
    new StripeWebhookVerifier(stripeWebhookSecret()),
    new PaymentWebhookRepository(webhookPdo())
);

$result = $service->handleStripe($payload, headerValue('Stripe-Signature'));

sendJson($result['body'], $result['status']);

function webhookPdo(): PDO
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

function headerValue(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $value = $_SERVER[$serverKey] ?? '';

    if ((!is_scalar($value) || $value === '') && function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $headerName => $headerValue) {
            if (strtolower((string)$headerName) === strtolower($name)) {
                $value = $headerValue;
                break;
            }
        }
    }

    return is_scalar($value) ? trim((string)$value) : '';
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

function stripeWebhookSecret(): string
{
    $secret = envString('STRIPE_WEBHOOK_SECRET');
    if ($secret !== '') {
        return $secret;
    }

    $mode = strtolower(envString('MODE', 'test'));
    return envString($mode === 'live' ? 'STRIPE_LIVE_WEBHOOK_SECRET' : 'STRIPE_TEST_WEBHOOK_SECRET');
}

function sendJson(array $payload, int $statusCode): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use A2BillingPlus\Config\RuntimeSettingRepository;

$keys = [
    'A2BP_UI_THEME',
    'VECTAVOIP_API_BASE_URL',
    'VECTAVOIP_INSTALL_KEY',
    'VECTAVOIP_INSTALLATION_ID',
    'VECTAVOIP_API_KEY',
    'VECTAVOIP_API_SECRET',
    'VECTAVOIP_DEFAULT_UPSTREAM_PROVIDER',
    'TWILIO_API_BASE_URL',
    'TWILIO_ACCOUNT_SID',
    'TWILIO_API_KEY',
    'TWILIO_API_SECRET',
    'TWILIO_AUTH_TOKEN',
    'TWILIO_SANDBOX_MODE',
    'TWILIO_DEFAULT_VOICE_URL',
    'TWILIO_DEFAULT_SMS_URL',
    'TWILIO_BYOC_TRUNK_SID',
    'MODE',
    'STRIPE_SECRET_KEY',
    'STRIPE_WEBHOOK_SECRET',
    'STRIPE_TEST_PUBLISHABLE_KEY',
    'STRIPE_TEST_SECRET_KEY',
    'STRIPE_TEST_RESTRICTED_KEY',
    'STRIPE_TEST_WEBHOOK_SECRET',
    'STRIPE_LIVE_PUBLISHABLE_KEY',
    'STRIPE_LIVE_SECRET_KEY',
    'STRIPE_LIVE_RESTRICTED_KEY',
    'STRIPE_LIVE_WEBHOOK_SECRET',
    'PAYMENT_CURRENCY',
];

$secretKeys = [
    'VECTAVOIP_API_KEY',
    'VECTAVOIP_API_SECRET',
    'TWILIO_API_KEY',
    'TWILIO_API_SECRET',
    'TWILIO_AUTH_TOKEN',
    'STRIPE_SECRET_KEY',
    'STRIPE_WEBHOOK_SECRET',
    'STRIPE_TEST_SECRET_KEY',
    'STRIPE_TEST_RESTRICTED_KEY',
    'STRIPE_TEST_WEBHOOK_SECRET',
    'STRIPE_LIVE_SECRET_KEY',
    'STRIPE_LIVE_RESTRICTED_KEY',
    'STRIPE_LIVE_WEBHOOK_SECRET',
];

$values = [];
foreach ($keys as $key) {
    $value = envString($key);
    if ($value !== '') {
        $values[$key] = $value;
    }
}

(new RuntimeSettingRepository(pdo()))->saveMany($values, $secretKeys);

echo json_encode([
    'success' => true,
    'imported_keys' => array_keys($values),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

function pdo(): PDO
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

function envString(string $key): string
{
    $file = getenv($key . '_FILE');
    if (is_string($file) && $file !== '' && is_readable($file)) {
        $contents = file_get_contents($file);
        if (is_string($contents)) {
            return trim($contents);
        }
    }

    $value = getenv($key);
    return is_string($value) ? trim($value) : '';
}

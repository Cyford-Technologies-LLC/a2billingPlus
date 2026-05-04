<?php

declare(strict_types=1);

/**
 * Inbound SMS webhook — called by VectaVoIP when an SMS arrives for a subscribed DID.
 * Verifies the request, stores the message, and fires the CRM callback.
 */

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Module\Messaging\SmsInboundHandler;
use A2BillingPlus\Module\Messaging\SmsMessageRepository;
use A2BillingPlus\Http\ApiResponder;

require_once __DIR__ . '/../../vendor/autoload.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ApiResponder::error('method_not_allowed', 'POST only.', 405)->send();
    exit;
}

$config  = AppConfig::fromEnvironment();
$rawBody = (string)file_get_contents('php://input');

// Verify webhook signature from VectaVoIP
$secret    = $config->string('VECTAVOIP_WEBHOOK_SECRET', '');
$signature = trim($_SERVER['HTTP_X_VECTAVOIP_SIGNATURE'] ?? '');
$timestamp = (int)($_SERVER['HTTP_X_VECTAVOIP_TIMESTAMP'] ?? 0);

if ($secret !== '') {
    $window = 300; // 5 minutes
    $now    = time();
    if (abs($now - $timestamp) > $window) {
        ApiResponder::error('replay_detected', 'Webhook timestamp too old.', 401)->send();
        exit;
    }
    $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    if (!hash_equals($expected, $signature)) {
        ApiResponder::error('invalid_signature', 'Webhook signature verification failed.', 401)->send();
        exit;
    }
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    ApiResponder::error('invalid_payload', 'Request body must be valid JSON.', 400)->send();
    exit;
}

$pdo = new PDO(
    $config->databaseDsn(),
    $config->string('A2BP_DB_USER', 'a2billinguser'),
    $config->string('A2BP_DB_PASSWORD', 'a2billing'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$handler = new SmsInboundHandler($pdo, new SmsMessageRepository($pdo));
$result  = $handler->handle($payload);

if (!$result['success']) {
    ApiResponder::error('inbound_failed', $result['message'], 422)->send();
    exit;
}

ApiResponder::ok(
    ['message_id' => $result['message_id'] ?? 0],
    ['resource' => 'sms-webhook', 'action' => 'inbound']
)->send();

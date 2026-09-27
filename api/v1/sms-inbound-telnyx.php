<?php

declare(strict_types=1);

/**
 * Telnyx inbound SMS webhook.
 *
 * Telnyx POSTs JSON here when an inbound SMS arrives on a Telnyx-hosted number.
 * This endpoint normalises Telnyx's format into VectaVoIP's internal format,
 * passes it to SmsInboundHandler (which stores the message and fires the CRM
 * callback), and returns HTTP 200 JSON so Telnyx does not retry.
 *
 * Configure this URL in the Telnyx Messaging Profile:
 *   https://vectavoip.com/backend/a2billingPlus/api/v1/sms-inbound-telnyx.php
 *
 * Telnyx payload shape:
 * {
 *   "data": {
 *     "event_type": "message.received",
 *     "payload": {
 *       "id": "uuid",
 *       "from": { "phone_number": "+14046090653" },
 *       "to":   [ { "phone_number": "+16784595864" } ],
 *       "text": "Hello World",
 *       "direction": "inbound"
 *     }
 *   }
 * }
 */

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Module\Messaging\SmsInboundHandler;
use A2BillingPlus\Module\Messaging\SmsMessageRepository;

require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json');

// Telnyx always POSTs JSON
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty body']);
    exit;
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Only handle inbound message events — silently ignore everything else
$eventType = $payload['data']['event_type'] ?? '';
if ($eventType !== 'message.received') {
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'event_type' => $eventType]);
    exit;
}

$data   = $payload['data']['payload'] ?? [];
$from   = trim((string)($data['from']['phone_number'] ?? ''));
$toList = $data['to'] ?? [];
$to     = trim((string)($toList[0]['phone_number'] ?? ''));
$body   = trim((string)($data['text'] ?? ''));
$msgId  = trim((string)($data['id']   ?? ''));

if ($from === '' || $to === '' || $body === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Normalise E.164
if (!str_starts_with($to,   '+')) { $to   = '+' . ltrim($to,   '+'); }
if (!str_starts_with($from, '+')) { $from = '+' . ltrim($from, '+'); }

$config = AppConfig::fromEnvironment();
$pdo = new PDO(
    $config->databaseDsn(),
    $config->string('A2BP_DB_USER', 'a2billinguser'),
    $config->string('A2BP_DB_PASSWORD', 'a2billing'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$handler = new SmsInboundHandler($pdo, new SmsMessageRepository($pdo));
$handler->handle([
    'event'              => 'sms.inbound',
    'to'                 => $to,
    'from'               => $from,
    'body'               => $body,
    'message_id'         => $msgId,
    'gateway_message_id' => $msgId,
]);

http_response_code(200);
echo json_encode(['status' => 'ok']);

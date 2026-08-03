<?php

declare(strict_types=1);

/**
 * Twilio inbound SMS webhook.
 *
 * Twilio POSTs form-encoded data (From, To, Body, MessageSid) here when an
 * inbound SMS arrives on a Twilio-hosted number. This endpoint normalises
 * Twilio's format into VectaVoIP's internal format, passes it to
 * SmsInboundHandler (which stores the message and fires the CRM callback),
 * and returns empty TwiML so Twilio does not retry.
 *
 * Configure this URL in the Twilio console for every Twilio DID:
 *   https://<vectavoip-host>/backend/a2billingPlus/api/v1/sms-inbound-twilio.php
 */

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Module\Messaging\SmsInboundHandler;
use A2BillingPlus\Module\Messaging\SmsMessageRepository;

require_once __DIR__ . '/../../vendor/autoload.php';

// Twilio always POSTs
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/xml');
    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    exit;
}

// Twilio sends form-encoded; read directly from $_POST
$from   = trim((string)($_POST['From']       ?? ''));
$to     = trim((string)($_POST['To']         ?? ''));
$body   = trim((string)($_POST['Body']       ?? ''));
$msgSid = trim((string)($_POST['MessageSid'] ?? ''));

if ($from === '' || $to === '' || $body === '') {
    http_response_code(400);
    header('Content-Type: text/xml');
    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    exit;
}

// Normalise E.164: Twilio sends +1... which is what we want, but ensure it.
// VectaVoIP DIDs are stored as +E.164.
if (!str_starts_with($to, '+'))   { $to   = '+' . ltrim($to,   '+'); }
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
    'message_id'         => $msgSid,
    'gateway_message_id' => $msgSid,
]);

// Always return empty TwiML — Twilio retries on non-2xx.
http_response_code(200);
header('Content-Type: text/xml');
echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';

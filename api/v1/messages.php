<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Module\Messaging\SmsMessageRepository;
use A2BillingPlus\Module\Messaging\VectaVoIPSmsGateway;

require_once __DIR__ . '/../../vendor/autoload.php';

$config = AppConfig::fromEnvironment();
$request = JsonRequest::fromGlobals();

$authError = (new ApiServiceKeyAuthenticator($config))->authenticate($request);
if ($authError !== null) {
    $authError->send();
    exit;
}

$pdo = new PDO(
    $config->databaseDsn(),
    $config->string('A2BP_DB_USER', 'a2billinguser'),
    $config->string('A2BP_DB_PASSWORD', 'a2billing'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$repository = new SmsMessageRepository($pdo);
$method = $request->getMethod();

if ($method === 'GET') {
    $limit = $request->getInt('limit', 50);
    $offset = $request->getInt('offset', 0);

    if ($limit < 1 || $limit > 100) {
        ApiResponder::error('invalid_limit', 'Limit must be between 1 and 100.', 422, ['field' => 'limit'])->send();
        exit;
    }

    $customerIdValue = $request->getString('customer_id');
    $customerId = null;
    if ($customerIdValue !== '') {
        if (preg_match('/^[1-9][0-9]*$/', $customerIdValue) !== 1) {
            ApiResponder::error('invalid_customer_id', 'customer_id must be a positive integer.', 422, ['field' => 'customer_id'])->send();
            exit;
        }
        $customerId = (int)$customerIdValue;
    }

    $direction = $request->getString('direction');
    if ($direction !== '' && !in_array($direction, ['inbound', 'outbound'], true)) {
        ApiResponder::error('invalid_direction', 'direction must be inbound or outbound.', 422, ['field' => 'direction'])->send();
        exit;
    }

    $did = trim($request->getString('did'));
    if (strlen($did) > 32) {
        ApiResponder::error('invalid_did', 'did filter must be 32 characters or fewer.', 422, ['field' => 'did'])->send();
        exit;
    }

    $result = $repository->search($limit, $offset, $customerId, $direction !== '' ? $direction : null, $did !== '' ? $did : null);

    ApiResponder::ok(
        ['messages' => $result['items']],
        ['resource' => 'messages', 'limit' => $limit, 'offset' => $offset, 'total' => $result['total']]
    )->send();
    exit;
}

if ($method === 'POST') {
    $payload = $request->getArray('message');

    $customerIdValue = trim((string)($payload['customer_id'] ?? ''));
    if ($customerIdValue === '' || preg_match('/^[1-9][0-9]*$/', $customerIdValue) !== 1) {
        ApiResponder::error('invalid_customer_id', 'message.customer_id must be a positive integer.', 422, ['field' => 'customer_id'])->send();
        exit;
    }

    $from = trim((string)($payload['from'] ?? ''));
    $to = trim((string)($payload['to'] ?? ''));
    $body = trim((string)($payload['body'] ?? ''));

    if ($from === '') {
        ApiResponder::error('missing_from', 'message.from is required.', 422, ['field' => 'from'])->send();
        exit;
    }
    if ($to === '') {
        ApiResponder::error('missing_to', 'message.to is required.', 422, ['field' => 'to'])->send();
        exit;
    }
    if ($body === '') {
        ApiResponder::error('missing_body', 'message.body is required.', 422, ['field' => 'body'])->send();
        exit;
    }
    if (strlen($body) > 1600) {
        ApiResponder::error('body_too_long', 'message.body must be 1600 characters or fewer.', 422, ['field' => 'body'])->send();
        exit;
    }

    $customerId = (int)$customerIdValue;
    $record = $repository->create($customerId, $from, $to, $body, 'outbound', 'pending');

    $apiKey = $config->string('VECTAVOIP_API_KEY');
    $apiSecret = $config->string('VECTAVOIP_API_SECRET');
    $baseUrl = $config->string('VECTAVOIP_API_BASE_URL', 'https://api.vectavoip.com');

    if ($apiKey !== '' && $apiSecret !== '') {
        $gateway = new VectaVoIPSmsGateway($baseUrl, $apiKey, $apiSecret);
        $result = $gateway->send($from, $to, $body);
        $status = $result->success ? 'sent' : 'failed';
        $record = $repository->updateDelivery(
            (int)$record['id'],
            $status,
            $result->gatewayMessageId,
            $result->success ? '' : $result->message
        );
    }

    ApiResponder::ok(['message' => $record], ['resource' => 'messages', 'action' => 'send'], 201)->send();
    exit;
}

ApiResponder::error('method_not_allowed', 'Use GET to list messages or POST to send.', 405)->send();

<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Module\Payment\PaymentReconciliationService;

require_once __DIR__ . '/../../vendor/autoload.php';

$config = AppConfig::fromEnvironment();
$request = JsonRequest::fromGlobals();

$authError = (new ApiServiceKeyAuthenticator($config))->authenticate($request);
if ($authError !== null) {
    $authError->send();
    exit;
}

if ($request->getMethod() !== 'GET') {
    ApiResponder::error('method_not_allowed', 'Payment reconciliation requires GET.', 405)->send();
    exit;
}

$from = trim($request->getString('from'));
$to = trim($request->getString('to'));
foreach (['from' => $from, 'to' => $to] as $field => $value) {
    if ($value === '') {
        ApiResponder::error('missing_' . $field, ucfirst($field) . ' is required.', 422, ['field' => $field])->send();
        exit;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value) !== 1) {
        ApiResponder::error('invalid_' . $field, ucfirst($field) . ' must be YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.', 422, ['field' => $field])->send();
        exit;
    }
}

try {
    $pdo = new PDO($config->databaseDsn(), $config->string('A2BP_DB_USER', 'a2billinguser'), $config->string('A2BP_DB_PASSWORD', 'a2billing'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $summary = (new PaymentReconciliationService($pdo))->summarize($from, $to);
} catch (Throwable $exception) {
    ApiResponder::error('payment_reconciliation_failed', $exception->getMessage(), 500)->send();
    exit;
}

ApiResponder::ok([
    'reconciliation' => $summary,
], [
    'resource' => 'payment-reconciliation',
    'filters' => [
        'from' => $from,
        'to' => $to,
    ],
])->send();

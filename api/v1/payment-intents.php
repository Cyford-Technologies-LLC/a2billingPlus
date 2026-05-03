<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Module\Payment\PaymentIntentService;
use A2BillingPlus\Module\Payment\StripePaymentIntentClient;

require_once __DIR__ . '/../../vendor/autoload.php';

$config = AppConfig::fromEnvironment();
$request = JsonRequest::fromGlobals();

$authError = (new ApiServiceKeyAuthenticator($config))->authenticate($request);
if ($authError !== null) {
    $authError->send();
    exit;
}

if ($request->getMethod() !== 'POST') {
    ApiResponder::error('method_not_allowed', 'Payment intent creation requires POST.', 405)->send();
    exit;
}

$service = new PaymentIntentService(new StripePaymentIntentClient($config->string('STRIPE_SECRET_KEY')));
$result = $service->createStripeIntent($request->getArray('payment'));

if (!$result->success) {
    ApiResponder::error('payment_intent_failed', $result->message, $result->statusCode ?: 422)->send();
    exit;
}

ApiResponder::ok([
    'payment_intent' => [
        'provider' => $result->provider,
        'id' => $result->paymentIntentId,
        'client_secret' => $result->clientSecret,
        'status' => $result->status,
    ],
], [
    'provider' => 'stripe',
], 201)->send();
